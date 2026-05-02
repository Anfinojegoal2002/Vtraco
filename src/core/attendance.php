<?php

declare(strict_types=1);

function month_bounds(string $month): array
{
    $start = new DateTimeImmutable($month . '-01');
    $end = $start->modify('last day of this month');
    return [$start, $end];
}

function default_status_for_date(string $date): string
{
    if (date('w', strtotime($date)) === '0') {
        return 'Week Off';
    }

    return attendance_date_is_closed($date) ? 'Absent' : '';
}

function attendance_date_is_closed(?string $date): bool
{
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    return $date < date('Y-m-d');
}

function attendance_status_for_user_date(int $userId, string $date): string
{
    $record = attendance_record($userId, $date);
    if (!$record) {
        return default_status_for_date($date);
    }

    $sessions = !empty($record['id']) ? attendance_sessions((int) $record['id']) : [];
    return resolved_attendance_status($record, $sessions);
}

function is_week_off_for_user_date(int $userId, string $date): bool
{
    return attendance_status_for_user_date($userId, $date) === 'Week Off';
}

function attendance_record(int $userId, string $date): ?array
{
    $stmt = db()->prepare('SELECT * FROM attendance_records WHERE user_id = :user_id AND attend_date = :attend_date');
    $stmt->execute(['user_id' => $userId, 'attend_date' => $date]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ensure_attendance_record(int $userId, string $date): array
{
    $record = attendance_record($userId, $date);
    if ($record) {
        return $record;
    }

    db()->prepare('INSERT INTO attendance_records (user_id, attend_date, status, created_at, updated_at) VALUES (:user_id, :attend_date, :status, :created_at, :updated_at)')
        ->execute([
            'user_id' => $userId,
            'attend_date' => $date,
            'status' => default_status_for_date($date),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    return attendance_record($userId, $date) ?: [];
}

function update_attendance_record(int $userId, string $date, array $fields): void
{
    $record = ensure_attendance_record($userId, $date);
    $sets = [];
    $params = ['id' => $record['id']];
    foreach ($fields as $key => $value) {
        $sets[] = $key . ' = :' . $key;
        $params[$key] = $value;
    }
    $sets[] = 'updated_at = :updated_at';
    $params['updated_at'] = now();
    $sql = 'UPDATE attendance_records SET ' . implode(', ', $sets) . ' WHERE id = :id';
    db()->prepare($sql)->execute($params);
}

function attendance_sessions(int $attendanceId): array
{
    $stmt = db()->prepare('SELECT * FROM attendance_sessions WHERE attendance_id = :attendance_id ORDER BY id');
    $stmt->execute(['attendance_id' => $attendanceId]);
    return $stmt->fetchAll();
}

function attendance_session_by_slot(int $attendanceId, string $slotName): ?array
{
    $stmt = db()->prepare('SELECT * FROM attendance_sessions WHERE attendance_id = :attendance_id AND slot_name = :slot_name ORDER BY id DESC LIMIT 1');
    $stmt->execute([
        'attendance_id' => $attendanceId,
        'slot_name' => $slotName,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function update_attendance_session(int $sessionId, array $fields): void
{
    $sets = [];
    $params = ['id' => $sessionId];
    foreach ($fields as $key => $value) {
        $sets[] = $key . ' = :' . $key;
        $params[$key] = $value;
    }
    $sql = 'UPDATE attendance_sessions SET ' . implode(', ', $sets) . ' WHERE id = :id';
    db()->prepare($sql)->execute($params);
}

function add_attendance_session(int $attendanceId, array $payload): void
{
    db()->prepare('INSERT INTO attendance_sessions (attendance_id, project_id, session_mode, slot_name, punch_in_path, punch_in_lat, punch_in_lng, punch_in_time, punch_out_time, college_name, session_name, day_portion, session_duration, total_students, present_students, topics_handled, location, created_at) VALUES (:attendance_id, :project_id, :session_mode, :slot_name, :punch_in_path, :punch_in_lat, :punch_in_lng, :punch_in_time, :punch_out_time, :college_name, :session_name, :day_portion, :session_duration, :total_students, :present_students, :topics_handled, :location, :created_at)')
        ->execute([
            'attendance_id' => $attendanceId,
            'project_id' => $payload['project_id'] ?? null,
            'session_mode' => $payload['session_mode'],
            'slot_name' => $payload['slot_name'] ?? null,
            'punch_in_path' => $payload['punch_in_path'] ?? null,
            'punch_in_lat' => $payload['punch_in_lat'] ?? null,
            'punch_in_lng' => $payload['punch_in_lng'] ?? null,
            'punch_in_time' => $payload['punch_in_time'] ?? null,
            'punch_out_time' => $payload['punch_out_time'] ?? null,
            'college_name' => $payload['college_name'] ?? null,
            'session_name' => $payload['session_name'] ?? null,
            'day_portion' => $payload['day_portion'] ?? null,
            'session_duration' => $payload['session_duration'] ?? null,
            'total_students' => $payload['total_students'] ?? null,
            'present_students' => $payload['present_students'] ?? null,
            'topics_handled' => $payload['topics_handled'] ?? null,
            'location' => $payload['location'] ?? null,
            'created_at' => now(),
        ]);
}

function session_has_manual_in(array $session): bool
{
    return trim((string) ($session['punch_in_path'] ?? '')) !== ''
        || trim((string) ($session['punch_in_time'] ?? '')) !== '';
}

function session_has_manual_out(array $session): bool
{
    return trim((string) ($session['college_name'] ?? '')) !== ''
        || trim((string) ($session['session_name'] ?? '')) !== ''
        || trim((string) ($session['location'] ?? '')) !== ''
        || trim((string) ($session['topics_handled'] ?? '')) !== ''
        || (int) ($session['total_students'] ?? 0) > 0
        || (int) ($session['present_students'] ?? 0) > 0
        || (float) ($session['session_duration'] ?? 0) > 0;
}

function attendance_seconds_between(?string $start, ?string $end): ?int
{
    $start = trim((string) $start);
    $end = trim((string) $end);
    if ($start === '' || $end === '') {
        return null;
    }

    $startTime = strtotime($start);
    $endTime = strtotime($end);
    if ($startTime === false || $endTime === false) {
        return null;
    }

    if ($endTime < $startTime) {
        $endTime += 86400;
    }

    return max(0, $endTime - $startTime);
}

function attendance_shift_window_for_record(array $record): ?array
{
    $employee = employee_by_id((int) ($record['user_id'] ?? 0));
    if ($employee) {
        $dateWindow = shift_window_for_employee_on_date($employee, (string) ($record['attend_date'] ?? ''));
        if ($dateWindow !== null) {
            return $dateWindow;
        }
    }

    $startTime = trim((string) ($record['shift_start_time'] ?? ''));
    $endTime = trim((string) ($record['shift_end_time'] ?? ''));
    if ($startTime !== '' && $endTime !== '' && $startTime !== $endTime) {
        return [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    return null;
}

function attendance_resolved_work_times(array $record, array $sessions): array
{
    $inTimes = [];
    $outTimes = [];

    foreach (['punch_in_time', 'biometric_in_time'] as $field) {
        $value = trim((string) ($record[$field] ?? ''));
        if ($value !== '') {
            $inTimes[] = $value;
        }
    }

    $value = trim((string) ($record['biometric_out_time'] ?? ''));
    if ($value !== '') {
        $outTimes[] = $value;
    }

    foreach ($sessions as $session) {
        $inTime = trim((string) ($session['punch_in_time'] ?? ''));
        if ($inTime !== '') {
            $inTimes[] = $inTime;
        }

        $outTime = trim((string) ($session['punch_out_time'] ?? ''));
        if ($outTime !== '') {
            $outTimes[] = $outTime;
        }
    }

    sort($inTimes);
    sort($outTimes);

    return [
        'in_time' => $inTimes[0] ?? null,
        'out_time' => $outTimes ? $outTimes[count($outTimes) - 1] : null,
    ];
}

function shift_based_attendance_status(array $record, array $sessions): ?string
{
    $shiftWindow = attendance_shift_window_for_record($record);
    if ($shiftWindow === null) {
        return null;
    }

    $workTimes = attendance_resolved_work_times($record, $sessions);
    $inTime = $workTimes['in_time'];
    $outTime = $workTimes['out_time'];
    if ($inTime === null && $outTime === null) {
        return null;
    }

    if ($inTime === null || $outTime === null) {
        return attendance_date_is_closed((string) ($record['attend_date'] ?? '')) ? 'Half Day' : 'Pending';
    }

    $attendDate = (string) ($record['attend_date'] ?? date('Y-m-d'));
    $shiftStart = strtotime($attendDate . ' ' . $shiftWindow['start_time']);
    $shiftEnd = strtotime($attendDate . ' ' . $shiftWindow['end_time']);
    $actualIn = strtotime($inTime);
    $actualOut = strtotime($outTime);
    if ($shiftStart === false || $shiftEnd === false || $actualIn === false || $actualOut === false) {
        return null;
    }

    if ($shiftEnd < $shiftStart) {
        $shiftEnd += 86400;
    }
    if ($actualOut < $actualIn) {
        $actualOut += 86400;
    }

    if ($actualIn > $shiftStart || $actualOut < $shiftEnd) {
        return 'Half Day';
    }

    return 'Present';
}

function manual_attendance_status(array $record, array $sessions): ?string
{
    $hasIncompleteManual = false;
    $hasCompletedManual = false;

    foreach ($sessions as $session) {
        if (($session['session_mode'] ?? '') !== 'manual_pair') {
            continue;
        }

        $hasManualIn = session_has_manual_in($session);
        $hasManualOut = session_has_manual_out($session);

        if ($hasManualIn && !$hasManualOut) {
            $hasIncompleteManual = true;
        }

        if ($hasManualIn && $hasManualOut) {
            $hasCompletedManual = true;
        }
    }

    if (!$sessions && trim((string) ($record['punch_in_path'] ?? '')) !== '') {
        $hasIncompleteManual = true;
    }

    if ($hasIncompleteManual) {
        return 'Half Day';
    }

    if ($hasCompletedManual) {
        return 'Present';
    }

    return null;
}

function resolved_attendance_status(array $record, array $sessions): string
{
    $overrideStatus = trim((string) ($record['admin_override_status'] ?? ''));
    if ($overrideStatus !== '' && $overrideStatus !== 'Pending') {
        return $overrideStatus;
    }

    $status = trim((string) ($record['status'] ?? ''));
    if ($status === '') {
        return default_status_for_date((string) ($record['attend_date'] ?? date('Y-m-d')));
    }

    if (in_array($status, ['Leave', 'Week Off'], true)) {
        return $status;
    }

    $manualStatus = manual_attendance_status($record, $sessions);
    if ($manualStatus !== null && $manualStatus !== 'Present') {
        return $manualStatus;
    }

    $shiftStatus = shift_based_attendance_status($record, $sessions);
    if ($shiftStatus !== null) {
        return $shiftStatus;
    }

    if ($manualStatus !== null) {
        return $manualStatus;
    }

    if ($status === 'Pending' && attendance_date_is_closed((string) ($record['attend_date'] ?? ''))) {
        return 'Absent';
    }

    return $status;
}

function manual_slot_limit(array $rules): int
{
    return max((int) ($rules['manual_out_count'] ?? 0), !empty($rules['manual_punch_in']) ? 1 : 0);
}

function manual_slot_name(array $rules, int $slotIndex): string
{
    $slotIndex = max(1, $slotIndex);
    return $rules['manual_out_slots'][$slotIndex - 1] ?? ('Manual Punch Slot ' . $slotIndex);
}
function month_attendance_for_user(int $userId, string $month): array
{
    [$start, $end] = month_bounds($month);
    $stmt = db()->prepare('SELECT * FROM attendance_records WHERE user_id = :user_id AND attend_date BETWEEN :start_date AND :end_date ORDER BY attend_date');
    $stmt->execute([
        'user_id' => $userId,
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
    ]);

    $records = [];
    foreach ($stmt->fetchAll() as $row) {
        $records[$row['attend_date']] = $row;
    }

    $out = [];
    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $key = $date->format('Y-m-d');
        $defaultStatus = default_status_for_date($key);
        if ($defaultStatus === 'Absent') {
            $defaultStatus = '';
        }
        $record = $records[$key] ?? [
            'id' => null,
            'user_id' => $userId,
            'attend_date' => $key,
            'status' => $defaultStatus,
            'punch_in_path' => null,
            'punch_in_lat' => null,
            'punch_in_lng' => null,
            'punch_in_time' => null,
            'biometric_in_time' => null,
            'biometric_out_time' => null,
            'leave_reason' => null,
            'admin_override_status' => null,
        ];
        $sessions = $record['id'] ? attendance_sessions((int) $record['id']) : [];
        $record['status'] = resolved_attendance_status($record, $sessions);
        $out[$key] = ['record' => $record, 'sessions' => $sessions];
    }

    $dates = array_keys($out);
    $totalDates = count($dates);
    for ($index = 0; $index < $totalDates; $index++) {
        $date = $dates[$index];
        $status = strtoupper(trim((string) ($out[$date]['record']['status'] ?? '')));
        if ($status !== 'ABSENT' || date('w', strtotime($date)) !== '0') {
            continue;
        }

        $previousStatus = $index > 0
            ? (string) ($out[$dates[$index - 1]]['record']['status'] ?? '')
            : '';
        $nextStatus = ($index + 1) < $totalDates
            ? (string) ($out[$dates[$index + 1]]['record']['status'] ?? '')
            : '';

        if (week_off_counts_as_present($previousStatus) && week_off_counts_as_present($nextStatus)) {
            $out[$date]['record']['status'] = 'Week Off';
        }
    }

    foreach (sandwich_week_off_absent_dates($out) as $absentDate) {
        if (!isset($out[$absentDate]['record'])) {
            continue;
        }
        $out[$absentDate]['record']['status'] = 'Absent';
        $out[$absentDate]['record']['sandwich_week_off_absent'] = true;
    }

    return $out;
}

function working_days_total(array $monthAttendance): float
{
    $total = 0.0;
    foreach ($monthAttendance as $entry) {
        $status = $entry['record']['status'] ?? 'Absent';
        if ($status === 'Present') {
            $total += 1.0;
        } elseif ($status === 'Half Day') {
            $total += 0.5;
        }
    }
    return $total;
}

function week_off_counts_as_present(string $status): bool
{
    return in_array(strtoupper(trim($status)), ['PRESENT', 'HALF DAY'], true);
}

function sandwich_week_off_absent_dates(array $monthAttendance): array
{
    $absentDates = [];
    $dates = array_keys($monthAttendance);
    $totalDates = count($dates);

    for ($index = 0; $index < $totalDates; $index++) {
        $date = $dates[$index];
        $status = strtoupper(trim((string) ($monthAttendance[$date]['record']['status'] ?? '')));
        if ($status !== 'WEEK OFF' || date('w', strtotime($date)) !== '0') {
            continue;
        }

        $previousStatus = $index > 0
            ? strtoupper(trim((string) ($monthAttendance[$dates[$index - 1]]['record']['status'] ?? '')))
            : '';
        $nextStatus = ($index + 1) < $totalDates
            ? strtoupper(trim((string) ($monthAttendance[$dates[$index + 1]]['record']['status'] ?? '')))
            : '';

        // A week off stays valid only when both adjacent days are attended.
        if (!week_off_counts_as_present($previousStatus) || !week_off_counts_as_present($nextStatus)) {
            $absentDates[] = $date;
        }
    }

    return $absentDates;
}

function sandwich_week_off_deduction_days(array $monthAttendance): int
{
    return count(sandwich_week_off_absent_dates($monthAttendance));
}

function attendance_counts(array $monthAttendance): array
{
    $counts = [
        'present' => 0,
        'absent' => 0,
        'half_day' => 0,
        'pending' => 0,
        'week_off' => 0,
        'leave' => 0,
        'unmarked' => 0,
        'sandwich_week_off_days' => 0,
        'payable_days' => 0.0,
        'working_days' => 0,
        'total_days' => count($monthAttendance),
    ];

    foreach ($monthAttendance as $entry) {
        $status = (string) ($entry['record']['status'] ?? '');
        $normStatus = strtoupper(trim($status));
        switch ($normStatus) {
            case 'PRESENT':
                $counts['present']++;
                $counts['payable_days'] += 1.0;
                break;
            case 'HALF DAY':
                $counts['half_day']++;
                $counts['payable_days'] += 0.5;
                break;
            case 'LEAVE':
                $counts['leave']++;
                break;
            case 'PENDING':
                $counts['pending']++;
                break;
            case 'WEEK OFF':
                $counts['week_off']++;
                break;
            case '':
                $counts['unmarked']++;
                break;
            case 'ABSENT':
                $counts['absent']++;
                break;
            default:
                $counts['absent']++;
                break;
        }
    }

    $counts['sandwich_week_off_days'] = 0;
    foreach ($monthAttendance as $entry) {
        if (!empty($entry['record']['sandwich_week_off_absent'])) {
            $counts['sandwich_week_off_days']++;
        }
    }

    $counts['working_days'] = max(1, $counts['total_days'] - ($counts['leave'] + $counts['week_off']));

    return $counts;
}

function salary_breakdown_for_month(float $salary, array $monthAttendance): array
{
    $counts = attendance_counts($monthAttendance);
    $presentDays = (float) working_days_total($monthAttendance);
    $workingDays = (int) $counts['working_days'];
    $payableDays = $presentDays;

    $dailyRate = $workingDays > 0 ? round($salary / $workingDays, 2) : 0.0;
    $calculatedSalary = round($salary * ($payableDays / max(1, $workingDays)), 2);

    return [
        'monthly_salary' => round($salary, 2),
        'daily_rate' => $dailyRate,
        'payable_days' => round($payableDays, 2),
        'present_days' => round($presentDays, 2),
        'working_days' => $workingDays,
        'calculated_salary' => $calculatedSalary,
        'counts' => $counts,
    ];
}

function vendor_session_breakdown_for_month(float $sessionRate, array $monthAttendance): array
{
    $fullSessions = 0;
    $halfSessions = 0;

    foreach ($monthAttendance as $entry) {
        $sessions = is_array($entry['sessions'] ?? null) ? $entry['sessions'] : [];

        foreach ($sessions as $session) {
            if (($session['session_mode'] ?? '') !== 'manual_pair') {
                continue;
            }
            if (!session_has_manual_in($session) || !session_has_manual_out($session)) {
                continue;
            }

            if (($session['day_portion'] ?? 'Full Day') === 'Half Day') {
                $halfSessions++;
            } else {
                $fullSessions++;
            }
        }
    }

    $sessionUnits = $fullSessions + ($halfSessions * 0.5);
    $calculatedSalary = round(($fullSessions * $sessionRate) + ($halfSessions * ($sessionRate / 2)), 2);

    return [
        'session_rate' => round($sessionRate, 2),
        'full_sessions' => $fullSessions,
        'half_sessions' => $halfSessions,
        'session_units' => round($sessionUnits, 2),
        'calculated_salary' => $calculatedSalary,
        'counts' => [
            'full_sessions' => $fullSessions,
            'half_sessions' => $halfSessions,
        ],
    ];
}

function employee_salary_breakdown_for_month(array $employee, array $monthAttendance): array
{
    $salary = (float) ($employee['salary'] ?? 0);
    $employeeType = strtolower(trim((string) ($employee['employee_type'] ?? '')));
    $employeeRole = strtolower(trim((string) ($employee['role'] ?? '')));

    if ($employeeType === 'vendor' || $employeeType === 'corporate' || $employeeRole === 'corporate_employee') {
        return vendor_session_breakdown_for_month($salary, $monthAttendance);
    }

    return salary_breakdown_for_month($salary, $monthAttendance);
}

function vendor_session_display_for_entry(array $entry): array
{
    $sessions = is_array($entry['sessions'] ?? null) ? $entry['sessions'] : [];
    $fullSessions = 0;
    $halfSessions = 0;

    foreach ($sessions as $session) {
        if (($session['session_mode'] ?? '') !== 'manual_pair') {
            continue;
        }
        if (!session_has_manual_in($session) || !session_has_manual_out($session)) {
            continue;
        }

        if (($session['day_portion'] ?? 'Full Day') === 'Half Day') {
            $halfSessions++;
        } else {
            $fullSessions++;
        }
    }

    if ($fullSessions > 0) {
        return [
            'status_class' => 'Present',
            'copy' => $fullSessions > 1 ? $fullSessions . ' Sessions' : 'Session',
        ];
    }

    if ($halfSessions > 0) {
        return [
            'status_class' => 'Half-Day',
            'copy' => $halfSessions > 1 ? $halfSessions . ' Half Sessions' : 'Half Session',
        ];
    }

    return [
        'status_class' => '',
        'copy' => '',
    ];
}

function salary_for_month(float $salary, array $monthAttendance): float
{
    return (float) salary_breakdown_for_month($salary, $monthAttendance)['calculated_salary'];
}

function incentive_breakdown_for_month(array $monthAttendance): array
{
    $fullDayCount = 0;
    $halfDayCount = 0;
    $amount = 0.0;
    $firstRecord = is_array(reset($monthAttendance)) ? (reset($monthAttendance)['record'] ?? []) : [];
    $userIdForMonth = (int) ($firstRecord['user_id'] ?? 0);
    $monthForIncentive = substr((string) ($firstRecord['attend_date'] ?? ''), 0, 7);

    foreach ($monthAttendance as $entry) {
        $sessions = is_array($entry['sessions'] ?? null) ? $entry['sessions'] : [];
        $record = is_array($entry['record'] ?? null) ? $entry['record'] : [];
        $userId = (int) ($record['user_id'] ?? 0);
        $attendDate = (string) ($record['attend_date'] ?? '');

        foreach ($sessions as $session) {
            if (($session['session_mode'] ?? '') !== 'manual_pair') {
                continue;
            }
            if (!session_has_manual_in($session) || !session_has_manual_out($session)) {
                continue;
            }

            $projectIncentive = employee_project_incentive_for_date($userId, (int) ($session['project_id'] ?? 0), $attendDate);
            if (($session['day_portion'] ?? 'Full Day') === 'Half Day') {
                $halfDayCount++;
            } else {
                $fullDayCount++;
            }
            $amount += $projectIncentive;
        }
    }

    return [
        'full_day_count' => $fullDayCount,
        'half_day_count' => $halfDayCount,
        'amount' => round($amount, 2),
        'assigned_amount' => assigned_project_incentive_total_for_month($userIdForMonth, $monthForIncentive),
    ];
}

function recent_leave_requests(int $limit = 10): array
{
    $limit = max(1, $limit);
    $adminId = current_admin_id();
    $role = current_manager_target_role();
    $sql = 'SELECT ar.attend_date, ar.leave_reason, ar.updated_at, u.id AS employee_id, u.emp_id, u.name, u.email
            FROM attendance_records ar
            JOIN users u ON u.id = ar.user_id
            WHERE u.role = :role AND ar.status = "Leave"';
    $params = ['role' => $role];

    if ($adminId !== null) {
        $sql .= ' AND u.admin_id = :admin_id';
        $params['admin_id'] = $adminId;
    }

    $sql .= ' ORDER BY ar.attend_date DESC, ar.updated_at DESC LIMIT ' . $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function attendance_snapshot_for_date(?string $date = null): array
{
    $date = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
    $counts = [
        'Present' => 0,
        'Absent' => 0,
        'Half Day' => 0,
        'Leave' => 0,
        'Week Off' => 0,
    ];
    $details = [];

    foreach (employees() as $employee) {
        $record = attendance_record((int) $employee['id'], $date);
        $sessions = $record && !empty($record['id']) ? attendance_sessions((int) $record['id']) : [];
        $status = $record ? resolved_attendance_status($record, $sessions) : default_status_for_date($date);

        if (!array_key_exists($status, $counts)) {
            $counts[$status] = 0;
        }
        $counts[$status]++;

        if (in_array($status, ['Half Day', 'Leave'], true)) {
            $detail = 'No additional detail submitted.';
            if ($status === 'Leave') {
                $detail = trim((string) ($record['leave_reason'] ?? '')) ?: 'No leave reason provided.';
            } elseif ($sessions) {
                $detail = count($sessions) . ' session(s) recorded for the day.';
            } else {
                $detail = 'Half day marked for today.';
            }

            $details[] = [
                'employee' => $employee,
                'status' => $status,
                'detail' => $detail,
            ];
        }
    }

    return [
        'date' => $date,
        'counts' => $counts,
        'details' => $details,
    ];
}

function normalize_attendance_csv_header(string $header): string
{
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($header))) ?? strtolower(trim($header));
}

function attendance_report_date(array $rows): ?string
{
    foreach ($rows as $row) {
        $line = trim(implode(' ', array_map(static fn($cell) => trim((string) $cell), $row)));
        if ($line !== '') {
            if (preg_match('/([0-9]{2}[\/\-][0-9]{2}[\/\-][0-9]{4})/', $line, $matches)) {
                $date = DateTimeImmutable::createFromFormat('d/m/Y', str_replace('-', '/', $matches[1]))
                    ?: DateTimeImmutable::createFromFormat('d-m-Y', $matches[1]);
                if ($date instanceof DateTimeImmutable) {
                    return $date->format('Y-m-d');
                }
            }

            if (preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})/', $line, $matches)) {
                return $matches[1];
            }
        }

        foreach ($row as $cell) {
            $value = trim((string) $cell);
            if ($value === '') {
                continue;
            }

            if (preg_match('/([0-9]{2}[\/\-][0-9]{2}[\/\-][0-9]{4})/', $value, $matches)) {
                $date = DateTimeImmutable::createFromFormat('d/m/Y', str_replace('-', '/', $matches[1]))
                    ?: DateTimeImmutable::createFromFormat('d-m-Y', $matches[1]);
                if ($date instanceof DateTimeImmutable) {
                    return $date->format('Y-m-d');
                }
            }

            if (preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})/', $value, $matches)) {
                return $matches[1];
            }
        }
    }

    return null;
}

function attendance_report_cell_date(string $value, ?string $fallbackDate = null): ?string
{
    $value = trim($value);
    if ($value === '') {
        return $fallbackDate;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        return $value;
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+\d{1,2}:\d{2}(?::\d{2})?$/', $value, $matches) === 1) {
        return $matches[1];
    }

    if ($fallbackDate !== null && preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{3})$/', $value, $matches) === 1) {
        $fallbackYear = (int) substr($fallbackDate, 0, 4);
        return sprintf('%04d-%02d-%02d', $fallbackYear, (int) $matches[2], (int) $matches[1]);
    }

    if ($fallbackDate !== null && preg_match('/^(\d{2})[\/\-](\d{2})$/', $value, $matches) === 1) {
        $fallbackYear = (int) substr($fallbackDate, 0, 4);
        return sprintf('%04d-%02d-%02d', $fallbackYear, (int) $matches[2], (int) $matches[1]);
    }

    foreach (['d/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y', 'd/m/y', 'd-m-y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d');
        }
    }

    if (is_numeric($value)) {
        $numeric = (float) $value;
        // Treat plain numeric values as Excel dates only within a realistic serial range.
        if ($numeric >= 20000 && $numeric <= 80000) {
            $excelEpoch = new DateTimeImmutable('1899-12-30');
            return $excelEpoch->modify('+' . (int) floor($numeric) . ' days')->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return $fallbackDate;
}

function attendance_report_time(?string $date, string $value): ?string
{
    $value = trim($value);
    if ($date === null || $value === '' || $value === '--:--' || $value === '--') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}\s+(\d{1,2}:\d{2}(?::\d{2})?)$/', $value, $matches) === 1) {
        $value = $matches[1];
    }

    if (preg_match('/^(\d{1,2})[:.](\d{1,2})(?:[:.](\d{1,2}))?\s*(AM|PM)?$/i', $value, $matches) === 1) {
        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $second = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;
        $meridiem = strtoupper((string) ($matches[4] ?? ''));
        $validHour = $meridiem !== '' ? ($hour >= 1 && $hour <= 12) : ($hour >= 0 && $hour <= 23);
        if ($validHour && $minute >= 0 && $minute <= 59 && $second >= 0 && $second <= 59) {
            if ($meridiem !== '') {
                if ($meridiem === 'PM' && $hour < 12) {
                    $hour += 12;
                } elseif ($meridiem === 'AM' && $hour === 12) {
                    $hour = 0;
                }
            }

            return sprintf('%s %02d:%02d:%02d', $date, $hour, $minute, $second);
        }
    }

    if (is_numeric($value)) {
        $numeric = (float) $value;
        if ($numeric > 0) {
            $fraction = $numeric >= 1 ? $numeric - floor($numeric) : $numeric;
            $seconds = (int) round($fraction * 86400);
            if ($seconds > 0 && $seconds < 86400) {
                $hours = intdiv($seconds, 3600);
                $minutes = intdiv($seconds % 3600, 60);
                $remainingSeconds = $seconds % 60;
                return sprintf('%s %02d:%02d:%02d', $date, $hours, $minutes, $remainingSeconds);
            }
        }
    }

    foreach (['H:i', 'H:i:s', 'g:i A', 'g:iA', 'h:i A', 'h:iA'] as $format) {
        $time = DateTimeImmutable::createFromFormat('Y-m-d ' . $format, $date . ' ' . $value);
        if ($time instanceof DateTimeImmutable) {
            return $time->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function attendance_import_status(string $rawStatus, ?string $inTime, ?string $outTime): string
{
    if ($inTime !== null && $outTime !== null) {
        return 'Present';
    }

    if ($inTime !== null || $outTime !== null) {
        return 'Half Day';
    }

    $status = strtoupper(trim($rawStatus));
    if (in_array($status, ['L', 'LEAVE'], true)) {
        return 'Leave';
    }

    if (in_array($status, ['W', 'WO', 'WEEKOFF', 'WEEK OFF'], true)) {
        return 'Week Off';
    }

    if (in_array($status, ['P/2', 'P2', '1/2P', 'PRESENT/2'], true)) {
        return 'Half Day';
    }

    return match ($status) {
        'P', 'PR', 'PRESENT' => 'Present',
        'H', 'HD', 'HALF', 'HALFDAY', 'HALF DAY' => 'Half Day',
        default => 'Absent',
    };
}

function attendance_import_status_for_employee(array $employee, string $date, string $rawStatus, ?string $inTime, ?string $outTime): string
{
    $status = attendance_import_status($rawStatus, $inTime, $outTime);
    if ($status === 'Absent' && $inTime === null && $outTime === null && date('w', strtotime($date)) === '0') {
        return 'Week Off';
    }

    $shiftWindow = shift_window_for_employee_on_date($employee, $date);
    if ($shiftWindow !== null && $inTime !== null && $outTime !== null) {
        $shiftStart = strtotime($date . ' ' . $shiftWindow['start_time']);
        $shiftEnd = strtotime($date . ' ' . $shiftWindow['end_time']);
        $actualIn = strtotime($inTime);
        $actualOut = strtotime($outTime);
        if ($shiftStart !== false && $shiftEnd !== false && $actualIn !== false && $actualOut !== false) {
            if ($shiftEnd < $shiftStart) {
                $shiftEnd += 86400;
            }
            if ($actualOut < $actualIn) {
                $actualOut += 86400;
            }

            return ($actualIn > $shiftStart || $actualOut < $shiftEnd) ? 'Half Day' : 'Present';
        }
    }

    return $status;
}

function detect_attendance_csv_delimiter(array $rawLines): string
{
    $candidates = [',', ';', "`t", '|'];
    $scores = array_fill_keys($candidates, 0);

    foreach (array_slice($rawLines, 0, 10) as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            continue;
        }

        foreach ($candidates as $delimiter) {
            $scores[$delimiter] += substr_count($trimmed, $delimiter);
        }
    }

    arsort($scores);
    $best = array_key_first($scores);
    return is_string($best) && $scores[$best] > 0 ? $best : ',';
}

function attendance_file_signature(string $path, int $bytes = 512): string
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return '';
    }

    $signature = fread($handle, $bytes);
    fclose($handle);
    return is_string($signature) ? $signature : '';
}

function attendance_is_xlsx_file(string $path, string $originalName = ''): bool
{
    $extension = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));
    if ($extension === 'xlsx') {
        return true;
    }

    return str_starts_with(attendance_file_signature($path, 4), "PK\x03\x04");
}

