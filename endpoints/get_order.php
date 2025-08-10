<?php
// /endpoints/get_order.php

// Si le script est exécuté directement, charge les dépendances indispensables
if (!function_exists('validateApiKey')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../api_keys.php';
    require_once __DIR__ . '/../functions.php';
    require_once __DIR__ . '/../database.php';
    require_once __DIR__ . '/../OrderModel.php';
    require_once __DIR__ . '/../OrangeMoneyPayment.php';
}

validateApiKey();

$orderId = $_GET['order_id'] ?? null;
if (empty($orderId) || !is_numeric($orderId)) {
    output('error', 'Valid order_id is required.', null, 400);
}

$orderModel  = new OrderModel(new Database());
$orderDetails = $orderModel->getOrderById((int)$orderId);

if ($orderDetails) {
    output('success', 'Order details fetched successfully!', $orderDetails, 200);
} else {
    output('error', 'Order not found.', null, 404);
}
