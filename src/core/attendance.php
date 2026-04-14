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
    db()->prepare('INSERT INTO attendance_sessions (attendance_id, session_mode, slot_name, punch_in_path, punch_in_lat, punch_in_lng, punch_in_time, punch_out_time, college_name, session_name, day_portion, session_duration, location, created_at) VALUES (:attendance_id, :session_mode, :slot_name, :punch_in_path, :punch_in_lat, :punch_in_lng, :punch_in_time, :punch_out_time, :college_name, :session_name, :day_portion, :session_duration, :location, :created_at)')
        ->execute([
            'attendance_id' => $attendanceId,
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
        || (float) ($session['session_duration'] ?? 0) > 0;
}

function manual_attendance_status(array $record, array $sessions): ?string
{
    $hasIncompleteManual = false;
    $hasCompletedManual = false;
    $hasFullDayManual = false;

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
            if (($session['day_portion'] ?? 'Full Day') !== 'Half Day') {
                $hasFullDayManual = true;
            }
        }
    }

    if (!$sessions && trim((string) ($record['punch_in_path'] ?? '')) !== '') {
        $hasIncompleteManual = true;
    }

    if ($hasIncompleteManual) {
        return attendance_date_is_closed((string) ($record['attend_date'] ?? '')) ? 'Absent' : 'Pending';
    }

    if ($hasCompletedManual) {
        return $hasFullDayManual ? 'Present' : 'Half Day';
    }

    return null;
}

function resolved_attendance_status(array $record, array $sessions): string
{
    $status = trim((string) ($record['status'] ?? ''));
    if ($status === '') {
        return default_status_for_date((string) ($record['attend_date'] ?? date('Y-m-d')));
    }

    if (in_array($status, ['Leave', 'Week Off'], true)) {
        return $status;
    }

    $manualStatus = manual_attendance_status($record, $sessions);
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
        ];
        $sessions = $record['id'] ? attendance_sessions((int) $record['id']) : [];
        $record['status'] = resolved_attendance_status($record, $sessions);
        $out[$key] = ['record' => $record, 'sessions' => $sessions];
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