function attendance_is_html_table_file(string $path, string $originalName = ''): bool
{
    $extension = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));
    $signature = strtolower(attendance_file_signature($path, 512));

    if (in_array($extension, ['html', 'htm'], true)) {
        return true;
    }

    if ($extension === 'xls' && (str_contains($signature, '<table') || str_contains($signature, '<html') || str_contains($signature, '<tr'))) {
        return true;
    }

    return str_contains($signature, '<table') || str_contains($signature, '<html');
}

function attendance_is_binary_xls_file(string $path, string $originalName = ''): bool
{
    $extension = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));
    if ($extension !== 'xls') {
        return false;
    }

    return attendance_file_signature($path, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
}

function assert_attendance_import_runtime_ready(string $path, string $originalName): void
{
    $extension = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));

    if (($extension === 'xlsx' || attendance_is_xlsx_file($path, $originalName)) && !class_exists(ZipArchive::class)) {
        throw new RuntimeException('Attendance .xlsx import requires the PHP zip extension. Enable extension=zip on the live server and restart Apache/PHP.');
    }

    if (attendance_is_html_table_file($path, $originalName) && !class_exists(DOMDocument::class)) {
        throw new RuntimeException('Attendance Excel/HTML import requires the PHP dom extension. Enable extension=dom on the live server and restart Apache/PHP.');
    }

    if (attendance_is_binary_xls_file($path, $originalName) && !class_exists(\Shuchkin\SimpleXLS::class)) {
        throw new RuntimeException('Attendance .xls import requires Composer dependencies. Run composer install, or php composer.phar install, in the vtraco folder on the live server.');
    }
}

