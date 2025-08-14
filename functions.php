<?php
// functions.php : Fichier de fonctions utilitaires

/**
 * Formate et envoie une réponse JSON, puis termine le script.
 * @param mixed $data Les données à encoder en JSON.
 * @param int $httpStatusCode Le code de statut HTTP à envoyer.
 */
function respondWithJson($data, int $httpStatusCode = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true, $httpStatusCode);
    }
    echo json_encode($data);
    exit();
}

/**
 * Valide la clé d'API fournie dans les en-têtes de la requête.
 * Lance une exception si la clé est manquante ou invalide.
 * @throws Exception
 */
function validateApiKey(): void {
    global $allowed_api_keys;  // Déclaré dans api_keys.php
    $apiKey = $_SERVER['HTTP_X_API_KEY'] 
           ?? $_SERVER['HTTP_X-API-KEY'] 
           ?? '';
    if (empty($apiKey)) {
        throw new Exception('Clé d\'API manquante.', 401);
    }
    if (!in_array($apiKey, $allowed_api_keys, true)) {
        throw new Exception('Clé d\'API invalide.', 401);
    }
}

/**
 * Lit et décode le corps JSON de la requête, ou renvoie une erreur.
 * @return array Les données JSON décodées.
 * @throws Exception
 */
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        throw new Exception('Corps de la requête vide.', 400);
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON invalide : ' . json_last_error_msg(), 400);
    }
    return $data;
}

/**
 * Échappe et nettoie récursivement un tableau ou une chaîne pour éviter les injections.
 * @param mixed $value
 * @return mixed
 */
function sanitize($value) {
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            $value[$k] = sanitize($v);
        }
        return $value;
    }
    return htmlspecialchars(strip_tags((string)$value), ENT_QUOTES, 'UTF-8');
}
