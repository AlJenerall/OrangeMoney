<?php
// index.php (ce fichier est en fait un endpoint handler)

require_once __DIR__ . '/../core/OrderModel.php';
require_once __DIR__ . '/../config/database.php'; // Chemin mis à jour
require_once __DIR__ . '/../core/common.php';
$gatewayFile = __DIR__ . '/../core/OrangeMoneyGateway.php';
if (file_exists($gatewayFile)) {
    require_once $gatewayFile;
} else {
    logMessage('Gateway file missing: ' . $gatewayFile);
}
if (!class_exists('OrangeMoneyGateway')) {
    logMessage('OrangeMoneyGateway class not found after include');
}
$omConfig = include __DIR__ . '/../config/orange_money.php'; // Charger la configuration Orange Money
$env = include __DIR__ . '/../env.php';

// Valider la clé API avant de traiter la requête
validateApiKey();

// La logique de routage est maintenant dans index.php, ce fichier est un handler spécifique
// Donc, la fonction handleCreateOrder est appelée directement.
handleCreateOrder();

function handleCreateOrder() {
    global $omConfig, $env; // Accéder aux configurations et à la clé secrète

    $input = getValidatedInput();
    logMessage("Create Order Input: " . json_encode($input));

    $requiredFields = ['amount', 'currency_code', 'description', 'customer_name', 'customer_email', 'custom_id', 'ipn_url', 'success_url', 'fail_url'];
    $validationResult = validateInputs($input, $requiredFields);
    if ($validationResult !== true) {
        output('error', $validationResult, null, 400);
    }

    $orderModel = new OrderModel();
    $omGateway = new OrangeMoneyGateway($omConfig);

    // Convert USD to OUV (1 USD = 100 OUV)
    $converted_amount = ($input['currency_code'] === 'USD') ? ($input['amount'] * 100) : $input['amount'];
    $currency = ($input['currency_code'] === 'USD') ? 'OUV' : $input['currency_code'];

    $payload = [
        'merchant_key' => $omGateway->getMerchantKey(), // Utiliser la clé du gateway
        'currency' => $currency,
        'order_id' => $input['custom_id'], // Utiliser custom_id comme order_id pour Orange Money
        'amount' => $converted_amount,
        'return_url' => $input['success_url'],
        'cancel_url' => $input['fail_url'],
        'notif_url' => $input['ipn_url']
    ];

    logMessage("Orange Payload: " . json_encode($payload));

    $orangeRes = $omGateway->initTransaction($payload);
    $orangeData = json_decode($orangeRes['body'], true);

    logMessage("Orange Response Code: " . $orangeRes['status_code']);
    logMessage("Orange Response Body: " . json_encode($orangeData));

    if ($orangeRes['status_code'] !== 201 || !isset($orangeData['payment_url'])) {
        output('error', 'Orange payment initiation failed: ' . ($orangeData['message'] ?? 'Unknown error'), null, 500);
    }

    // Préparer les données de la commande pour la base de données
    $orderData = [
        'amount' => $input['amount'],
        'currency_code' => $input['currency_code'],
        'description' => $input['description'],
        'customer_name' => $input['customer_name'],
        'customer_email' => $input['customer_email'],
        'custom_id' => $input['custom_id'], // L'ID de commande de Dhru Fusion
        'ipn_url' => $input['ipn_url'],
        'success_url' => $input['success_url'],
        'fail_url' => $input['fail_url'],
        'order_date' => date('Y-m-d H:i:s'),
        'pay_token' => $orangeData['pay_token'] ?? null,
        'payment_url' => $orangeData['payment_url'] ?? null,
        'notif_token' => $orangeData['notif_token'] ?? null,
        'status' => 'Pending' // Statut initial
    ];

    $newOrderId = $orderModel->createOrder($orderData);

    if (!$newOrderId) {
        output('error', 'Failed to save order to database.', null, 500);
    }

    // Générer le checksum pour le lien de redirection vers checkout.html
    $checksumData = [
        'order_id' => $newOrderId,
        'ipn_url' => $input['ipn_url'],
        'order_date' => $orderData['order_date'],
    ];
    $checksum = generateChecksum($checksumData, $env['APP_SECRET_KEY']);

    // Rediriger vers la page de paiement simulée ou directement vers Orange Money
    $checkout_url = $orangeData['payment_url']; // URL de paiement d'Orange Money
    // Ou si vous voulez passer par votre page de checkout simulée:
    // $checkout_url = 'checkout.html?order_id=' . $newOrderId . '&checksum=' . $checksum;


    output('success', 'Order created successfully.', [
        'order_id' => $newOrderId, // L'ID de commande interne
        'checkout_url' => $checkout_url
    ]);
}
