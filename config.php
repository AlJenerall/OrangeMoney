<?php
// config.php : Fichier de configuration central
/*
 * Ce fichier contient des constantes de configuration pour votre
 * passerelle de paiement.
 */

// --- Clés d'API et Clé Secrète ---

// Liste des clés API autorisées pour appeler votre passerelle
$allowed_api_keys = [
    '8a35d9c4e2ccbd484cee94517806624c741cde76659a52d356f1a187f27d2c6a'
];
// Private Key Dhru (doit être IDENTIQUE à celle configurée dans l'admin Dhru)
if (!defined('DHRU_SECRET_KEY')) {
    define('DHRU_SECRET_KEY', 'MonSecretDhru2025');
}

// --- Configuration de la Base de Données ---

// Sélectionnez le type de base de données: 'mysql' ou 'sqlite'.
define('DB_TYPE', 'mysql');
// Paramètres pour MySQL (utilisés seulement si DB_TYPE = 'mysql').
define('DB_HOST', 'localhost');
define('DB_NAME', 'ttyyqpmy_MyOm');
define('DB_USER', 'ttyyqpmy_MyOmUser');
define('DB_PASS', 'MyOmPassword25@');
// Paramètre pour SQLite (utilisé seulement si DB_TYPE = 'sqlite').
define('DB_SQLITE_PATH', __DIR__ . '/database.sqlite');

// --- Configuration de l'API Orange Money ---

define('ORANGE_MONEY_MERCHANT_KEY', '6ef742f8');
define('ORANGE_MONEY_AUTH_HEADER', 'Basic a2lyVUN2REVGajhaRER4Mlc0Wll6ZjRSRWFhV1JUckk6MDJ2d0JBSHh0bzJaRkRyTWpJeXpqSzZxNWF3azAxd1R2Ymp2NWpJUmdkTGY=');
define('ORANGE_MONEY_API_BASE', 'https://api.orange.com/orange-money-webpay/gn/v1');
define('ORANGE_MONEY_AUTH_URL', 'https://api.orange.com/oauth/v3/token');

// --- Paramètres de la Passerelle ---

define('USD_TO_GNF_RATE', 1000); // Taux de change indicatif
define('MIN_AMOUNT_GNF', 1000); // Montant minimum en GNF
define('MAX_AMOUNT_GNF', 5000000); // Montant maximum en GNF

// --- Définition de l'URL de base (CORRIGÉE) ---

$protocol    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_path = dirname($_SERVER['SCRIPT_NAME'] ?? '/');

// **LA CORRECTION EST ICI** : on s'assure qu'il n'y a PAS de slash à la fin.
// Le slash sera ajouté par les scripts qui en ont besoin.
$base_path   = rtrim($script_path, '/'); 

define('BASE_URL', $protocol . '://' . $host . $base_path);

