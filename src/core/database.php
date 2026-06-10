<?php

declare(strict_types=1);

function db_server(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ensure_database_exists(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $databaseName = str_replace('`', '``', DB_NAME);
    db_server()->exec('CREATE DATABASE IF NOT EXISTS `' . $databaseName . '` CHARACTER SET ' . DB_CHARSET . ' COLLATE ' . DB_COLLATION);
    $ensured = true;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    ensure_database_exists();

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('SET NAMES ' . DB_CHARSET);

    return $pdo;
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $stmt->execute([
        'schema' => DB_NAME,
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function index_exists(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name');
    $stmt->execute([
        'schema' => DB_NAME,
        'table_name' => $table,
        'index_name' => $indexName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function unique_index_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare('SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name AND NON_UNIQUE = 0 ORDER BY INDEX_NAME, SEQ_IN_INDEX');
    $stmt->execute([
        'schema' => DB_NAME,
        'table_name' => $table,
    ]);

    $indexes = [];
    foreach ($stmt->fetchAll() as $row) {
        $indexName = (string) $row['INDEX_NAME'];
        if (!isset($indexes[$indexName])) {
            $indexes[$indexName] = [];
        }
        $indexes[$indexName][] = (string) $row['COLUMN_NAME'];
    }

    return $indexes;
}

function backfill_punch_photos_from_files(PDO $pdo): void
{
    $appRoot = str_replace('\\', '/', dirname(__DIR__, 2));
    $tables = ['attendance_sessions', 'attendance_records'];

    foreach ($tables as $table) {
        $select = $pdo->query("SELECT id, punch_in_path FROM {$table} WHERE punch_in_path IS NOT NULL AND punch_in_path <> '' AND punch_in_photo IS NULL LIMIT 100");
        if (!$select) {
            continue;
        }

        $update = $pdo->prepare("UPDATE {$table} SET punch_in_photo = :photo, punch_in_photo_mime = :mime, punch_in_photo_name = :name WHERE id = :id");
        foreach ($select->fetchAll() as $row) {
            $relativePath = str_replace('\\', '/', trim((string) ($row['punch_in_path'] ?? '')));
            $relativePath = preg_replace('#^https?://[^/]+#i', '', $relativePath) ?? '';
            $relativePath = ltrim($relativePath, '/');
            if ($relativePath === '' || str_contains($relativePath, '..')) {
                continue;
            }

            $fullPath = $appRoot . '/' . $relativePath;
            if (!is_file($fullPath)) {
                continue;
            }

            $mime = mime_content_type($fullPath) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                continue;
            }

            $contents = file_get_contents($fullPath);
            if ($contents === false) {
                continue;
            }

            $update->execute([
                'photo' => $contents,
                'mime' => $mime,
                'name' => basename($relativePath),
                'id' => (int) $row['id'],
            ]);
        }
    }
}

function backfill_reimbursement_attachments_from_files(PDO $pdo): void
{
    $appRoot = str_replace('\\', '/', dirname(__DIR__, 2));
    $select = $pdo->query("SELECT id, attachment_path, attachment_mime FROM employee_reimbursements WHERE attachment_path IS NOT NULL AND attachment_path <> '' AND attachment_data IS NULL LIMIT 50");
    if (!$select) {
        return;
    }

    $update = $pdo->prepare('UPDATE employee_reimbursements SET attachment_data = :data WHERE id = :id');
    foreach ($select->fetchAll() as $row) {
        $relativePath = str_replace('\\', '/', trim((string) ($row['attachment_path'] ?? '')));
        $relativePath = preg_replace('#^https?://[^/]+#i', '', $relativePath) ?? '';
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            continue;
        }

        $fullPath = $appRoot . '/' . $relativePath;
        if (!is_file($fullPath)) {
            continue;
        }

        $mime = (string) ($row['attachment_mime'] ?? '');
        $data = str_starts_with($mime, 'image/')
            ? optimized_database_image_contents($fullPath)
            : file_get_contents($fullPath);
        if ($data === false) {
            continue;
        }

        $update->execute([
            'data' => is_array($data) ? $data['data'] : $data,
            'id' => (int) $row['id'],
        ]);
    }
}

function optimized_database_image_contents(string $source): array|false
{
    $raw = file_get_contents($source);
    if ($raw === false) {
        return false;
    }

    if (!function_exists('imagecreatefromstring')) {
        return [
            'data' => $raw,
            'mime' => mime_content_type($source) ?: 'image/jpeg',
        ];
    }

    $image = @imagecreatefromstring($raw);
    if (!$image) {
        return [
            'data' => $raw,
            'mime' => mime_content_type($source) ?: 'image/jpeg',
        ];
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $scale = min(1, 1200 / max($width, $height));
    $targetWidth = max(1, (int) round($width * $scale));
    $targetHeight = max(1, (int) round($height * $scale));
    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    imagedestroy($image);

    $encoded = false;
    foreach ([78, 68, 58] as $quality) {
        ob_start();
        imagejpeg($canvas, null, $quality);
        $candidate = ob_get_clean();
        if (is_string($candidate) && ($encoded === false || strlen($candidate) < strlen($encoded))) {
            $encoded = $candidate;
        }
        if (is_string($candidate) && strlen($candidate) <= 700 * 1024) {
            break;
        }
    }
    imagedestroy($canvas);

    return is_string($encoded)
        ? ['data' => $encoded, 'mime' => 'image/jpeg']
        : ['data' => $raw, 'mime' => mime_content_type($source) ?: 'image/jpeg'];
}

function initialize_database(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        role VARCHAR(50) NOT NULL,
        admin_id INT UNSIGNED NULL,
        emp_id VARCHAR(100) NULL,
        name VARCHAR(191) NOT NULL,
        email VARCHAR(191) NOT NULL,
        phone VARCHAR(50) NULL,
        shift VARCHAR(191) NULL,
        salary DECIMAL(12,2) NOT NULL DEFAULT 0,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_users_role_email (role, email),
        INDEX idx_users_role_admin_id (role, admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!table_has_column($pdo, 'users', 'admin_id')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN admin_id INT UNSIGNED NULL AFTER role');
    }
    if (!table_has_column($pdo, 'users', 'shift')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN shift VARCHAR(191) NULL AFTER phone');
    }
    $pdo->exec('ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL');
    if (!index_exists($pdo, 'users', 'idx_users_role_admin_id')) {
        $pdo->exec('CREATE INDEX idx_users_role_admin_id ON users(role, admin_id)');
    }
    foreach (unique_index_columns($pdo, 'users') as $indexName => $columns) {
        if ($indexName === 'PRIMARY' || !in_array($columns, [['email'], ['role', 'email']], true)) {
            continue;
        }

        $pdo->exec('ALTER TABLE users DROP INDEX `' . str_replace('`', '``', $indexName) . '`');
    }
    if (!index_exists($pdo, 'users', 'idx_users_role_email')) {
        $pdo->exec('CREATE INDEX idx_users_role_email ON users(role, email)');
    }
    if (!table_has_column($pdo, 'users', 'force_password_change')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
    }
    if (!table_has_column($pdo, 'users', 'password_reset_requested_at')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN password_reset_requested_at DATETIME NULL AFTER force_password_change');
    }
    if (!table_has_column($pdo, 'users', 'password_changed_at')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER password_reset_requested_at');
    }
    if (!table_has_column($pdo, 'users', 'status')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "ACTIVE" AFTER role');
    }
    if (!index_exists($pdo, 'users', 'idx_users_password_reset_requested_at')) {
        $pdo->exec('CREATE INDEX idx_users_password_reset_requested_at ON users(password_reset_requested_at)');
    }
    // Repair missing statuses for existing users
    $pdo->exec("UPDATE users SET status = 'ACTIVE' WHERE status IS NULL OR status = ''");
    $pdo->exec("UPDATE users SET status = 'ACTIVE' WHERE role IN ('admin', 'freelancer', 'external_vendor') AND status NOT IN ('ACTIVE', 'BLOCKED', 'PENDING')");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_otps (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        role VARCHAR(50) NOT NULL,
        email VARCHAR(191) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_password_reset_otps_email_role (email, role),
        INDEX idx_password_reset_otps_user_id (user_id),
        CONSTRAINT fk_password_reset_otps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!table_has_column($pdo, 'users', 'employee_type')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN employee_type VARCHAR(50) DEFAULT NULL AFTER salary');
    }
    if (!table_has_column($pdo, 'users', 'company_name')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN company_name VARCHAR(191) NULL AFTER phone');
    }
    if (!table_has_column($pdo, 'users', 'company_address')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN company_address TEXT NULL AFTER company_name');
    }
    if (!table_has_column($pdo, 'users', 'company_email')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN company_email VARCHAR(191) NULL AFTER company_address');
    }
    if (!table_has_column($pdo, 'users', 'company_phone')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN company_phone VARCHAR(50) NULL AFTER company_email');
    }
    if (!table_has_column($pdo, 'users', 'representative_name')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN representative_name VARCHAR(191) NULL AFTER company_phone');
    }
    if (!table_has_column($pdo, 'users', 'personal_email')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN personal_email VARCHAR(191) NULL AFTER representative_name');
    }
    if (!table_has_column($pdo, 'users', 'personal_phone')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN personal_phone VARCHAR(50) NULL AFTER personal_email');
    }
    if (!table_has_column($pdo, 'users', 'gst_no')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN gst_no VARCHAR(50) NULL AFTER representative_name');
    }
    if (!table_has_column($pdo, 'users', 'pan_no')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN pan_no VARCHAR(50) NULL AFTER gst_no');
    }
    if (!table_has_column($pdo, 'users', 'bank_account_no')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN bank_account_no VARCHAR(100) NULL AFTER pan_no');
    }
    if (!table_has_column($pdo, 'users', 'bank_ifsc_code')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN bank_ifsc_code VARCHAR(50) NULL AFTER bank_account_no');
    }
    if (!table_has_column($pdo, 'users', 'bank_branch')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN bank_branch VARCHAR(191) NULL AFTER bank_ifsc_code');
    }
    if (!table_has_column($pdo, 'users', 'bank_name')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN bank_name VARCHAR(191) NULL AFTER bank_branch');
    }
    if (!table_has_column($pdo, 'users', 'recruiter_name')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN recruiter_name VARCHAR(191) NULL AFTER employee_type');
    }
    if (!table_has_column($pdo, 'users', 'recruited_through')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN recruited_through VARCHAR(100) NULL AFTER recruiter_name');
    }
    if (!table_has_column($pdo, 'users', 'designation')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN designation VARCHAR(100) NULL AFTER recruited_through');
    }
    if (!table_has_column($pdo, 'users', 'date_of_joining')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN date_of_joining DATE NULL AFTER designation');
    }
    if (!table_has_column($pdo, 'users', 'profile_status')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN profile_status VARCHAR(50) NOT NULL DEFAULT "incomplete" AFTER date_of_joining');
    }
    if (!table_has_column($pdo, 'users', 'profile_rejection_reason')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN profile_rejection_reason TEXT NULL AFTER profile_status');
    }
    if (!table_has_column($pdo, 'users', 'profile_changed_fields_json')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN profile_changed_fields_json TEXT NULL AFTER profile_rejection_reason');
    }
    if (!table_has_column($pdo, 'users', 'profile_changed_at')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN profile_changed_at DATETIME NULL AFTER profile_changed_fields_json');
    }
    if (!table_has_column($pdo, 'users', 'date_of_birth')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN date_of_birth DATE NULL AFTER profile_changed_at');
    }
    if (!table_has_column($pdo, 'users', 'gender')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN gender VARCHAR(50) NULL AFTER date_of_birth');
    }
    if (!table_has_column($pdo, 'users', 'highest_qualification')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN highest_qualification VARCHAR(191) NULL AFTER gender');
    }
    if (!table_has_column($pdo, 'users', 'address')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN address TEXT NULL AFTER highest_qualification');
    }
    if (!table_has_column($pdo, 'users', 'offer_letter_name')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN offer_letter_name VARCHAR(191) NULL AFTER address');
    }
    if (!table_has_column($pdo, 'users', 'offer_letter_address')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN offer_letter_address TEXT NULL AFTER offer_letter_name');
    }
    if (!table_has_column($pdo, 'users', 'offer_letter_designation')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN offer_letter_designation VARCHAR(191) NULL AFTER offer_letter_address');
    }
    if (!table_has_column($pdo, 'users', 'offer_letter_signature_path')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN offer_letter_signature_path VARCHAR(255) NULL AFTER offer_letter_designation');
    }
    if (!table_has_column($pdo, 'users', 'offer_letter_signature_name')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN offer_letter_signature_name VARCHAR(191) NULL AFTER offer_letter_signature_path');
    }
    if (!table_has_column($pdo, 'users', 'training_experience_years')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN training_experience_years VARCHAR(50) NULL AFTER address');
    }
    if (!table_has_column($pdo, 'users', 'languages_known')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN languages_known TEXT NULL AFTER training_experience_years');
    }
    if (!table_has_column($pdo, 'users', 'technical_skills')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN technical_skills TEXT NULL AFTER languages_known');
    }
    if (!table_has_column($pdo, 'users', 'account_holder_name')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN account_holder_name VARCHAR(191) NULL AFTER bank_name');
    }
    foreach (['aadhaar_card', 'pan_card', 'profile_photo', 'qualification_certificate', 'bank_proof', 'resume'] as $documentKey) {
        if (!table_has_column($pdo, 'users', $documentKey . '_path')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN ' . $documentKey . '_path VARCHAR(255) NULL');
        }
        if (!table_has_column($pdo, 'users', $documentKey . '_name')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN ' . $documentKey . '_name VARCHAR(191) NULL');
        }
    }
    foreach (['company_logo'] as $documentKey) {
        if (!table_has_column($pdo, 'users', $documentKey . '_path')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN ' . $documentKey . '_path VARCHAR(255) NULL');
        }
        if (!table_has_column($pdo, 'users', $documentKey . '_name')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN ' . $documentKey . '_name VARCHAR(191) NULL');
        }
    }
    $pdo->exec("UPDATE users SET profile_status = 'verified' WHERE role IN ('admin', 'freelancer', 'external_vendor', 'super_admin') AND (profile_status IS NULL OR profile_status = '' OR profile_status = 'incomplete')");
    $pdo->exec("UPDATE users SET profile_status = 'verified', profile_rejection_reason = NULL WHERE role IN ('employee', 'corporate_employee') AND (role = 'corporate_employee' OR employee_type IN ('corporate', 'vendor') OR designation IN ('Contractual', 'Vendor')) AND profile_status <> 'verified'");
    $pdo->exec("UPDATE users SET profile_status = 'incomplete' WHERE role IN ('employee', 'corporate_employee') AND (profile_status IS NULL OR profile_status = '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_rules (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        rule_type VARCHAR(100) NOT NULL,
        slot_name VARCHAR(191) NULL,
        project_session_date DATE NULL,
        employee_date DATE NULL,
        project_session_from DATE NULL,
        project_session_to DATE NULL,
        shift_from DATE NULL,
        shift_to DATE NULL,
        employee_from DATE NULL,
        employee_to DATE NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX idx_employee_rules_user_id (user_id),
        CONSTRAINT fk_employee_rules_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!table_has_column($pdo, 'employee_rules', 'project_session_date')) {
        $pdo->exec('ALTER TABLE employee_rules ADD COLUMN project_session_date DATE NULL AFTER slot_name');
    }
    if (!table_has_column($pdo, 'employee_rules', 'employee_date')) {
        $pdo->exec('ALTER TABLE employee_rules ADD COLUMN employee_date DATE NULL AFTER project_session_date');
    }
    if (!table_has_column($pdo, 'employee_rules', 'project_session_from')) {
        $pdo->exec('ALTER TABLE employee_rules ADD COLUMN project_session_from DATE NULL AFTER employee_date');
    }
    if (!table_has_column($pdo, 'employee_rules', 'project_session_to')) {
        $pdo->exec('ALTER TABLE employee_rules ADD COLUMN project_session_to DATE NULL AFTER project_session_from');
    }
    if (!table_has_column($pdo, 'employee_rules', 'shift_from')) {
        $pdo->exec('ALTER TABLE employee_rules ADD COLUMN shift_from DATE NULL AFTER project_session_to');
    }
    if (!table_has_column($pdo, 'employee_rules', 'shift_to')) {
        $pdo->exec('ALTER TABLE employee_rules ADD COLUMN shift_to DATE NULL AFTER shift_from');
    }
    if (!table_has_column($pdo, 'employee_rules', 'employee_from')) {
        $pdo->exec('ALTER TABLE employee_rules ADD COLUMN employee_from DATE NULL AFTER shift_to');
    }
    if (!table_has_column($pdo, 'employee_rules', 'employee_to')) {
        $pdo->exec('ALTER TABLE employee_rules ADD COLUMN employee_to DATE NULL AFTER employee_from');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_project_assignments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        project_id INT UNSIGNED NOT NULL,
        project_from DATE NULL,
        project_to DATE NULL,
        project_incentive DECIMAL(12,2) NOT NULL DEFAULT 0,
        project_daily_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
        project_pay_basis VARCHAR(20) NOT NULL DEFAULT 'daily',
        created_at DATETIME NOT NULL,
        UNIQUE KEY uniq_employee_project_assignment (user_id, project_id),
        INDEX idx_employee_project_assignments_user_id (user_id),
        INDEX idx_employee_project_assignments_project_id (project_id),
        CONSTRAINT fk_employee_project_assignments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_employee_project_assignments_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!table_has_column($pdo, 'employee_project_assignments', 'project_from')) {
        $pdo->exec('ALTER TABLE employee_project_assignments ADD COLUMN project_from DATE NULL AFTER project_id');
    }
    if (!table_has_column($pdo, 'employee_project_assignments', 'project_to')) {
        $pdo->exec('ALTER TABLE employee_project_assignments ADD COLUMN project_to DATE NULL AFTER project_from');
    }
    if (!table_has_column($pdo, 'employee_project_assignments', 'project_incentive')) {
        $pdo->exec('ALTER TABLE employee_project_assignments ADD COLUMN project_incentive DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER project_to');
    }
    if (!table_has_column($pdo, 'employee_project_assignments', 'project_daily_salary')) {
        $pdo->exec('ALTER TABLE employee_project_assignments ADD COLUMN project_daily_salary DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER project_incentive');
    }
    if (!table_has_column($pdo, 'employee_project_assignments', 'project_pay_basis')) {
        $pdo->exec("ALTER TABLE employee_project_assignments ADD COLUMN project_pay_basis VARCHAR(20) NOT NULL DEFAULT 'daily' AFTER project_daily_salary");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS shift_timings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED NOT NULL,
        shift_name VARCHAR(191) NOT NULL,
        shift_date DATE NULL,
        shift_from DATE NULL,
        shift_to DATE NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_shift_timings_admin_id (admin_id),
        CONSTRAINT fk_shift_timings_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!table_has_column($pdo, 'shift_timings', 'shift_date')) {
        $pdo->exec('ALTER TABLE shift_timings ADD COLUMN shift_date DATE NULL AFTER shift_name');
    }
    if (!table_has_column($pdo, 'shift_timings', 'shift_from')) {
        $pdo->exec('ALTER TABLE shift_timings ADD COLUMN shift_from DATE NULL AFTER shift_date');
    }
    if (!table_has_column($pdo, 'shift_timings', 'shift_to')) {
        $pdo->exec('ALTER TABLE shift_timings ADD COLUMN shift_to DATE NULL AFTER shift_from');
    }
    $pdo->exec('UPDATE shift_timings SET shift_from = COALESCE(shift_from, shift_date), shift_to = COALESCE(shift_to, shift_date) WHERE shift_date IS NOT NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED NULL,
        project_code VARCHAR(50) NULL,
        project_name VARCHAR(191) NOT NULL,
        vendor_name VARCHAR(191) NULL,
        college_name VARCHAR(191) NOT NULL,
        location VARCHAR(191) NOT NULL,
        total_days INT NOT NULL,
        session_type ENUM('FULL_DAY', 'FIRST_HALF', 'SECOND_HALF') NOT NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        approval_status VARCHAR(50) NOT NULL DEFAULT 'verified',
        created_by_user_id INT UNSIGNED NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_projects_admin_active (admin_id, is_active),
        INDEX idx_projects_active_dates (is_active, start_date, end_date),
        INDEX idx_projects_name (project_name),
        CONSTRAINT fk_projects_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!table_has_column($pdo, 'projects', 'admin_id')) {
        $pdo->exec('ALTER TABLE projects ADD COLUMN admin_id INT UNSIGNED NULL AFTER id');
    }
    if (!table_has_column($pdo, 'projects', 'project_code')) {
        $pdo->exec('ALTER TABLE projects ADD COLUMN project_code VARCHAR(50) NULL AFTER admin_id');
    }
    if (!table_has_column($pdo, 'projects', 'vendor_name')) {
        $pdo->exec('ALTER TABLE projects ADD COLUMN vendor_name VARCHAR(191) NULL AFTER project_name');
    }
    if (!index_exists($pdo, 'projects', 'idx_projects_admin_active')) {
        $pdo->exec('CREATE INDEX idx_projects_admin_active ON projects(admin_id, is_active)');
    }
    if (!table_has_column($pdo, 'projects', 'approval_status')) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN approval_status VARCHAR(50) NOT NULL DEFAULT 'verified' AFTER end_date");
    }
    if (!table_has_column($pdo, 'projects', 'created_by_user_id')) {
        $pdo->exec('ALTER TABLE projects ADD COLUMN created_by_user_id INT UNSIGNED NULL AFTER approval_status');
    }
    $pdo->exec("UPDATE projects SET approval_status = 'verified' WHERE approval_status IS NULL OR approval_status = ''");
    $pdo->exec("UPDATE projects SET project_code = CONCAT('P', LPAD(id, 3, '0')) WHERE approval_status = 'verified' AND (project_code IS NULL OR project_code = '' OR project_code LIKE 'PRJ-%')");
    $pdo->exec('ALTER TABLE projects MODIFY COLUMN start_date DATE NULL');
    $pdo->exec('ALTER TABLE projects MODIFY COLUMN end_date DATE NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_records (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        attend_date DATE NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Absent',
        punch_in_path VARCHAR(255) NULL,
        punch_in_photo LONGBLOB NULL,
        punch_in_photo_mime VARCHAR(100) NULL,
        punch_in_photo_name VARCHAR(255) NULL,
        punch_in_lat VARCHAR(100) NULL,
        punch_in_lng VARCHAR(100) NULL,
        punch_in_time DATETIME NULL,
        biometric_in_time DATETIME NULL,
        biometric_out_time DATETIME NULL,
        leave_reason TEXT NULL,
        admin_override_status VARCHAR(50) NULL,
        admin_override_by_user_id INT UNSIGNED NULL,
        admin_override_by_name VARCHAR(191) NULL,
        admin_override_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uniq_user_attend_date (user_id, attend_date),
        INDEX idx_attendance_records_user_id (user_id),
        CONSTRAINT fk_attendance_records_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_sessions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        attendance_id INT UNSIGNED NOT NULL,
        project_id INT UNSIGNED NULL,
        session_mode VARCHAR(100) NOT NULL,
        slot_name VARCHAR(191) NULL,
        punch_in_path VARCHAR(255) NULL,
        punch_in_photo LONGBLOB NULL,
        punch_in_photo_mime VARCHAR(100) NULL,
        punch_in_photo_name VARCHAR(255) NULL,
        punch_in_lat VARCHAR(100) NULL,
        punch_in_lng VARCHAR(100) NULL,
        punch_in_time DATETIME NULL,
        punch_out_time DATETIME NULL,
        college_name VARCHAR(191) NULL,
        session_name VARCHAR(191) NULL,
        day_portion VARCHAR(100) NULL,
        session_duration DECIMAL(10,2) NULL,
        total_students INT UNSIGNED NULL,
        present_students INT UNSIGNED NULL,
        topics_handled TEXT NULL,
        location VARCHAR(191) NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_attendance_sessions_attendance_id (attendance_id),
        INDEX idx_attendance_sessions_project_id (project_id),
        CONSTRAINT fk_attendance_sessions_attendance FOREIGN KEY (attendance_id) REFERENCES attendance_records(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS reimbursements (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        attendance_session_id INT UNSIGNED NOT NULL,
        incentive_earned DECIMAL(12,2) NOT NULL DEFAULT 0,
        reimbursement_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX idx_reimbursements_session_id (attendance_session_id),
        CONSTRAINT fk_reimbursements_session FOREIGN KEY (attendance_session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_reimbursements (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        admin_id INT UNSIGNED NOT NULL,
        expense_date DATE NOT NULL,
        category VARCHAR(50) NOT NULL,
        expense_description TEXT NOT NULL,
        amount_requested DECIMAL(12,2) NOT NULL DEFAULT 0,
        amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
        remaining_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
        attachment_path VARCHAR(255) NULL,
        attachment_name VARCHAR(255) NULL,
        attachment_mime VARCHAR(100) NULL,
        attachment_data LONGBLOB NULL,
        payment_id INT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        INDEX idx_employee_reimbursements_user_date (user_id, expense_date),
        UNIQUE KEY uniq_employee_reimbursements_user_date (user_id, expense_date),
        INDEX idx_employee_reimbursements_admin_status (admin_id, status),
        INDEX idx_employee_reimbursements_category (category),
        CONSTRAINT fk_employee_reimbursements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_employee_reimbursements_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS reimbursement_payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        reimbursement_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        admin_id INT UNSIGNED NOT NULL,
        amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
        payment_method VARCHAR(100) NOT NULL,
        bank_details TEXT NULL,
        transaction_id VARCHAR(191) NULL,
        proof_path VARCHAR(255) NULL,
        proof_name VARCHAR(255) NULL,
        proof_mime VARCHAR(100) NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uniq_reimbursement_payments_reimbursement_id (reimbursement_id),
        INDEX idx_reimbursement_payments_user_id (user_id),
        INDEX idx_reimbursement_payments_admin_id (admin_id),
        CONSTRAINT fk_reimbursement_payments_reimbursement FOREIGN KEY (reimbursement_id) REFERENCES employee_reimbursements(id) ON DELETE CASCADE,
        CONSTRAINT fk_reimbursement_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_reimbursement_payments_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!table_has_column($pdo, 'employee_reimbursements', 'attachment_data')) {
        $pdo->exec('ALTER TABLE employee_reimbursements ADD COLUMN attachment_data LONGBLOB NULL AFTER attachment_mime');
    }
    backfill_reimbursement_attachments_from_files($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        admin_id INT UNSIGNED NOT NULL,
        payment_type VARCHAR(50) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        bank_name VARCHAR(50) NOT NULL,
        transfer_mode VARCHAR(50) NULL,
        transaction_id VARCHAR(191) NULL,
        payment_date DATE NOT NULL,
        proof_path VARCHAR(255) NULL,
        proof_name VARCHAR(255) NULL,
        proof_mime VARCHAR(100) NULL,
        remarks TEXT NULL,
        reimbursement_id INT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        INDEX idx_payments_admin_date (admin_id, payment_date),
        INDEX idx_payments_user_date (user_id, payment_date),
        INDEX idx_payments_type (payment_type),
        INDEX idx_payments_bank_name (bank_name),
        INDEX idx_payments_reimbursement_id (reimbursement_id),
        CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_payments_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_payments_reimbursement FOREIGN KEY (reimbursement_id) REFERENCES employee_reimbursements(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendor_payment_invoice_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        vendor_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        project_id INT UNSIGNED NOT NULL,
        invoice_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
        admin_note TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        INDEX idx_vendor_payment_invoice_vendor_status (vendor_id, status),
        INDEX idx_vendor_payment_invoice_user_date (user_id, invoice_date),
        INDEX idx_vendor_payment_invoice_project_date (project_id, invoice_date),
        CONSTRAINT fk_vendor_payment_invoice_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_vendor_payment_invoice_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_vendor_payment_invoice_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS contractual_payment_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        admin_id INT UNSIGNED NOT NULL,
        request_month CHAR(7) NOT NULL,
        request_date DATE NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
        note TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uniq_contractual_payment_request_date (user_id, request_date),
        INDEX idx_contractual_payment_admin_status (admin_id, status, request_month),
        CONSTRAINT fk_contractual_payment_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_contractual_payment_request_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!table_has_column($pdo, 'contractual_payment_requests', 'request_date')) {
        $pdo->exec('ALTER TABLE contractual_payment_requests ADD COLUMN request_date DATE NULL AFTER request_month');
    }
    if (!index_exists($pdo, 'contractual_payment_requests', 'idx_contractual_payment_request_user')) {
        $pdo->exec('ALTER TABLE contractual_payment_requests ADD INDEX idx_contractual_payment_request_user (user_id)');
    }
    if (index_exists($pdo, 'contractual_payment_requests', 'uniq_contractual_payment_request_month')) {
        $pdo->exec('ALTER TABLE contractual_payment_requests DROP INDEX uniq_contractual_payment_request_month');
    }
    if (!index_exists($pdo, 'contractual_payment_requests', 'uniq_contractual_payment_request_date')) {
        $pdo->exec('ALTER TABLE contractual_payment_requests ADD UNIQUE KEY uniq_contractual_payment_request_date (user_id, request_date)');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        actor_user_id INT UNSIGNED NULL,
        title VARCHAR(191) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'info',
        related_type VARCHAR(100) NULL,
        related_id INT UNSIGNED NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX idx_notifications_user_read (user_id, is_read),
        INDEX idx_notifications_created_at (created_at),
        CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        actor_user_id INT UNSIGNED NULL,
        actor_role VARCHAR(50) NOT NULL DEFAULT 'guest',
        target_user_id INT UNSIGNED NULL,
        action VARCHAR(100) NOT NULL,
        details_json LONGTEXT NULL,
        ip_address VARCHAR(64) NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_activity_logs_actor_user (actor_user_id),
        INDEX idx_activity_logs_target_user (target_user_id),
        INDEX idx_activity_logs_action (action),
        INDEX idx_activity_logs_created_at (created_at),
        CONSTRAINT fk_activity_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_activity_logs_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $sessionColumns = [
        'project_id' => 'INT UNSIGNED NULL AFTER attendance_id',
        'punch_in_path' => 'VARCHAR(255) NULL AFTER slot_name',
        'punch_in_photo' => 'LONGBLOB NULL AFTER punch_in_path',
        'punch_in_photo_mime' => 'VARCHAR(100) NULL AFTER punch_in_photo',
        'punch_in_photo_name' => 'VARCHAR(255) NULL AFTER punch_in_photo_mime',
        'punch_in_lat' => 'VARCHAR(100) NULL AFTER punch_in_photo_name',
        'punch_in_lng' => 'VARCHAR(100) NULL AFTER punch_in_lat',
        'punch_in_time' => 'DATETIME NULL AFTER punch_in_lng',
        'punch_out_time' => 'DATETIME NULL AFTER punch_in_time',
        'total_students' => 'INT UNSIGNED NULL AFTER session_duration',
        'present_students' => 'INT UNSIGNED NULL AFTER total_students',
        'topics_handled' => 'TEXT NULL AFTER present_students',
    ];
    foreach ($sessionColumns as $column => $definition) {
        if (!table_has_column($pdo, 'attendance_sessions', $column)) {
            $pdo->exec('ALTER TABLE attendance_sessions ADD COLUMN ' . $column . ' ' . $definition);
            if ($column === 'project_id') {
                $pdo->exec('CREATE INDEX idx_attendance_sessions_project_id ON attendance_sessions(project_id)');
            }
        }
    }
 
    $pdo->exec("UPDATE attendance_sessions
        SET punch_out_time = COALESCE(punch_out_time, created_at)
        WHERE punch_out_time IS NULL
          AND (college_name IS NOT NULL OR session_name IS NOT NULL OR location IS NOT NULL OR session_duration IS NOT NULL)");
 
    $pdo->exec("UPDATE attendance_sessions s
        JOIN (
            SELECT MIN(id) AS first_session_id
            FROM attendance_sessions
            GROUP BY attendance_id
        ) first_session ON first_session.first_session_id = s.id
        JOIN attendance_records ar ON ar.id = s.attendance_id
        SET s.punch_in_path = COALESCE(s.punch_in_path, ar.punch_in_path),
            s.punch_in_lat = COALESCE(s.punch_in_lat, ar.punch_in_lat),
            s.punch_in_lng = COALESCE(s.punch_in_lng, ar.punch_in_lng),
            s.punch_in_time = COALESCE(s.punch_in_time, ar.punch_in_time)
        WHERE ar.punch_in_path IS NOT NULL");

    $recordPhotoColumns = [
        'punch_in_photo' => 'LONGBLOB NULL AFTER punch_in_path',
        'punch_in_photo_mime' => 'VARCHAR(100) NULL AFTER punch_in_photo',
        'punch_in_photo_name' => 'VARCHAR(255) NULL AFTER punch_in_photo_mime',
        'admin_override_by_user_id' => 'INT UNSIGNED NULL AFTER admin_override_status',
        'admin_override_by_name' => 'VARCHAR(191) NULL AFTER admin_override_by_user_id',
        'admin_override_at' => 'DATETIME NULL AFTER admin_override_by_name',
    ];
    foreach ($recordPhotoColumns as $column => $definition) {
        if (!table_has_column($pdo, 'attendance_records', $column)) {
            $pdo->exec('ALTER TABLE attendance_records ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
    backfill_punch_photos_from_files($pdo);

    if (!index_exists($pdo, 'employee_reimbursements', 'uniq_employee_reimbursements_user_date')) {
        $dupStmt = $pdo->query('SELECT COUNT(*) FROM (
            SELECT user_id, expense_date, COUNT(*) AS cnt
            FROM employee_reimbursements
            GROUP BY user_id, expense_date
            HAVING cnt > 1
        ) AS t');
        $dups = (int) $dupStmt->fetchColumn();
        if ($dups === 0) {
            $pdo->exec('ALTER TABLE employee_reimbursements ADD UNIQUE KEY uniq_employee_reimbursements_user_date (user_id, expense_date)');
        }
    }

    $earliestAdminId = (int) ($pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY created_at, id LIMIT 1")->fetchColumn() ?: 0);
    if ($earliestAdminId > 0) {
        $pdo->prepare('UPDATE users SET admin_id = :admin_id WHERE role = "employee" AND admin_id IS NULL')
            ->execute(['admin_id' => $earliestAdminId]);
        $pdo->prepare('UPDATE projects SET admin_id = :admin_id WHERE admin_id IS NULL')
            ->execute(['admin_id' => $earliestAdminId]);
    }
    $pdo->exec("UPDATE users SET password_changed_at = created_at WHERE password_changed_at IS NULL AND role = 'admin'");

    $superAdminEmail = 'arun@vtraco';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND email = :email");
    $stmt->execute(['email' => $superAdminEmail]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO users (role, name, email, password_hash, status, created_at) VALUES ('super_admin', 'Super Admin', :email, :password_hash, 'ACTIVE', :created_at)")
            ->execute([
                'email' => $superAdminEmail,
                'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