function attendance_excel_column_index(string $reference): int
{
    $letters = strtoupper((string) preg_replace('/[^A-Z]/i', '', $reference));
    if ($letters === '') {
        return 0;
    }

    $index = 0;
    foreach (str_split($letters) as $letter) {
        $index = ($index * 26) + (ord($letter) - 64);
    }

    return max(0, $index - 1);
}

function attendance_xlsx_shared_strings(ZipArchive $zip): array
{
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if (!is_string($sharedXml) || $sharedXml === '') {
        return [];
    }

    $xml = simplexml_load_string($sharedXml);
    if (!$xml instanceof SimpleXMLElement) {
        return [];
    }

    $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $strings = [];
    foreach ($xml->xpath('/x:sst/x:si') ?: [] as $stringNode) {
        $parts = [];
        foreach ($stringNode->xpath('.//*[local-name()=\'t\']') ?: [] as $textNode) {
            $parts[] = (string) $textNode;
        }
        $strings[] = implode('', $parts);
    }

    return $strings;
}

function attendance_xlsx_first_sheet_path(ZipArchive $zip): ?string
{
    if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
        return 'xl/worksheets/sheet1.xml';
    }

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if (!is_string($workbookXml) || !is_string($relsXml)) {
        return null;
    }

    $workbook = simplexml_load_string($workbookXml);
    $rels = simplexml_load_string($relsXml);
    if (!$workbook instanceof SimpleXMLElement || !$rels instanceof SimpleXMLElement) {
        return null;
    }

    $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $rels->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $sheetNodes = $workbook->xpath('/x:workbook/x:sheets/x:sheet');
    if (!$sheetNodes) {
        return null;
    }

    $relationAttributes = $sheetNodes[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships', true);
    $relationId = trim((string) ($relationAttributes['id'] ?? ''));
    if ($relationId === '') {
        return null;
    }

    $relationNodes = $rels->xpath('/rel:Relationships/rel:Relationship[@Id="' . $relationId . '"]');
    if (!$relationNodes) {
        return null;
    }

    $target = ltrim((string) ($relationNodes[0]['Target'] ?? ''), '/');
    if ($target === '') {
        return null;
    }

    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
}

