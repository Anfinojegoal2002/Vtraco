<?php

declare(strict_types=1);

function employee_count(): int
{
    $adminId = current_admin_id();
    $role = current_manager_target_role();
    if ($adminId === null) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = :role");
        $stmt->execute(['role' => $role]);
        return (int) $stmt->fetchColumn();
    }

    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = :role AND admin_id = :admin_id");
    $stmt->execute(['role' => $role, 'admin_id' => $adminId]);
    return (int) $stmt->fetchColumn();
}

function admin_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
}

function employees(): array
{
    $adminId = current_admin_id();
    $role = current_manager_target_role();
    if ($adminId === null) {
        $stmt = db()->prepare("SELECT * FROM users WHERE role = :role ORDER BY name");
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll();
    }

    $stmt = db()->prepare("SELECT * FROM users WHERE role = :role AND admin_id = :admin_id ORDER BY name");
    $stmt->execute(['role' => $role, 'admin_id' => $adminId]);
    return $stmt->fetchAll();
}

function employee_by_id(int $id): ?array
{
    $adminId = current_admin_id();
    $role = current_manager_target_role();
    if ($adminId === null) {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = :id AND role = :role");
        $stmt->execute(['id' => $id, 'role' => $role]);
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = :id AND role = :role AND admin_id = :admin_id");
        $stmt->execute([
            'id' => $id,
            'role' => $role,
            'admin_id' => $adminId,
        ]);
    }

    $row = $stmt->fetch();
    return $row ?: null;
}

function employee_by_emp_code(string $empCode): ?array
{
    $empCode = trim($empCode);
    if ($empCode === '') {
        return null;
    }

    $adminId = current_admin_id();
    $role = current_manager_target_role();
    if ($adminId === null) {
        $stmt = db()->prepare("SELECT * FROM users WHERE emp_id = :emp_id AND role = :role LIMIT 1");
        $stmt->execute(['emp_id' => $empCode, 'role' => $role]);
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE emp_id = :emp_id AND role = :role AND admin_id = :admin_id LIMIT 1");
        $stmt->execute([
            'emp_id' => $empCode,
            'role' => $role,
            'admin_id' => $adminId,
        ]);
    }

    $row = $stmt->fetch();
    return $row ?: null;
}

function employee_by_name(string $name): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $adminId = current_admin_id();
    $role = current_manager_target_role();
    if ($adminId === null) {
        $stmt = db()->prepare("SELECT * FROM users WHERE role = :role AND LOWER(name) = LOWER(:name) ORDER BY id LIMIT 1");
        $stmt->execute(['name' => $name, 'role' => $role]);
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE role = :role AND admin_id = :admin_id AND LOWER(name) = LOWER(:name) ORDER BY id LIMIT 1");
        $stmt->execute([
            'name' => $name,
            'role' => $role,
            'admin_id' => $adminId,
        ]);
    }

    $row = $stmt->fetch();
    return $row ?: null;
}
function random_password(int $length = 12): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function normalize_rules_from_input(array $source): array
{
    $manualEnabled = !empty($source['manual_punch']) || !empty($source['manual_punch_in']) || !empty($source['manual_punch_out']);
    $biometricEnabled = !empty($source['biometric_punch']) || !empty($source['biometric_punch_in']) || !empty($source['biometric_punch_out']);
    $count = max(1, (int) ($source['manual_out_count'] ?? 1));

    return [
        'manual_punch_in' => $manualEnabled,
        'manual_punch_out' => $manualEnabled,
        'manual_out_count' => $manualEnabled ? $count : 0,
        'biometric_punch_in' => $biometricEnabled,
        'biometric_punch_out' => $biometricEnabled,
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

function shift_timings(): array
{
    $adminId = current_admin_id();
    if ($adminId === null) {
        return [];
    }

    $stmt = db()->prepare('SELECT * FROM shift_timings WHERE admin_id = :admin_id ORDER BY start_time, shift_name, id');
    $stmt->execute(['admin_id' => $adminId]);
    return $stmt->fetchAll();
}

function shift_timing_exists(string $startTime, string $endTime): bool
{
    $adminId = current_admin_id();
    if ($adminId === null) {
        return false;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM shift_timings WHERE admin_id = :admin_id AND start_time = :start_time AND end_time = :end_time');
    $stmt->execute([
        'admin_id' => $adminId,
        'start_time' => $startTime,
        'end_time' => $endTime,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function add_shift_timing(array $data): void
{
    $adminId = current_admin_id();
    if ($adminId === null) {
        throw new RuntimeException('An administrator must be signed in to manage shifts.');
    }

    $startTime = (string) ($data['start_time'] ?? '');
    $endTime = (string) ($data['end_time'] ?? '');
    if (shift_timing_exists($startTime, $endTime)) {
        throw new RuntimeException('This shift timing is already posted.');
    }

    db()->prepare('INSERT INTO shift_timings (admin_id, shift_name, start_time, end_time, created_at) VALUES (:admin_id, :shift_name, :start_time, :end_time, :created_at)')
        ->execute([
            'admin_id' => $adminId,
            'shift_name' => trim((string) ($data['shift_name'] ?? '')),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'created_at' => now(),
        ]);
}

function delete_shift_timing(int $shiftId): void
{
    $adminId = current_admin_id();
    if ($adminId === null) {
        throw new RuntimeException('An administrator must be signed in to manage shifts.');
    }

    db()->prepare('DELETE FROM shift_timings WHERE id = :id AND admin_id = :admin_id')
        ->execute([
            'id' => $shiftId,
            'admin_id' => $adminId,
        ]);
}