function salary_for_month(float $salary, array $monthAttendance): float
{
    $daysInMonth = max(1, count($monthAttendance));
    return (working_days_total($monthAttendance) * $salary) / $daysInMonth;
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

function attendance_report_time(?string $date, string $value): ?string
{
    $value = trim($value);
    if ($date === null || $value === '' || $value === '--:--' || $value === '--') {
        return null;
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
    $status = strtoupper(trim($rawStatus));
    return match ($status) {
        'P', 'PR', 'PRESENT' => 'Present',
        'A', 'ABSENT' => 'Absent',
        'H', 'HD', 'HALF', 'HALFDAY', 'HALF DAY' => 'Half Day',
        'L', 'LEAVE' => 'Leave',
        'WO', 'WEEKOFF', 'WEEK OFF' => 'Week Off',
        default => ($inTime !== null || $outTime !== null ? 'Present' : 'Absent'),
    };
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
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open the attendance Excel file.');
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

function attendance_report_rows(string $path, string $originalName = ''): array
{
    if (attendance_is_xlsx_file($path, $originalName)) {
        return attendance_extract_xlsx_rows($path);
    }

    if (attendance_is_html_table_file($path, $originalName)) {
        return attendance_extract_html_rows($path);
    }

    return attendance_extract_csv_rows($path);
}

function attendance_header_key(string $header): ?string
{
    return match ($header) {
        'empcode', 'empid', 'employeecode', 'employeeid', 'staffid', 'staffcode' => 'empcode',
        'name', 'employeename', 'staffname', 'fullname', 'username' => 'name',
        'status', 'attendancestatus' => 'status',
        'intime', 'inpunch', 'punchin', 'checkin' => 'intime',
        'outtime', 'outpunch', 'punchout', 'checkout' => 'outtime',
        'remark', 'remarks' => 'remark',
        default => null,
    };
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

function attendance_metadata_row_text(string $rowText): bool
{
    return $rowText === ''
        || str_contains($rowText, 'daily performance report')
        || str_contains($rowText, 'dept name')
        || str_contains($rowText, 'date :-')
        || preg_match('/^date\s*[:\-]/', $rowText) === 1;
}

function attendance_value_looks_like_empcode(string $value): bool
{
    $trimmed = trim($value);
    if ($trimmed === '' || attendance_value_looks_like_status($trimmed) || attendance_value_looks_like_time($trimmed)) {
        return false;
    }

    return preg_match('/^(?=.*\d)[A-Za-z0-9\-]{3,}$/', $trimmed) === 1;
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
                if (preg_match('/[A-Za-z]/', $value) && str_contains($value, ' ') && !attendance_value_looks_like_status($value) && !attendance_metadata_row_text(strtolower($value))) {
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
        arsort($nameScores);
        arsort($empCodeScores);

        $statusColumn = array_key_first($statusScores);
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
    if ($reportDate === null) {
        throw new RuntimeException('Could not detect the report date in the attendance file.');
    }

    $headerIndex = null;
    $headerMap = [];
    foreach ($rows as $index => $row) {
        $candidateMap = [];
        foreach ($row as $columnIndex => $cell) {
            $normalized = normalize_attendance_csv_header((string) $cell);
            $headerKey = attendance_header_key($normalized);
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
        throw new RuntimeException('Could not find the Empcode/Status header row in the attendance report.');
    }

    if (!isset($headerMap['empcode']) && !isset($headerMap['name'])) {
        throw new RuntimeException('Attendance report is missing the employee column.');
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

        $empCode = isset($headerMap['empcode']) ? trim((string) ($row[$headerMap['empcode']] ?? '')) : '';
        $employeeName = isset($headerMap['name']) ? trim((string) ($row[$headerMap['name']] ?? '')) : '';
        if (attendance_metadata_row_text($rowText) || ($empCode === '' && $employeeName === '') || str_starts_with(strtolower($empCode), 'total')) {
            continue;
        }

        $statusRaw = isset($headerMap['status']) ? trim((string) ($row[$headerMap['status']] ?? '')) : '';
        $remark = isset($headerMap['remark']) ? trim((string) ($row[$headerMap['remark']] ?? '')) : '';
        $inTime = isset($headerMap['intime']) ? attendance_report_time($reportDate, trim((string) ($row[$headerMap['intime']] ?? ''))) : null;
        $outTime = isset($headerMap['outtime']) ? attendance_report_time($reportDate, trim((string) ($row[$headerMap['outtime']] ?? ''))) : null;
        $status = attendance_import_status($statusRaw, $inTime, $outTime);

        $entries[] = [
            'emp_code' => $empCode,
            'employee_name' => $employeeName,
            'date' => $reportDate,
            'status' => $status,
            'biometric_in_time' => $inTime,
            'biometric_out_time' => $outTime,
            'leave_reason' => ($remark !== '' && $remark !== '--') ? $remark : null,
        ];
    }

    if (!$entries) {
        throw new RuntimeException('Attendance report does not contain any usable employee rows.');
    }

    return [
        'date' => $reportDate,
        'entries' => $entries,
    ];
}

function import_attendance_report_csv(string $path, ?string $overrideDate = null, string $originalName = ''): array
{
    $report = parse_attendance_report_csv($path, $overrideDate, $originalName);
    $imported = 0;
    $created = 0;
    $skipped = 0;
    $unmatched = [];

    foreach ($report['entries'] as $entry) {
        $employee = null;
        $empCode = trim((string) ($entry['emp_code'] ?? ''));
        $employeeName = trim((string) ($entry['employee_name'] ?? ''));

        if ($empCode !== '') {
            $employee = employee_by_emp_code($empCode);
            if (!$employee) {
                try {
                    $employee = create_employee_from_attendance_entry($entry);
                    if ($employee) {
                        $created++;
                    }
                } catch (Throwable) {
                    $employee = null;
                }
            }
        }

        if (!$employee && $empCode === '' && $employeeName !== '') {
            $employee = employee_by_name($employeeName);
            if (!$employee) {
                try {
                    $employee = create_employee_from_attendance_entry($entry);
                    if ($employee) {
                        $created++;
                    }
                } catch (Throwable) {
                    $employee = null;
                }
            }
        }

        if (!$employee || empty($entry['date'])) {
            $skipped++;
            $unmatched[] = $empCode !== '' ? $empCode : ($employeeName !== '' ? $employeeName : 'Unknown Employee');
            continue;
        }

        update_attendance_record((int) $employee['id'], (string) $entry['date'], [
            'status' => $entry['status'],
            'biometric_in_time' => $entry['biometric_in_time'],
            'biometric_out_time' => $entry['biometric_out_time'],
            'leave_reason' => in_array($entry['status'], ['Leave', 'Half Day'], true) ? $entry['leave_reason'] : null,
        ]);
        $imported++;
    }

    return [
        'date' => $report['date'],
        'imported' => $imported,
        'created' => $created,
        'skipped' => $skipped,
        'unmatched' => array_values(array_unique($unmatched)),
    ];
}

function validate_attendance_report_upload(array $file): void
{
    validate_uploaded_file($file, ['xlsx', 'xls', 'csv', 'txt'], 8 * 1024 * 1024, 'attendance file');

    $extension = uploaded_file_extension($file);
    $path = (string) ($file['tmp_name'] ?? '');
    $originalName = (string) ($file['name'] ?? '');

    if ($extension === 'xlsx' && !attendance_is_xlsx_file($path, $originalName)) {
        throw new RuntimeException('The uploaded attendance file does not look like a valid .xlsx workbook.');
    }

    if ($extension === 'xls' && !attendance_is_html_table_file($path, $originalName)) {
        throw new RuntimeException('Legacy .xls imports must be HTML-style table exports. Save binary .xls files as .xlsx or .csv first.');
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
    return str_replace(__DIR__ . '/../', '', $target);
}




















