<?php
// /endpoints/create_order.php

/**
 * VERSION AVEC LOGS DÉTAILLÉS
 * Ce script génère la page de redirection HTML et utilise la méthode de checksum MD5 de Dhru.
 */

try {
    // --- Chargement autonome des dépendances ---
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../functions.php'; // Pour respondWithJson()
    require_once __DIR__ . '/../database.php';
    require_once __DIR__ . '/../OrderModel.php';
} catch (Throwable $e) {
    error_log('ERREUR FATALE dans create_order.php (chargement): ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Erreur critique de configuration.']);
    exit();
}

error_log('--- create_order.php: Début du traitement (logique MD5 de Dhru) ---');

try {
    // --- Récupération des données (format JSON de Dhru) ---
    $inputJSON = json_decode(file_get_contents('php://input'), true) ?: [];
    $input = array_merge($inputJSON, $_GET, $_POST);
    
    // --- LOG DÉTAILLÉ : Ce que nous recevons de Dhru ---
    error_log('create_order.php: Données reçues (JSON, GET, POST): ' . print_r($input, true));

    if (empty($input['custom_id'])) {
        throw new Exception('Custom ID manquant.', 400);
    }

    $db = new Database();
    $orderModel = new OrderModel($db);
    $customId = filter_var($input['custom_id'], FILTER_SANITIZE_STRING);

    // --- Protection anti-doublons ---
    $existingOrder = $orderModel->getPendingOrderByCustomId($customId);

    if ($existingOrder && !empty($existingOrder['payment_url'])) {
        error_log("REUSE_EXISTING: Réutilisation de l'URL pour la facture {$customId}.");
        $checkoutUrl = $existingOrder['payment_url'];
    } else {
        // --- Création d'une nouvelle commande ---
        error_log("CREATE_NEW: Création d'une nouvelle commande pour la facture {$customId}.");
        $orderData = [
            'amount' => (float)($input['amount'] ?? 0), 'amount_gnf' => 0,
            'custom_id' => $customId, 'description' => filter_var($input['description'] ?? 'Paiement', FILTER_SANITIZE_STRING),
            'customer_email' => filter_var($input['customer_email'] ?? '', FILTER_SANITIZE_EMAIL),
            'ipn_url' => filter_var($input['ipn_url'], FILTER_SANITIZE_URL),
            'success_url' => filter_var($input['success_url'], FILTER_SANITIZE_URL),
            'fail_url' => filter_var($input['fail_url'], FILTER_SANITIZE_URL),
            'order_date' => date('Y-m-d H:i:s'), 'status' => 'pending_creation',
            'payment_url' => null, 'pay_token' => null, 'notif_token' => null, 'order_id_om' => null
        ];
        $orderId = $orderModel->createOrder($orderData);

        if (empty($orderId)) { throw new Exception("Échec de la création de la commande."); }

        // --- LOG DÉTAILLÉ : Données de la commande après création/récupération ---
        error_log('create_order.php: Commande créée/récupérée: ' . print_r($orderData, true) . ' (ID: ' . $orderId . ')');

        // --- Calcul du checksum selon la méthode exacte de Dhru ---
        $checksum = md5($orderId . $orderData['ipn_url'] . $orderData['order_date']);
        $checkoutUrl = BASE_URL . "/checkout.html?order_id={$orderId}&checksum={$checksum}";

        $orderModel->updateOrder($orderId, ['payment_url' => $checkoutUrl, 'status' => 'pending_payment']);
    }

    // --- LOG DÉTAILLÉ : URL de redirection envoyée à Dhru ---
    error_log('create_order.php: URL de redirection finale renvoyée: ' . $checkoutUrl);

    // --- Envoi de la réponse JSON propre ---
    respondWithJson(['status' => 'success', 'url' => $checkoutUrl]);

} catch (Throwable $e) {
    error_log('ERREUR FATALE dans create_order.php: ' . $e->getMessage());
    respondWithJson(['status' => 'error', 'message' => 'Erreur Interne: ' . $e->getMessage()], 500);
}
