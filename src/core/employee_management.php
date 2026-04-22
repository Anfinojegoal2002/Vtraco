<?php

declare(strict_types=1);

function employee_emp_id_exists(string $empId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE role IN ("employee", "corporate_employee") AND emp_id = :emp_id');
    $stmt->execute(['emp_id' => $empId]);
    return (int) $stmt->fetchColumn() > 0;
}

function generate_employee_emp_id(array $reserved = []): string
{
    $reservedLookup = array_fill_keys($reserved, true);

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $candidate = 'EMP' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        if (isset($reservedLookup[$candidate])) {
            continue;
        }
        if (!employee_emp_id_exists($candidate)) {
            return $candidate;
        }
    }

    $candidate = 'EMP' . date('ymdHis') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    while (isset($reservedLookup[$candidate]) || employee_emp_id_exists($candidate)) {
        $candidate = 'EMP' . date('ymdHis') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    }

    return $candidate;
}

function generated_employee_name(string $email = '', string $empId = ''): string
{
    $source = trim($email);
    if ($source !== '' && str_contains($source, '@')) {
        $source = strstr($source, '@', true) ?: $source;
    }

    $source = preg_replace('/[._\-]+/', ' ', $source) ?? $source;
    $source = preg_replace('/\s+/', ' ', trim($source)) ?? trim($source);
    if ($source !== '') {
        return ucwords(strtolower($source));
    }

    if (trim($empId) !== '') {
        return 'Employee ' . trim($empId);
    }

    return 'Employee';
}

