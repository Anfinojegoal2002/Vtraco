<?php

declare(strict_types=1);

/**
 * Fetches attendance report data based on filters.
 *
 * @param array $filters Filters: employee_ids (array), project_ids (array), from_date (string), to_date (string)
 * @return array
 */
function get_attendance_report_data(array $filters): array
{
    $db = db();
    $adminId = project_scope_admin_id();
    $where = ['1=1'];
    $params = [];
    if ($adminId !== null) {
        $where[] = 'u.admin_id = ?';
        $params[] = $adminId;
    }

    if (!empty($filters['employee_ids'])) {
        $placeholders = implode(',', array_fill(0, count($filters['employee_ids']), '?'));
        $where[] = "u.id IN ($placeholders)";
        foreach ($filters['employee_ids'] as $id) {
            $params[] = (int) $id;
        }
    }

    if (!empty($filters['from_date'])) {
        $where[] = 'ar.attend_date >= ?';
        $params[] = $filters['from_date'];
    }

    if (!empty($filters['to_date'])) {
        $where[] = 'ar.attend_date <= ?';
        $params[] = $filters['to_date'];
    }

    $baseWhere = implode(' AND ', $where);
    $projectJoinFilter = '';
    $manualParams = $params;
    if (!empty($filters['project_ids'])) {
        $placeholders = implode(',', array_fill(0, count($filters['project_ids']), '?'));
        $projectJoinFilter = " AND p.id IN ($placeholders)";
        foreach ($filters['project_ids'] as $id) {
            $manualParams[] = (int) $id;
        }
    }

    $sql = "SELECT * FROM (
            SELECT
                ar.attend_date AS date,
                u.name AS employee_name,
                p.project_name AS project_name,
                COALESCE(NULLIF(s.day_portion, ''), p.session_type, 'FULL_DAY') AS session_type,
                CASE
                    WHEN ar.status IN ('Present', 'Half Day', 'Leave') THEN ar.status
                    WHEN ar.status = 'Pending' AND COALESCE(NULLIF(s.day_portion, ''), p.session_type, 'FULL_DAY') = 'FULL_DAY' THEN 'Present'
                    WHEN ar.status = 'Pending' THEN 'Half Day'
                    ELSE ar.status
                END AS attendance_status,
                CASE
                    WHEN s.id IS NOT NULL THEN 'Project Manual'
                    WHEN ar.punch_in_time IS NOT NULL THEN 'Manual'
                    ELSE 'Attendance'
                END AS attendance_source,
                s.slot_name AS slot_name,
                COALESCE(s.punch_in_time, ar.punch_in_time) AS manual_punch_in,
                COALESCE(NULLIF(s.punch_in_path, ''), NULLIF(ar.punch_in_path, '')) AS manual_punch_in_photo,
                COALESCE(s.punch_in_photo, ar.punch_in_photo) AS manual_punch_in_photo_data,
                COALESCE(NULLIF(s.punch_in_photo_mime, ''), NULLIF(ar.punch_in_photo_mime, '')) AS manual_punch_in_photo_mime,
                COALESCE(NULLIF(s.punch_in_photo_name, ''), NULLIF(ar.punch_in_photo_name, '')) AS manual_punch_in_photo_name,
                s.punch_out_time AS manual_punch_out,
                NULL AS biometric_punch_in,
                NULL AS biometric_punch_out,
                s.total_students AS total_students,
                s.present_students AS present_students,
                s.topics_handled AS topics_handled,
                CASE WHEN s.id IS NULL THEN 1 ELSE 0 END AS sort_group
            FROM attendance_records ar
            JOIN users u ON ar.user_id = u.id
            LEFT JOIN attendance_sessions s ON s.attendance_id = ar.id
            LEFT JOIN projects p ON s.project_id = p.id
            WHERE {$baseWhere}
            {$projectJoinFilter}
              AND (
                  s.id IS NOT NULL
                  OR (ar.punch_in_time IS NOT NULL AND ar.biometric_in_time IS NULL AND ar.biometric_out_time IS NULL)
                  OR (ar.biometric_in_time IS NULL AND ar.biometric_out_time IS NULL)
              )
            UNION ALL
            SELECT
                ar.attend_date AS date,
                u.name AS employee_name,
                NULL AS project_name,
                'BIOMETRIC' AS session_type,
                CASE
                    WHEN ar.status IN ('Present', 'Half Day', 'Leave') THEN ar.status
                    WHEN ar.biometric_in_time IS NOT NULL AND ar.biometric_out_time IS NOT NULL THEN 'Present'
                    WHEN ar.biometric_in_time IS NOT NULL OR ar.biometric_out_time IS NOT NULL THEN 'Half Day'
                    ELSE ar.status
                END AS attendance_status,
                'Biometric' AS attendance_source,
                'Biometric Attendance' AS slot_name,
                NULL AS manual_punch_in,
                NULL AS manual_punch_in_photo,
                NULL AS manual_punch_in_photo_data,
                NULL AS manual_punch_in_photo_mime,
                NULL AS manual_punch_in_photo_name,
                NULL AS manual_punch_out,
                ar.biometric_in_time AS biometric_punch_in,
                ar.biometric_out_time AS biometric_punch_out,
                NULL AS total_students,
                NULL AS present_students,
                NULL AS topics_handled,
                0 AS sort_group
            FROM attendance_records ar
            JOIN users u ON ar.user_id = u.id
            WHERE {$baseWhere}
              AND (ar.biometric_in_time IS NOT NULL OR ar.biometric_out_time IS NOT NULL)
            ) report_rows
            ORDER BY date DESC, employee_name ASC, sort_group ASC, project_name ASC, slot_name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($manualParams, $params));

    return $stmt->fetchAll();
}

