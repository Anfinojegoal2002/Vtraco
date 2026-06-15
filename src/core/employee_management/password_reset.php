<?php

declare(strict_types=1);

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


