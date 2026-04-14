<?php

declare(strict_types=1);

return [
    'host' => getenv('VTRACO_MAIL_HOST') ?: '',
    'port' => (int) (getenv('VTRACO_MAIL_PORT') ?: 0),
    'username' => getenv('VTRACO_MAIL_USERNAME') ?: '',
    'password' => getenv('VTRACO_MAIL_PASSWORD') ?: '',
    'encryption' => getenv('VTRACO_MAIL_ENCRYPTION') ?: '',
    'from_fallback' => getenv('VTRACO_MAIL_FROM_FALLBACK') ?: '',
];