function attendance_extract_xlsx_rows(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('The ZipArchive PHP extension is missing on this server. Please enable it in your hosting panel (e.g., Hostinger PHP extensions) to import .xlsx files.');
    }

    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException('The SimpleXML PHP extension is missing on this server. Please enable it to import .xlsx files.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open the attendance Excel file. It might be corrupt or in an unsupported format.');
    }

    try {
        $sheetPath = attendance_xlsx_first_sheet_path($zip);
        if ($sheetPath === null) {
            throw new RuntimeException('Could not read the first worksheet from the attendance Excel file.');
        }

        $sheetXml = $zip->getFromName($sheetPath);
        if (!is_string($sheetXml) || $sheetXml === '') {
            throw new RuntimeException('Could not read attendance rows from the Excel worksheet.');
        }

        $sharedStrings = attendance_xlsx_shared_strings($zip);
        $sheet = simplexml_load_string($sheetXml);
        if (!$sheet instanceof SimpleXMLElement) {
            throw new RuntimeException('Could not parse the attendance Excel worksheet.');
        }

        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($sheet->xpath('/x:worksheet/x:sheetData/x:row') ?: [] as $rowNode) {
            $row = [];
            $fallbackIndex = 0;
            foreach ($rowNode->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main') as $cellNode) {
                if ($cellNode->getName() !== 'c') {
                    continue;
                }

                $attributes = $cellNode->attributes();
                $reference = (string) ($attributes['r'] ?? '');
                $columnIndex = $reference !== '' ? attendance_excel_column_index($reference) : $fallbackIndex;
                $type = (string) ($attributes['t'] ?? '');
                $value = '';

                if ($type === 'inlineStr') {
                    $parts = [];
                    foreach ($cellNode->xpath('.//*[local-name()=\'t\']') ?: [] as $textNode) {
                        $parts[] = (string) $textNode;
                    }
                    $value = implode('', $parts);
                } else {
                    $rawValue = trim((string) ($cellNode->v ?? ''));
                    $value = $type === 's' ? (string) ($sharedStrings[(int) $rawValue] ?? $rawValue) : $rawValue;
                }

                $row[$columnIndex] = $value;
                $fallbackIndex = $columnIndex + 1;
            }

            if ($row === []) {
                $rows[] = [];
                continue;
            }

            ksort($row);
            $filled = array_fill(0, max(array_keys($row)) + 1, '');
            foreach ($row as $index => $value) {
                $filled[$index] = $value;
            }
            $rows[] = $filled;
        }

        return $rows;
    } finally {
        $zip->close();
    }
}

