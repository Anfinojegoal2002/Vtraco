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

function app_url(array $params = []): string
{
    $configuredAppUrl = trim((string) (getenv('VTRACO_APP_URL') ?: ''));
    $baseUrl = $configuredAppUrl !== '' ? $configuredAppUrl : trim((string) BASE_URL);
    if ($baseUrl === '') {
        $baseUrl = '/index.php';
    }

    if (!preg_match('#^https?://#i', $baseUrl)) {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            $host = 'localhost';
        }
        $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $scheme = $isHttps ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $host . '/' . ltrim($baseUrl, '/');
    }

    if ($params === []) {
        return $baseUrl;
    }

    return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . http_build_query($params);
}

function normalize_relative_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $basePath = str_replace('\\', '/', dirname(__DIR__, 2));
    if (str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }

    return ltrim($path, '/');
}

function public_file_path(string $path): string
{
    $path = normalize_relative_path($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return asset_url($path);
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
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

function notifications_for_user(int $userId, int $limit = 10): array
{
    $limit = max(1, min(100, $limit));
    $stmt = db()->prepare(
        'SELECT *
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY is_read ASC, created_at DESC, id DESC
         LIMIT ' . $limit
    );
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
    $stmt->execute(['user_id' => $userId]);

    return (int) $stmt->fetchColumn();
}

function mark_notifications_read(int $userId): void
{
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0')
        ->execute(['user_id' => $userId]);
}

function mark_notification_read(int $userId, int $notificationId): void
{
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id')
        ->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);
}

function password_meets_policy(string $password): bool
{
    $length = strlen($password);
    return $length >= 1 && $length <= 6;
}

function password_policy_message(): string
{
    return 'Password must be 6 characters or less. Letters, numbers, and symbols are allowed.';
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

function render_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
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
    if (!$user) {
        return null;
    }

    if (in_array($user['role'] ?? '', ['admin', 'freelancer', 'external_vendor'], true)) {
        return (int) $user['id'];
    }

    if (employee_has_power_access($user) && !empty($user['admin_id'])) {
        return (int) $user['admin_id'];
    }

    return null;
}

function user_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'employee' => 'Employee',
        'corporate_employee' => 'Corporate Employee',
        'external_vendor' => 'External Vendor',
        'freelancer' => 'Corporate Employee',
        'super_admin' => 'Super Admin',
        default => ucwords(str_replace('_', ' ', $role)),
    };
}

function employee_designation_groups(): array
{
    return [
        'Internal Teams' => [
            'HR' => 'HR',
            'Accounts' => 'Accounts',
            'Management Team' => 'Management Team',
            'Backend Team' => 'Backend Team',
        ],
        'Field and Training' => [
            'Project Coordinator' => 'Project Coordinator',
            'In-house Trainer' => 'In-house Trainer',
        ],
        'Employee Types' => [
            'Regular Employee' => 'Regular Employee',
            'Contractual' => 'Contractual',
            'Vendor' => 'Vendor',
        ],
    ];
}

function employee_designation_options(): array
{
    $options = [];
    foreach (employee_designation_groups() as $group) {
        foreach ($group as $value => $label) {
            $options[$value] = $label;
        }
    }

    return $options;
}

function valid_employee_designation(string $designation): bool
{
    return array_key_exists($designation, employee_designation_options());
}

function employee_recruitment_sources(): array
{
    return ['Referral', 'Job Portal', 'Walk-in', 'Campus', 'Social Media', 'Consultancy', 'Other'];
}

function employee_profile_required_text_fields(?array $user): array
{
    $isContractual = (string) ($user['role'] ?? '') === 'corporate_employee';

    return $isContractual
        ? [
            'emp_id',
            'name',
            'date_of_joining',
            'date_of_birth',
            'gender',
            'training_experience_years',
            'languages_known',
            'email',
            'phone',
            'technical_skills',
            'bank_name',
            'bank_account_no',
            'bank_ifsc_code',
            'account_holder_name',
        ]
        : [
            'emp_id',
            'name',
            'email',
            'date_of_joining',
            'date_of_birth',
            'gender',
            'highest_qualification',
            'phone',
            'address',
            'bank_name',
            'bank_account_no',
            'bank_ifsc_code',
            'account_holder_name',
        ];
}

