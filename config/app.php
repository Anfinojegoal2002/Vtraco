<?php

declare(strict_types=1);

return [
    'name' => 'V Traco',
    'timezone' => 'Asia/Calcutta',
    'base_url' => '',
    'paths' => [
        'sqlite_migration_source' => dirname(__DIR__) . '/storage/data/app.sqlite',
        'mail_log' => dirname(__DIR__) . '/storage/emails',
        'uploads' => dirname(__DIR__) . '/storage/uploads/punches',
        'logs' => dirname(__DIR__) . '/storage/logs',
    ],
];