function report_photo_url(?string $path): string
{
    return public_file_path((string) ($path ?? ''));
}

function report_photo_file_path(?string $path): string
{
    $relativePath = normalize_relative_path((string) ($path ?? ''));
    if ($relativePath === '' || preg_match('#^https?://#i', $relativePath)) {
        return '';
    }

    $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return is_file($fullPath) ? $fullPath : '';
}

function report_photo_data_uri(?string $path): string
{
    $fullPath = report_photo_file_path($path);
    if ($fullPath === '') {
        return '';
    }

    $mime = mime_content_type($fullPath) ?: '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        return '';
    }

    $contents = file_get_contents($fullPath);
    if ($contents === false) {
        return '';
    }

    return 'data:' . $mime . ';base64,' . base64_encode($contents);
}

function report_photo_data_uri_from_row(array $row): string
{
    $contents = $row['manual_punch_in_photo_data'] ?? null;
    $mime = (string) ($row['manual_punch_in_photo_mime'] ?? '');
    if ($contents !== null && $contents !== '' && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'data:' . $mime . ';base64,' . base64_encode((string) $contents);
    }

    return report_photo_data_uri($row['manual_punch_in_photo'] ?? '');
}

function report_photo_label_from_row(array $row): string
{
    $url = report_photo_url($row['manual_punch_in_photo'] ?? '');
    if ($url !== '') {
        return $url;
    }

    if (!empty($row['manual_punch_in_photo_data'])) {
        $name = trim((string) ($row['manual_punch_in_photo_name'] ?? ''));
        return $name !== '' ? 'Stored in database: ' . $name : 'Stored in database';
    }

    return 'N/A';
}

/**
 * Exports report data to CSV.
 */
function export_report_csv(array $data, array $filters = []): void
{
    $month = report_calendar_month_from_filters($filters);
    $employees = $month !== '' ? report_calendar_employees_from_filters($filters) : [];
    if ($month !== '' && $employees !== []) {
        export_report_calendar_csv($month, $employees);
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_report_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Employee Name', 'Source', 'Project Name', 'Slot', 'Session Type', 'Attendance Status', 'Manual Punch In', 'Manual Punch In Photo', 'Manual Punch Out', 'Biometric Punch In', 'Biometric Punch Out', 'Total Students', 'Present Students', 'Topics Handled']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['date'],
            $row['employee_name'],
            $row['attendance_source'] ?: 'Attendance',
            $row['project_name'] ?: 'N/A',
            $row['slot_name'] ?: 'N/A',
            $row['session_type'] ?: 'N/A',
            $row['attendance_status'],
            $row['manual_punch_in'] ?: 'N/A',
            report_photo_url($row['manual_punch_in_photo'] ?? '') ?: 'N/A',
            $row['manual_punch_out'] ?: 'N/A',
            $row['biometric_punch_in'] ?: 'N/A',
            $row['biometric_punch_out'] ?: 'N/A',
            $row['total_students'] ?: 'N/A',
            $row['present_students'] ?: 'N/A',
            $row['topics_handled'] ?: 'N/A',
        ]);
    }
    fclose($output);
    exit;
}

