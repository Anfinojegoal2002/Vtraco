<?php

declare(strict_types=1);

$config = [
    'host' => getenv('VTRACO_MAIL_HOST') ?: '',
    'port' => (int) (getenv('VTRACO_MAIL_PORT') ?: 0),
    'username' => getenv('VTRACO_MAIL_USERNAME') ?: '',
    'password' => getenv('VTRACO_MAIL_PASSWORD') ?: '',
    'encryption' => getenv('VTRACO_MAIL_ENCRYPTION') ?: '',
    'from_fallback' => getenv('VTRACO_MAIL_FROM_FALLBACK') ?: '',
];

$localPath = __DIR__ . '/mail.local.php';
if (is_file($localPath)) {
    $localConfig = require $localPath;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

return $config;
