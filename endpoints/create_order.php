<?php
    /**
     * Entrée de paiement allégée pour Dhru Fusion Pro via Orange Money Guinée
     */

    // --- Configuration de débogage ---
    ini_set('display_errors', 1); // Afficher les erreurs à l'écran (pour le développement)
    ini_set('log_errors', 1);     // Enregistrer les erreurs dans les logs
    error_reporting(E_ALL);       // Rapporter toutes les erreurs PHP
    ini_set('error_log', __DIR__ . '/../create_order_debug.log'); // Log dans un fichier local spécifique
    // --- Fin Configuration de débogage ---

    // Ces headers sont déjà gérés par la fonction output() dans common.php
    // header('X-Content-Type-Options: nosniff');
    // header('X-Frame-Options: SAMEORIGIN');
    // header('X-XSS-Protection: 1; mode=block');
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

    require_once __DIR__ . '/../api_keys.php';
    require_once __DIR__ . '/../common.php'; // Contient la fonction output()
    require_once __DIR__ . '/../OrderModel.php';
    require_once __DIR__ . '/../OrangeMoneyPayment.php';

    define('USD_TO_GNF_RATE', 8650); // Correction du taux de change si nécessaire, basé sur checkout.html
    define('MIN_AMOUNT_GNF', 100);
    define('MAX_AMOUNT_GNF', 5000000);
    define('MAX_INPUT_SIZE', 10240);

    // Compatibilité PHP < 8 pour str_contains
    if (!function_exists('str_contains')) {
        function str_contains(string $haystack, string $needle): bool {
            return $needle !== '' && strpos($haystack, $needle) !== false;
        }
    }

    // Convertisseur USD → GNF
    function convertUsdToGnf($amountUsd) {
        return round($amountUsd * USD_TO_GNF_RATE);
    }

    // Validation + nettoyage
    function validateAndSanitizeInput($input, $type = 'string', $maxLength = 255) {
        if ($input === null) return null; // Gérer les inputs null

        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) ?: null;
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) ?: null;
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : 0;
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : 0.0;
            default:
                // FILTER_SANITIZE_STRING est déprécié en PHP 8.1, mais compatible avec les versions antérieures
                // Pour PHP 8.1+, utiliser htmlspecialchars ou strip_tags
                return substr(filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH), 0, $maxLength);
        }
    }

    // --- Début du traitement de la requête ---
    error_log("--- create_order.php: Début du traitement ---");
    error_log("Requête HTTP_METHOD: " . $_SERVER['REQUEST_METHOD']);
    error_log("Requête URI: " . $_SERVER['REQUEST_URI']);

    // Authentification par clé API
    $headers = getallheaders();
    $apiKey = $headers['X-Api-Key'] ?? $headers['X-API-KEY'] ?? null;
    error_log("API Key reçue: " . ($apiKey ? substr($apiKey, 0, 5) . '...' : 'Non fournie'));

    // Correction: Remplacement de la fonction fléchée par une fonction anonyme classique
    $validKey = $apiKey && array_filter($apiKeys, function($k) use ($apiKey) {
        return hash_equals($k, $apiKey);
    });

    if (!$validKey) {
        error_log("Erreur: Clé API non autorisée ou manquante.");
        // Utilisation de output() pour une réponse JSON cohérente
        output('error', 'Unauthorized', ['error_code' => 'INVALID_API_KEY'], 401);
    }

    // Contrôle CORS origin
    $allowedOrigins = [
        'https://gsmeasytech.dhrufusion.in',
        'https://gsmeasytech.dhrufusion.net',
        'https://tty.yqp.mybluehost.me'
    ];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin && !in_array($origin, $allowedOrigins)) {
        error_log("CSRF: Origin non autorisé - $origin");
        // Optionnel: bloquer la requête si l'origine n'est pas autorisée
        // output('error', 'Forbidden Origin', ['error_code' => 'FORBIDDEN_ORIGIN'], 403);
    }

    // Lecture des données
    $rawInput = file_get_contents('php://input', false, null, 0, MAX_INPUT_SIZE);
    error_log("Raw input (payload): " . $rawInput);

    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erreur de décodage JSON: " . json_last_error_msg());
        // Tenter de lire depuis $_POST si le JSON est invalide (pour compatibilité)
        $input = $_POST;
        error_log("Tentative de lecture depuis \$_POST: " . print_r($input, true));
    }

    if (empty($input)) {
        error_log("Erreur: Payload de requête vide.");
        output('error', 'Request payload cannot be empty.', ['error_code' => 'NO_DATA'], 400);
    }

    // Champs requis
    $requiredFields = ['amount', 'custom_id'];
    $missing = [];
    foreach ($requiredFields as $field) {
        // Correction: Vérifier si la clé existe et si la valeur est vide
        if (!isset($input[$field]) || empty($input[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) { // Correction: Utiliser !empty() pour vérifier si le tableau contient des éléments
        error_log("Erreur: Champs requis manquants: " . implode(', ', $missing));
        output('error', 'Missing: ' . implode(', ', $missing), ['error_code' => 'MISSING_FIELDS'], 400);
    }

    $amountUsd = validateAndSanitizeInput($input['amount'], 'float');
    if ($amountUsd <= 0 || $amountUsd > 10000) {
        error_log("Erreur: Montant USD invalide: " . $amountUsd);
        output('error', 'Invalid amount', ['error_code' => 'INVALID_AMOUNT'], 400);
    }

    $amountGnf = convertUsdToGnf($amountUsd);
    if ($amountGnf < MIN_AMOUNT_GNF || $amountGnf > MAX_AMOUNT_GNF) {
        error_log("Erreur: Montant GNF hors limites: " . $amountGnf);
        output('error', 'Amount out of bounds', ['error_code' => 'AMOUNT_OUT_OF_LIMITS'], 400);
    }

    try {
        $orderModel = new OrderModel();
        $orderData = [
            'amount' => $amountUsd,
            'amount_gnf' => $amountGnf,
            'currency_code' => 'USD',
            'currency_om' => 'GNF',
            'exchange_rate' => USD_TO_GNF_RATE,
            'description' => validateAndSanitizeInput($input['description'] ?? 'Service', 'string', 500),
            'customer_name' => validateAndSanitizeInput($input['customer_name'] ?? 'Client', 'string', 100),
            'customer_email' => validateAndSanitizeInput($input['customer_email'] ?? 'client@example.com', 'email') ?: 'client@example.com',
            'custom_id' => validateAndSanitizeInput($input['custom_id'], 'string', 50),
            'ipn_url' => validateAndSanitizeInput($input['ipn_url'] ?? '', 'url'),
            'success_url' => validateAndSanitizeInput($input['success_url'] ?? '', 'url'),
            'fail_url' => validateAndSanitizeInput($input['fail_url'] ?? '', 'url'),
            'order_date' => date('Y-m-d H:i:s'),
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
            'referrer' => substr($_SERVER['HTTP_REFERER'] ?? 'direct', 0, 255)
        ];

        $orderId = $orderModel->createOrder($orderData);
        if (!$orderId) {
            error_log("Erreur: Échec de la création de la commande en base de données.");
            throw new Exception('DB failure');
        }
        error_log("Commande créée en DB avec ID: " . $orderId);

        // Générer un ID unique pour Orange Money
        $orange_order_id = $orderData['custom_id'] . '_' . substr(time(), -6) . str_pad(mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
        $orangeData = [
            'amount' => $amountGnf,
            'currency_code' => 'GNF',
            'custom_id' => $orange_order_id, // Utiliser l'ID unique généré pour Orange Money
            'description' => $orderData['description'],
            'customer_name' => $orderData['customer_name'],
            'customer_email' => $orderData['customer_email'],
            'success_url' => $orderData['success_url'] ?: ($_SERVER['HTTP_REFERER'] ?? ''),
            'fail_url' => $orderData['fail_url'] ?: ($_SERVER['HTTP_REFERER'] ?? ''),
        ];
        error_log("Données envoyées à Orange Money: " . json_encode($orangeData));

        $orangeMoney = new OrangeMoneyPayment();
        $payment = null;
        for ($i = 1; $i <= 3; $i++) {
            try {
                $payment = $orangeMoney->createPayment($orangeData);
                // S'assurer que $payment est un tableau associatif
                if (!is_array($payment)) {
                    $payment = json_decode(json_encode($payment), true);
                }
                error_log("Réponse Orange Money (tentative $i): " . print_r($payment, true));
                break; // Sortir de la boucle si le paiement est créé avec succès
            } catch (Exception $e) {
                error_log("Erreur Orange Money (tentative $i): " . $e->getMessage());
                if ($i === 3) throw $e; // Lancer l'exception après la dernière tentative
                sleep($i); // Attendre avant de réessayer
            }
        }

        $paymentUrl = $payment['checkout_url'] ?? null; // Utiliser 'checkout_url' comme défini dans OrangeMoneyPayment.php
        if (empty($paymentUrl)) {
            error_log("Erreur: Champ 'checkout_url' manquant ou vide dans la réponse Orange Money: " . print_r($payment, true));
            // Utilisation de output() pour une réponse JSON cohérente
            output('error', 'Lien de paiement Orange Money non reçu.', ['error_code' => 'NO_PAYMENT_URL'], 500);
        }

        $orderModel->updateOrder($orderId, [
            'pay_token' => $payment['pay_token'] ?? null,
            'notif_token' => $payment['notif_token'] ?? null,
            'order_id_om' => $orange_order_id,
            'status' => 'pending_payment',
            'api_response' => json_encode($payment) // Enregistrer la réponse complète de l'API Orange Money
        ]);
        error_log("Commande DB mise à jour avec les tokens Orange Money.");

        // --- DÉBUT DE LA CORRECTION ---
        // Supprimer la logique de détection de l'en-tête Accept et la redirection HTML
        // Toujours renvoyer du JSON, car le frontend (checkout.html) attend du JSON.
        output('success', 'Order created successfully.', [
            'order_id' => $orderId,
            'url' => $paymentUrl, // C'est cette 'url' que le JS de checkout.html attend
            'pay_token' => $payment['pay_token'] ?? null,
            'notif_token' => $payment['notif_token'] ?? null // Ajouter notif_token pour le débogage si besoin
        ], 201); // Utiliser 201 Created pour une nouvelle ressource
        // --- FIN DE LA CORRECTION ---

    } catch (Exception $e) {
        error_log("Erreur fatale lors du traitement de la commande: " . $e->getMessage());
        // Utilisation de output() pour une réponse JSON cohérente
        output('error', 'Erreur serveur: ' . $e->getMessage(), ['error_code' => 'PAYMENT_ERROR'], 500);
    }
    error_log("--- create_order.php: Fin du traitement ---");
?>