function export_report_calendar_csv(string $month, array $employees): void
{
    [$start] = month_bounds($month);
    $monthLabel = $start->format('F Y');
    $monthShort = strtoupper($start->format('M'));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_calendar_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    foreach ($employees as $employee) {
        $monthAttendance = month_attendance_for_user((int) ($employee['id'] ?? 0), $month);
        $counts = attendance_counts($monthAttendance);
        $offset = (int) $start->format('w');
        $cells = [];
        for ($i = 0; $i < $offset; $i++) {
            $cells[] = null;
        }
        foreach ($monthAttendance as $date => $entry) {
            $cells[] = ['date' => $date, 'entry' => $entry];
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        fputcsv($output, ['Track Attendance Calendar']);
        fputcsv($output, [
            'Employee',
            (string) ($employee['name'] ?? 'Employee'),
            'Emp ID',
            (string) (($employee['emp_id'] ?? '') ?: '-'),
            'Month',
            $monthLabel,
        ]);
        fputcsv($output, ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']);

        foreach (array_chunk($cells, 7) as $week) {
            $row = [];
            foreach ($week as $cell) {
                if ($cell === null) {
                    $row[] = '';
                    continue;
                }

                $entry = $cell['entry'];
                $record = is_array($entry['record'] ?? null) ? $entry['record'] : [];
                $status = (string) ($record['status'] ?? '');
                if (!empty($record['sandwich_week_off_absent'])) {
                    $status = 'Absent';
                }
                if ($status === 'Pending') {
                    $status = 'Half Day';
                }
                $times = attendance_resolved_work_times($record, is_array($entry['sessions'] ?? null) ? $entry['sessions'] : []);
                $row[] = $monthShort . '-' . (int) date('j', strtotime((string) $cell['date']))
                    . "\nStatus: " . ($status !== '' ? $status : '-')
                    . "\nLog in: " . report_calendar_time($times['in_time'] ?? null)
                    . "\nLogout: " . report_calendar_time($times['out_time'] ?? null)
                    . "\nProject: " . report_calendar_project_label($entry);
            }
            fputcsv($output, $row);
        }

        fputcsv($output, []);
        fputcsv($output, [
            'Present',
            (string) ($counts['present'] ?? 0),
            'Half Day',
            (string) ($counts['half_day'] ?? 0),
            'Absent',
            (string) ($counts['absent'] ?? 0),
            'Working Days',
            (string) ($counts['working_days'] ?? 0),
        ]);
        fputcsv($output, []);
        fputcsv($output, []);
    }

    fclose($output);
    exit;
}

/**
 * Exports report data to PDF using Dompdf.
 */
function report_calendar_month_from_filters(array $filters): string
{
    $fromDate = (string) ($filters['from_date'] ?? '');
    $toDate = (string) ($filters['to_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        return '';
    }
    if (substr($fromDate, 0, 7) !== substr($toDate, 0, 7)) {
        return '';
    }

    return substr($fromDate, 0, 7);
}

function report_calendar_employees_from_filters(array $filters): array
{
    if (!empty($filters['project_ids'])) {
        return [];
    }

    $employeeIds = array_values(array_unique(array_filter(array_map('intval', (array) ($filters['employee_ids'] ?? [])))));
    if ($employeeIds === []) {
        return employees();
    }

    $selected = [];
    foreach (employees() as $employee) {
        if (in_array((int) ($employee['id'] ?? 0), $employeeIds, true)) {
            $selected[] = $employee;
        }
    }

    return $selected;
}

function report_calendar_time(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('h:i A', $timestamp) : $value;
}

function report_calendar_status_class(string $status): string
{
    $normalized = strtolower(str_replace(' ', '-', trim($status)));
    return in_array($normalized, ['present', 'half-day', 'absent', 'week-off', 'leave'], true) ? $normalized : 'blank';
}

function report_calendar_project_label(array $entry): string
{
    $sessions = is_array($entry['sessions'] ?? null) ? $entry['sessions'] : [];
    if ($sessions === []) {
        return '-';
    }

    $labels = [];
    foreach ($sessions as $session) {
        $label = trim((string) (($session['college_name'] ?? '') ?: ($session['session_name'] ?? '') ?: ($session['location'] ?? '')));
        if ($label === '' && !empty($session['project_id'])) {
            $label = 'Project #' . (int) $session['project_id'];
        }
        if ($label !== '') {
            $labels[$label] = $label;
        }
    }

    return $labels ? implode(', ', array_slice(array_values($labels), 0, 2)) : '-';
}

function report_calendar_pdf_html(array $filters): string
{
    $month = report_calendar_month_from_filters($filters);
    $employees = $month !== '' ? report_calendar_employees_from_filters($filters) : [];
    if ($month === '' || $employees === []) {
        return '';
    }

    [$start] = month_bounds($month);
    $monthLabel = $start->format('F Y');
    $monthShort = strtoupper($start->format('M'));

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            @page { margin: 18px; }
            body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; font-size: 10px; }
            .employee-sheet { page-break-after: always; }
            .employee-sheet:last-child { page-break-after: auto; }
            .sheet-head { margin-bottom: 10px; }
            .sheet-head h1 { margin: 0 0 4px; font-size: 18px; color: #172554; }
            .sheet-head p { margin: 0; color: #475569; }
            .legend { margin: 8px 0 10px; }
            .legend span { display: inline-block; margin-right: 10px; }
            .swatch { display: inline-block; width: 10px; height: 10px; margin-right: 4px; vertical-align: middle; border: 1px solid #94a3b8; }
            table.calendar { width: 100%; border-collapse: collapse; table-layout: fixed; border: 1px solid #9ca3af; }
            .calendar-title { border: 1px solid #9ca3af; padding: 5px 6px; font-weight: 700; text-align: left; color: #000; background: #fff; }
            .weekday { border: 1px solid #9ca3af; padding: 4px 6px; text-transform: uppercase; font-weight: 700; color: #000; background: #fff; }
            .day { height: 82px; border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; color: #000; overflow: hidden; }
            .day.blank { background: #fff; }
            .day.present { background: #34a853; }
            .day.half-day { background: #fbbc04; }
            .day.absent { background: #ff1f1f; }
            .day.week-off, .day.leave { background: #1111ff; color: #fff; }
            .date { display: block; font-size: 11px; font-weight: 800; margin-bottom: 3px; color: inherit; }
            .line { display: block; font-size: 8.5px; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: inherit; }
            .summary { margin-top: 10px; width: 100%; border-collapse: collapse; }
            .summary td { border: 1px solid #cbd5e1; padding: 6px; }
            .summary strong { display: block; font-size: 12px; color: #172554; }
        </style>
    </head>
    <body>
        <?php foreach ($employees as $employee): ?>
            <?php
                $monthAttendance = month_attendance_for_user((int) ($employee['id'] ?? 0), $month);
                $counts = attendance_counts($monthAttendance);
                $offset = (int) $start->format('w');
                $cells = [];
                for ($i = 0; $i < $offset; $i++) {
                    $cells[] = null;
                }
                foreach ($monthAttendance as $date => $entry) {
                    $cells[] = ['date' => $date, 'entry' => $entry];
                }
                while (count($cells) % 7 !== 0) {
                    $cells[] = null;
                }
            ?>
            <section class="employee-sheet">
                <div class="sheet-head">
                    <h1>Track Attendance Calendar</h1>
                    <p><?= h((string) ($employee['name'] ?? 'Employee')) ?> (<?= h((string) (($employee['emp_id'] ?? '') ?: '-')) ?>) - <?= h($monthLabel) ?></p>
                </div>
                <div class="legend">
                    <span><i class="swatch" style="background:#34a853;"></i>Present</span>
                    <span><i class="swatch" style="background:#ff1f1f;"></i>Absent</span>
                    <span><i class="swatch" style="background:#fbbc04;"></i>Half Day</span>
                    <span><i class="swatch" style="background:#1111ff;"></i>Week Off / Leave</span>
                </div>
                <table class="calendar">
                    <tr><th class="calendar-title" colspan="7">ACTUAL WORK TIME</th></tr>
                    <tr>
                        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                            <th class="weekday"><?= h($weekday) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <?php foreach (array_chunk($cells, 7) as $week): ?>
                        <tr>
                            <?php foreach ($week as $cell): ?>
                                <?php if ($cell === null): ?>
                                    <td class="day blank"></td>
                                <?php else: ?>
                                    <?php
                                        $entry = $cell['entry'];
                                        $record = is_array($entry['record'] ?? null) ? $entry['record'] : [];
                                        $status = (string) ($record['status'] ?? '');
                                        if (!empty($record['sandwich_week_off_absent'])) {
                                            $status = 'Absent';
                                        }
                                        if ($status === 'Pending') {
                                            $status = 'Half Day';
                                        }
                                        $times = attendance_resolved_work_times($record, is_array($entry['sessions'] ?? null) ? $entry['sessions'] : []);
                                    ?>
                                    <td class="day <?= h(report_calendar_status_class($status)) ?>">
                                        <span class="date"><?= h($monthShort . '-' . (int) date('j', strtotime((string) $cell['date']))) ?></span>
                                        <span class="line">log in: <?= h(report_calendar_time($times['in_time'] ?? null)) ?></span>
                                        <span class="line">logout: <?= h(report_calendar_time($times['out_time'] ?? null)) ?></span>
                                        <span class="line">project: <?= h(report_calendar_project_label($entry)) ?></span>
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <table class="summary">
                    <tr>
                        <td><strong><?= h((string) ($counts['present'] ?? 0)) ?></strong>Present</td>
                        <td><strong><?= h((string) ($counts['half_day'] ?? 0)) ?></strong>Half Day</td>
                        <td><strong><?= h((string) ($counts['absent'] ?? 0)) ?></strong>Absent</td>
                        <td><strong><?= h((string) ($counts['working_days'] ?? 0)) ?></strong>Working Days</td>
                    </tr>
                </table>
            </section>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
    return (string) ob_get_clean();
}

function export_report_pdf(array $data, array $filters = []): void
{
    require_once __DIR__ . '/../../vendor/autoload.php';
    $dompdf = new \Dompdf\Dompdf();
    $calendarHtml = report_calendar_pdf_html($filters);
    if ($calendarHtml !== '') {
        $dompdf->loadHtml($calendarHtml);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('attendance_calendar_' . date('Ymd_His') . '.pdf');
        exit;
    }

    $canEmbedPhotos = extension_loaded('gd');

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            table { width: 100%; border-collapse: collapse; font-family: sans-serif; font-size: 10px; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
            th { background-color: #f2295b; color: white; }
            h1 { font-family: sans-serif; color: #333; }
            .punch-photo { width: 58px; height: 58px; object-fit: cover; border: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <h1>Attendance Report</h1>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee Name</th>
                    <th>Source</th>
                    <th>Project Name</th>
                    <th>Slot</th>
                    <th>Session Type</th>
                    <th>Attendance Status</th>
                    <th>Manual Punch In</th>
                    <th>Manual Punch In Photo</th>
                    <th>Manual Punch Out</th>
                    <th>Biometric Punch In</th>
                    <th>Biometric Punch Out</th>
                    <th>Total Students</th>
                    <th>Present Students</th>
                    <th>Topics Handled</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?= h((string)$row['date']) ?></td>
                        <td><?= h((string)$row['employee_name']) ?></td>
                        <td><?= h((string)(($row['attendance_source'] ?? '') ?: 'Attendance')) ?></td>
                        <td><?= h((string)($row['project_name'] ?: 'N/A')) ?></td>
                        <td><?= h((string)($row['slot_name'] ?: 'N/A')) ?></td>
                        <td><?= h((string)($row['session_type'] ?: 'N/A')) ?></td>
                        <td><?= h((string)$row['attendance_status']) ?></td>
                        <td><?= h((string) (($row['manual_punch_in'] ?? '') ?: 'N/A')) ?></td>
                        <td>
                            <?php $photoData = $canEmbedPhotos ? report_photo_data_uri_from_row($row) : ''; ?>
                            <?php if ($photoData !== ''): ?>
                                <img class="punch-photo" src="<?= h($photoData) ?>" alt="Manual punch in photo">
                            <?php else: ?>
                                <?= h(report_photo_label_from_row($row)) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= h((string) (($row['manual_punch_out'] ?? '') ?: 'N/A')) ?></td>
                        <td><?= h((string) (($row['biometric_punch_in'] ?? '') ?: 'N/A')) ?></td>
                        <td><?= h((string) (($row['biometric_punch_out'] ?? '') ?: 'N/A')) ?></td>
                        <td><?= h((string) (($row['total_students'] ?? '') ?: 'N/A')) ?></td>
                        <td><?= h((string) (($row['present_students'] ?? '') ?: 'N/A')) ?></td>
                        <td><?= h((string) (($row['topics_handled'] ?? '') ?: 'N/A')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('attendance_report_' . date('Ymd_His') . '.pdf');
    exit;
}
