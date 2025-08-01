<?php

validateApiKey();

$orderId = $_GET['order_id'] ?? null;
if (empty($orderId) || !is_numeric($orderId)) {
    output('error', 'Valid order_id is required.', null, 400);
}

$orderModel = new OrderModel(new Database());
$orderDetails = $orderModel->getOrderById((int)$orderId);

if ($orderDetails) {
    output('success', 'Order details fetched successfully!', $orderDetails, 200);
} else {
    output('error', 'Order not found.', null, 404);
}
