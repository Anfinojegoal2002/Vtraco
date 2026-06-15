<?php

declare(strict_types=1);

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

    $role = $employeeType === 'corporate' ? 'corporate_employee' : current_manager_target_role();
    $profileStatus = in_array($employeeType, ['corporate', 'vendor'], true) ? 'verified' : 'incomplete';

    if (role_requires_unique_email($role) && role_email_exists($role, $email)) {
        throw new RuntimeException('This employee email is already assigned.');
    }
    
    $normalizedProjectIds = normalize_project_assignment_ids($projectIds);
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('INSERT INTO users (role, admin_id, emp_id, name, email, phone, shift, salary, employee_type, recruiter_name, recruited_through, designation, date_of_joining, profile_status, password_hash, force_password_change, password_changed_at, created_at) VALUES (:role, :admin_id, :emp_id, :name, :email, :phone, :shift, :salary, :employee_type, :recruiter_name, :recruited_through, :designation, :date_of_joining, :profile_status, :password_hash, :force_password_change, :password_changed_at, :created_at)')
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
                'recruiter_name' => $recruiterName,
                'recruited_through' => $recruitedThrough,
                'designation' => $designation,
                'date_of_joining' => $dateOfJoining,
                'profile_status' => $profileStatus,
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


