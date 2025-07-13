<?php
/*
 * This file is part of the Dhru Fusion Pro Payment Gateway.
 *
 * @license    Proprietary
 * @copyright  2024 Dhru.com
 * @author     Dhru Fusion Team
 * @description Custom Payment Gateway Development Kit for Dhru Fusion Pro.
 * @powered    Powered by Dhru.com
 */

$envKeys = getenv('API_KEYS');
$apiKeys = array_filter(array_map('trim', explode(',', $envKeys ?: '8a35d9c4e2ccbd484cee94517806624c741cde76659a52d356f1a187f27d2c6a')));


return $apiKeys;