function employee_profile_required_document_fields(?array $user): array
{
    $isContractual = (string) ($user['role'] ?? '') === 'corporate_employee';

    return $isContractual
        ? ['pan_card', 'bank_proof', 'profile_photo', 'resume']
        : ['aadhaar_card', 'pan_card', 'profile_photo', 'qualification_certificate', 'bank_proof', 'resume'];
}

function employee_profile_fields_complete(?array $user): bool
{
    if (!$user || !in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true)) {
        return true;
    }

    foreach (employee_profile_required_text_fields($user) as $field) {
        if (trim((string) ($user[$field] ?? '')) === '') {
            return false;
        }
    }

    foreach (employee_profile_required_document_fields($user) as $field) {
        if (trim((string) ($user[$field . '_path'] ?? '')) === '') {
            return false;
        }
    }

    return true;
}

function employee_profile_requires_completion(?array $user): bool
{
    if (!$user || !in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true)) {
        return false;
    }

    return !employee_profile_fields_complete($user);
}

function employee_profile_is_verified(?array $user): bool
{
    if (!$user || !in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true)) {
        return true;
    }

    return (string) ($user['profile_status'] ?? '') === 'verified';
}

function employee_profile_verification_exempt(?array $user): bool
{
    if (!$user) {
        return false;
    }

    $role = (string) ($user['role'] ?? '');
    $employeeType = strtolower(trim((string) ($user['employee_type'] ?? '')));
    $designation = strtolower(trim((string) ($user['designation'] ?? '')));

    return $role === 'corporate_employee'
        || in_array($employeeType, ['corporate', 'vendor'], true)
        || in_array($designation, ['contractual', 'vendor'], true);
}

function employee_is_vendor_trainer(?array $user): bool
{
    if (!$user) {
        return false;
    }

    $role = (string) ($user['role'] ?? '');
    $employeeType = strtolower(trim((string) ($user['employee_type'] ?? '')));
    $designation = strtolower(trim((string) ($user['designation'] ?? '')));

    return $role === 'employee' && ($employeeType === 'vendor' || $designation === 'vendor');
}

function employee_is_hr_reviewer(?array $user): bool
{
    if (!$user) {
        return false;
    }

    if (in_array((string) ($user['role'] ?? ''), ['admin', 'freelancer'], true)) {
        return true;
    }

    return in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true)
        && (string) ($user['designation'] ?? '') === 'HR'
        && (string) ($user['profile_status'] ?? '') === 'verified';
}

function employee_is_in_house_trainer(?array $user): bool
{
    if (!$user || (string) ($user['role'] ?? '') !== 'employee') {
        return false;
    }

    return (string) ($user['designation'] ?? '') === 'In-house Trainer';
}

function employee_is_project_coordinator(?array $user): bool
{
    if (!$user || (string) ($user['role'] ?? '') !== 'employee') {
        return false;
    }

    return (string) ($user['designation'] ?? '') === 'Project Coordinator';
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
    return in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor', 'freelancer', 'super_admin'], true);
}

function is_vendor_profile_complete(?array $user): bool
{
    if (!$user || ($user['role'] ?? '') !== 'external_vendor') {
        return true;
    }

    $required = [
        'company_name',
        'company_address',
        'company_email',
        'company_phone',
        'representative_name',
        'designation',
        'personal_email',
        'personal_phone',
        'bank_proof_path',
        'company_logo_path',
        'profile_photo_path',
    ];

    foreach ($required as $field) {
        if (trim((string) ($user[$field] ?? '')) === '') {
            return false;
        }
    }

    return true;
}

function can_self_register_role(string $role): bool
{
    return in_array($role, ['admin', 'corporate_employee'], true);
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

function employee_has_power_access(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user || !in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true)) {
        return false;
    }

    $rules = employee_rules((int) ($user['id'] ?? 0));
    return !empty($rules['power_access']);
}

function employee_power_attendance_scopes(?array $user = null): array
{
    $user = $user ?? current_user();
    if (!employee_has_power_access($user)) {
        return [];
    }

    $rules = employee_rules((int) ($user['id'] ?? 0));
    $scopes = [];
    foreach (power_attendance_scope_options() as $scope => $label) {
        if (!empty($rules['power_attendance_' . $scope])) {
            $scopes[] = $scope;
        }
    }

    return $scopes;
}

function employee_has_power_attendance_access(?array $user = null): bool
{
    return employee_power_attendance_scopes($user) !== [];
}

function employee_has_power_projects_access(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!employee_has_power_access($user)) {
        return false;
    }

    $rules = employee_rules((int) ($user['id'] ?? 0));
    return !empty($rules['power_projects']);
}

