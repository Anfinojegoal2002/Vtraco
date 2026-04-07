<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(dirname(DB_PATH))) {
        mkdir(dirname(DB_PATH), 0777, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function initialize_database(): void
{
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role TEXT NOT NULL CHECK(role IN ("admin", "employee")),
        emp_id TEXT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        phone TEXT NULL,
        salary REAL NOT NULL DEFAULT 0,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS employee_rules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        rule_type TEXT NOT NULL,
        slot_name TEXT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS attendance_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        attend_date TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "Absent",
        punch_in_path TEXT NULL,
        punch_in_lat TEXT NULL,
        punch_in_lng TEXT NULL,
        punch_in_time TEXT NULL,
        biometric_in_time TEXT NULL,
        biometric_out_time TEXT NULL,
        leave_reason TEXT NULL,
        admin_override_status TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(user_id, attend_date),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS attendance_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        attendance_id INTEGER NOT NULL,
        session_mode TEXT NOT NULL,
        slot_name TEXT NULL,
        college_name TEXT NULL,
        session_name TEXT NULL,
        day_portion TEXT NULL,
        session_duration REAL NULL,
        location TEXT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY(attendance_id) REFERENCES attendance_records(id) ON DELETE CASCADE
    )');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

function employee_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
}

function admin_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
}

function employees(): array
{
    return db()->query("SELECT * FROM users WHERE role = 'employee' ORDER BY name")->fetchAll();
}

function employee_by_id(int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM users WHERE id = :id AND role = 'employee'");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function random_password(int $length = 6): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function normalize_rules_from_input(array $source): array
{
    $manualOutEnabled = !empty($source['manual_punch_out']);
    $count = max(1, (int) ($source['manual_out_count'] ?? 1));

    return [
        'manual_punch_in' => !empty($source['manual_punch_in']),
        'manual_punch_out' => $manualOutEnabled,
        'manual_out_count' => $manualOutEnabled ? $count : 0,
        'biometric_punch_in' => !empty($source['biometric_punch_in']),
        'biometric_punch_out' => !empty($source['biometric_punch_out']),
    ];
}

function save_employee_rules(int $userId, array $rules): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM employee_rules WHERE user_id = :user_id')->execute(['user_id' => $userId]);

    $insert = $pdo->prepare('INSERT INTO employee_rules (user_id, rule_type, slot_name, sort_order, created_at) VALUES (:user_id, :rule_type, :slot_name, :sort_order, :created_at)');
    $order = 0;

    foreach (['manual_punch_in', 'biometric_punch_in', 'biometric_punch_out'] as $type) {
        if (!empty($rules[$type])) {
            $insert->execute([
                'user_id' => $userId,
                'rule_type' => $type,
                'slot_name' => null,
                'sort_order' => $order++,
                'created_at' => now(),
            ]);
        }
    }

    if (!empty($rules['manual_punch_out'])) {
        for ($i = 1; $i <= (int) $rules['manual_out_count']; $i++) {
            $insert->execute([
                'user_id' => $userId,
                'rule_type' => 'manual_punch_out',
                'slot_name' => 'Manual Punch Slot ' . $i,
                'sort_order' => $order++,
                'created_at' => now(),
            ]);
        }
    }

    $pdo->commit();
}

function employee_rules(int $userId): array
{
    $stmt = db()->prepare('SELECT rule_type, slot_name FROM employee_rules WHERE user_id = :user_id ORDER BY sort_order, id');
    $stmt->execute(['user_id' => $userId]);
    $rules = [
        'manual_punch_in' => false,
        'manual_punch_out' => false,
        'manual_out_count' => 0,
        'manual_out_slots' => [],
        'biometric_punch_in' => false,
        'biometric_punch_out' => false,
    ];

    foreach ($stmt->fetchAll() as $row) {
        if ($row['rule_type'] === 'manual_punch_out') {
            $rules['manual_punch_out'] = true;
            $rules['manual_out_count']++;
            $rules['manual_out_slots'][] = $row['slot_name'] ?: 'Manual Punch Slot';
        } else {
            $rules[$row['rule_type']] = true;
        }
    }

    return $rules;
}

