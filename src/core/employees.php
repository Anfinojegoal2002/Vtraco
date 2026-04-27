<?php

declare(strict_types=1);

function employee_count(): int
{
    $user = current_user();
    if (($user['role'] ?? '') === 'admin') {
        return (int) db()->query("SELECT COUNT(*) FROM users WHERE role IN ('employee', 'corporate_employee')")->fetchColumn();
    }

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
    $user = current_user();
    if (($user['role'] ?? '') === 'external_vendor') {
        $stmt = db()->prepare("SELECT * FROM users WHERE role IN ('employee', 'corporate_employee') AND admin_id = :admin_id ORDER BY name");
        $stmt->execute(['admin_id' => $adminId]);
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE role = :role AND admin_id = :admin_id ORDER BY name");
        $stmt->execute(['role' => $role, 'admin_id' => $adminId]);
    }
    return $stmt->fetchAll();
}

function employee_by_id(int $id): ?array
{
    $user = current_user();
    if (($user['role'] ?? '') === 'admin') {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = :id AND role IN ('employee', 'corporate_employee')");
        $stmt->execute(['id' => $id]);
    } else {
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
    }

    $row = $stmt->fetch();
    return $row ?: null;
}

function normalize_emp_code_for_match(string $empCode): string
{
    $normalized = strtoupper(trim($empCode));
    $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized) ?? $normalized;
    if (preg_match('/^([A-Z]+)0*([0-9]+)$/', $normalized, $matches) === 1) {
        return $matches[1] . $matches[2];
    }

    return $normalized;
}

function employee_by_emp_code(string $empCode): ?array
{
    $empCode = trim($empCode);
    if ($empCode === '') {
        return null;
    }

    $adminId = current_admin_id();
    $role = current_manager_target_role();
    $targetCode = normalize_emp_code_for_match($empCode);
    if ($adminId === null) {
        $stmt = db()->prepare("SELECT * FROM users WHERE role = :role");
        $stmt->execute(['role' => $role]);
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE role = :role AND admin_id = :admin_id");
        $stmt->execute([
            'role' => $role,
            'admin_id' => $adminId,
        ]);
    }

    foreach ($stmt->fetchAll() as $row) {
        if (normalize_emp_code_for_match((string) ($row['emp_id'] ?? '')) === $targetCode) {
            return $row;
        }
    }

    return null;
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
    $manageTransaction = !$pdo->inTransaction();

    if ($manageTransaction) {
        $pdo->beginTransaction();
    }

    try {
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

        if ($manageTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($manageTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
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

function standard_shift_options(): array
{
    return [
        '09:00 AM - 06:00 PM',
        '11:30 AM - 08:30 PM',
    ];
}

function format_shift_selection_from_times(string $startTime, string $endTime): string
{
    return date('h:i A', strtotime($startTime)) . ' - ' . date('h:i A', strtotime($endTime));
}

function shift_time_to_24h(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    foreach (['H:i:s', 'H:i', 'g:i A', 'g:iA', 'h:i A', 'h:iA'] as $format) {
        $time = DateTimeImmutable::createFromFormat($format, $value);
        if ($time instanceof DateTimeImmutable) {
            return $time->format('H:i:s');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('H:i:s', $timestamp);
}

function normalize_shift_selection(?string $shift): string
{
    $shift = trim((string) $shift);
    if ($shift === '') {
        return '';
    }

    $normalized = str_replace('–', '-', $shift);
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    $normalized = trim($normalized);

    if (in_array($normalized, ['10:30 AM - 08:30 PM', '10:30 AM - 8:30 PM'], true)) {
        $normalized = '11:30 AM - 08:30 PM';
    }

    foreach (standard_shift_options() as $option) {
        if (strcasecmp($normalized, $option) === 0) {
            return $option;
        }
    }

    return $normalized;
}

function shift_window_from_label(?string $shift): ?array
{
    $shift = normalize_shift_selection($shift);
    if ($shift === '') {
        return null;
    }

    $parts = preg_split('/\s*-\s*/', $shift, 2);
    if (!is_array($parts) || count($parts) !== 2) {
        return null;
    }

    $startTime = shift_time_to_24h($parts[0]);
    $endTime = shift_time_to_24h($parts[1]);
    if ($startTime === null || $endTime === null || $startTime === $endTime) {
        return null;
    }

    return [
        'start_time' => $startTime,
        'end_time' => $endTime,
        'shift_name' => $shift,
    ];
}

function shift_window_for_employee(array $employee): ?array
{
    $shift = normalize_shift_selection((string) ($employee['shift'] ?? ''));
    if ($shift === '') {
        return null;
    }

    $window = shift_window_from_label($shift);
    if ($window !== null) {
        return $window;
    }

    $adminId = (int) ($employee['admin_id'] ?? 0);
    if ($adminId <= 0 && (($employee['role'] ?? '') === 'admin')) {
        $adminId = (int) ($employee['id'] ?? 0);
    }

    if ($adminId <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT shift_name, start_time, end_time FROM shift_timings WHERE admin_id = :admin_id');
    $stmt->execute(['admin_id' => $adminId]);

    foreach ($stmt->fetchAll() as $row) {
        $shiftName = normalize_shift_selection((string) ($row['shift_name'] ?? ''));
        $rowWindow = [
            'start_time' => trim((string) ($row['start_time'] ?? '')),
            'end_time' => trim((string) ($row['end_time'] ?? '')),
            'shift_name' => $shiftName,
        ];

        if ($shiftName !== '' && strcasecmp($shiftName, $shift) === 0) {
            return $rowWindow;
        }

        $formattedWindow = shift_window_from_label(format_shift_selection_from_times(
            (string) ($row['start_time'] ?? ''),
            (string) ($row['end_time'] ?? '')
        ));
        if ($formattedWindow !== null && strcasecmp($formattedWindow['shift_name'], $shift) === 0) {
            return [
                'start_time' => $formattedWindow['start_time'],
                'end_time' => $formattedWindow['end_time'],
                'shift_name' => $shift,
            ];
        }
    }

    return null;
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

function resolve_shift_selection_from_input(array $source, ?string $fallbackShift = null, bool $allowBlank = false): string
{
    $selectedShift = normalize_shift_selection((string) ($source['shift'] ?? $fallbackShift ?? ''));
    $customStartTime = trim((string) ($source['custom_shift_start_time'] ?? ''));
    $customEndTime = trim((string) ($source['custom_shift_end_time'] ?? ''));

    if ($customStartTime === '' && $customEndTime === '') {
        if ($selectedShift === '' && !$allowBlank && $fallbackShift !== null) {
            return normalize_shift_selection($fallbackShift);
        }
        return $selectedShift;
    }

    if ($customStartTime === '' || $customEndTime === '') {
        throw new RuntimeException('Enter both custom shift start time and end time.');
    }

    if ($customStartTime === $customEndTime) {
        throw new RuntimeException('Custom shift start time and end time must be different.');
    }

    $resolvedShift = format_shift_selection_from_times($customStartTime, $customEndTime);

    $currentUser = current_user();
    if (($currentUser['role'] ?? '') === 'admin' && !shift_timing_exists($customStartTime, $customEndTime)) {
        add_shift_timing([
            'start_time' => $customStartTime,
            'end_time' => $customEndTime,
        ]);
    }

    return $resolvedShift;
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