function attendance_extract_html_rows(string $path): array
{
    $html = file_get_contents($path);
    if ($html === false || trim($html) === '') {
        throw new RuntimeException('Unable to read the attendance file.');
    }

    $rows = [];
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($loaded) {
        foreach ($dom->getElementsByTagName('tr') as $tr) {
            $row = [];
            foreach ($tr->childNodes as $cell) {
                if (!in_array($cell->nodeName, ['td', 'th'], true)) {
                    continue;
                }
                $row[] = trim(preg_replace('/\s+/', ' ', (string) $cell->textContent) ?? '');
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
    }

    return $rows;
}

function attendance_extract_csv_rows(string $path): array
{
    $rawLines = file($path, FILE_IGNORE_NEW_LINES);
    if ($rawLines === false) {
        throw new RuntimeException('Unable to read the attendance file.');
    }

    $delimiter = detect_attendance_csv_delimiter($rawLines);
    $rows = [];
    foreach ($rawLines as $line) {
        $rows[] = str_getcsv((string) $line, $delimiter);
    }

    return $rows;
}

function attendance_extract_binary_xls_rows(string $path): array
{
    if (!class_exists(\Shuchkin\SimpleXLS::class)) {
        throw new RuntimeException('Binary .xls support is not available right now.');
    }

    $xls = \Shuchkin\SimpleXLS::parseFile($path);
    if (!$xls) {
        $message = \Shuchkin\SimpleXLS::parseError();
        throw new RuntimeException($message !== '' ? $message : 'Unable to read the .xls workbook.');
    }

    return array_map(static function (array $row): array {
        return array_map(static function ($cell): string {
            if (is_array($cell)) {
                if (($cell['t'] ?? '') === 'd' && isset($cell['raw']) && is_numeric($cell['raw'])) {
                    return gmdate('Y-m-d H:i:s', (int) round((float) $cell['raw']));
                }

                return (string) ($cell['value'] ?? '');
            }

            return (string) $cell;
        }, $row);
    }, $xls->rowsEx());
}

function attendance_binary_xls_full_emp_codes(string $path): array
{
    $contents = @file_get_contents($path);
    if ($contents === false || $contents === '') {
        return [];
    }

    $text = preg_replace('/[^\x20-\x7E]+/', ' ', $contents) ?? '';
    $blocks = preg_split('/Empcode/i', $text) ?: [];
    $codes = [];
    foreach ($blocks as $block) {
        if (preg_match('/\b[A-Z][0-9]{3,}\b/i', $block, $matches) === 1) {
            $codes[] = strtoupper($matches[0]);
        }
    }

    return $codes;
}

function attendance_replace_value_after_label(array &$row, string $label, string $value): void
{
    $target = normalize_attendance_csv_header($label);
    $count = count($row);
    for ($index = 0; $index < $count; $index++) {
        if (normalize_attendance_csv_header((string) ($row[$index] ?? '')) !== $target) {
            continue;
        }

        for ($next = $index + 1; $next < min($count, $index + 4); $next++) {
            if (trim((string) ($row[$next] ?? '')) !== '') {
                $row[$next] = $value;
                return;
            }
        }

        $row[min($count, $index + 2)] = $value;
        return;
    }
}

function attendance_restore_binary_xls_emp_codes(array $rows, string $path, string $originalName): array
{
    if (!attendance_is_binary_xls_file($path, $originalName)) {
        return $rows;
    }

    $codes = attendance_binary_xls_full_emp_codes($path);
    if ($codes === []) {
        return $rows;
    }

    $codeIndex = 0;
    foreach ($rows as &$row) {
        if (normalize_attendance_csv_header((string) ($row[0] ?? '')) !== 'empcod') {
            continue;
        }
        if (!isset($codes[$codeIndex])) {
            break;
        }
        attendance_replace_value_after_label($row, 'Empcod', $codes[$codeIndex]);
        $codeIndex++;
    }
    unset($row);

    return $rows;
}

function attendance_report_rows(string $path, string $originalName = ''): array
{
    if (attendance_is_xlsx_file($path, $originalName)) {
        $rows = attendance_extract_xlsx_rows($path);
    } elseif (attendance_is_binary_xls_file($path, $originalName)) {
        $rows = attendance_extract_binary_xls_rows($path);
    } elseif (attendance_is_html_table_file($path, $originalName)) {
        $rows = attendance_extract_html_rows($path);
    } else {
        $rows = attendance_extract_csv_rows($path);
    }

    $rows = array_map(static function (array $row): array {
        return array_map(static function ($cell): string {
            $text = (string) $cell;
            $text = str_replace("\0", '', $text);
            return trim($text);
        }, $row);
    }, $rows);

    return attendance_restore_binary_xls_emp_codes($rows, $path, $originalName);
}

function attendance_header_key(string $header): ?string
{
    return match ($header) {
        'date', 'attendancedate', 'reportdate', 'workdate', 'day', 'attendanceon' => 'date',
        'empcode', 'empid', 'empno', 'employeecode', 'employeeid', 'employeeno', 'employeenumber', 'staffid', 'staffcode', 'staffno', 'code', 'id' => 'empcode',
        'name', 'employeename', 'employee', 'staffname', 'fullname', 'username', 'personname' => 'name',
        'status', 'statu', 'attendance', 'attendancestatus', 'daystatus', 'presentstatus', 'attstatus' => 'status',
        'intime', 'intim', 'in', 'intime1', 'inpunch', 'punchin', 'checkin', 'firstin', 'logintime' => 'intime',
        'outtime', 'outtim', 'out', 'outtime1', 'outpunch', 'punchout', 'checkout', 'firstout', 'logouttime' => 'outtime',
        'remark', 'remar', 'remarks', 'comment', 'comments', 'reason', 'notes' => 'remark',
        default => null,
    };
}

function attendance_first_non_empty_column_value(array $row, array $columns): string
{
    foreach ($columns as $columnIndex) {
        $value = trim((string) ($row[$columnIndex] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function attendance_first_time_from_columns(string $date, array $row, array $columns): ?string
{
    $times = [];
    foreach ($columns as $columnIndex) {
        $time = attendance_report_time($date, trim((string) ($row[$columnIndex] ?? '')));
        if ($time !== null) {
            $times[] = $time;
        }
    }

    if ($times === []) {
        return null;
    }

    sort($times);
    return $times[0];
}

function attendance_last_time_from_columns(string $date, array $row, array $columns): ?string
{
    $times = [];
    foreach ($columns as $columnIndex) {
        $time = attendance_report_time($date, trim((string) ($row[$columnIndex] ?? '')));
        if ($time !== null) {
            $times[] = $time;
        }
    }

    if ($times === []) {
        return null;
    }

    sort($times);
    return $times[count($times) - 1];
}

function attendance_month_year_from_title(array $rows): ?array
{
    $title = strtoupper(trim((string) ($rows[0][0] ?? '')));
    if ($title === '') {
        return null;
    }

    if (preg_match('/([A-Z]+)\s+(\d{4})/', $title, $matches) !== 1) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!F Y', ucfirst(strtolower($matches[1])) . ' ' . $matches[2]);
    if (!$date instanceof DateTimeImmutable) {
        return null;
    }

    return [
        'month' => (int) $date->format('m'),
        'year' => (int) $date->format('Y'),
    ];
}

function attendance_month_performance_month_year(array $rows, ?string $fallbackDate = null): ?array
{
    foreach ($rows as $row) {
        $line = trim(implode(' ', array_map(static fn($cell) => trim((string) $cell), $row)));
        if ($line === '') {
            continue;
        }

        if (preg_match('/([A-Za-z]+)\s*-\s*(\d{3,4})/', $line, $matches) !== 1) {
            continue;
        }

        $monthName = ucfirst(strtolower($matches[1]));
        $yearDigits = $matches[2];
        $fallbackYear = $fallbackDate !== null ? (int) substr($fallbackDate, 0, 4) : (int) date('Y');
        $year = strlen($yearDigits) === 4 ? (int) $yearDigits : $fallbackYear;
        $date = DateTimeImmutable::createFromFormat('!F Y', $monthName . ' ' . $year);
        if ($date instanceof DateTimeImmutable) {
            return [
                'month' => (int) $date->format('m'),
                'year' => (int) $date->format('Y'),
            ];
        }
    }

    return null;
}

function attendance_block_value_after_label(array $row, string $label): string
{
    $normalizedLabels = array_map('normalize_attendance_csv_header', array_map('trim', explode('|', $label)));
    $count = count($row);
    for ($index = 0; $index < $count; $index++) {
        if (!in_array(normalize_attendance_csv_header((string) ($row[$index] ?? '')), $normalizedLabels, true)) {
            continue;
        }

        for ($next = $index + 1; $next < min($count, $index + 4); $next++) {
            $value = trim((string) ($row[$next] ?? ''));
            if ($value === '') {
                continue;
            }

            $normalizedValue = normalize_attendance_csv_header($value);
            if (in_array($normalizedValue, ['nam', 'name', 'empcod', 'empcode', 'deptnam', 'deptname', 'designation', 'category', 'status', 'department'], true)) {
                return '';
            }

            return $value;
        }
    }

    return '';
}

function attendance_extract_month_performance_entries(array $rows, ?string $fallbackDate = null): array
{
    $monthYear = attendance_month_performance_month_year($rows, $fallbackDate);
    if ($monthYear === null) {
        return [];
    }

    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int) $monthYear['month'], (int) $monthYear['year']);
    $entries = [];
    $totalRows = count($rows);

    for ($index = 0; $index + 9 < $totalRows; $index++) {
        $metaRow = $rows[$index] ?? [];
        $employeeRow = $rows[$index + 1] ?? [];
        $inRow = $rows[$index + 4] ?? [];
        $outRow = $rows[$index + 5] ?? [];
        $statusRow = $rows[$index + 9] ?? [];

        $metaKey = normalize_attendance_csv_header((string) ($metaRow[0] ?? ''));
        $employeeKey = normalize_attendance_csv_header((string) ($employeeRow[0] ?? ''));
        $inKey = normalize_attendance_csv_header((string) ($inRow[0] ?? ''));
        $outKey = normalize_attendance_csv_header((string) ($outRow[0] ?? ''));
        $statusKey = normalize_attendance_csv_header((string) ($statusRow[0] ?? ''));

        if (
            !in_array($metaKey, ['deptnam', 'deptname', 'department'], true)
            || !in_array($employeeKey, ['empcod', 'empcode', 'empid', 'employeeid'], true)
            || !in_array($inKey, ['i', 'in', 'intime', 'punchin'], true)
            || !in_array($outKey, ['o', 'ou', 'out', 'outtime', 'punchout'], true)
            || !in_array($statusKey, ['statu', 'status', 'attendance'], true)
        ) {
            continue;
        }

        $empCode = attendance_block_value_after_label($employeeRow, 'Empcod|Emp Code|Empcode|Emp ID|Employee ID');
        $employeeName = attendance_block_value_after_label($employeeRow, 'Nam|Name|Employee Name|Staff Name');
        if (!attendance_value_looks_like_empcode($empCode)) {
            $empCode = '';
        }
        if (!attendance_value_looks_like_name($employeeName)) {
            $employeeName = '';
        }
        if ($empCode === '' && $employeeName === '') {
            continue;
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', (int) $monthYear['year'], (int) $monthYear['month'], $day);
            $inTime = attendance_report_time($date, trim((string) ($inRow[$day] ?? '')));
            $outTime = attendance_report_time($date, trim((string) ($outRow[$day] ?? '')));
            $statusRaw = trim((string) ($statusRow[$day] ?? ''));

            $entries[] = [
                'emp_code' => $empCode,
                'employee_name' => $employeeName,
                'date' => $date,
                'status' => attendance_import_status($statusRaw, $inTime, $outTime),
                'biometric_in_time' => $inTime,
                'biometric_out_time' => $outTime,
                'leave_reason' => null,
            ];
        }
    }

    return $entries;
}

function attendance_extract_monthly_register_entries(array $rows): array
{
    $title = strtoupper(trim((string) ($rows[0][0] ?? '')));
    $headerRow = $rows[2] ?? [];
    $subHeaderRow = $rows[3] ?? [];
    if (!str_contains($title, 'ATTENDANCE REGISTER')) {
        return [];
    }

    $monthYear = attendance_month_year_from_title($rows);
    if ($monthYear === null) {
        return [];
    }

    $empCodeHeader = normalize_attendance_csv_header((string) ($headerRow[1] ?? ''));
    $employeeNameHeader = normalize_attendance_csv_header((string) ($headerRow[2] ?? ''));
    if ($empCodeHeader !== 'empcode' || $employeeNameHeader !== 'employeename') {
        return [];
    }

    $entries = [];
    $dateColumns = [];
    for ($column = 3; $column < count($headerRow); $column += 3) {
        $label = trim((string) ($headerRow[$column] ?? ''));
        if (preg_match('/^(\d{1,2})\b/', $label, $matches) !== 1) {
            break;
        }

        $day = (int) $matches[1];
        if (!checkdate((int) $monthYear['month'], $day, (int) $monthYear['year'])) {
            continue;
        }

        $dateColumns[] = [
            'date' => sprintf('%04d-%02d-%02d', (int) $monthYear['year'], (int) $monthYear['month'], $day),
            'in' => $column,
            'out' => $column + 1,
            'status' => $column + 2,
        ];
    }

    if ($dateColumns === []) {
        return [];
    }

    $inHeader = normalize_attendance_csv_header((string) ($subHeaderRow[3] ?? ''));
    $outHeader = normalize_attendance_csv_header((string) ($subHeaderRow[4] ?? ''));
    if (!str_contains($inHeader, 'intime') || !str_contains($outHeader, 'offtime')) {
        return [];
    }

    foreach (array_slice($rows, 4) as $row) {
        $empCode = trim((string) ($row[1] ?? ''));
        $employeeName = trim((string) ($row[2] ?? ''));
        if ($empCode === '' && $employeeName === '') {
            continue;
        }

        foreach ($dateColumns as $mapping) {
            $inTime = attendance_report_time($mapping['date'], trim((string) ($row[$mapping['in']] ?? '')));
            $outTime = attendance_report_time($mapping['date'], trim((string) ($row[$mapping['out']] ?? '')));
            $statusRaw = trim((string) ($row[$mapping['status']] ?? ''));

            $entries[] = [
                'emp_code' => $empCode,
                'employee_name' => $employeeName,
                'date' => $mapping['date'],
                'status' => attendance_import_status($statusRaw, $inTime, $outTime),
                'biometric_in_time' => $inTime,
                'biometric_out_time' => $outTime,
                'leave_reason' => null,
            ];
        }
    }

    return $entries;
}

function attendance_extract_periodic_report_entries(array $rows, ?string $fallbackDate = null): array
{
    $entries = [];
    $totalRows = count($rows);

    for ($i = 0; $i < $totalRows; $i++) {
        $row = $rows[$i];
        $firstCell = strtolower(trim((string) ($row[0] ?? '')));
        if ($firstCell !== 'empcod' && $firstCell !== 'empcode' && $firstCell !== 'empid' && $firstCell !== 'id') {
            continue;
        }

        $empCode = trim((string) ($row[1] ?? ''));
        $employeeName = trim((string) ($row[4] ?? ''));
        if ($empCode === '' && $employeeName === '') {
            continue;
        }

        $headerRow = $rows[$i + 1] ?? [];
        $headerFirstCell = strtolower(trim((string) ($headerRow[0] ?? '')));
        if ($headerFirstCell !== 'date') {
            continue;
        }

        $candidateMap = [];
        foreach ($headerRow as $columnIndex => $cell) {
            $headerKey = attendance_header_key(normalize_attendance_csv_header((string) $cell));
            if ($headerKey === null || isset($candidateMap[$headerKey])) {
                continue;
            }
            $candidateMap[$headerKey] = $columnIndex;
        }

        for ($j = $i + 2; $j < $totalRows; $j++) {
            $dataRow = $rows[$j];
            $dataFirstCell = strtolower(trim((string) ($dataRow[0] ?? '')));
            if (in_array($dataFirstCell, ['empcod', 'empcode', 'empid', 'id'], true)) {
                break;
            }

            $dataRowText = strtolower(trim(implode(' ', array_map(static fn($cell) => trim((string) $cell), $dataRow))));
            if ($dataRowText === '' || attendance_summary_row_text($dataRowText) || attendance_metadata_row_text($dataRowText)) {
                continue;
            }

            $rowDate = isset($candidateMap['date'])
                ? attendance_report_cell_date((string) ($dataRow[$candidateMap['date']] ?? ''), $fallbackDate)
                : $fallbackDate;
            if ($rowDate === null) {
                continue;
            }

            $statusRaw = isset($candidateMap['status']) ? trim((string) ($dataRow[$candidateMap['status']] ?? '')) : '';
            $remark = isset($candidateMap['remark']) ? trim((string) ($dataRow[$candidateMap['remark']] ?? '')) : '';
            $inTime = isset($candidateMap['intime']) ? attendance_report_time($rowDate, trim((string) ($dataRow[$candidateMap['intime']] ?? ''))) : null;
            $outTime = isset($candidateMap['outtime']) ? attendance_report_time($rowDate, trim((string) ($dataRow[$candidateMap['outtime']] ?? ''))) : null;
            $status = attendance_import_status($statusRaw, $inTime, $outTime);

            $entries[] = [
                'emp_code' => $empCode,
                'employee_name' => $employeeName,
                'date' => $rowDate,
                'status' => $status,
                'biometric_in_time' => $inTime,
                'biometric_out_time' => $outTime,
                'leave_reason' => ($remark !== '' && $remark !== '--') ? $remark : null,
            ];
        }
    }

    return $entries;
}

function attendance_summary_row_text(string $rowText): bool
{
    return $rowText === ''
        || str_contains($rowText, 'total for whole company')
        || str_contains($rowText, 'total present')
        || str_contains($rowText, 'total absent')
        || str_contains($rowText, 'total late in')
        || str_contains($rowText, 'total half day');
}

function attendance_value_looks_like_status(string $value): bool
{
    $normalized = strtoupper(trim($value));
    if ($normalized === '') {
        return false;
    }

    return in_array($normalized, ['P', 'PR', 'PRESENT', 'A', 'ABSENT', 'H', 'HD', 'HALF', 'HALF DAY', 'HALFDAY', 'L', 'LEAVE', 'WO', 'WEEKOFF', 'WEEK OFF'], true);
}

function attendance_value_looks_like_time(string $value): bool
{
    $trimmed = trim($value);
    if ($trimmed === '' || $trimmed === '--:--' || $trimmed === '--') {
        return true;
    }

    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $trimmed)) {
        return true;
    }

    if (preg_match('/^\d{1,2}:\d{2}\s?(AM|PM)$/i', $trimmed)) {
        return true;
    }

    return is_numeric($trimmed) && (float) $trimmed >= 0 && (float) $trimmed < 1;
}

function attendance_value_looks_like_date(string $value): bool
{
    return attendance_report_cell_date($value, null) !== null;
}

function attendance_metadata_row_text(string $rowText): bool
{
    $normalized = normalize_attendance_csv_header($rowText);

    return $rowText === ''
        || str_contains($rowText, 'daily performance report')
        || str_contains($rowText, 'dept name')
        || str_contains($rowText, 'dept. name')
        || str_contains($normalized, 'deptname')
        || str_contains($rowText, 'date :-')
        || preg_match('/^date\s*[:\-]/', $rowText) === 1;
}

function attendance_value_looks_like_empcode(string $value): bool
{
    $trimmed = trim($value);
    if ($trimmed === '' || attendance_value_looks_like_status($trimmed) || attendance_value_looks_like_time($trimmed) || attendance_value_looks_like_date($trimmed)) {
        return false;
    }

    return preg_match('/^(?=.*\d)[A-Za-z0-9._\/\-]{2,}$/', $trimmed) === 1;
}

function attendance_value_looks_like_name(string $value): bool
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return false;
    }

    if (attendance_value_looks_like_status($trimmed) || attendance_value_looks_like_time($trimmed) || attendance_value_looks_like_date($trimmed)) {
        return false;
    }

    if (attendance_metadata_row_text(strtolower($trimmed))) {
        return false;
    }

    if (!preg_match('/[A-Za-z]/', $trimmed)) {
        return false;
    }

    return preg_match('/^[A-Za-z][A-Za-z .\'-]{1,}$/', $trimmed) === 1;
}