function rules_summary(array $rules): string
{
    $parts = [];
    if (!empty($rules['manual_punch_in'])) {
        $parts[] = 'Manual Punch In';
    }
    if (!empty($rules['manual_punch_out'])) {
        $parts[] = 'Manual Punch Out (' . (int) $rules['manual_out_count'] . ')';
    }
    if (!empty($rules['biometric_punch_in'])) {
        $parts[] = 'Biometric Punch In';
    }
    if (!empty($rules['biometric_punch_out'])) {
        $parts[] = 'Biometric Punch Out';
    }
    return $parts ? implode('<br>', array_map('h', $parts)) : '<span class="muted">No rules assigned</span>';
}

function rules_explanation_html(array $rules): string
{
    $parts = [];
    if (!empty($rules['manual_punch_in'])) {
        $parts[] = 'Manual Punch In: upload a geo-tagged punch-in photo.';
    }
    if (!empty($rules['manual_punch_out'])) {
        $parts[] = 'Manual Punch Out: submit ' . (int) $rules['manual_out_count'] . ' session slot(s) with session details.';
    }
    if (!empty($rules['biometric_punch_in'])) {
        $parts[] = 'Biometric Punch In: record a biometric punch-in time.';
    }
    if (!empty($rules['biometric_punch_out'])) {
        $parts[] = 'Biometric Punch Out: record a biometric punch-out time.';
    }
    return implode('<br>', array_map('h', $parts));
}

function ensure_mail_log_dir(): void
{
    if (!is_dir(MAIL_LOG_PATH)) {
        mkdir(MAIL_LOG_PATH, 0777, true);
    }
}
function mail_sender_identity(): array
{
    $user = current_user();
    $defaultEmail = MAIL_SMTP_FROM_FALLBACK;
    $defaultName = APP_NAME;

    if ($user && ($user['role'] ?? '') === 'admin') {
        $candidateEmail = filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if ($candidateEmail) {
            $candidateName = trim((string) ($user['name'] ?? '')) ?: $defaultName;
            $candidateName = preg_replace('/[\r\n]+/', ' ', $candidateName) ?? $candidateName;
            return [
                'name' => $candidateName,
                'email' => $candidateEmail,
            ];
        }
    }

    return [
        'name' => $defaultName,
        'email' => $defaultEmail,
    ];
}

function mail_transport_config(): array
{
    $host = trim((string) (getenv('VTRACO_MAIL_HOST') ?: MAIL_SMTP_HOST));
    $port = (int) (getenv('VTRACO_MAIL_PORT') ?: MAIL_SMTP_PORT);
    $username = trim((string) (getenv('VTRACO_MAIL_USERNAME') ?: MAIL_SMTP_USERNAME));
    $password = (string) (getenv('VTRACO_MAIL_PASSWORD') ?: MAIL_SMTP_PASSWORD);
    $encryption = strtolower(trim((string) (getenv('VTRACO_MAIL_ENCRYPTION') ?: MAIL_SMTP_ENCRYPTION)));

    return [
        'host' => $host,
        'port' => $port > 0 ? $port : 587,
        'username' => $username,
        'password' => $password,
        'encryption' => in_array($encryption, ['ssl', 'tls'], true) ? $encryption : '',
        'use_smtp' => $host !== '',
        'auth' => $username !== '' && $password !== '',
    ];
}

