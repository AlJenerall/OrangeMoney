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

// Charger les variables d'environnement pour la clé secrète
$env = include __DIR__ . '/../env.php';

function output($status, $message, $data = null, $httpCode = 200) {
    header('Content-Type: application/json', true, $httpCode);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data ?? [],
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

function validateInputs($inputData, $requiredFields) {
    foreach ($requiredFields as $field) {
        if (empty($inputData[$field])) {
            return "The field '$field' is required.";
        }
    }
    return true;
}

// Validate the JSON input
function getValidatedInput() {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        output('error', 'Request payload cannot be empty.', null, 400);
    }

    $decodedInput = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        output('error', 'Invalid JSON payload: ' . json_last_error_msg(), null, 400);
    }

    $decodedInput = sanitizeArray($decodedInput);

    return $decodedInput;
}

// Optional: A recursive function to sanitize array inputs
function sanitizeArray($data) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = sanitizeArray($value);
        } else {
            $data[$key] = htmlspecialchars(strip_tags($value));
        }
    }
    return $data;
}

function sendIpnDetailsToDhruFusion($ipn_url, $orderId)
{
    $eventPayload = [
        "event" => [
            "type" => "charge:confirmed",
            "data" => [
                "order_id" => $orderId
            ]
        ]
    ];

    $response = sendPostRequest($ipn_url, $eventPayload);

    if ($response['status_code'] == 200) {
        return [
            'status' => 'success',
            'message' => "IPN data sent successfully to Dhru Fusion Pro: " . $response['response'],
        ];
    } else {
        return [
            'status' => 'failure',
            'message' => "Failed to send IPN data to Dhru Fusion Pro. HTTP Status Code: " . $response['status_code'] . " Response: " . $response['response'],
        ];
    }
}

function sendPostRequest($url, $data)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'status_code' => $httpCode,
        'response' => $response
    ];
}

function validateApiKey() {
    // Utiliser $_SERVER pour une meilleure portabilité
    $token = null;
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $token = trim($_SERVER['HTTP_X_API_KEY']);
    } elseif (function_exists('apache_request_headers')) { // Fallback pour Apache
        $headers = apache_request_headers();
        if (isset($headers['X-Api-Key'])) {
            $token = trim($headers['X-Api-Key']);
        }
    }

    if (empty($token)) {
        output('error', 'Api Key token is required in header.', null, 401);
    }

    if (!validateKey($token)) {
        output('error', 'Unauthorized. Invalid or expired token.', null, 401);
    }
}

function validateKey($token) {
    global $apiKeys; // $apiKeys est chargé dans index.php

    return in_array($token, $apiKeys);
}

/**
 * Génère un checksum HMAC-SHA256 pour les données fournies.
 * @param array $data Les données à inclure dans le checksum.
 * @param string $secretKey La clé secrète pour le HMAC.
 * @return string Le checksum généré.
 */
function generateChecksum(array $data, string $secretKey): string
{
    // Convertir les données en une chaîne canonique (ex: JSON trié)
    // Assurez-vous que l'ordre des clés est toujours le même pour une cohérence
    ksort($data);
    $dataString = json_encode($data);

    return hash_hmac('sha256', $dataString, $secretKey);
}