function attendance_guess_header_map(array $rows): array
{
    $limit = count($rows);
    for ($start = 0; $start < $limit; $start++) {
        $sampleRows = [];
        for ($i = $start; $i < $limit && count($sampleRows) < 5; $i++) {
            $row = $rows[$i];
            $rowText = strtolower(trim(implode(' ', array_map(static fn($cell) => trim((string) $cell), $row))));
            if (attendance_summary_row_text($rowText) || attendance_metadata_row_text($rowText)) {
                if ($sampleRows !== []) {
                    break;
                }
                continue;
            }

            $sampleRows[] = $row;
        }

        if ($sampleRows === []) {
            continue;
        }

        $statusScores = [];
        $timeScores = [];
        $dateScores = [];
        $nameScores = [];
        $empCodeScores = [];

        foreach ($sampleRows as $row) {
            foreach ($row as $columnIndex => $cell) {
                $value = trim((string) $cell);
                if ($value === '') {
                    continue;
                }

                if (attendance_value_looks_like_status($value)) {
                    $statusScores[$columnIndex] = ($statusScores[$columnIndex] ?? 0) + 1;
                }
                if (attendance_value_looks_like_time($value)) {
                    $timeScores[$columnIndex] = ($timeScores[$columnIndex] ?? 0) + 1;
                }
                if (attendance_value_looks_like_date($value)) {
                    $dateScores[$columnIndex] = ($dateScores[$columnIndex] ?? 0) + 1;
                }
                if (attendance_value_looks_like_name($value)) {
                    $nameScores[$columnIndex] = ($nameScores[$columnIndex] ?? 0) + 1;
                }
                if (attendance_value_looks_like_empcode($value)) {
                    $empCodeScores[$columnIndex] = ($empCodeScores[$columnIndex] ?? 0) + 1;
                }
            }
        }

        if ($statusScores === [] && $timeScores === []) {
            continue;
        }

        arsort($statusScores);
        arsort($timeScores);
        arsort($dateScores);
        arsort($nameScores);
        arsort($empCodeScores);

        $statusColumn = array_key_first($statusScores);
        $dateColumn = array_key_first($dateScores);
        $timeColumns = array_keys($timeScores);
        sort($timeColumns);
        if ($statusColumn !== null) {
            $timeColumns = array_values(array_filter($timeColumns, static fn($column) => $column < $statusColumn));
        }
        $inTimeColumn = $timeColumns[0] ?? null;
        if (count($timeColumns) >= 4) {
            $outTimeColumn = $timeColumns[3];
        } else {
            $outTimeColumn = count($timeColumns) > 1 ? $timeColumns[count($timeColumns) - 1] : $inTimeColumn;
        }
        $nameColumn = array_key_first($nameScores);
        $empCodeColumn = array_key_first($empCodeScores);

        if ($empCodeColumn === null && $nameColumn === null) {
            continue;
        }

        return [
            'header_index' => $start - 1,
            'header_map' => array_filter([
                'date' => $dateColumn,
                'empcode' => $empCodeColumn,
                'name' => $nameColumn,
                'status' => $statusColumn,
                'intime' => $inTimeColumn,
                'outtime' => $outTimeColumn,
            ], static fn($value) => $value !== null),
        ];
    }

    return [];
}
function parse_attendance_report_csv(string $path, ?string $overrideDate = null, string $originalName = ''): array
{
    $rows = attendance_report_rows($path, $originalName);
    if (!$rows) {
        throw new RuntimeException('Attendance file is empty.');
    }

    $detectedDate = attendance_report_date($rows);
    $reportDate = $detectedDate ?: (($overrideDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $overrideDate)) ? $overrideDate : null);

    $monthlyEntries = attendance_extract_monthly_register_entries($rows);
    if ($monthlyEntries !== []) {
        $entryDates = array_values(array_unique(array_map(
            static fn(array $entry): string => (string) ($entry['date'] ?? ''),
            array_filter($monthlyEntries, static fn(array $entry): bool => !empty($entry['date']))
        )));
        sort($entryDates);

        return [
            'date' => $entryDates[0] ?? $reportDate,
            'dates' => $entryDates,
            'entries' => $monthlyEntries,
        ];
    }

    $monthPerformanceEntries = attendance_extract_month_performance_entries($rows, $reportDate);
    if ($monthPerformanceEntries !== []) {
        $entryDates = array_values(array_unique(array_map(
            static fn(array $entry): string => (string) ($entry['date'] ?? ''),
            array_filter($monthPerformanceEntries, static fn(array $entry): bool => !empty($entry['date']))
        )));
        sort($entryDates);

        return [
            'date' => $entryDates[0] ?? $reportDate,
            'dates' => $entryDates,
            'entries' => $monthPerformanceEntries,
        ];
    }

    $periodicEntries = attendance_extract_periodic_report_entries($rows, $reportDate);
    if ($periodicEntries !== []) {
        $entryDates = array_values(array_unique(array_map(
            static fn(array $entry): string => (string) ($entry['date'] ?? ''),
            array_filter($periodicEntries, static fn(array $entry): bool => !empty($entry['date']))
        )));
        sort($entryDates);

        return [
            'date' => $reportDate,
            'dates' => $entryDates,
            'entries' => $periodicEntries,
        ];
    }

    $headerIndex = null;
    $headerMap = [];
    foreach ($rows as $index => $row) {
        $candidateMap = [];
        foreach ($row as $columnIndex => $cell) {
            $normalized = normalize_attendance_csv_header((string) $cell);
            $headerKey = attendance_header_key($normalized);
            if ($headerKey === 'empcode') {
                $candidateMap['empcode_columns'] = $candidateMap['empcode_columns'] ?? [];
                $candidateMap['empcode_columns'][] = $columnIndex;
                $candidateMap['empcode'] = $candidateMap['empcode'] ?? $columnIndex;
                continue;
            }
            if ($headerKey !== null && !isset($candidateMap[$headerKey])) {
                $candidateMap[$headerKey] = $columnIndex;
            }
        }

        if ((isset($candidateMap['empcode']) || isset($candidateMap['name'])) && (isset($candidateMap['status']) || isset($candidateMap['intime']) || isset($candidateMap['outtime']))) {
            $headerIndex = $index;
            $headerMap = $candidateMap;
            break;
        }
    }

    if ($headerIndex === null) {
        $guessed = attendance_guess_header_map($rows);
        if ($guessed !== []) {
            $headerIndex = (int) ($guessed['header_index'] ?? -1);
            $headerMap = (array) ($guessed['header_map'] ?? []);
        }
    }

    if ($headerIndex === null) {
        throw new RuntimeException('Could not find the employee header row in the attendance report. Use columns like Emp ID or Empcode for the employee field, and Attendance or Status for the attendance field.');
    }

    if (!isset($headerMap['empcode']) && !isset($headerMap['name'])) {
        throw new RuntimeException('Attendance report is missing the employee column.');
    }
    if ($reportDate === null && !isset($headerMap['date'])) {
        throw new RuntimeException('Could not detect the report date in the attendance file.');
    }

    $entries = [];
    for ($i = max(0, $headerIndex + 1); $i < count($rows); $i++) {
        $row = $rows[$i];
        $rowText = strtolower(trim(implode(' ', array_map(static fn($cell) => trim((string) $cell), $row))));
        if ($rowText === '') {
            continue;
        }

        if (attendance_summary_row_text($rowText)) {
            break;
        }

        $empCode = isset($headerMap['empcode_columns'])
            ? attendance_first_non_empty_column_value($row, (array) $headerMap['empcode_columns'])
            : (isset($headerMap['empcode']) ? trim((string) ($row[$headerMap['empcode']] ?? '')) : '');
        $employeeName = isset($headerMap['name']) ? trim((string) ($row[$headerMap['name']] ?? '')) : '';
        if (attendance_metadata_row_text($rowText) || ($empCode === '' && $employeeName === '') || str_starts_with(strtolower($empCode), 'total')) {
            continue;
        }

        $rowDate = isset($headerMap['date'])
            ? attendance_report_cell_date((string) ($row[$headerMap['date']] ?? ''), $reportDate)
            : $reportDate;
        if ($rowDate === null) {
            continue;
        }

        $statusRaw = isset($headerMap['status']) ? trim((string) ($row[$headerMap['status']] ?? '')) : '';
        $remark = isset($headerMap['remark']) ? trim((string) ($row[$headerMap['remark']] ?? '')) : '';
        $inTime = isset($headerMap['intime']) ? attendance_report_time($rowDate, trim((string) ($row[$headerMap['intime']] ?? ''))) : null;
        $outTime = isset($headerMap['outtime']) ? attendance_report_time($rowDate, trim((string) ($row[$headerMap['outtime']] ?? ''))) : null;
        $status = attendance_import_status($statusRaw, $inTime, $outTime);

        $entries[] = [
            'emp_code' => $empCode,
            'employee_name' => $employeeName,
            'date' => $rowDate,
            'status' => $status,
            'biometric_in_time' => $inTime,
            'biometric_out_time' => $outTime,
            'leave_reason' => ($remark !== '' && $remark !== '--') ? $remark : null,
        ];
    }

    if (!$entries) {
        throw new RuntimeException('Attendance report does not contain any usable employee rows.');
    }

    $entryDates = array_values(array_unique(array_map(
        static fn(array $entry): string => (string) ($entry['date'] ?? ''),
        array_filter($entries, static fn(array $entry): bool => !empty($entry['date']))
    )));
    sort($entryDates);

    return [
        'date' => $reportDate,
        'dates' => $entryDates,
        'entries' => $entries,
    ];
}

