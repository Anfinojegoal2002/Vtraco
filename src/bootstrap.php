<?php

declare(strict_types=1);

session_start();

date_default_timezone_set('Asia/Calcutta');

const APP_NAME = 'V Traco';
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'vtraco';
const DB_USERNAME = 'root';
const DB_PASSWORD = '';
const DB_CHARSET = 'utf8mb4';
const DB_COLLATION = 'utf8mb4_unicode_ci';
const SQLITE_MIGRATION_SOURCE = __DIR__ . '/../storage/data/app.sqlite';
const MAIL_LOG_PATH = __DIR__ . '/../storage/emails';
const UPLOAD_PATH = __DIR__ . '/../storage/uploads/punches';
const BASE_URL = '/vtraco/index.php';
const MAIL_SMTP_HOST = 'smtp.gmail.com';
const MAIL_SMTP_PORT = 587;
const MAIL_SMTP_USERNAME = 'anfinojegoal@gmail.com';
const MAIL_SMTP_PASSWORD = 'fbrm apbm glsm mznr';
const MAIL_SMTP_ENCRYPTION = 'tls';
const MAIL_SMTP_FROM_FALLBACK = 'anfinojegoal@gmail.com';