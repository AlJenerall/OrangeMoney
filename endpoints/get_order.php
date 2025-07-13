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

// Valider la clé API avant de traiter la requête
validateApiKey();

$orderModel = new OrderModel();

$orderId = $_GET['order_id'] ?? null;

if (empty($orderId) || !is_numeric($orderId)) {
    output('error', 'Valid order_id is required.', null, 400);
}

$orderDetails = $orderModel->getOrderById($orderId);

if (!$orderDetails) {
    output('error', 'Order not found.', null, 404);
}

$out = [];
$out['order_id'] = $orderDetails['order_id']; // Utiliser l'ID de la base de données
$out['amount'] = $orderDetails['amount'];
$out['description'] = $orderDetails['description'];
$out['currency_code'] = $orderDetails['currency_code'];
$out['custom_id'] = $orderDetails['custom_id'];
$out['status'] = $orderDetails['status'];
$out['received_amount'] = $orderDetails['received_amount'];
$out['transaction_id'] = $orderDetails['transaction_id'];
$out['order_date'] = $orderDetails['order_date'];
$out['payment_url'] = $orderDetails['payment_url'];
$out['pay_token'] = $orderDetails['pay_token'];

output('success', 'Order details fetched successfully!', $out, 200);
