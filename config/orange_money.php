<?php
// Charger les variables d'environnement␊
$env = include __DIR__ . '/../env.php';

return [
    'client_id' => $env['ORANGE_MONEY_CLIENT_ID'],
    'client_secret' => $env['ORANGE_MONEY_CLIENT_SECRET'],
    'merchant_key' => $env['ORANGE_MONEY_MERCHANT_KEY'],
    'base_url' => $env['ORANGE_MONEY_BASE_URL'],
];
