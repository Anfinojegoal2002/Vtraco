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
    $sql = "SELECT 
                ar.attend_date AS date,
                u.name AS employee_name,
                p.project_name AS project_name,
                s.day_portion AS session_type,
                ar.status AS attendance_status,
                r.incentive_earned AS incentive_earned,
                r.reimbursement_amount AS reimbursement_amount
            FROM attendance_records ar
            JOIN users u ON ar.user_id = u.id
            JOIN attendance_sessions s ON s.attendance_id = ar.id
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN reimbursements r ON r.attendance_session_id = s.id
            WHERE 1=1";

    $params = [];

    if (!empty($filters['employee_ids'])) {
        $placeholders = implode(',', array_fill(0, count($filters['employee_ids']), '?'));
        $sql .= " AND u.id IN ($placeholders)";
        foreach ($filters['employee_ids'] as $id) {
            $params[] = (int) $id;
        }
    }

    if (!empty($filters['project_ids'])) {
        $placeholders = implode(',', array_fill(0, count($filters['project_ids']), '?'));
        $sql .= " AND p.id IN ($placeholders)";
        foreach ($filters['project_ids'] as $id) {
            $params[] = (int) $id;
        }
    }

    if (!empty($filters['from_date'])) {
        $sql .= " AND ar.attend_date >= ?";
        $params[] = $filters['from_date'];
    }

    if (!empty($filters['to_date'])) {
        $sql .= " AND ar.attend_date <= ?";
        $params[] = $filters['to_date'];
    }

    $sql .= " ORDER BY ar.attend_date DESC, u.name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Exports report data to CSV.
 */
function export_report_csv(array $data): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_report_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Employee Name', 'Project Name', 'Session Type', 'Attendance Status', 'Incentive Earned', 'Reimbursement Amount']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['date'],
            $row['employee_name'],
            $row['project_name'] ?: 'N/A',
            $row['session_type'] ?: 'N/A',
            $row['attendance_status'],
            number_format((float) ($row['incentive_earned'] ?? 0), 2),
            number_format((float) ($row['reimbursement_amount'] ?? 0), 2)
        ]);
    }
    fclose($output);
    exit;
}

/**
 * Exports report data to PDF using Dompdf.
 */
function export_report_pdf(array $data): void
{
    require_once __DIR__ . '/../../vendor/autoload.php';
    $dompdf = new \Dompdf\Dompdf();

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            table { width: 100%; border-collapse: collapse; font-family: sans-serif; font-size: 12px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2295b; color: white; }
            h1 { font-family: sans-serif; color: #333; }
        </style>
    </head>
    <body>
        <h1>Attendance Report</h1>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee Name</th>
                    <th>Project Name</th>
                    <th>Session Type</th>
                    <th>Status</th>
                    <th>Incentive</th>
                    <th>Reimbursement</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?= h((string)$row['date']) ?></td>
                        <td><?= h((string)$row['employee_name']) ?></td>
                        <td><?= h((string)($row['project_name'] ?: 'N/A')) ?></td>
                        <td><?= h((string)($row['session_type'] ?: 'N/A')) ?></td>
                        <td><?= h((string)$row['attendance_status']) ?></td>
                        <td><?= number_format((float)($row['incentive_earned'] ?? 0), 2) ?></td>
                        <td><?= number_format((float)($row['reimbursement_amount'] ?? 0), 2) ?></td>
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
