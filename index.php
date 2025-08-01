<?php
/**
 * index.php : Routeur principal
 * Point d'entrée unique pour toutes les requêtes.
 * Charge les dépendances et dirige vers le bon script.
 *
 * Structure attendue :
 * /orange_money/
 * ├── index.php             (ce fichier)
 * ├── config.php
 * ├── functions.php
 * ├── ipn.php
 * │
 * └── endpoints/
 *     ├── create_order.php
 *     └── get_order.php
 */

// Configuration de base pour le logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');
error_reporting(E_ALL);

// Détermine l'action demandée via le paramètre GET
$action = $_GET['action'] ?? null;

// --- Détection spéciale pour l'IPN d'Orange Money ---
// Orange Money envoie parfois les IPN sans paramètre ?action=ipn
// On inspecte le corps de la requête pour identifier un IPN
if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_body = file_get_contents('php://input');
    $data = json_decode($input_body, true);
    if (isset($data['notif_token'])) {
        $action = 'ipn'; // C'est une notification IPN
        error_log("INFO: Requête POST sans action détectée comme IPN via notif_token.");
    }
}

// Si aucune action n'est définie après les vérifications, 'create_order' par défaut
if (is_null($action)) {
    $action = 'create_order';
}

// --- Chargement des dépendances communes ---
// Cette section est cruciale. Elle garantit que toutes les fonctions
// et configurations sont disponibles pour les scripts qui suivent.
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/api_keys.php';
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/OrderModel.php';
    require_once __DIR__ . '/OrangeMoneyPayment.php';
} catch (Throwable $e) {
    error_log("ERREUR FATALE: Impossible de charger un fichier de base. " . $e->getMessage());
    http_response_code(500);
    // On s'assure de renvoyer du JSON même si la fonction `output` n'est pas chargée
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Erreur de configuration serveur.']);
    exit;
}

// --- Routage vers le script approprié ---
// Utilise des chemins absolus avec __DIR__ pour une robustesse maximale.
switch ($action) {
    case 'create_order':
        // Correction: pointe vers le dossier /endpoints
        require_once __DIR__ . '/endpoints/create_order.php';
        break;

    case 'get_order':
        // Correction: pointe vers le dossier /endpoints
        require_once __DIR__ . '/endpoints/get_order.php';
        break;

    case 'ipn':
        // ipn.php est à la racine, comme convenu
        require_once __DIR__ . '/ipn.php';
        break;

    case 'success':
        // Page de succès simple
        echo "<h1>Paiement réussi</h1><p>Merci pour votre achat. Votre commande est en cours de traitement.</p>";
        break;

    case 'fail':
    case 'cancel':
        // Page d'échec simple
        echo "<h1>Paiement échoué</h1><p>Le paiement a été annulé ou a échoué. Veuillez réessayer ou contacter le support.</p>";
        break;

    default:
        // Gère les actions inconnues
        http_response_code(404);
        output('error', 'Action non valide.', ['action_demandee' => htmlspecialchars($action)], 404);
        break;
}

