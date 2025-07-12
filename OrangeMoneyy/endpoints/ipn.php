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
 * This script processes notifications from third-party payment systems about payment statuses.
 * It performs the following steps:
 *
 * 1. Retrieves and validates the necessary order details.
 * 2. Verifies the payment status internally (e.g., status = completed, amount validated).
 * 3. Updates the order status and payment information.
 * 4. Retrieves the Dhru Fusion IPN URL saved with the order.
 * 5. Sends an IPN notification with payment details to the Dhru Fusion IPN URL.
 * 6. Redirects to the appropriate success or failure URL based on the payment outcome.
 *
 * Ensures both data integrity and validation throughout the process.
 *
+------------------------------------------------------+
|         Third-Party Payment Gateway                  |
|    (e.g., PayPal, Razorpay, etc.)                    |
+------------------------------------------------------+
                          |
                          v
+------------------------------------------------------+
|       Verifies Payment Status Internally             |
|   (e.g., status = completed, amount validated)       |
+------------------------------------------------------+
                          |
                          v
+------------------------------------------------------+
|   Retrieves Dhru Fusion IPN URL from Saved Order     |
|   Example:                                            |
|   https://{workspace}.dhrufusion.in/api/system/v1/   |
|   ipn?gateway_id={1111-AAAA-BBB-CCC}                 |
+------------------------------------------------------+
                          |
                          v
+------------------------------------------------------+
|   Sends IPN Notification with Payment Data to        |
|             Dhru Fusion's IPN URL                    |
+------------------------------------------------------+
 */

require_once __DIR__ . '/../core/common.php';
require_once __DIR__ . '/../core/OrderModel.php';
require_once __DIR__ . '/../core/OrangeMoneyGateway.php';
$omConfig = include __DIR__ . '/../config/orange_money.php';
$omGateway = new OrangeMoneyGateway($omConfig);
global $input;
$orderModel = new OrderModel();

$getChecksum = $_GET['checksum']?? null;
if (!$getChecksum) {
    output('error', 'Checksum is required in the query string', null, 400);
}

$orderDetails = $orderModel->getOrderById($input['order_id']);
$checksum = md5($input['order_id'] . $orderDetails['ipn_url'] . $orderDetails['order_date']);
if($getChecksum !== $checksum) {
    output('error', 'Invalid checksum', null, 400);
}



if (!$orderDetails) {
    output('error', 'Order not found.', null, 404);
}

if ($orderDetails['status']=="Paid") {
    output('error', sprintf('Order status already updated to %s', htmlspecialchars($orderDetails['status'], ENT_QUOTES, 'UTF-8')), null, 200);
}

$orderId = $orderDetails['order_id'];

$statusPayload = [
    'order_id' => (string)$orderId,
    'amount' => (float)$orderDetails['amount'],
    'pay_token' => $orderDetails['pay_token']
];
$statusResp = $omGateway->getTransactionStatus($statusPayload);
if ($statusResp['status_code'] == 201) {
    $statusData = json_decode($statusResp['body'], true);
    $payment_status = $statusData['status'] === 'SUCCESS' ? 'Paid' : $statusData['status'];
    $transaction_id = $statusData['txnid'];
    $received_amount = $orderDetails['amount'];
} else {
    $payment_status = $input['payment_status'];
    $received_amount = $input['received_amount'];
    $transaction_id = $input['transaction_id'];
}

$orderData = [
    'status' => $payment_status,
    'received_amount' => $received_amount,
    'transaction_id' => $transaction_id
];

$result = $orderModel->updateOrder($orderId, $orderData);
$orderDetails = $orderModel->getOrderById($input['order_id']);

$out = [];
if ($payment_status == 'Paid') {
    $ipn_url = $orderDetails['ipn_url'];
    $redirect_url = $orderDetails['success_url'];


    /*
     * Send payment details to Dhru Fusion Pro.
     *
     * This function is responsible for sending the order details to the Dhru Fusion Pro
     * API once the payment status is marked as "Paid". It uses the provided IPN URL and
     * the order ID to notify the third-party system of the successful payment.
     *
     * @param string $ipn_url The IPN URL configured for Dhru Fusion Pro notifications.
     * @param int    $orderId The unique identifier of the order being updated.
     *
     * @return array Contains the response message and status from the Dhru Fusion Pro API.
     */

    $ipnResult = sendIpnDetailsToDhruFusion($ipn_url, $orderId);
    $out['ipn_response'] = $ipnResult['message'];


}
else {
    $redirect_url = $orderDetails['fail_url'];
}

$out['redirect_url'] = htmlspecialchars_decode($redirect_url);

if ($orderDetails) {
    output('success', 'Order details updated successfully!', $out, 200);
}
else {
    output('error', 'Order not found.', null, 404);
}
