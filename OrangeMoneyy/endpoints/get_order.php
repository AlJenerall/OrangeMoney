<?php
/*
 * This file is part of the Dhru Fusion Pro Payment Gateway.
 *
 * @license    Proprietary
 * @copyright  2024 Dhru.com
 * @author     Dhru Fusion Team
 * @description Custom Payment Gateway Development Kit for Dhru Fusion Pro.
 * @powered    Powered by Dhru.com
 *
 *
+---------------------------------------------------------------+
|         Dhru Fusion Receives Successful IPN Notification      |
|     (Payment Gateway confirms transaction completed)          |
+---------------------------------------------------------------+
                             |
                             v
+---------------------------------------------------------------+
|   Dhru Fusion Calls Gateway to Verify Payment Details via:    |
|   https://example.com/?action=get_order&order_id={order_id}   |
+---------------------------------------------------------------+
                             |
                             v
+---------------------------------------------------------------+
|             Endpoint Responds with JSON Payload:              |
|                                                               |
| {
|   "status": "success",
|   "message": "Order details fetched successfully!",
|   "data": {
|     "order_id": "6",
|     "amount": 100.1,
|     "description": "#INV-2025-03-24-44798",
|     "currency_code": "USD",
|     "custom_id": "44798",                ← Dhru Fusion Order ID
|     "status": "Paid",
|     "received_amount": "100.1",
|     "transaction_id": "AVC2349ASDFka93"
|   },
|   "timestamp": "2025-03-24 16:24:45"
| }
+---------------------------------------------------------------+
*/

require_once __DIR__ . '/../core/common.php';
require_once __DIR__ . '/../core/OrderModel.php';

$orderModel = new OrderModel();

// Get the order ID from the request (e.g., `/get_order.php?order_id=123`)
$orderId = $_GET['order_id'] ?? null;

if (empty($orderId) || !is_numeric($orderId)) {
    output('error', 'Valid order_id is required.', null, 400);
}

$orderDetails = $orderModel->getOrderById($orderId);

$out = [];
$out['order_id'] = $orderId;
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


if ($orderDetails) {
    output('success', 'Order details fetched successfully!', $out, 200);
} else {
    output('error', 'Order not found.', null, 404);
}

