<?php

declare(strict_types=1);

$config = [
    'base_url' => getenv('VTRACO_ETIME_BASE_URL') ?: 'https://api.etimeoffice.com/api/',
    'corporate_id' => getenv('VTRACO_ETIME_CORPORATE_ID') ?: '',
    'username' => getenv('VTRACO_ETIME_USERNAME') ?: '',
    'password' => getenv('VTRACO_ETIME_PASSWORD') ?: '',
    'timeout' => (int) (getenv('VTRACO_ETIME_TIMEOUT') ?: 30),
    'webhook_secret' => getenv('VTRACO_ETIME_WEBHOOK_SECRET') ?: '',
];

$localPath = __DIR__ . '/etime.local.php';
if (is_file($localPath)) {
    $localConfig = require $localPath;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

return $config;
