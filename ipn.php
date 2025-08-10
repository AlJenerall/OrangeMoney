<?php
// ipn.php : Gestion des notifications de paiement (IPN)

error_log("--- ipn.php: Début du traitement IPN ---");

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    error_log("IPN: Données JSON invalides.");
    http_response_code(400);
    exit('Invalid JSON');
}

error_log("IPN reçu: " . json_encode($input));

// Extraction des données
$status = $input['status'] ?? null;
$notif_token = $input['notif_token'] ?? null;
$txnid = $input['txnid'] ?? null;

if (empty($notif_token)) {
    error_log("IPN: notif_token manquant.");
    http_response_code(400);
    exit('notif_token requis');
}

try {
    $orderModel = new OrderModel(new Database());
    $order = $orderModel->getOrderByNotifToken($notif_token);

    if (!$order) {
        error_log("IPN: Commande non trouvée pour notif_token: $notif_token");
        http_response_code(404);
        exit('Commande non trouvée');
    }
    
    // Évite de traiter plusieurs fois la même notification
    if ($order['status'] === 'paid' || $order['status'] === 'failed') {
        error_log("IPN: Commande déjà traitée (statut: {$order['status']}). Ignoré.");
        http_response_code(200);
        exit('OK - Already Processed');
    }

    $dhruParams = [
        'invoiceid' => $order['custom_id'],
        'txnid' => $txnid,
        'amount' => $order['amount']
    ];

    switch (strtoupper($status)) {
        case 'SUCCESS':
            $orderModel->updateOrder($order['order_id'], ['status' => 'paid', 'transaction_id' => $txnid]);
            error_log("Paiement confirmé pour commande {$order['order_id']}");
            $dhruParams['paymentstatus'] = 'Success';
            break;

        case 'FAILED':
        case 'CANCELLED':
            $orderModel->updateOrder($order['order_id'], ['status' => 'failed', 'transaction_id' => $txnid]);
            error_log("Paiement échoué pour commande {$order['order_id']}");
            $dhruParams['paymentstatus'] = 'Failed';
            break;

        default:
            error_log("IPN: Statut non géré '$status' pour notif_token: $notif_token");
            http_response_code(200);
            exit('OK - Status Ignored');
    }

    // Relayer l'information à Dhru Fusion
    if (!empty($order['ipn_url'])) {
        $dhruResp = sendIpnDetailsToDhruFusion($order['ipn_url'], $dhruParams);
        $logMsg = "Relai IPN vers Dhru: URL={$order['ipn_url']}, Code={$dhruResp['status_code']}, Réponse={$dhruResp['response']}";
        error_log($logMsg);
        $orderModel->updateOrder($order['order_id'], ['ipn_response' => $logMsg]);
    } else {
        error_log("Aucune URL IPN trouvée pour la commande {$order['order_id']}. Impossible de notifier Dhru.");
    }

    http_response_code(200);
    echo 'OK';

} catch (Exception $e) {
    error_log("Erreur fatale dans ipn.php: " . $e->getMessage());
    http_response_code(500);
    exit('Erreur Serveur');
}
