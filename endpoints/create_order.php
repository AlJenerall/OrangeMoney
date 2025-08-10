<?php
// /endpoints/create_order.php

// Si le script est exécuté directement, charge les dépendances indispensables
if (!function_exists('validateApiKey')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../api_keys.php';
    require_once __DIR__ . '/../functions.php';
    require_once __DIR__ . '/../database.php';
    require_once __DIR__ . '/../OrderModel.php';
    require_once __DIR__ . '/../OrangeMoneyPayment.php';
}

error_log("--- create_order.php: Début du traitement ---");

// Vérification de la clé API transmise dans les entêtes
validateApiKey();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    output('error', 'Données JSON invalides ou manquantes.', ['error_code' => 'NO_DATA'], 400);
}

$requiredFields = ['amount', 'custom_id', 'ipn_url', 'success_url', 'fail_url'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        output('error', 'Champ manquant: ' . $field, ['error_code' => 'MISSING_FIELDS'], 400);
    }
}

$amountUsd = (float)validateAndSanitizeInput($input['amount'], 'float');
$amountGnf = round($amountUsd * USD_TO_GNF_RATE);

if ($amountGnf < MIN_AMOUNT_GNF || $amountGnf > MAX_AMOUNT_GNF) {
    output('error', 'Montant hors des limites autorisées.', ['error_code' => 'AMOUNT_OUT_OF_LIMITS'], 400);
}

try {
    $orderModel = new OrderModel(new Database());

    $customId = validateAndSanitizeInput($input['custom_id'], 'string', 50);

    // Vérifie s'il existe déjà une commande en attente pour ce custom_id
    $existing = $orderModel->getPendingOrderByCustomId($customId);

    $forceHtml = isset($_GET['redirect']) || (!empty($input['redirect']));

    if ($existing && !empty($existing['payment_url'])) {
        // Renvoie directement l'URL de paiement existante
        respondWithCheckoutUrl((int)$existing['order_id'], $existing['payment_url'], $forceHtml);
    }

    $orderData = [
        'amount'       => $amountUsd,
        'amount_gnf'   => $amountGnf,
        'custom_id'    => $customId,
        'description'  => validateAndSanitizeInput($input['description'] ?? 'Paiement Dhru Fusion', 'string', 255),
        'customer_email' => validateAndSanitizeInput($input['customer_email'] ?? 'default@email.com', 'email'),
        'ipn_url'      => validateAndSanitizeInput($input['ipn_url'], 'url'),
        'success_url'  => validateAndSanitizeInput($input['success_url'], 'url'),
        'fail_url'     => validateAndSanitizeInput($input['fail_url'], 'url'),
        'order_date'   => date('Y-m-d H:i:s'),
        'status'       => 'pending',
        'payment_url'  => null
    ];

    $orderId         = $orderModel->createOrder($orderData);
    $orange_order_id = $orderData['custom_id'] . '_' . $orderId;

    $orangeData = [
        'amount'    => $amountGnf,
        'order_id'  => $orange_order_id,
        'return_url' => $orderData['success_url'],
        'cancel_url' => $orderData['fail_url'],
        'notif_url'  => BASE_URL . '?action=ipn'
    ];

    $orangeMoney = new OrangeMoneyPayment();
    $payment     = $orangeMoney->createPayment($orangeData);

    if (empty($payment['payment_url'])) {
        throw new Exception("URL de paiement non reçue d'Orange Money.");
    }

    $orderModel->updateOrder($orderId, [
        'pay_token'   => $payment['pay_token'] ?? null,
        'notif_token' => $payment['notif_token'] ?? null,
        'order_id_om' => $orange_order_id,
        'payment_url' => $payment['payment_url'],
        'status'      => 'pending_payment'
    ]);

    // Réponse finale au format attendu par Dhru
    respondWithCheckoutUrl((int)$orderId, $payment['payment_url'], $forceHtml);

} catch (Exception $e) {
    error_log("Erreur fatale dans create_order.php: " . $e->getMessage());
    output('error', 'Erreur serveur: ' . $e->getMessage(), ['error_code' => 'SERVER_ERROR'], 500);
}