function send_html_mail(string $to, string $subject, string $html): array
{
    ensure_mail_log_dir();
    $sender = mail_sender_identity();
    $transport = mail_transport_config();
    $fromName = preg_replace('/[\r\n]+/', ' ', (string) ($sender['name'] ?? APP_NAME)) ?: APP_NAME;
    $fromEmail = filter_var((string) ($sender['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: MAIL_SMTP_FROM_FALLBACK;
    $safeRecipient = preg_replace('/[^a-z0-9]+/i', '_', strtolower($to)) ?: 'recipient';
    $filename = MAIL_LOG_PATH . '/' . date('Ymd_His') . '_' . $safeRecipient . '.html';
    $document = '<!doctype html><html><head><meta charset="utf-8"><title>' . h($subject) . '</title></head><body style="font-family:Inter,Segoe UI,Arial,sans-serif;color:#172554;line-height:1.65;">'
        . '<h2>' . h($subject) . '</h2><p><strong>From:</strong> ' . h($fromName) . ' &lt;' . h($fromEmail) . '&gt;</p><p><strong>To:</strong> ' . h($to) . '</p>' . $html . '</body></html>';
    file_put_contents($filename, $document);

    $result = [
        'sent' => false,
        'log_file' => basename($filename),
        'log_path' => $filename,
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'transport' => $transport['use_smtp'] ? 'phpmailer-smtp' : 'phpmailer-mail',
        'error' => '',
    ];

    try {
        $mailer = new PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $document;
        $mailer->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $mailer->setFrom($fromEmail, $fromName, false);
        $mailer->addReplyTo($fromEmail, $fromName);
        $mailer->addAddress($to);

        if ($transport['use_smtp']) {
            $mailer->isSMTP();
            $mailer->Host = $transport['host'];
            $mailer->Port = $transport['port'];
            $mailer->SMTPAuth = $transport['auth'];
            $mailer->Username = $transport['username'];
            $mailer->Password = $transport['password'];
            if ($transport['encryption'] === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($transport['encryption'] === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        } else {
            $mailer->isMail();
        }

        $mailer->send();
        $result['sent'] = true;
    } catch (PHPMailerException $exception) {
        $result['error'] = $exception->getMessage();
    } catch (Throwable $exception) {
        $result['error'] = $exception->getMessage();
    }

    return $result;
}

function send_employee_credentials_email(array $employee, string $password, array $rules): array
{
    $html = '<p>Hello ' . h($employee['name']) . ',</p>'
        . '<p>Your V Traco employee account has been created.</p>'
        . '<p>A password was created automatically for your account. Please use the credentials below to sign in.</p>'
        . '<p><strong>Employee Email:</strong> ' . h($employee['email']) . '<br>'
        . '<strong>Auto-generated Password:</strong> ' . h($password) . '</p>'
        . '<p><strong>Assigned Rules</strong><br>' . rules_explanation_html($rules) . '</p>';
    return send_html_mail((string) $employee['email'], 'Your V Traco Login Credentials', $html);
}

function send_rules_updated_email(array $employee, array $rules): array
{
    $html = '<p>Hello ' . h($employee['name']) . ',</p>'
        . '<p>Your attendance rules have been updated in V Traco.</p>'
        . '<p><strong>Applied Rules</strong><br>' . rules_explanation_html($rules) . '</p>';
    return send_html_mail((string) $employee['email'], 'V Traco Rules Updated', $html);
}

function employee_credentials_delivery_message(array $employee, array $mailResult): string
{
    if (!empty($mailResult['sent'])) {
        return 'Employee added successfully. The auto-generated password was sent to ' . $employee['email'] . '.';
    }

    return 'Employee added successfully, but PHPMailer could not deliver the email. Configure SMTP in src/bootstrap.php or VTRACO_MAIL_* environment variables. A copy was saved in storage/emails/' . ($mailResult['log_file'] ?? '') . (($mailResult['error'] ?? '') !== '' ? ' | Error: ' . $mailResult['error'] : '') . '.';
}

function insert_employee(array $data, array $rules): array
{
    $password = random_password(6);
    db()->prepare('INSERT INTO users (role, emp_id, name, email, phone, salary, password_hash, created_at) VALUES ("employee", :emp_id, :name, :email, :phone, :salary, :password_hash, :created_at)')
        ->execute([
            'emp_id' => trim((string) $data['emp_id']),
            'name' => trim((string) $data['name']),
            'email' => trim((string) $data['email']),
            'phone' => trim((string) $data['phone']),
            'salary' => (float) $data['salary'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => now(),
        ]);
    $employee = employee_by_id((int) db()->lastInsertId());
    if (!$employee) {
        throw new RuntimeException('Failed to create employee.');
    }
    save_employee_rules((int) $employee['id'], $rules);
    $mailResult = send_employee_credentials_email($employee, $password, $rules);

    return [
        'employee' => $employee,
        'mail_result' => $mailResult,
    ];
}

function normalize_csv_header(string $header): string
{
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
    $header = strtolower(trim($header));
    return preg_replace('/[^a-z0-9]+/', '', $header) ?? $header;
}

function parse_employee_csv(string $path): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Unable to read the CSV file.');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new RuntimeException('CSV file is empty.');
    }

    $headerMap = [];
    foreach ($header as $index => $column) {
        $headerMap[normalize_csv_header((string) $column)] = $index;
    }

    $aliases = [
        'emp_id' => ['empid', 'employeeid', 'employeecode', 'employee'],
        'name' => ['name', 'employeename'],
        'email' => ['email', 'emailaddress', 'mail'],
        'phone' => ['phonenumber', 'phone', 'mobilenumber', 'mobile'],
        'salary' => ['salary', 'monthlysalary', 'pay', 'amount'],
    ];

    $columns = [];
    foreach ($aliases as $field => $possible) {
        foreach ($possible as $alias) {
            if (array_key_exists($alias, $headerMap)) {
                $columns[$field] = $headerMap[$alias];
                break;
            }
        }
    }

    foreach (['emp_id', 'name', 'email', 'phone', 'salary'] as $required) {
        if (!array_key_exists($required, $columns)) {
            fclose($handle);
            throw new RuntimeException('Missing required CSV column for ' . $required . '.');
        }
    }

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $record = [
            'emp_id' => trim((string) ($row[$columns['emp_id']] ?? '')),
            'name' => trim((string) ($row[$columns['name']] ?? '')),
            'email' => trim((string) ($row[$columns['email']] ?? '')),
            'phone' => trim((string) ($row[$columns['phone']] ?? '')),
            'salary' => (float) trim((string) ($row[$columns['salary']] ?? '0')),
        ];
        if ($record['emp_id'] === '' && $record['name'] === '' && $record['email'] === '') {
            continue;
        }
        $rows[] = $record;
    }

    fclose($handle);

    if (!$rows) {
        throw new RuntimeException('CSV file has no usable rows.');
    }

    return $rows;
}

function month_bounds(string $month): array
{
    $start = new DateTimeImmutable($month . '-01');
    $end = $start->modify('last day of this month');
    return [$start, $end];
}

function default_status_for_date(string $date): string
{
    return date('w', strtotime($date)) === '0' ? 'Week Off' : 'Absent';
}

function attendance_record(int $userId, string $date): ?array
{
    $stmt = db()->prepare('SELECT * FROM attendance_records WHERE user_id = :user_id AND attend_date = :attend_date');
    $stmt->execute(['user_id' => $userId, 'attend_date' => $date]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ensure_attendance_record(int $userId, string $date): array
{
    $record = attendance_record($userId, $date);
    if ($record) {
        return $record;
    }

    db()->prepare('INSERT INTO attendance_records (user_id, attend_date, status, created_at, updated_at) VALUES (:user_id, :attend_date, :status, :created_at, :updated_at)')
        ->execute([
            'user_id' => $userId,
            'attend_date' => $date,
            'status' => default_status_for_date($date),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    return attendance_record($userId, $date) ?: [];
}

function update_attendance_record(int $userId, string $date, array $fields): void
{
    $record = ensure_attendance_record($userId, $date);
    $sets = [];
    $params = ['id' => $record['id']];
    foreach ($fields as $key => $value) {
        $sets[] = $key . ' = :' . $key;
        $params[$key] = $value;
    }
    $sets[] = 'updated_at = :updated_at';
    $params['updated_at'] = now();
    $sql = 'UPDATE attendance_records SET ' . implode(', ', $sets) . ' WHERE id = :id';
    db()->prepare($sql)->execute($params);
}

function attendance_sessions(int $attendanceId): array
{
    $stmt = db()->prepare('SELECT * FROM attendance_sessions WHERE attendance_id = :attendance_id ORDER BY id');
    $stmt->execute(['attendance_id' => $attendanceId]);
    return $stmt->fetchAll();
}

function add_attendance_session(int $attendanceId, array $payload): void
{
    db()->prepare('INSERT INTO attendance_sessions (attendance_id, session_mode, slot_name, college_name, session_name, day_portion, session_duration, location, created_at) VALUES (:attendance_id, :session_mode, :slot_name, :college_name, :session_name, :day_portion, :session_duration, :location, :created_at)')
        ->execute([
            'attendance_id' => $attendanceId,
            'session_mode' => $payload['session_mode'],
            'slot_name' => $payload['slot_name'] ?? null,
            'college_name' => $payload['college_name'] ?? null,
            'session_name' => $payload['session_name'] ?? null,
            'day_portion' => $payload['day_portion'] ?? null,
            'session_duration' => $payload['session_duration'] ?? null,
            'location' => $payload['location'] ?? null,
            'created_at' => now(),
        ]);
}

function month_attendance_for_user(int $userId, string $month): array
{
    [$start, $end] = month_bounds($month);
    $stmt = db()->prepare('SELECT * FROM attendance_records WHERE user_id = :user_id AND attend_date BETWEEN :start_date AND :end_date ORDER BY attend_date');
    $stmt->execute([
        'user_id' => $userId,
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
    ]);

    $records = [];
    foreach ($stmt->fetchAll() as $row) {
        $records[$row['attend_date']] = $row;
    }

    $out = [];
    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $key = $date->format('Y-m-d');
        $record = $records[$key] ?? [
            'id' => null,
            'user_id' => $userId,
            'attend_date' => $key,
            'status' => default_status_for_date($key),
            'punch_in_path' => null,
            'punch_in_lat' => null,
            'punch_in_lng' => null,
            'punch_in_time' => null,
            'biometric_in_time' => null,
            'biometric_out_time' => null,
            'leave_reason' => null,
        ];
        $sessions = $record['id'] ? attendance_sessions((int) $record['id']) : [];
        $out[$key] = ['record' => $record, 'sessions' => $sessions];
    }

    return $out;
}

function working_days_total(array $monthAttendance): float
{
    $total = 0.0;
    foreach ($monthAttendance as $entry) {
        $status = $entry['record']['status'] ?? 'Absent';
        if ($status === 'Present') {
            $total += 1.0;
        } elseif ($status === 'Half Day') {
            $total += 0.5;
        }
    }
    return $total;
}

function salary_for_month(float $salary, array $monthAttendance): float
{
    $daysInMonth = max(1, count($monthAttendance));
    return (working_days_total($monthAttendance) * $salary) / $daysInMonth;
}

function attendance_snapshot_for_date(?string $date = null): array
{
    $date = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
    $counts = [
        'Present' => 0,
        'Absent' => 0,
        'Half Day' => 0,
        'Leave' => 0,
        'Week Off' => 0,
    ];
    $details = [];

    foreach (employees() as $employee) {
        $record = attendance_record((int) $employee['id'], $date);
        $status = $record['status'] ?? default_status_for_date($date);
        $sessions = $record && !empty($record['id']) ? attendance_sessions((int) $record['id']) : [];

        if (!array_key_exists($status, $counts)) {
            $counts[$status] = 0;
        }
        $counts[$status]++;

        if (in_array($status, ['Half Day', 'Leave'], true)) {
            $detail = 'No additional detail submitted.';
            if ($status === 'Leave') {
                $detail = trim((string) ($record['leave_reason'] ?? '')) ?: 'No leave reason provided.';
            } elseif ($sessions) {
                $detail = count($sessions) . ' session(s) recorded for the day.';
            } else {
                $detail = 'Half day marked for today.';
            }

            $details[] = [
                'employee' => $employee,
                'status' => $status,
                'detail' => $detail,
            ];
        }
    }

    return [
        'date' => $date,
        'counts' => $counts,
        'details' => $details,
    ];
}

function handle_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Punch photo upload failed.');
    }
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0777, true);
    }
    $ext = pathinfo((string) ($file['name'] ?? 'upload.jpg'), PATHINFO_EXTENSION) ?: 'jpg';
    $target = UPLOAD_PATH . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('Unable to save punch photo.');
    }
    return str_replace(__DIR__ . '/../', '', $target);
}

function handle_post_action(string $action): void
{
    switch ($action) {
        case 'login':
            $role = $_POST['role'] ?? 'admin';
            $stmt = db()->prepare('SELECT * FROM users WHERE role = :role AND email = :email');
            $stmt->execute([
                'role' => $role,
                'email' => trim((string) ($_POST['email'] ?? '')),
            ]);
            $user = $stmt->fetch();
            if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                flash('success', 'Welcome back, ' . $user['name'] . '.');
                redirect_to($user['role'] === 'admin' ? 'admin_dashboard' : 'employee_attendance');
            }
            flash('error', ucfirst((string) $role) . ' login failed.');
            if (($_POST['return_page'] ?? '') === 'landing') {
                redirect_to('landing', ['auth' => $role]);
            }
            redirect_to('login', ['role' => $role]);
            break;

        case 'register_admin':
            if (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
                flash('error', 'Passwords do not match.');
                redirect_to('register');
            }
            try {
                db()->prepare('INSERT INTO users (role, emp_id, name, email, phone, salary, password_hash, created_at) VALUES ("admin", NULL, :name, :email, :phone, 0, :password_hash, :created_at)')
                    ->execute([
                        'name' => trim((string) ($_POST['name'] ?? '')),
                        'email' => trim((string) ($_POST['email'] ?? '')),
                        'phone' => trim((string) ($_POST['phone'] ?? '')),
                        'password_hash' => password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
                        'created_at' => now(),
                    ]);
                flash('success', 'Admin account created.');
                redirect_to('login', ['role' => 'admin']);
            } catch (Throwable $exception) {
                flash('error', 'Admin registration failed. Email may already exist.');
                redirect_to('register');
            }
            break;

        case 'employee_manual_next':
            require_role('admin');
            $_SESSION['pending_employee'] = [
                'emp_id' => trim((string) ($_POST['emp_id'] ?? '')),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'phone' => trim((string) ($_POST['phone'] ?? '')),
                'salary' => (float) ($_POST['salary'] ?? 0),
            ];
            redirect_to('admin_employees', ['stage' => 'manual_rules']);
            break;

        case 'employee_manual_submit':
            require_role('admin');
            $pending = $_SESSION['pending_employee'] ?? null;
            if (!$pending) {
                flash('error', 'No pending employee found.');
                redirect_to('admin_employees');
            }
            try {
                $rules = normalize_rules_from_input($_POST);
                $createdEmployee = insert_employee($pending, $rules);
                unset($_SESSION['pending_employee']);
                flash('success', employee_credentials_delivery_message($createdEmployee['employee'], $createdEmployee['mail_result']));
            } catch (Throwable $exception) {
                flash('error', 'Unable to add employee. Email or Emp ID may already exist.');
            }
            redirect_to('admin_employees');
            break;

        case 'employee_csv_upload':
            require_role('admin');
            try {
                $_SESSION['pending_csv_import'] = parse_employee_csv((string) $_FILES['csv_file']['tmp_name']);
                flash('success', 'CSV uploaded. Assign rules to continue.');
                redirect_to('admin_employees', ['stage' => 'csv_rules']);
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
                redirect_to('admin_employees');
            }
            break;

        case 'employee_csv_submit':
            require_role('admin');
            $rows = $_SESSION['pending_csv_import'] ?? [];
            if (!$rows) {
                flash('error', 'No CSV import is pending.');
                redirect_to('admin_employees');
            }
            $rules = normalize_rules_from_input($_POST);
            if (!$rules['manual_punch_in'] && !$rules['manual_punch_out'] && !$rules['biometric_punch_in'] && !$rules['biometric_punch_out']) {
                flash('error', 'Select at least one rule before submitting the CSV import.');
                redirect_to('admin_employees', ['stage' => 'csv_rules']);
            }
            $created = 0;
            $skipped = 0;
            $emailsSent = 0;
            $emailsLogged = 0;
            foreach ($rows as $row) {
                try {
                    $createdEmployee = insert_employee($row, $rules);
                    $created++;
                    if (!empty($createdEmployee['mail_result']['sent'])) {
                        $emailsSent++;
                    } else {
                        $emailsLogged++;
                    }
                } catch (Throwable $exception) {
                    $skipped++;
                }
            }
            unset($_SESSION['pending_csv_import']);
            $message = 'CSV import completed. Created: ' . $created;
            if ($emailsSent) {
                $message .= ' | Emails sent: ' . $emailsSent;
            }
            if ($emailsLogged) {
                $message .= ' | Logged locally: ' . $emailsLogged;
            }
            if ($skipped) {
                $message .= ' | Skipped: ' . $skipped;
            }
            flash('success', $message);
            redirect_to('admin_employees');
            break;

        case 'employee_update':
            require_role('admin');
            try {
                db()->prepare('UPDATE users SET emp_id = :emp_id, name = :name, email = :email, phone = :phone, salary = :salary WHERE id = :id AND role = "employee"')
                    ->execute([
                        'id' => (int) ($_POST['user_id'] ?? 0),
                        'emp_id' => trim((string) ($_POST['emp_id'] ?? '')),
                        'name' => trim((string) ($_POST['name'] ?? '')),
                        'email' => trim((string) ($_POST['email'] ?? '')),
                        'phone' => trim((string) ($_POST['phone'] ?? '')),
                        'salary' => (float) ($_POST['salary'] ?? 0),
                    ]);
                flash('success', 'Employee updated.');
            } catch (Throwable $exception) {
                flash('error', 'Unable to update employee.');
            }
            redirect_to('admin_employees');
            break;

        case 'employee_delete':
            require_role('admin');
            db()->prepare('DELETE FROM users WHERE id = :id AND role = "employee"')->execute(['id' => (int) ($_POST['user_id'] ?? 0)]);
            flash('success', 'Employee deleted.');
            redirect_to('admin_employees');
            break;

        case 'apply_rules':
            require_role('admin');
            $ids = array_map('intval', $_POST['employee_ids'] ?? []);
            $rules = normalize_rules_from_input($_POST);
            if (!$ids) {
                flash('error', 'Select at least one employee.');
                redirect_to('admin_rules');
            }
            foreach ($ids as $id) {
                $employee = employee_by_id($id);
                if (!$employee) {
                    continue;
                }
                save_employee_rules($id, $rules);
                send_rules_updated_email($employee, $rules);
            }
            flash('success', 'Rules applied successfully.');
            redirect_to('admin_rules');
            break;

        case 'admin_set_status':
            require_role('admin');
            update_attendance_record((int) ($_POST['employee_id'] ?? 0), (string) ($_POST['attend_date'] ?? ''), [
                'status' => (string) ($_POST['status'] ?? 'Absent'),
                'admin_override_status' => (string) ($_POST['status'] ?? 'Absent'),
            ]);
            flash('success', 'Attendance status updated.');
            redirect_to('admin_attendance', [
                'employee_id' => (int) ($_POST['employee_id'] ?? 0),
                'month' => substr((string) ($_POST['attend_date'] ?? date('Y-m-d')), 0, 7),
            ]);
            break;

        case 'employee_punch_in':
            $employee = require_role('employee');
            try {
                $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
                $path = handle_upload($_FILES['punch_photo'] ?? []);
                update_attendance_record((int) $employee['id'], $date, [
                    'status' => 'Present',
                    'punch_in_path' => $path,
                    'punch_in_lat' => trim((string) ($_POST['latitude'] ?? '')),
                    'punch_in_lng' => trim((string) ($_POST['longitude'] ?? '')),
                    'punch_in_time' => now(),
                ]);
                flash('success', 'Punch in submitted.');
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
            }
            redirect_to('employee_attendance', ['month' => substr((string) ($_POST['attend_date'] ?? date('Y-m-d')), 0, 7)]);
            break;

        case 'employee_manual_out':
            $employee = require_role('employee');
            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
            $record = ensure_attendance_record((int) $employee['id'], $date);
            add_attendance_session((int) $record['id'], [
                'session_mode' => 'manual_out',
                'slot_name' => trim((string) ($_POST['slot_name'] ?? 'Manual Punch Slot 1')),
                'college_name' => trim((string) ($_POST['college_name'] ?? '')),
                'session_name' => trim((string) ($_POST['session_name'] ?? '')),
                'day_portion' => trim((string) ($_POST['day_portion'] ?? 'Full Day')),
                'session_duration' => (float) ($_POST['session_duration'] ?? 0),
                'location' => trim((string) ($_POST['location'] ?? '')),
            ]);
            update_attendance_record((int) $employee['id'], $date, [
                'status' => (($_POST['day_portion'] ?? 'Full Day') === 'Half Day') ? 'Half Day' : 'Present',
            ]);
            flash('success', 'Manual punch out submitted.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;

        case 'employee_biometric':
            $employee = require_role('employee');
            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
            $type = (string) ($_POST['stamp_type'] ?? 'in');
            update_attendance_record((int) $employee['id'], $date, [
                $type === 'out' ? 'biometric_out_time' : 'biometric_in_time' => now(),
                'status' => 'Present',
            ]);
            flash('success', 'Biometric ' . ($type === 'out' ? 'out' : 'in') . ' captured.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;

        case 'employee_leave':
            $employee = require_role('employee');
            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
            update_attendance_record((int) $employee['id'], $date, [
                'status' => 'Leave',
                'leave_reason' => trim((string) ($_POST['leave_reason'] ?? '')),
            ]);
            flash('success', 'Leave request recorded.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;

        case 'logout':
            unset($_SESSION['user_id']);
            flash('success', 'Logged out successfully.');
            redirect_to('landing');
            break;
    }
}