function import_attendance_report_csv(string $path, ?string $overrideDate = null, string $originalName = ''): array
{
    $report = parse_attendance_report_csv($path, $overrideDate, $originalName);
    $imported = 0;
    $skipped = 0;
    $unmatched = [];
    $ambiguous = [];

    foreach ($report['entries'] as $entry) {
        $empCode = trim((string) ($entry['emp_code'] ?? ''));
        $employeeName = trim((string) ($entry['employee_name'] ?? ''));
        $employee = $employeeName !== ''
            ? employee_by_attendance_identity($empCode, $employeeName)
            : ($empCode !== '' ? employee_by_emp_code($empCode) : null);

        if (!$employee || empty($entry['date'])) {
            $skipped++;
            $label = $empCode !== '' ? $empCode : ($employeeName !== '' ? $employeeName : 'Unknown Employee');
            if ($empCode !== '' && employee_emp_code_match_issue($empCode) === 'duplicate_numeric') {
                $ambiguous[] = $label;
            } else {
                $unmatched[] = $label;
            }
            continue;
        }

        $shiftWindow = shift_window_for_employee_on_date($employee, (string) $entry['date']);
        $resolvedStatus = attendance_import_status_for_employee(
            $employee,
            (string) $entry['date'],
            (string) ($entry['status'] ?? ''),
            $entry['biometric_in_time'] ?? null,
            $entry['biometric_out_time'] ?? null
        );

        $existingRecord = attendance_record((int) $employee['id'], (string) $entry['date']);
        $hasAdminOverride = trim((string) ($existingRecord['admin_override_status'] ?? '')) !== '';
        $recordFields = [
            'biometric_in_time' => $entry['biometric_in_time'],
            'biometric_out_time' => $entry['biometric_out_time'],
            'shift_start_time' => $shiftWindow['start_time'] ?? null,
            'shift_end_time' => $shiftWindow['end_time'] ?? null,
        ];

        if (!$hasAdminOverride) {
            $recordFields['status'] = $resolvedStatus;
            $recordFields['admin_override_status'] = null;
            $recordFields['leave_reason'] = in_array($resolvedStatus, ['Leave', 'Half Day'], true) ? $entry['leave_reason'] : null;
        }

        update_attendance_record((int) $employee['id'], (string) $entry['date'], $recordFields);
        $imported++;
    }

    return [
        'date' => $report['date'],
        'dates' => $report['dates'] ?? [],
        'imported' => $imported,
        'skipped' => $skipped,
        'unmatched' => array_values(array_unique($unmatched)),
        'ambiguous' => array_values(array_unique($ambiguous)),
    ];
}

