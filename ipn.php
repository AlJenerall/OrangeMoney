<?php
// ipn.php : Script de notification de paiement (version définitive - logique Dhru)

/**
 * Ce script valide le checksum MD5 et envoie l'IPN à Dhru dans le format JSON attendu.
 */

try {
    // --- Chargement des dépendances ---
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/OrderModel.php';
} catch (Throwable $e) {
    error_log('ERREUR FATALE dans ipn.php (chargement): ' . $e->getMessage());
    respondWithJson(['status' => 'error', 'message' => 'Erreur de configuration serveur.'], 500);
}

error_log('--- ipn.php: Début du traitement IPN (logique MD5 & JSON de Dhru) ---');

try {
    // --- Récupérer les données ---
    $receivedChecksum = $_GET['checksum'] ?? '';
    $rawInput = file_get_contents('php://input');
    $ipnData = json_decode($rawInput, true);
    $orderId = $ipnData['order_id'] ?? null;

    if (empty($orderId) || empty($receivedChecksum)) {
        throw new Exception('Données IPN ou checksum manquants.', 400);
    }

    // --- Récupérer la commande et valider le checksum interne ---
    $db = new Database();
    $orderModel = new OrderModel($db);
    $order = $orderModel->getOrderById((int)$orderId);

    if (!$order) { throw new Exception('Commande introuvable.', 404); }

    // Validation du checksum selon la méthode exacte de Dhru
    $expectedChecksum = md5($order['order_id'] . $order['ipn_url'] . $order['order_date']);
    if ($receivedChecksum !== $expectedChecksum) {
        throw new Exception('Checksum invalide (venant de checkout.html).', 403);
    }

    // --- Mise à jour locale et préparation des données pour Dhru ---
    $dhruStatus = ($ipnData['payment_status'] === 'Paid') ? 'Success' : 'Failed';
    $orderModel->updateOrderStatus($order['order_id'], $dhruStatus);

    // --- Envoyer la notification à Dhru au bon format ---
    if ($dhruStatus === 'Success') {
        $ipnUrl = $order['ipn_url'];
        $dhruOrderId = $order['custom_id']; // L'ID que Dhru connaît

        // Construction du payload JSON structuré comme attendu par Dhru
        $eventPayload = [
            "event" => [
                "type" => "charge:confirmed",
                "data" => [ "order_id" => $dhruOrderId ]
            ]
        ];

        // Envoi de la requête en JSON
        $ch = curl_init($ipnUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($eventPayload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json']
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("Relai IPN vers Dhru ({$ipnUrl}): Code={$httpCode}, Payload=" . json_encode($eventPayload) . ", Réponse={$response}");
    }

    // --- Renvoyer l'URL de redirection finale ---
    $redirectUrl = ($dhruStatus === 'Success') ? $order['success_url'] : $order['fail_url'];
    respondWithJson(['status'  => 'success', 'data' => ['redirect_url' => $redirectUrl]]);

} catch (Throwable $e) {
    error_log("ERREUR FATALE dans ipn.php: " . $e->getMessage());
    respondWithJson(['status' => 'error', 'message' => 'Erreur IPN: ' . $e->getMessage()], 500);
}
