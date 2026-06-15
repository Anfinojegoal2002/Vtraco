<?php

declare(strict_types=1);

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
    $recruiterName = trim((string) ($data['recruiter_name'] ?? ''));
    $recruitedThrough = trim((string) ($data['recruited_through'] ?? ''));
    $designation = trim((string) ($data['designation'] ?? 'Regular Employee'));
    $dateOfJoining = trim((string) ($data['date_of_joining'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Employee email must be valid.');
    }
    if ($phone === '') {
        throw new RuntimeException('Employee phone number is required.');
    }
    if ($salary < 0) {
        throw new RuntimeException('Employee salary must be zero or greater.');
    }
    if ($recruiterName === '') {
        throw new RuntimeException('Recruiter name is required.');
    }
    if ($recruitedThrough === '') {
        throw new RuntimeException('Recruited through is required.');
    }
    if ($designation === '') {
        throw new RuntimeException('Employee designation is required.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfJoining)) {
        throw new RuntimeException('Date of joining is required.');
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

        $profileStatus = in_array((string) ($scope['employee_type'] ?? ''), ['corporate', 'vendor'], true) ? 'verified' : 'incomplete';

        $pdo->prepare('UPDATE users SET admin_id = :admin_id, emp_id = :emp_id, name = :name, email = :email, phone = :phone, shift = :shift, salary = :salary, employee_type = :employee_type, recruiter_name = :recruiter_name, recruited_through = :recruited_through, designation = :designation, date_of_joining = :date_of_joining, profile_status = :profile_status, password_hash = :password_hash, force_password_change = 1, password_changed_at = NULL, password_reset_requested_at = NULL WHERE id = :id')
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
                'recruiter_name' => $recruiterName,
                'recruited_through' => $recruitedThrough,
                'designation' => $designation,
                'date_of_joining' => $dateOfJoining,
                'profile_status' => $profileStatus,
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


