<?php
// endpoints/get_order.php (Version finale intelligente)

/**
 * Cette version recherche par order_id, et si ça échoue,
 * elle recherche par custom_id pour gérer les appels de Dhru.
 */

try {
    // Chargement des dépendances...
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../api_keys.php';
    require_once __DIR__ . '/../functions.php';
    require_once __DIR__ . '/../database.php';
    require_once __DIR__ . '/../OrderModel.php';

    validateApiKey();

    $identifier = $_GET['order_id'] ?? null;
    if (!$identifier) {
        throw new Exception('Identifiant de commande manquant.', 400);
    }

    $orderModel = new OrderModel(new Database());
    $order = null;

    // --- LA LOGIQUE INTELLIGENTE EST ICI ---
    // 1. On essaie de trouver par l'ID numérique de notre base de données
    if (is_numeric($identifier)) {
        $order = $orderModel->getOrderById((int)$identifier);
    }

    // 2. Si on n'a rien trouvé, on essaie de chercher par le custom_id de Dhru
    if (!$order) {
        $order = $orderModel->getOrderByCustomId($identifier);
    }
    // --- FIN DE LA LOGIQUE ---

    if (!$order) {
        throw new Exception('Commande introuvable avec l\'identifiant fourni.', 404);
    }

    respondWithJson(['status' => 'success', 'data' => $order]);

} catch (Throwable $e) {
    $httpCode = in_array($e->getCode(), [400, 401, 404]) ? $e->getCode() : 500;
    error_log('ERREUR dans get_order.php: ' . $e->getMessage());
    respondWithJson(['status' => 'error', 'message' => 'Erreur lors de la récupération : ' . $e->getMessage()], $httpCode);
}