function employee_power_team_scopes(?array $user = null): array
{
    $user = $user ?? current_user();
    if (!employee_has_power_access($user)) {
        return [];
    }

    $rules = employee_rules((int) ($user['id'] ?? 0));
    $scopes = [];
    foreach (power_team_scope_options() as $scope => $label) {
        if (!empty($rules['power_team_' . $scope])) {
            $scopes[] = $scope;
        }
    }

    return $scopes;
}

function employee_has_power_team_access(?array $user = null): bool
{
    return employee_power_team_scopes($user) !== [];
}

function employee_has_power_accounts_access(?array $user = null): bool
{
    return employee_power_account_scopes($user) !== [];
}

function employee_power_account_scopes(?array $user = null): array
{
    $user = $user ?? current_user();
    if (!employee_has_power_access($user)) {
        return [];
    }

    $rules = employee_rules((int) ($user['id'] ?? 0));
    $scopes = [];
    foreach (power_accounts_scope_options() as $scope => $label) {
        if (!empty($rules['power_accounts_' . $scope]) || (!empty($rules['power_accounts']) && empty($rules['power_accounts_verify']) && empty($rules['power_accounts_pay']) && empty($rules['power_accounts_history']))) {
            $scopes[] = $scope;
        }
    }

    return $scopes;
}

function employee_has_power_account_scope(string $scope, ?array $user = null): bool
{
    return in_array($scope, employee_power_account_scopes($user), true);
}

function can_access_power_admin_pages(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }

    return in_array((string) ($user['role'] ?? ''), ['admin', 'freelancer', 'external_vendor'], true)
        || employee_has_power_access($user);
}

function require_power_admin_access(array $baseRoles = ['admin']): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in with an allowed account to continue.');
        redirect_to('login');
    }

    if (in_array((string) ($user['role'] ?? ''), $baseRoles, true)) {
        return $user;
    }

    if (employee_has_power_access($user) && !empty($user['admin_id'])) {
        $delegatedUser = $user;
        $delegatedUser['delegated_user_id'] = (int) ($user['id'] ?? 0);
        $delegatedUser['id'] = (int) $user['admin_id'];
        return $delegatedUser;
    }

    flash('error', 'You do not have permission to access this page.');
    redirect_to(home_page_for_user($user));
}

function require_power_attendance_access(array $baseRoles = ['admin']): array
{
    $user = current_user();
    $delegated = require_power_admin_access($baseRoles);
    if ($user && in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true) && !employee_has_power_attendance_access($user)) {
        flash('error', 'Track Attendance power is not assigned to your account.');
        redirect_to(home_page_for_user($user));
    }

    return $delegated;
}

function require_power_projects_access(array $baseRoles = ['admin']): array
{
    $user = current_user();
    $delegated = require_power_admin_access($baseRoles);
    if ($user && in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true) && !employee_has_power_projects_access($user)) {
        flash('error', 'Employee Projects power is not assigned to your account.');
        redirect_to(home_page_for_user($user));
    }

    return $delegated;
}

function require_power_team_access(array $baseRoles = ['admin']): array
{
    $user = current_user();
    $delegated = require_power_admin_access($baseRoles);
    if ($user && in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true) && !employee_has_power_team_access($user)) {
        flash('error', 'Employee power is not assigned to your account.');
        redirect_to(home_page_for_user($user));
    }

    return $delegated;
}

function require_power_accounts_access(array $baseRoles = ['admin'], ?string $scope = null): array
{
    $user = current_user();
    $delegated = require_power_admin_access($baseRoles);
    if ($user && in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true) && !employee_has_power_accounts_access($user)) {
        flash('error', 'Accounts power is not assigned to your account.');
        redirect_to(home_page_for_user($user));
    }
    if ($scope !== null && $user && in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true) && !employee_has_power_account_scope($scope, $user)) {
        flash('error', 'This Accounts power is not assigned to your account.');
        redirect_to('admin_accounts');
    }

    return $delegated;
}

function home_page_for_user(array $user): string
{
    $role = (string) ($user['role'] ?? '');

    return match (true) {
        $role === 'super_admin' => 'super_admin_dashboard',
        $role === 'admin' => 'admin_dashboard',
        $role === 'freelancer' => 'corporate_dashboard',
        $role === 'external_vendor' => 'vendor_dashboard',
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
