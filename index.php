<?php
/**
 * index.php – Routeur principal de la passerelle Dhru Fusion Pro
 * Placez-le à la racine du dossier orange_money/
 */

define('ROOTDIR', __DIR__);

// Chargement des utilitaires et de la configuration
require_once ROOTDIR . '/functions.php';   // respondWithJson, getJsonInput, validateApiKey…
require_once ROOTDIR . '/config.php';      // constantes BASE_URL, DB_TYPE, etc.
require_once ROOTDIR . '/api_keys.php';    // tableau $allowed_api_keys

/* --------------------------------------------------------------------------
 * 1) Déterminer l’action demandée
 * ------------------------------------------------------------------------ */
$action = trim($_GET['action'] ?? '', '/');

/* --------------------------------------------------------------------------
 * 2) Si la requête est POST JSON, on lit immédiatement le corps
 *    (les handlers y accèderont via la variable globale $input)
 * ------------------------------------------------------------------------ */
$input = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = getJsonInput();   // lève Exception si vide ou JSON invalide
    } catch (Throwable $e) {
        respondWithJson(
            ['status' => 'error', 'message' => $e->getMessage()],
            $e->getCode() ?: 400
        );
    }
}

/* --------------------------------------------------------------------------
 * 3) Routage vers l’endpoint approprié
 * ------------------------------------------------------------------------ */
switch ($action) {

    // --- Création d’une commande (appelé par Dhru Fusion) ---------------
    case 'create_order':
        require ROOTDIR . '/endpoints/create_order.php';
        break;

    // --- Récupération d’une commande (protégé par clé API) --------------
    case 'get_order':
        try {
            validateApiKey();      // Vérifie l’en-tête X-API-KEY
        } catch (Throwable $e) {
            respondWithJson(
                ['status' => 'error', 'message' => $e->getMessage()],
                $e->getCode() ?: 401
            );
        }
        require ROOTDIR . '/endpoints/get_order.php';
        break;

    // --- IPN provenant du simulateur / du gateway tiers -----------------
    case 'ipn':
        require ROOTDIR . '/endpoints/ipn.php';
        break;

    // --- Route inconnue -------------------------------------------------
    default:
        respondWithJson(
            ['status' => 'error', 'message' => 'Endpoint inconnu.'],
            404
        );
}
