<?php

declare(strict_types=1);

function etime_webhook_secret(): string
{
    $config = etime_config();
    return trim((string) ($config['webhook_secret'] ?? ''));
}

function etime_webhook_request_secret(): string
{
    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (stripos($authorization, 'Bearer ') === 0) {
        return trim(substr($authorization, 7));
    }

    return trim((string) (
        $_SERVER['HTTP_X_VTRACO_WEBHOOK_SECRET']
        ?? $_SERVER['HTTP_X_ETIME_WEBHOOK_SECRET']
        ?? $_GET['secret']
        ?? ''
    ));
}   

function verify_etime_webhook_request(): void
{
    $secret = etime_webhook_secret();
    if ($secret === '') {
        throw new RuntimeException('eTime webhook secret is not configured.');
    }

    if (!hash_equals($secret, etime_webhook_request_secret())) {
        throw new RuntimeException('Invalid eTime webhook secret.');
    }
}

function etime_webhook_payload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $payload = [];

    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    if ($payload === [] && $_POST !== []) {
        $payload = $_POST;
    }

    if (isset($payload['data']) && is_array($payload['data'])) {
        $payload = $payload['data'];
    }

    return $payload;
}

function etime_webhook_value(array $payload, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $payload) && trim((string) $payload[$key]) !== '') {
            return trim((string) $payload[$key]);
        }
    }

    $normalized = [];
    foreach ($payload as $key => $value) {
        $normalized[strtolower(str_replace(['_', '-', ' '], '', (string) $key))] = $value;
    }

    foreach ($keys as $key) {
        $lookup = strtolower(str_replace(['_', '-', ' '], '', $key));
        if (array_key_exists($lookup, $normalized) && trim((string) $normalized[$lookup]) !== '') {
            return trim((string) $normalized[$lookup]);
        }
    }

    return '';
}

function etime_webhook_parse_datetime(string $value, ?string $fallbackDate = null): ?string
{
    $value = trim($value);
    if ($value === '' || str_contains($value, '--')) {
        return null;
    }

    foreach ([
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd-m-Y H:i:s',
        'd-m-Y H:i',
        'm/d/Y H:i:s',
        'm/d/Y H:i',
    ] as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $value);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->format('Y-m-d H:i:s');
        }
    }

    if ($fallbackDate !== null) {
        return etime_parse_time($value, $fallbackDate);
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
}

function etime_webhook_resolve_punches(array $payload): array
{
    $dateRaw = etime_webhook_value($payload, ['DateString', 'AttendDate', 'AttendanceDate', 'date', 'PunchDate']);
    $punchRaw = etime_webhook_value($payload, ['PunchDate', 'PunchTime', 'punch_time', 'time', 'datetime']);
    $date = null;

    foreach ([$dateRaw, $punchRaw] as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $parsedDate = etime_parse_record_date($candidate);
        if ($parsedDate !== null) {
            $date = $parsedDate;
            break;
        }
        $timestamp = strtotime($candidate);
        if ($timestamp !== false) {
            $date = date('Y-m-d', $timestamp);
            break;
        }
    }

    $date = $date ?: date('Y-m-d');
    $inTime = etime_webhook_parse_datetime(etime_webhook_value($payload, ['INTime', 'InTime', 'in_time', 'PunchInTime']), $date);
    $outTime = etime_webhook_parse_datetime(etime_webhook_value($payload, ['OUTTime', 'OutTime', 'out_time', 'PunchOutTime']), $date);
    $punchTime = etime_webhook_parse_datetime($punchRaw, $date);
    $type = strtolower(etime_webhook_value($payload, ['punch_type', 'PunchType', 'type', 'direction', 'M_Flag', 'flag']));

    if ($punchTime !== null && $inTime === null && $outTime === null) {
        if (str_contains($type, 'out') || $type === 'o' || $type === '1') {
            $outTime = $punchTime;
        } else {
            $inTime = $punchTime;
        }
    }

    return [
        'date' => $date,
        'in_time' => $inTime,
        'out_time' => $outTime,
        'status' => etime_webhook_value($payload, ['Status', 'status']),
    ];
}

function mark_etime_webhook_attendance(array $payload): array
{
    $empCode = etime_webhook_value($payload, ['Empcode', 'EmpCode', 'empcode', 'emp_code', 'employee_code', 'employee_id']);
    $name = etime_webhook_value($payload, ['Name', 'EmployeeName', 'employee_name', 'name']);
    $employee = employee_by_attendance_identity($empCode, $name);

    if (!$employee) {
        throw new RuntimeException('Employee not found for eTime webhook punch.');
    }

    $punches = etime_webhook_resolve_punches($payload);
    $record = attendance_record((int) $employee['id'], (string) $punches['date']);
    $inTime = $punches['in_time'];
    $outTime = $punches['out_time'];

    if ($inTime !== null && $outTime === null && $record && !empty($record['biometric_in_time'])) {
        $existingIn = strtotime((string) $record['biometric_in_time']);
        $newPunch = strtotime($inTime);
        if ($existingIn !== false && $newPunch !== false && $newPunch > $existingIn) {
            $outTime = $inTime;
            $inTime = null;
        }
    }

    $fields = [];
    if ($inTime !== null) {
        $fields['biometric_in_time'] = $inTime;
    }
    if ($outTime !== null) {
        $fields['biometric_out_time'] = $outTime;
    }
    if ($fields === []) {
        throw new RuntimeException('Webhook punch did not include a usable punch time.');
    }

    $existingIn = $inTime ?? ($record['biometric_in_time'] ?? null);
    $existingOut = $outTime ?? ($record['biometric_out_time'] ?? null);
    $fields['status'] = etime_status_to_attendance((string) $punches['status'], $existingIn, $existingOut);

    update_attendance_record((int) $employee['id'], (string) $punches['date'], $fields);

    return [
        'employee_id' => (int) $employee['id'],
        'empcode' => $empCode,
        'date' => (string) $punches['date'],
        'biometric_in_time' => $existingIn,
        'biometric_out_time' => $existingOut,
        'status' => $fields['status'],
    ];
}

function handle_etime_punch_webhook(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        render_json(['success' => false, 'message' => 'Use POST for eTime webhook punches.'], 405);
    }

    try {
        verify_etime_webhook_request();
        $result = mark_etime_webhook_attendance(etime_webhook_payload());
        render_json(['success' => true, 'message' => 'Attendance marked.', 'result' => $result]);
    } catch (Throwable $exception) {
        report_exception($exception, 'eTime webhook punch failed.');
        render_json(['success' => false, 'message' => $exception->getMessage()], 400);
    }
}
