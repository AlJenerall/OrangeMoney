<?php
return [
    'DB_TYPE' => getenv('DB_TYPE') ?: 'mysql',
    'DB_HOST' => getenv('DB_HOST') ?: 'localhost',
    'DB_NAME' => getenv('DB_NAME') ?: 'ttyyqpmy_MyOm',
    'DB_USER' => getenv('DB_USER') ?: 'ttyyqpmy_MyOmUser',
    'DB_PASS' => getenv('DB_PASS') ?: 'MyOmPassword25@',
    'SQLITE_FILE' => getenv('SQLITE_FILE') ?: __DIR__ . '/database.sqlite',

    'ORANGE_MONEY_CLIENT_ID' => getenv('ORANGE_MONEY_CLIENT_ID') ?: 'mcpzniKFcMIDRUJIl3vpvEP6s5zABWQV',
    'ORANGE_MONEY_CLIENT_SECRET' => getenv('ORANGE_MONEY_CLIENT_SECRET') ?: 'hsjP66JGXxPHLBFK5d9lLoOjpxw8qpjfyGrQZL6BPheJ',
    'ORANGE_MONEY_MERCHANT_KEY' => getenv('ORANGE_MONEY_MERCHANT_KEY') ?: '268671bb',
    'ORANGE_MONEY_BASE_URL' => getenv('ORANGE_MONEY_BASE_URL') ?: 'https://api.orange.com',

    'APP_SECRET_KEY' => getenv('APP_SECRET_KEY') ?: '8a35d9c4e2ccbd484cee94517806624c741cde76659a52d356f1a187f27d2c6a',
];
