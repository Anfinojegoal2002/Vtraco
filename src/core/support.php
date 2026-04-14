<?php

declare(strict_types=1);

function now(): string
{
    return date('Y-m-d H:i:s');
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_path_url(): string
{
    $basePath = str_replace('\\', '/', dirname(BASE_URL));

    if ($basePath === '/' || $basePath === '.' || $basePath === '\\') {
        return '';
    }

    return rtrim($basePath, '/');
}

function asset_url(string $path): string
{
    return base_path_url() . '/' . ltrim($path, '/');
}

function ensure_directory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf_request(): void
{
    $token = (string) ($_POST['_csrf'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        throw new RuntimeException('Your session expired or the request token was invalid. Please refresh the page and try again.');
    }
}

function client_ip_address(): string
{
    $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded !== '') {
        $parts = array_map('trim', explode(',', $forwarded));
        if (($parts[0] ?? '') !== '') {
            return $parts[0];
        }
    }

    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) ?: 'unknown';
}

function app_log(string $level, string $message, array $context = []): void
{
    ensure_directory(APP_LOG_DIR);
    $line = '[' . now() . '] ' . strtoupper($level) . ': ' . $message;
    if ($context !== []) {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($encoded) && $encoded !== '') {
            $line .= ' ' . $encoded;
        }
    }

    file_put_contents(APP_LOG_DIR . '/app.log', $line . PHP_EOL, FILE_APPEND);
}

function report_exception(Throwable $exception, string $message, array $context = []): void
{
    $context['exception'] = get_class($exception);
    $context['error'] = $exception->getMessage();
    $context['file'] = $exception->getFile();
    $context['line'] = $exception->getLine();
    app_log('error', $message, $context);
}

function audit_log(string $action, array $details = [], ?int $targetUserId = null, ?array $actor = null): void
{
    $actor = $actor ?? current_user() ?? [];
    $actorId = isset($actor['id']) ? (int) $actor['id'] : null;
    $actorRole = trim((string) ($actor['role'] ?? 'guest')) ?: 'guest';
    $detailJson = $details !== [] ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

    try {
        db()->prepare('INSERT INTO activity_logs (actor_user_id, actor_role, target_user_id, action, details_json, ip_address, created_at) VALUES (:actor_user_id, :actor_role, :target_user_id, :action, :details_json, :ip_address, :created_at)')
            ->execute([
                'actor_user_id' => $actorId,
                'actor_role' => $actorRole,
                'target_user_id' => $targetUserId,
                'action' => $action,
                'details_json' => $detailJson,
                'ip_address' => client_ip_address(),
                'created_at' => now(),
            ]);
    } catch (Throwable $exception) {
        report_exception($exception, 'Failed to write activity log.', [
            'action' => $action,
            'target_user_id' => $targetUserId,
            'details' => $details,
        ]);
    }
}

function password_meets_policy(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1;
}

function password_policy_message(): string
{
    return 'Password must be at least 8 characters and include at least one letter and one number.';
}

function forgot_password_cooldown_seconds(): int
{
    return 15 * 60;
}

function password_change_required(array $user): bool
{
    return !empty($user['force_password_change']);
}

function human_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format($bytes / (1024 * 1024), 1) . ' MB';
}

function upload_error_message(int $error, string $label): string
{
    $name = ucfirst($label);

    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $name . ' exceeds the server upload limit.',
        UPLOAD_ERR_PARTIAL => $name . ' was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => $name . ' is required.',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Unable to save the uploaded ' . $label . '.',
        UPLOAD_ERR_EXTENSION => 'A server extension blocked the uploaded ' . $label . '.',
        default => 'Unable to upload the ' . $label . '.',
    };
}

function uploaded_file_extension(array $file): string
{
    return strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
}

function uploaded_file_mime_type(array $file): string
{
    $path = (string) ($file['tmp_name'] ?? '');
    if ($path === '' || !is_file($path)) {
        return '';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);
    return is_string($mime) ? strtolower($mime) : '';
}

function validate_uploaded_file(array $file, array $allowedExtensions, int $maxBytes, string $label): void
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message($error, $label));
    }

    $path = (string) ($file['tmp_name'] ?? '');
    if ($path === '' || !is_uploaded_file($path)) {
        throw new RuntimeException('Invalid ' . $label . ' upload.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        $size = (int) (@filesize($path) ?: 0);
    }
    if ($size <= 0) {
        throw new RuntimeException(ucfirst($label) . ' is empty.');
    }
    if ($size > $maxBytes) {
        throw new RuntimeException(ucfirst($label) . ' exceeds the ' . human_file_size($maxBytes) . ' limit.');
    }

    $extension = uploaded_file_extension($file);
    if ($allowedExtensions !== [] && !in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Invalid ' . $label . ' format. Allowed: ' . implode(', ', $allowedExtensions) . '.');
    }
}

function user_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'E';
}

function redirect_to(string $page, array $params = []): void
{
    $query = http_build_query(array_merge(['page' => $page], $params));
    header('Location: ' . BASE_URL . '?' . $query);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $items;
}

function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_role(string $role): array
{
    $user = current_user();
    if (!$user || $user['role'] !== $role) {
        flash('error', 'Please sign in as ' . $role . ' to continue.');
        redirect_to('login', ['role' => $role]);
    }

    return $user;
}

function current_admin_id(): ?int
{
    $user = current_user();
    if (!$user || !in_array($user['role'] ?? '', ['admin', 'freelancer'], true)) {
        return null;
    }

    return (int) $user['id'];
}

function user_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'employee' => 'Employee',
        'corporate_employee' => 'Corporate Staff',
        'external_vendor' => 'External Vendor',
        'freelancer' => 'Corporate Employee',
        default => ucwords(str_replace('_', ' ', $role)),
    };
}

function current_manager_target_role(): string
{
    $user = current_user();
    if (!$user) {
        return 'employee';
    }

    return $user['role'] === 'freelancer' ? 'corporate_employee' : 'employee';
}

function can_login_role(string $role): bool
{
    return in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor', 'freelancer'], true);
}

function can_self_register_role(string $role): bool
{
    return in_array($role, ['admin', 'external_vendor', 'freelancer'], true);
}

function role_requires_unique_email(string $role): bool
{
    return in_array($role, ['employee', 'corporate_employee'], true);
}

function role_email_exists(string $role, string $email, ?int $ignoreUserId = null): bool
{
    $email = trim($email);
    if ($email === '') {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM users WHERE role = :role AND LOWER(email) = LOWER(:email)';
    $params = [
        'role' => $role,
        'email' => $email,
    ];

    if ($ignoreUserId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $ignoreUserId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}

function is_member_portal_role(string $role): bool
{
    return in_array($role, ['external_vendor', 'freelancer'], true);
}

function home_page_for_user(array $user): string
{
    $role = (string) ($user['role'] ?? '');

    return match (true) {
        $role === 'admin' => 'admin_dashboard',
        $role === 'freelancer' => 'admin_dashboard',
        $role === 'employee' => 'employee_attendance',
        $role === 'corporate_employee' => 'employee_attendance',
        is_member_portal_role($role) => 'member_dashboard',
        default => 'landing',
    };
}

function require_roles(array $roles): array
{
    $user = current_user();
    if (!$user || !in_array((string) ($user['role'] ?? ''), $roles, true)) {
        flash('error', 'Please sign in with an allowed account to continue.');
        redirect_to('login');
    }

    return $user;
}
