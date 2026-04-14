<?php

declare(strict_types=1);

function load_config(string $name): array
{
    $path = __DIR__ . '/../config/' . $name . '.php';

    if (!file_exists($path)) {
        throw new RuntimeException('Missing config file: ' . $path);
    }

    $config = require $path;

    if (!is_array($config)) {
        throw new RuntimeException('Config file must return an array: ' . $path);
    }

    return $config;
}

$appConfig = load_config('app');
$databaseConfig = load_config('database');
$mailConfig = load_config('mail');

$detectedBaseUrl = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
$configuredBaseUrl = trim((string) ($appConfig['base_url'] ?? ''));
$baseUrl = $configuredBaseUrl !== '' ? $configuredBaseUrl : $detectedBaseUrl;

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

date_default_timezone_set((string) ($appConfig['timezone'] ?? 'UTC'));

define('APP_NAME', (string) ($appConfig['name'] ?? 'V Traco'));
define('DB_HOST', (string) ($databaseConfig['host'] ?? '127.0.0.1'));
define('DB_PORT', (int) ($databaseConfig['port'] ?? 3306));
define('DB_NAME', (string) ($databaseConfig['name'] ?? 'vtraco'));
define('DB_USERNAME', (string) ($databaseConfig['username'] ?? 'root'));
define('DB_PASSWORD', (string) ($databaseConfig['password'] ?? ''));
define('DB_CHARSET', (string) ($databaseConfig['charset'] ?? 'utf8mb4'));
define('DB_COLLATION', (string) ($databaseConfig['collation'] ?? 'utf8mb4_unicode_ci'));
define('SQLITE_MIGRATION_SOURCE', (string) (($appConfig['paths']['sqlite_migration_source'] ?? __DIR__ . '/../storage/data/app.sqlite')));
define('MAIL_LOG_PATH', (string) (($appConfig['paths']['mail_log'] ?? __DIR__ . '/../storage/emails')));
define('UPLOAD_PATH', (string) (($appConfig['paths']['uploads'] ?? __DIR__ . '/../storage/uploads/punches')));
define('APP_LOG_DIR', (string) (($appConfig['paths']['logs'] ?? __DIR__ . '/../storage/logs')));
define('BASE_URL', $baseUrl);
define('MAIL_SMTP_HOST', (string) ($mailConfig['host'] ?? ''));
define('MAIL_SMTP_PORT', (int) ($mailConfig['port'] ?? 0));
define('MAIL_SMTP_USERNAME', (string) ($mailConfig['username'] ?? ''));
define('MAIL_SMTP_PASSWORD', (string) ($mailConfig['password'] ?? ''));
define('MAIL_SMTP_ENCRYPTION', (string) ($mailConfig['encryption'] ?? ''));
define('MAIL_SMTP_FROM_FALLBACK', (string) ($mailConfig['from_fallback'] ?? ''));
