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
define('ROOTDIR', __DIR__);

require_once ROOTDIR . '/core/common.php';
$apiKeys = include ROOTDIR . '/config/api_keys.php';
require_once ROOTDIR . '/env.php'; // Charger les variables d'environnement

// Get the request URI
$action = trim($_GET['action'] ?? '', '/');

// Valider la clé API pour toutes les requêtes entrantes
validateApiKey();

// Simple Routing logic
switch ($action) {
    case 'create_order':
        $input = getValidatedInput(); // Valider le payload JSON
        require_once ROOTDIR . '/endpoints/create_order.php';
        break;

    case 'get_order':
        // get_order.php gère sa propre validation d'input (order_id via GET)
        require_once ROOTDIR . '/endpoints/get_order.php';
        break;

    case 'ipn':
        $input = getValidatedInput(); // Valider le payload JSON
        require_once ROOTDIR . '/endpoints/ipn.php';
        break;

    default:
        http_response_code(404);
        output('error', 'Unknown endpoint.', null, 404);
        break;
}
