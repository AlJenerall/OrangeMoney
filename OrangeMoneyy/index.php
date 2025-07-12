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
require_once ROOTDIR . '/config/api_keys.php';

// Get the request URI
$action = trim($_GET['action'] ?? '', '/');

// Simple Routing logic
switch ($action) {
    case 'create_order':
        $input = getValidatedInput(); // Validate JSON payload
        require_once ROOTDIR . '/endpoints/create_order.php';
        break;

    case 'get_order':
        require_once ROOTDIR . '/endpoints/get_order.php';
        break;

    case 'ipn':
        $input = getValidatedInput(); // Validate JSON payload
        require_once ROOTDIR . '/endpoints/ipn.php';
        break;

    default:
        http_response_code(404);
        output('error', 'Unknown endpoint.', null, 404);
        break;
}
