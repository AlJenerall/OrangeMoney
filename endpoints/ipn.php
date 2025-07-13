<?php
/*
 * This file is part of the Dhru Fusion Pro Payment Gateway.
 *
 * @license    Proprietary
 * @copyright  2024 Dhru.com
 * @author     Dhru Fusion Team
 * @description Custom Payment Gateway Development Kit for Dhru Fusion Pro.
 * @powered    Powered by Dhru.com
 */

require_once __DIR__ . '/../core/common.php';
require_once __DIR__ . '/../core/OrderModel.php';
require_once __DIR__ . '/../core/OrangeMoneyGateway.php';
$omConfig = include __DIR__ . '/../config/orange_money.php';
$env = include __DIR__ . '/../env.php';

// Valider la clé API avant de traiter la requête
validateApiKey();
$orderModel = new OrderModel();

$omGateway = new OrangeMoneyGateway($omConfig);
global $input; // $input est défini dans index.php

$getChecksum = $_GET['checksum'] ?? null;
if (!$getChecksum) {
    output('error', 'Checksum is required in the query string', null, 400);
}

// Valider les champs requis de l'input
$requiredFields = ['order_id']; // payment_status, received_amount, transaction_id ne sont plus requis pour la vérification initiale
$validationResult = validateInputs($input, $requiredFields);
if ($validationResult !== true) {
    output('error', $validationResult, null, 400);
}

$orderId = $input['order_id'];
$orderDetails = $orderModel->getOrderById($orderId);

if (!$orderDetails) {
    output('error', 'Order not found.', null, 404);
}

// Reconstruire les données utilisées pour le checksum côté serveur
$checksumData = [
    'order_id' => $orderDetails['order_id'],
    'ipn_url' => $orderDetails['ipn_url'],
    'order_date' => $orderDetails['order_date'],
    // Inclure d'autres données pertinentes si elles sont utilisées pour générer le checksum côté client
];
$expectedChecksum = generateChecksum($checksumData, $env['APP_SECRET_KEY']);

if ($getChecksum !== $expectedChecksum) {
    output('error', 'Invalid checksum', null, 400);
}

if ($orderDetails['status'] == "Paid") {
    output('error', sprintf('Order status already updated to %s', $orderDetails['status']), null, 200);
}

$statusPayload = [
    'order_id' => (string)$orderId,
    'amount' => (float)$orderDetails['amount'],
    'pay_token' => $orderDetails['pay_token']
];

$payment_status = 'Failed'; // Statut par défaut en cas d'échec de vérification
$transaction_id = null;
$received_amount = 0;

$statusResp = $omGateway->getTransactionStatus($statusPayload);
$statusData = json_decode($statusResp['body'], true);

if ($statusResp['status_code'] == 201 && isset($statusData['status'])) {
    // Vérifier le statut réel auprès d'Orange Money
    if ($statusData['status'] === 'SUCCESS') {
        $payment_status = 'Paid';
        $transaction_id = $statusData['txnid'] ?? null;
        $received_amount = $orderDetails['amount']; // Utiliser le montant de la commande pour la cohérence
    } else {
        $payment_status = $statusData['status']; // Utiliser le statut d'Orange Money
    }
} else {
    // Si la vérification Orange Money échoue, ne pas se fier aux données de l'IPN
    // Loguer l'erreur pour investigation
    error_log("Orange Money status check failed for order {$orderId}. Response: " . $statusResp['body']);
    // Le statut reste 'Failed' par défaut ou peut être mis à 'Pending_Verification'
    $payment_status = 'Pending_Verification'; // Ou un autre statut indiquant un problème
}

$orderData = [
    'status' => $payment_status,
    'received_amount' => $received_amount,
    'transaction_id' => $transaction_id
];

$result = $orderModel->updateOrder($orderId, $orderData);
$orderDetails = $orderModel->getOrderById($orderId); // Recharger les détails après la mise à jour

$out = [];
if ($payment_status == 'Paid') {
    $ipn_url = $orderDetails['ipn_url'];
    $redirect_url = $orderDetails['success_url'];

    $ipnResult = sendIpnDetailsToDhruFusion($ipn_url, $orderId);
    $out['ipn_response'] = $ipnResult['message'];
} else {
    $redirect_url = $orderDetails['fail_url'];
}

$out['redirect_url'] = htmlspecialchars_decode($redirect_url);

if ($orderDetails) {
    output('success', 'Order details updated successfully!', $out, 200);
} else {
    output('error', 'Order not found after update.', null, 500); // Devrait être 500 car l'ordre existait
}
