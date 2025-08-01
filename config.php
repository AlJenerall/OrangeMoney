<?php
// config.php : Fichier de configuration central

define('DB_TYPE', 'mysql'); // 'mysql' ou 'sqlite'

// -- Paramètres pour MySQL (utilisés si DB_TYPE = 'mysql') --
define('DB_HOST', 'localhost');
define('DB_NAME', 'ttyyqpmy_MyOm');
define('DB_USER', 'ttyyqpmy_MyOmUser');
define('DB_PASS', 'MyOmPassword25@');

// -- Paramètres pour SQLite (utilisés si DB_TYPE = 'sqlite') --
define('DB_SQLITE_PATH', __DIR__ . '/database.sqlite');

// --- 2. Configuration de l'API Orange Money ---
define('ORANGE_MONEY_MERCHANT_KEY', '6ef742f8'); // Votre clé marchand
define('ORANGE_MONEY_AUTH_HEADER', 'Basic a2lyVUN2REVGajhaRER4Mlc0Wll6ZjRSRWFhV1JUckk6MDJ2d0JBSHh0bzJaRkRyTWpJeXpqSzZxNWF3azAxd1R2Ymp2NWpJUmdkTGY='); // Votre header d'autorisation
define('ORANGE_MONEY_API_BASE', 'https://api.orange.com/orange-money-webpay/gn/v1'); // URL de l'API
define('ORANGE_MONEY_AUTH_URL', 'https://api.orange.com/oauth/v3/token');

// --- 3. Paramètres de la Passerelle ---
define('USD_TO_GNF_RATE', 1000); // Taux de change
define('MIN_AMOUNT_GNF', 1000); // Montant minimum en GNF
define('MAX_AMOUNT_GNF', 5000000); // Montant maximum en GNF

// --- 4. URL de base de votre passerelle ---
// Détecte automatiquement l'URL de base
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_path = dirname($_SERVER['SCRIPT_NAME']);
// Assure que le chemin se termine par un slash
$base_path = rtrim($script_path, '/') . '/';
define('BASE_URL', $protocol . '://' . $host . $base_path);
