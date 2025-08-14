<?php
// api_keys.php - Configuration correcte pour Dhru Fusion Pro
// ============================================================

// Liste des clés API autorisées pour appeler votre passerelle
$allowed_api_keys = [
    '8a35d9c4e2ccbd484cee94517806624c741cde76659a52d356f1a187f27d2c6a'
];

// Private Key Dhru - TRÈS IMPORTANT !
// Cette clé DOIT être EXACTEMENT la même que celle configurée dans:
// Dhru Admin Panel > Settings > Payment Gateways > [Votre Gateway] > Private Key
if (!defined('DHRU_PRIVATE_KEY')) {
    define('DHRU_PRIVATE_KEY', 'd405b74d'); // Remplacez par votre vraie clé privée Dhru
}

// Alternative: Si Dhru utilise "Secret Key" au lieu de "Private Key"
if (!defined('DHRU_SECRET_KEY')) {
    define('DHRU_SECRET_KEY', 'd405b74d'); // Même valeur que DHRU_PRIVATE_KEY
}

// Merchant ID si requis par certaines versions de Dhru
if (!defined('DHRU_MERCHANT_ID')) {
    define('DHRU_MERCHANT_ID', 'your_merchant_id'); // Remplacez si nécessaire
}