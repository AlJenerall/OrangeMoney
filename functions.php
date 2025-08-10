<?php
// functions.php : utilitaires et helpers

/**
 * Gère la réponse en renvoyant soit une page HTML (tests manuels),
 * soit un JSON compatible Dhru Fusion.
 */
function respondWithCheckoutUrl(int $orderId, string $redirectUrl, bool $forceHtml = false)
{
    // Mode HTML classique pour les tests manuels
    if ($forceHtml) {
        $safeRedirectUrl = htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Redirection vers le paiement</title>
    <script type="text/javascript">
        window.top.location.href = '{$safeRedirectUrl}';
    </script>
</head>
<body>
    <p>Redirection en cours...</p>
    <p>Si la redirection échoue, <a href="{$safeRedirectUrl}">cliquez ici</a>.</p>
</body>
</html>
HTML;
        exit;
    }

    // Réponse JSON propre pour Dhru Fusion
    header('Content-Type: application/json');
    echo json_encode([
        "status"  => "success",
        "message" => "Order created successfully.",
        "data"    => [
            "order_id"     => $orderId,
            "checkout_url" => $redirectUrl   // on renvoie la vraie URL (et non '$redirectUrl')
        ]
    ]);
    exit;
}

/**
 * Fonction d'affichage d’erreur uniforme
 */
function output(string $status, string $message, $data = null, int $httpCode = 200)
{
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode([
        "status"  => $status,
        "message" => $message,
        "data"    => $data
    ]);
    exit;
}

/**
 * Nettoie et valide les champs envoyés par l’utilisateur
 */
function validateAndSanitizeInput($input, $type = 'string', $maxLength = 255) {
    $input = trim($input);

    switch ($type) {
        case 'email':
            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Adresse e-mail invalide.");
            }
            return $input;

        case 'url':
            if (!filter_var($input, FILTER_VALIDATE_URL)) {
                throw new Exception("URL invalide.");
            }
            return $input;

        case 'float':
            if (!is_numeric($input)) {
                throw new Exception("Nombre invalide.");
            }
            return (float)$input;

        case 'string':
        default:
            $input = substr(strip_tags($input), 0, $maxLength);
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Vérifie que la requête possède une clé API autorisée
 */
function validateApiKey() {
    global $allowed_api_keys;

    // Récupère les en-têtes HTTP
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
    }

    // Normalise en minuscules
    $headers = array_change_key_case($headers, CASE_LOWER);

    $token = $headers['x-api-key'] ?? $_GET['api_key'] ?? null;

    if (!$token) {
        output('error', 'Api Key token is required.', null, 401);
    }

    $token = trim($token);
    if (!in_array($token, $allowed_api_keys, true)) {
        output('error', 'Unauthorized. Invalid or expired token.', null, 401);
    }
}

/**
 * Relaye les détails IPN à Dhru Fusion
 */
function sendIpnDetailsToDhruFusion(string $ipn_url, array $payload) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ipn_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'status_code' => $httpCode,
        'response'    => $response
    ];
}