function guessed_employee_name(array $row, array $columns, array $headerMap, string $email, string $empId): string
{
    if (($columns['name'] ?? null) !== null) {
        $name = trim((string) ($row[$columns['name']] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }

    foreach ($headerMap as $header => $index) {
        if (!str_contains($header, 'name')) {
            continue;
        }

        $value = trim((string) ($row[$index] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $knownIndexes = [];
    foreach (['emp_id', 'email', 'phone', 'shift', 'salary'] as $field) {
        if (($columns[$field] ?? null) !== null) {
            $knownIndexes[(int) $columns[$field]] = true;
        }
    }

    foreach ($row as $index => $value) {
        if (isset($knownIndexes[(int) $index])) {
            continue;
        }

        $candidate = trim((string) $value);
        if ($candidate === '' || str_contains($candidate, '@')) {
            continue;
        }

        if (preg_match('/[A-Za-z]/', $candidate)) {
            return $candidate;
        }
    }

    return generated_employee_name($email, $empId);
}

function insert_employee(array $data, array $rules, array $projectIds = []): array
{
    $password = random_password();
    $adminId = current_admin_id();
    if ($adminId === null) {
        throw new RuntimeException('An administrator must be signed in to add employees.');
    }

    $employeeType = trim((string) ($data['employee_type'] ?? 'regular'));
    $manager = current_user();
    if (($manager['role'] ?? '') === 'freelancer') {
        $employeeType = 'corporate';
    }
    if (!in_array($employeeType, ['regular', 'vendor', 'corporate'], true)) {
        $employeeType = 'regular';
    }

    // Allow Admin to assign employees to a specific Vendor.
    if (($manager['role'] ?? '') !== 'freelancer' && ($data['vendor_id'] ?? 0) > 0) {
        $adminId = (int) $data['vendor_id'];
    }

    $empId = trim((string) ($data['emp_id'] ?? ''));
    if ($empId === '') {
        $empId = generate_employee_emp_id();
    }

    $email = trim((string) ($data['email'] ?? ''));
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        $name = generated_employee_name($email, $empId);
    }
    $phone = trim((string) ($data['phone'] ?? ''));
    $salary = (float) ($data['salary'] ?? 0);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Employee email must be valid.');
    }
    if ($phone === '') {
        throw new RuntimeException('Employee phone number is required.');
    }
    if ($salary < 0) {
        throw new RuntimeException('Employee salary must be zero or greater.');
    }

    $role = $employeeType === 'corporate' ? 'corporate_employee' : current_manager_target_role();

    if (role_requires_unique_email($role) && role_email_exists($role, $email)) {
        throw new RuntimeException('This employee email is already assigned.');
    }
    
    $normalizedProjectIds = normalize_project_assignment_ids($projectIds);
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('INSERT INTO users (role, admin_id, emp_id, name, email, phone, shift, salary, employee_type, password_hash, force_password_change, password_changed_at, created_at) VALUES (:role, :admin_id, :emp_id, :name, :email, :phone, :shift, :salary, :employee_type, :password_hash, :force_password_change, :password_changed_at, :created_at)')
            ->execute([
                'role' => $role,
                'admin_id' => $adminId,
                'emp_id' => $empId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'shift' => normalize_shift_selection((string) ($data['shift'] ?? '')),
                'salary' => $salary,
                'employee_type' => $employeeType,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'force_password_change' => 1,
                'password_changed_at' => null,
                'created_at' => now(),
            ]);

        $employeeId = (int) $pdo->lastInsertId();
        save_employee_rules($employeeId, $rules);
        save_employee_project_assignments($employeeId, $normalizedProjectIds);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    $employee = employee_by_id($employeeId);
    if (!$employee) {
        throw new RuntimeException('Failed to create employee.');
    }
    $mailResult = send_employee_credentials_email($employee, $password, $rules);

    return [
        'employee' => $employee,
        'mail_result' => $mailResult,
        'password' => $password,
    ];
}

function attendance_import_employee_email(string $empId): string
{
    $base = strtolower(trim($empId)) !== '' ? strtolower(trim($empId)) : 'employee';
    $base = preg_replace('/[^a-z0-9]+/', '.', $base) ?? $base;
    $base = trim($base, '.');
    if ($base === '') {
        $base = 'employee';
    }

    $role = current_manager_target_role();
    for ($suffix = 0; $suffix < 1000; $suffix++) {
        $email = $suffix === 0
            ? sprintf('attendance.%s@vtraco.local', $base)
            : sprintf('attendance.%s.%d@vtraco.local', $base, $suffix);

        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE role = :role AND email = :email');
        $stmt->execute(['role' => $role, 'email' => $email]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $email;
        }
    }

    return sprintf('attendance.%s.%d@vtraco.local', $base, time());
}

function create_employee_from_attendance_entry(array $entry): ?array
{
    $adminId = current_admin_id();
    if ($adminId === null) {
        return null;
    }

    $empId = trim((string) ($entry['emp_code'] ?? ''));
    if ($empId === '') {
        $empId = generate_employee_emp_id();
    }

    $name = trim((string) ($entry['employee_name'] ?? ''));
    if ($name === '') {
        $name = generated_employee_name('', $empId);
    }

    $email = attendance_import_employee_email($empId);
    $password = random_password();

    $role = current_manager_target_role();
    db()->prepare('INSERT INTO users (role, admin_id, emp_id, name, email, phone, shift, salary, password_hash, force_password_change, password_changed_at, created_at) VALUES (:role, :admin_id, :emp_id, :name, :email, :phone, :shift, :salary, :password_hash, :force_password_change, :password_changed_at, :created_at)')
        ->execute([
            'role' => $role,
            'admin_id' => $adminId,
            'emp_id' => $empId,
            'name' => $name,
            'email' => $email,
            'phone' => '',
            'shift' => normalize_shift_selection((string) ($entry['shift'] ?? '')),
            'salary' => 0,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'force_password_change' => 1,
            'password_changed_at' => null,
            'created_at' => now(),
        ]);

    return employee_by_id((int) db()->lastInsertId());
}

function validate_employee_csv_upload(array $file): void
{
    validate_uploaded_file($file, ['xlsx', 'xls', 'csv', 'txt'], 2 * 1024 * 1024, 'employee file');

    $extension = uploaded_file_extension($file);
    $path = (string) ($file['tmp_name'] ?? '');
    $originalName = (string) ($file['name'] ?? '');

    if ($extension === 'xlsx' && !attendance_is_xlsx_file($path, $originalName)) {
        throw new RuntimeException('The uploaded employee file does not look like a valid .xlsx workbook.');
    }

    if ($extension === 'xls' && !attendance_is_html_table_file($path, $originalName) && !attendance_is_binary_xls_file($path, $originalName)) {
        throw new RuntimeException('The uploaded employee .xls file could not be recognized as a supported Excel workbook.');
    }
}

function employee_reset_is_rate_limited(array $employee): bool
{
    $requestedAt = trim((string) ($employee['password_reset_requested_at'] ?? ''));
    if ($requestedAt === '') {
        return false;
    }

    $timestamp = strtotime($requestedAt);
    if ($timestamp === false) {
        return false;
    }

    return $timestamp + forgot_password_cooldown_seconds() > time();
}

function reset_employee_password(int $employeeId): array
{
    $employee = employee_by_id($employeeId);
    if (!$employee) {
        throw new RuntimeException('Employee not found for this administrator.');
    }

    $password = random_password();
    $role = current_manager_target_role();
    db()->prepare('UPDATE users SET password_hash = :password_hash, force_password_change = 1, password_reset_requested_at = :password_reset_requested_at, password_changed_at = NULL WHERE id = :id AND role = :role AND admin_id = :admin_id')
        ->execute([
            'role' => $role,
            'id' => $employeeId,
            'admin_id' => (int) current_admin_id(),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'password_reset_requested_at' => now(),
        ]);

    $mailResult = send_employee_credentials_email($employee, $password, employee_rules($employeeId));

    return [
        'employee' => $employee,
        'mail_result' => $mailResult,
        'password' => $password,
    ];
}

function employee_by_email(string $email): ?array
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE role IN ("employee", "corporate_employee") AND LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function reset_employee_password_by_email(string $email): array
{
    $employee = employee_by_email($email);
    if (!$employee) {
        return [
            'handled' => false,
            'rate_limited' => false,
            'employee' => null,
            'mail_result' => [],
        ];
    }

    if (employee_reset_is_rate_limited($employee)) {
        return [
            'handled' => true,
            'rate_limited' => true,
            'employee' => $employee,
            'mail_result' => [],
        ];
    }

    $password = random_password();
    db()->prepare('UPDATE users SET password_hash = :password_hash, force_password_change = 1, password_reset_requested_at = :password_reset_requested_at, password_changed_at = NULL WHERE id = :id AND role = :role')
        ->execute([
            'id' => (int) $employee['id'],
            'role' => $employee['role'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'password_reset_requested_at' => now(),
        ]);

    $mailResult = send_employee_credentials_email($employee, $password, employee_rules((int) $employee['id']));

    return [
        'handled' => true,
        'rate_limited' => false,
        'employee' => $employee,
        'mail_result' => $mailResult,
        'password' => $password,
    ];
}

function normalize_csv_header(string $header): string
{
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
    $header = strtolower(trim($header));
    return preg_replace('/[^a-z0-9]+/', '', $header) ?? $header;
}

function parse_employee_csv(string $path, string $originalName = ''): array
{
    $sourceRows = attendance_report_rows($path, $originalName);
    $header = $sourceRows[0] ?? null;
    if (!is_array($header) || $header === []) {
        throw new RuntimeException('Employee import file is empty.');
    }

    $headerMap = [];
    foreach ($header as $index => $column) {
        $headerMap[normalize_csv_header((string) $column)] = $index;
    }

    $aliases = [
        'emp_id' => ['empid', 'employeeid', 'employeecode', 'employeeno', 'employeenumber', 'empcode', 'staffid', 'staffcode', 'staffno', 'code'],
        'name' => ['name', 'employeename', 'employee', 'fullname', 'staffname', 'username', 'personname'],
        'email' => ['email', 'emailaddress', 'mail', 'officialemail', 'workemail'],
        'phone' => ['phonenumber', 'phone', 'mobilenumber', 'mobile', 'contactnumber', 'contact', 'mobileno', 'phoneno', 'contactno'],
        'shift' => ['shift', 'workshift', 'timeslot', 'timing'],
        'salary' => ['salary', 'monthlysalary', 'pay', 'amount', 'wage', 'salaryamount', 'monthlypay', 'basicpay'],
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

    foreach (['phone', 'salary'] as $required) {
        if (!array_key_exists($required, $columns)) {
            throw new RuntimeException('Missing required CSV column for ' . $required . '.');
        }
    }

    $columns['emp_id'] = $columns['emp_id'] ?? null;
    $columns['name'] = $columns['name'] ?? null;
    $columns['email'] = $columns['email'] ?? null;
    $columns['shift'] = $columns['shift'] ?? null;

    $rows = [];
    $reservedEmpIds = [];
    $rowNumber = 1;
    foreach (array_slice($sourceRows, 1) as $row) {
        $rowNumber++;
        $email = trim((string) (($columns['email'] !== null) ? ($row[$columns['email']] ?? '') : ''));
        $phone = trim((string) ($row[$columns['phone']] ?? ''));
        $salaryText = trim((string) ($row[$columns['salary']] ?? '0'));
        $empId = trim((string) (($columns['emp_id'] !== null) ? ($row[$columns['emp_id']] ?? '') : ''));

        if ($email === '' && $phone === '' && $salaryText === '') {
            continue;
        }

        if ($empId === '') {
            $empId = generate_employee_emp_id($reservedEmpIds);
        }
        $reservedEmpIds[] = $empId;

        if ($email === '') {
            $email = attendance_import_employee_email($empId);
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' has an invalid email address.');
        }
        if ($phone === '') {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' is missing a phone number.');
        }
        if (!is_numeric($salaryText) || (float) $salaryText < 0) {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' has an invalid salary value.');
        }

        $name = guessed_employee_name($row, $columns, $headerMap, $email, $empId);

        $rows[] = [
            'emp_id' => $empId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'shift' => trim((string) (($columns['shift'] !== null) ? ($row[$columns['shift']] ?? '') : '')),
            'salary' => (float) $salaryText,
        ];
    }

    if (!$rows) {
        throw new RuntimeException('Employee import file has no usable rows.');
    }

    return $rows;
}
