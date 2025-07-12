<?php
// index.php

require_once __DIR__ . '/core/OrderModel.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/common.php';

header('Content-Type: application/json');

function logMessage($message) {
    file_put_contents('gateway.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'create_order':
        if ($method === 'POST') {
            handleCreateOrder();
        }
        break;
    case 'ipn':
        if ($method === 'POST') {
            handleIPN();
        }
        break;
    case 'get_order':
        if ($method === 'GET') {
            handleGetOrder();
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found.']);
        break;
}

function handleCreateOrder() {
    $input = getValidatedInput();
    logMessage("Create Order Input: " . json_encode($input));

    $requiredFields = ['amount', 'currency_code', 'order_id', 'success_url', 'fail_url', 'ipn_url'];
    $validationResult = validateInputs($input, $requiredFields);
    if ($validationResult !== true) {
        output('error', $validationResult, null, 400);
    }

    $auth = 'bWNwem5pS0ZjTUlEUlVKSWwzdnB2RVA2czV6QUJXUVY6aHNqUDY2SkdYeFBITEJGSzVkOWxMb09qcHh3OHFwamZ5R3JRWkw2QlBoZUo=';

    $ch = curl_init("https://api.orange.com/oauth/v2/token");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic $auth",
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $tokenRes = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $access_token = $tokenRes['access_token'] ?? null;
    logMessage("Access Token Response: " . json_encode($tokenRes));

    if (!$access_token) {
        output('error', 'Failed to get access token.', null, 500);
    }

    // Convert USD to OUV (1 USD = 100 OUV)
    $converted_amount = ($input['currency_code'] === 'USD') ? ($input['amount'] * 100) : $input['amount'];
    $currency = ($input['currency_code'] === 'USD') ? 'OUV' : $input['currency_code'];

    $payload = [
        'merchant_key' => '268671bb',
        'currency' => $currency,
        'order_id' => $input['order_id'],
        'amount' => $converted_amount,
        'return_url' => $input['success_url'],
        'cancel_url' => $input['fail_url'],
        'notif_url' => $input['ipn_url']
    ];

    logMessage("Orange Payload: " . json_encode($payload));

    $ch = curl_init("https://api.orange.com/orange-money-webpay/dev/v1/webpayment");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $orangeRes = json_decode(curl_exec($ch), true);
    curl_close($ch);

    logMessage("Orange Response: " . json_encode($orangeRes));

    if (!isset($orangeRes['payment_url'])) {
        output('error', 'Orange payment initiation failed.', null, 500);
    }

    saveOrder($input['order_id'], $converted_amount, 'Pending');

    output('success', 'Order created successfully.', [
        'order_id' => $input['order_id'],
        'checkout_url' => $orangeRes['payment_url']
    ]);
}

function handleIPN() {
    $input = getValidatedInput();
    logMessage("IPN Received: " . json_encode($input));

    if (!isset($input['order_id'], $input['payment_status'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing IPN parameters.']);
        return;
    }

    updateOrderStatus($input['order_id'], $input['payment_status']);

    echo json_encode(['status' => 'success', 'message' => 'IPN processed successfully.']);
}

function handleGetOrder() {
    $orderId = $_GET['order_id'] ?? null;
    if (!$orderId) {
        echo json_encode(['status' => 'error', 'message' => 'Missing order_id.']);
        return;
    }

    $order = getOrder($orderId);
    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found.']);
        return;
    }

    echo json_encode(['status' => 'success', 'data' => $order]);
}

// OrderModel.php

function saveOrder($order_id, $amount, $status) {
    $db = getDb();
    $stmt = $db->prepare("INSERT INTO orders (order_id, amount, status, created_at) VALUES (?, ?, ?, datetime('now'))");
    $stmt->execute([$order_id, $amount, $status]);
}

function updateOrderStatus($order_id, $status) {
    $db = getDb();
    $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = datetime('now') WHERE order_id = ?");
    $stmt->execute([$status, $order_id]);
}

function getOrder($order_id) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// database.php

function getDb() {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:orders.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        initDb($db);
    }
    return $db;
}

function initDb($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id TEXT UNIQUE,
        amount REAL,
        status TEXT,
        created_at TEXT,
        updated_at TEXT
    )");
}
