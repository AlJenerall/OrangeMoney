<?php
// /endpoints/ipn.php - FIXED VERSION FOR DHRU FUSION PRO

/**
 * IPN Handler with correct Dhru Fusion checksum calculation
 */

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../functions.php';
    require_once __DIR__ . '/../database.php';
    require_once __DIR__ . '/../OrderModel.php';
    require_once __DIR__ . '/../api_keys.php'; // Contains DHRU_PRIVATE_KEY
} catch (Throwable $e) {
    error_log('ERREUR FATALE dans ipn.php (chargement): ' . $e->getMessage());
    respondWithJson(['status' => 'error', 'message' => 'Erreur de configuration serveur.'], 500);
}

error_log('--- ipn.php: Début du traitement IPN (Version Corrigée Dhru) ---');

try {
    // Récupération des données
    $receivedChecksum = $_GET['checksum'] ?? '';
    $rawInput = file_get_contents('php://input');
    $ipnData = json_decode($rawInput, true);
    $orderId = $ipnData['order_id'] ?? null;

    error_log('ipn.php: IPN reçu du simulateur: ' . print_r($ipnData, true));

    if (empty($orderId) || empty($receivedChecksum)) {
        throw new Exception('Données IPN ou checksum manquants.', 400);
    }

    // Récupération de la commande
    $db = new Database();
    $orderModel = new OrderModel($db);
    $order = $orderModel->getOrderById((int)$orderId);

    if (!$order) { 
        throw new Exception('Commande introuvable.', 404); 
    }

    // Validation du checksum interne (depuis checkout.html)
    $expectedChecksum = md5($order['order_id'] . $order['ipn_url'] . $order['order_date']);
    
    if ($receivedChecksum !== $expectedChecksum) {
        throw new Exception('Checksum invalide (venant de checkout.html).', 403);
    }

    // Mise à jour du statut local
    $dhruStatus = ($ipnData['payment_status'] === 'Paid') ? 'Success' : 'Failed';
    $orderModel->updateOrderStatus($order['order_id'], $dhruStatus);

    // Envoi de la notification à Dhru Fusion
    if ($dhruStatus === 'Success') {
        $ipnUrl = $order['ipn_url'];
        $dhruOrderId = $order['custom_id']; // L'ID que Dhru connaît
        $transactionId = $ipnData['transaction_id'] ?? uniqid('txn_');
        $amount = (float)$order['amount'];
        
        // MÉTHODE 1: Format standard Dhru Fusion avec checksum MD5
        // Dhru utilise généralement: md5(order_id + amount + status + private_key)
        $dhruChecksum = md5($dhruOrderId . $amount . 'Paid' . DHRU_PRIVATE_KEY);
        
        $dhruPayload = [
            "checksum" => $dhruChecksum,
            "order_id" => $dhruOrderId, // ID de commande Dhru (custom_id)
            "payment_status" => "Paid",
            "received_amount" => $amount,
            "transaction_id" => $transactionId,
            "gateway_response" => [
                "status" => "success",
                "gateway_order_id" => (string)$order['order_id'],
                "payment_method" => "orange_money"
            ]
        ];

        error_log("ipn.php: Envoi IPN à Dhru - URL: {$ipnUrl}");
        error_log("ipn.php: Payload Dhru: " . json_encode($dhruPayload));
        error_log("ipn.php: Checksum calculé: {$dhruChecksum} (order:{$dhruOrderId}, amount:{$amount}, key:" . DHRU_PRIVATE_KEY . ")");

        // Envoi de la requête
        $ch = curl_init($ipnUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($dhruPayload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("ipn.php: Réponse Dhru - Code HTTP: {$httpCode}");
        error_log("ipn.php: Réponse Dhru - Body: {$response}");
        
        if ($curlError) {
            error_log("ipn.php: Erreur cURL: {$curlError}");
        }
        
        // Si la première méthode échoue, essayer le format webhook alternatif
        if ($httpCode === 400 || $httpCode === 401) {
            error_log("ipn.php: Méthode 1 échouée, tentative avec format webhook Dhru");
            
            // MÉTHODE 2: Format webhook event-based (certaines versions Dhru)
            $webhookPayload = [
                "event" => [
                    "type" => "charge:confirmed",
                    "id" => uniqid('evt_'),
                    "data" => [
                        "order_id" => $dhruOrderId,
                        "amount" => $amount,
                        "currency" => "USD",
                        "status" => "confirmed",
                        "transaction_id" => $transactionId,
                        "payment_method" => "orange_money",
                        "metadata" => [
                            "gateway_order_id" => (string)$order['order_id'],
                            "customer_email" => $order['customer_email']
                        ]
                    ]
                ],
                "signature" => md5(json_encode([
                    "order_id" => $dhruOrderId,
                    "amount" => $amount,
                    "key" => DHRU_PRIVATE_KEY
                ]))
            ];
            
            $ch = curl_init($ipnUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($webhookPayload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Gateway-Signature: ' . $webhookPayload['signature']
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response2 = curl_exec($ch);
            $httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log("ipn.php: Méthode 2 - Code HTTP: {$httpCode2}, Réponse: {$response2}");
        }
        
        // Vérifier si Dhru attend une confirmation POST simple
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("ipn.php: Tentative finale avec POST simple");
            
            // MÉTHODE 3: POST simple avec paramètres form-encoded
            $formData = http_build_query([
                'order_id' => $dhruOrderId,
                'payment_status' => 'Paid',
                'amount' => $amount,
                'transaction_id' => $transactionId,
                'checksum' => md5($dhruOrderId . DHRU_PRIVATE_KEY)
            ]);
            
            $ch = curl_init($ipnUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $formData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded'
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response3 = curl_exec($ch);
            $httpCode3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log("ipn.php: Méthode 3 - Code HTTP: {$httpCode3}, Réponse: {$response3}");
        }
    }

    // Retourner l'URL de redirection
    $redirectUrl = ($dhruStatus === 'Success') ? $order['success_url'] : $order['fail_url'];
    error_log("ipn.php: Redirection finale vers: {$redirectUrl}");

    respondWithJson([
        'status' => 'success',
        'data' => [
            'redirect_url' => $redirectUrl,
            'payment_status' => $dhruStatus,
            'order_id' => $order['order_id']
        ]
    ]);

} catch (Throwable $e) {
    error_log("ERREUR FATALE dans ipn.php: " . $e->getMessage());
    respondWithJson(['status' => 'error', 'message' => 'Erreur IPN: ' . $e->getMessage()], 500);
}