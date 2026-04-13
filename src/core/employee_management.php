<?php

declare(strict_types=1);

function employee_emp_id_exists(string $empId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE role = "employee" AND emp_id = :emp_id');
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

function insert_employee(array $data, array $rules): array
{
    $password = random_password(6);
    $adminId = current_admin_id();
    if ($adminId === null) {
        throw new RuntimeException('An administrator must be signed in to add employees.');
    }

    $empId = trim((string) ($data['emp_id'] ?? ''));
    if ($empId === '') {
        $empId = generate_employee_emp_id();
    }

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        $name = generated_employee_name(trim((string) ($data['email'] ?? '')), $empId);
    }

    db()->prepare('INSERT INTO users (role, admin_id, emp_id, name, email, phone, shift, salary, password_hash, created_at) VALUES ("employee", :admin_id, :emp_id, :name, :email, :phone, :shift, :salary, :password_hash, :created_at)')
        ->execute([
            'admin_id' => $adminId,
            'emp_id' => $empId,
            'name' => $name,
            'email' => trim((string) $data['email']),
            'phone' => trim((string) $data['phone']),
            'shift' => trim((string) ($data['shift'] ?? '')),
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

    for ($suffix = 0; $suffix < 1000; $suffix++) {
        $email = $suffix === 0
            ? sprintf('attendance.%s@vtraco.local', $base)
            : sprintf('attendance.%s.%d@vtraco.local', $base, $suffix);

        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE role = "employee" AND email = :email');
        $stmt->execute(['email' => $email]);
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
    $password = random_password(6);

    db()->prepare('INSERT INTO users (role, admin_id, emp_id, name, email, phone, shift, salary, password_hash, created_at) VALUES ("employee", :admin_id, :emp_id, :name, :email, :phone, :shift, :salary, :password_hash, :created_at)')
        ->execute([
            'admin_id' => $adminId,
            'emp_id' => $empId,
            'name' => $name,
            'email' => $email,
            'phone' => '',
            'shift' => trim((string) ($entry['shift'] ?? '')),
            'salary' => 0,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => now(),
        ]);

    return employee_by_id((int) db()->lastInsertId());
}
function reset_employee_password(int $employeeId): array
{
    $employee = employee_by_id($employeeId);
    if (!$employee) {
        throw new RuntimeException('Employee not found for this administrator.');
    }

    $password = random_password(6);
    db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id AND role = "employee" AND admin_id = :admin_id')
        ->execute([
            'id' => $employeeId,
            'admin_id' => (int) current_admin_id(),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
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

    $stmt = db()->prepare('SELECT * FROM users WHERE role = "employee" AND LOWER(email) = LOWER(:email) LIMIT 1');
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function reset_employee_password_by_email(string $email): array
{
    $employee = employee_by_email($email);
    if (!$employee) {
        throw new RuntimeException('No employee account found for that email.');
    }

    $password = random_password(6);
    db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id AND role = "employee"')
        ->execute([
            'id' => (int) $employee['id'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

    $mailResult = send_employee_credentials_email($employee, $password, employee_rules((int) $employee['id']));

    return [
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

    foreach (['email', 'phone', 'salary'] as $required) {
        if (!array_key_exists($required, $columns)) {
            fclose($handle);
            throw new RuntimeException('Missing required CSV column for ' . $required . '.');
        }
    }

    $columns['emp_id'] = $columns['emp_id'] ?? null;
    $columns['name'] = $columns['name'] ?? null;
    $columns['shift'] = $columns['shift'] ?? null;

    $rows = [];
    $reservedEmpIds = [];
    while (($row = fgetcsv($handle)) !== false) {
        $email = trim((string) ($row[$columns['email']] ?? ''));
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

    fclose($handle);

    if (!$rows) {
        throw new RuntimeException('CSV file has no usable rows.');
    }

    return $rows;
}

