<?php
define('ROOTDIR', __DIR__);

$action = $_GET['action'] ?? 'checkout';
switch ($action) {
    case 'create_order':
        require_once ROOTDIR . '/endpoints/create_order.php';
        break;
    case 'ipn':
        require_once ROOTDIR . '/endpoints/ipn.php';
        break;
    case 'get_order':
        require_once ROOTDIR . '/endpoints/get_order.php';
        break;
     default:
        // Affiche l’interface stylée
        header('Content-Type: text/html; charset=UTF-8');
        readfile(__DIR__ . '/checkout.html');
        break;
}


