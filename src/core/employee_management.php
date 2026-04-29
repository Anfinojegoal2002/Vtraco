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
    } elseif (($manager['role'] ?? '') === 'external_vendor') {
        $employeeType = 'vendor';
    }
    if (!in_array($employeeType, ['regular', 'vendor', 'corporate'], true)) {
        $employeeType = 'regular';
    }
    if ($employeeType === 'vendor' && ($manager['role'] ?? '') !== 'external_vendor') {
        throw new RuntimeException('Vendor employees can only be added by the vendor.');
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

function employee_import_scope(array $data): array
{
    $adminId = current_admin_id();
    if ($adminId === null) {
        throw new RuntimeException('An administrator must be signed in to import employees.');
    }

    $employeeType = trim((string) ($data['employee_type'] ?? 'regular'));
    $manager = current_user();
    if (($manager['role'] ?? '') === 'freelancer') {
        $employeeType = 'corporate';
    } elseif (($manager['role'] ?? '') === 'external_vendor') {
        $employeeType = 'vendor';
    }
    if (!in_array($employeeType, ['regular', 'vendor', 'corporate'], true)) {
        $employeeType = 'regular';
    }
    if ($employeeType === 'vendor' && ($manager['role'] ?? '') !== 'external_vendor') {
        throw new RuntimeException('Vendor employees can only be added by the vendor.');
    }

    $role = $employeeType === 'corporate' ? 'corporate_employee' : current_manager_target_role();

    return [
        'admin_id' => $adminId,
        'employee_type' => $employeeType,
        'role' => $role,
    ];
}

function find_existing_employee_for_import(array $data): ?array
{
    $scope = employee_import_scope($data);
    $email = trim((string) ($data['email'] ?? ''));
    $empId = trim((string) ($data['emp_id'] ?? ''));

    $user = current_user();
    $isAdmin = ($user['role'] ?? '') === 'admin';

    if ($empId !== '') {
        $sql = $isAdmin
            ? "SELECT * FROM users WHERE role IN ('employee', 'corporate_employee') AND (admin_id = :admin_id OR admin_id IS NULL) AND emp_id = :emp_id ORDER BY id DESC LIMIT 1"
            : 'SELECT * FROM users WHERE role = :role AND admin_id = :admin_id AND emp_id = :emp_id ORDER BY id DESC LIMIT 1';
        
        $params = $isAdmin 
            ? ['admin_id' => $scope['admin_id'], 'emp_id' => $empId]
            : ['role' => $scope['role'], 'admin_id' => $scope['admin_id'], 'emp_id' => $empId];

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    if ($email !== '') {
        $sql = $isAdmin
            ? "SELECT * FROM users WHERE role IN ('employee', 'corporate_employee') AND (admin_id = :admin_id OR admin_id IS NULL) AND LOWER(email) = LOWER(:email) ORDER BY id DESC LIMIT 1"
            : 'SELECT * FROM users WHERE role = :role AND admin_id = :admin_id AND LOWER(email) = LOWER(:email) ORDER BY id DESC LIMIT 1';
            
        $params = $isAdmin 
            ? ['admin_id' => $scope['admin_id'], 'email' => $email]
            : ['role' => $scope['role'], 'admin_id' => $scope['admin_id'], 'email' => $email];

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    return null;
}

function import_employee_row(array $data, array $rules, array $projectIds = []): array
{
    $existingEmployee = find_existing_employee_for_import($data);
    if (!$existingEmployee) {
        $createdEmployee = insert_employee($data, $rules, $projectIds);
        $createdEmployee['result'] = 'created';
        return $createdEmployee;
    }

    $scope = employee_import_scope($data);
    $password = random_password();
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        $name = generated_employee_name((string) ($data['email'] ?? ''), (string) ($data['emp_id'] ?? ''));
    }
    $email = trim((string) ($data['email'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $salary = (float) ($data['salary'] ?? 0);
    $empId = trim((string) ($data['emp_id'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Employee email must be valid.');
    }
    if ($phone === '') {
        throw new RuntimeException('Employee phone number is required.');
    }
    if ($salary < 0) {
        throw new RuntimeException('Employee salary must be zero or greater.');
    }
    if (role_email_exists($scope['role'], $email, (int) $existingEmployee['id'])) {
        throw new RuntimeException('This employee email is already assigned.');
    }

    $normalizedProjectIds = normalize_project_assignment_ids($projectIds);
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $emailToSave = (str_ends_with($email, '@vtraco.local') && !str_ends_with($existingEmployee['email'] ?? '', '@vtraco.local'))
            ? (string) $existingEmployee['email']
            : $email;

        $pdo->prepare('UPDATE users SET admin_id = :admin_id, emp_id = :emp_id, name = :name, email = :email, phone = :phone, shift = :shift, salary = :salary, employee_type = :employee_type, password_hash = :password_hash, force_password_change = 1, password_changed_at = NULL, password_reset_requested_at = NULL WHERE id = :id')
            ->execute([
                'id' => (int) $existingEmployee['id'],
                'admin_id' => $scope['admin_id'],
                'emp_id' => $empId,
                'name' => $name,
                'email' => $emailToSave,
                'phone' => $phone,
                'shift' => normalize_shift_selection((string) ($data['shift'] ?? '')),
                'salary' => $salary,
                'employee_type' => $scope['employee_type'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        save_employee_rules((int) $existingEmployee['id'], $rules);
        save_employee_project_assignments((int) $existingEmployee['id'], $normalizedProjectIds);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $employee = employee_by_id((int) $existingEmployee['id']) ?: $existingEmployee;
    $mailResult = send_employee_credentials_email($employee, $password, $rules);

    return [
        'employee' => $employee,
        'mail_result' => $mailResult,
        'password' => $password,
        'result' => 'updated',
    ];
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

function normalize_import_phone(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '';
    }

    if (preg_match('/^\d+(?:\.0+)?$/', $phone) === 1) {
        return preg_replace('/\.0+$/', '', $phone) ?? $phone;
    }

    if (preg_match('/^\d+(?:\.\d+)?E[+-]?\d+$/i', $phone) === 1) {
        return number_format((float) $phone, 0, '', '');
    }

    return $phone;
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
        'email' => ['email', 'emailaddress', 'emailid', 'mail', 'mailid', 'mailaddress', 'officialemail', 'workemail'],
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

    foreach (['emp_id', 'name', 'email', 'phone', 'salary'] as $required) {
        if (!array_key_exists($required, $columns)) {
            throw new RuntimeException('Missing required CSV column for ' . $required . '.');
        }
    }

    $columns['shift'] = $columns['shift'] ?? null;

    $rows = [];
    $reservedEmpIds = [];
    $rowNumber = 1;
    foreach (array_slice($sourceRows, 1) as $row) {
        $rowNumber++;
        $email = trim((string) (($columns['email'] !== null) ? ($row[$columns['email']] ?? '') : ''));
        $phone = normalize_import_phone((string) ($row[$columns['phone']] ?? ''));
        $salaryText = trim((string) ($row[$columns['salary']] ?? '0'));
        $empId = trim((string) (($columns['emp_id'] !== null) ? ($row[$columns['emp_id']] ?? '') : ''));

        if ($email === '' && $phone === '' && $salaryText === '') {
            continue;
        }

        if ($empId === '') {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' is missing an employee ID.');
        }
        $reservedEmpIds[] = $empId;

        if ($email === '') {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' is missing an email address.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' has an invalid email address.');
        }
        if ($phone === '') {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' is missing a phone number.');
        }
        if (!is_numeric($salaryText) || (float) $salaryText < 0) {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' has an invalid salary value.');
        }

        $name = trim((string) ($row[$columns['name']] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' is missing a name.');
        }

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
