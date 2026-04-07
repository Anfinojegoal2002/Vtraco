<?php

declare(strict_types=1);

session_start();

date_default_timezone_set('Asia/Calcutta');

const APP_NAME = 'V Traco';
const DB_PATH = __DIR__ . '/../storage/data/app.sqlite';
const MAIL_LOG_PATH = __DIR__ . '/../storage/emails';
const UPLOAD_PATH = __DIR__ . '/../storage/uploads/punches';
const BASE_URL = '/vtraco/index.php';
const MAIL_SMTP_HOST = '';
const MAIL_SMTP_PORT = 587;
const MAIL_SMTP_USERNAME = '';
const MAIL_SMTP_PASSWORD = '';
const MAIL_SMTP_ENCRYPTION = 'tls';
const MAIL_SMTP_FROM_FALLBACK = 'no-reply@vtraco.local';
