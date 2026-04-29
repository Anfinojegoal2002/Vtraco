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
    if (!table_has_column($pdo, 'users', 'representative_name')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN representative_name VARCHAR(191) NULL AFTER company_name');
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_rules (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        rule_type VARCHAR(100) NOT NULL,
        slot_name VARCHAR(191) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX idx_employee_rules_user_id (user_id),
        CONSTRAINT fk_employee_rules_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS shift_timings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED NOT NULL,
        shift_name VARCHAR(191) NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_shift_timings_admin_id (admin_id),
        CONSTRAINT fk_shift_timings_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED NULL,
        project_name VARCHAR(191) NOT NULL,
        college_name VARCHAR(191) NOT NULL,
        location VARCHAR(191) NOT NULL,
        total_days INT NOT NULL,
        session_type ENUM('FULL_DAY', 'FIRST_HALF', 'SECOND_HALF') NOT NULL,
        start_date DATE NULL,
        end_date DATE NULL,
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
    if (!index_exists($pdo, 'projects', 'idx_projects_admin_active')) {
        $pdo->exec('CREATE INDEX idx_projects_admin_active ON projects(admin_id, is_active)');
    }
    $pdo->exec('ALTER TABLE projects MODIFY COLUMN start_date DATE NULL');
    $pdo->exec('ALTER TABLE projects MODIFY COLUMN end_date DATE NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_records (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        attend_date DATE NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Absent',
        punch_in_path VARCHAR(255) NULL,
        punch_in_lat VARCHAR(100) NULL,
        punch_in_lng VARCHAR(100) NULL,
        punch_in_time DATETIME NULL,
        biometric_in_time DATETIME NULL,
        biometric_out_time DATETIME NULL,
        leave_reason TEXT NULL,
        admin_override_status VARCHAR(50) NULL,
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
        punch_in_lat VARCHAR(100) NULL,
        punch_in_lng VARCHAR(100) NULL,
        punch_in_time DATETIME NULL,
        punch_out_time DATETIME NULL,
        college_name VARCHAR(191) NULL,
        session_name VARCHAR(191) NULL,
        day_portion VARCHAR(100) NULL,
        session_duration DECIMAL(10,2) NULL,
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
        'punch_in_lat' => 'VARCHAR(100) NULL AFTER punch_in_path',
        'punch_in_lng' => 'VARCHAR(100) NULL AFTER punch_in_lat',
        'punch_in_time' => 'DATETIME NULL AFTER punch_in_lng',
        'punch_out_time' => 'DATETIME NULL AFTER punch_in_time',
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