function validate_attendance_report_upload(array $file): void
{
    validate_uploaded_file($file, ['xlsx', 'xls', 'csv', 'txt'], 20 * 1024 * 1024, 'attendance file');

    $extension = uploaded_file_extension($file);
    $path = (string) ($file['tmp_name'] ?? '');
    $originalName = (string) ($file['name'] ?? '');

    assert_attendance_import_runtime_ready($path, $originalName);

    if ($extension === 'xlsx' && !attendance_is_xlsx_file($path, $originalName)) {
        throw new RuntimeException('The uploaded attendance file does not look like a valid .xlsx workbook.');
    }

    if ($extension === 'xls' && !attendance_is_html_table_file($path, $originalName) && !attendance_is_binary_xls_file($path, $originalName)) {
        throw new RuntimeException('The uploaded attendance .xls file could not be recognized as a supported Excel workbook.');
    }
}

function validate_punch_photo_upload(array $file): void
{
    validate_uploaded_file($file, ['jpg', 'jpeg', 'png', 'webp'], 4 * 1024 * 1024, 'punch photo');

    $mime = uploaded_file_mime_type($file);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Punch photo must be a JPG, PNG, or WebP image.');
    }

    if (@getimagesize((string) ($file['tmp_name'] ?? '')) === false) {
        throw new RuntimeException('Punch photo upload is not a valid image.');
    }
}

function handle_upload(array $file): string
{
    validate_punch_photo_upload($file);
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0777, true);
    }
    $ext = uploaded_file_extension($file) ?: 'jpg';
    $target = UPLOAD_PATH . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('Unable to save punch photo.');
    }
    $appRoot = str_replace('\\', '/', dirname(__DIR__, 2));
    $normalizedTarget = str_replace('\\', '/', $target);
    if (strpos($normalizedTarget, $appRoot) === 0) {
        $normalizedTarget = substr($normalizedTarget, strlen($appRoot));
    }

    return normalize_relative_path($normalizedTarget);
}




















