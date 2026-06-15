<?php

declare(strict_types=1);

function contractual_dashboard_admin_for_employee(array $employee): array
{
    $adminId = (int) ($employee['admin_id'] ?? 0);
    if ($adminId <= 0) {
        return ['id' => 0, 'name' => 'Admin'];
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $admin = $stmt->fetch();

    return $admin ?: ['id' => $adminId, 'name' => 'Admin'];
}


