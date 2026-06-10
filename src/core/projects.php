<?php

declare(strict_types=1);

function project_session_types(): array
{
    return ['FULL_DAY', 'FIRST_HALF', 'SECOND_HALF'];
}

function project_session_label(string $sessionType): string
{
    return match ($sessionType) {
        'FULL_DAY' => 'Full Day',
        'FIRST_HALF' => 'First Half',
        'SECOND_HALF' => 'Second Half',
        default => ucwords(strtolower(str_replace('_', ' ', $sessionType))),
    };
}

function project_form_defaults(): array
{
    return [
        'id' => 0,
        'project_name' => '',
        'college_name' => '',
        'location' => '',
        'total_days' => 1,
        'session_type' => 'FULL_DAY',
        'is_active' => 1,
    ];
}

function project_scope_admin_id(?array $user = null): ?int
{
    $user = $user ?? current_user();
    if (!$user) {
        return null;
    }

    if (in_array((string) ($user['role'] ?? ''), ['admin', 'freelancer', 'external_vendor'], true)) {
        return (int) $user['id'];
    }

    if (in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true) && !empty($user['admin_id'])) {
        return (int) $user['admin_id'];
    }

    return null;
}

function projects(?int $adminId = null): array
{
    $adminId ??= project_scope_admin_id();
    if ($adminId !== null) {
        $stmt = db()->prepare('SELECT p.*, creator.name AS created_by_name
            FROM projects p
            LEFT JOIN users creator ON creator.id = p.created_by_user_id
            WHERE p.admin_id = :admin_id
            ORDER BY FIELD(p.approval_status, "pending", "verified", "rejected") ASC, p.is_active DESC, p.updated_at DESC, p.project_name ASC, p.id DESC');
        $stmt->execute(['admin_id' => $adminId]);
        return $stmt->fetchAll();
    }

    $stmt = db()->query('SELECT p.*, creator.name AS created_by_name
        FROM projects p
        LEFT JOIN users creator ON creator.id = p.created_by_user_id
        ORDER BY FIELD(p.approval_status, "pending", "verified", "rejected") ASC, p.is_active DESC, p.updated_at DESC, p.project_name ASC, p.id DESC');
    return $stmt->fetchAll();
}

function active_projects(?int $adminId = null): array
{
    $user = current_user();
    if (($user['role'] ?? '') === 'external_vendor' && $adminId === null) {
        return db()->query('SELECT * FROM projects WHERE is_active = 1 AND approval_status = "verified" ORDER BY project_name ASC, id DESC')->fetchAll();
    }

    $adminId ??= project_scope_admin_id();
    if ($adminId !== null) {
        $stmt = db()->prepare('SELECT * FROM projects WHERE admin_id = :admin_id AND is_active = 1 AND approval_status = "verified" ORDER BY project_name ASC, id DESC');
        $stmt->execute(['admin_id' => $adminId]);
        return $stmt->fetchAll();
    }

    $stmt = db()->query('SELECT * FROM projects WHERE is_active = 1 AND approval_status = "verified" ORDER BY project_name ASC, id DESC');
    return $stmt->fetchAll();
}

function project_by_id(int $projectId, ?int $adminId = null): ?array
{
    $adminId ??= project_scope_admin_id();
    if ($adminId !== null) {
        $stmt = db()->prepare('SELECT * FROM projects WHERE id = :id AND admin_id = :admin_id LIMIT 1');
        $stmt->execute([
            'id' => $projectId,
            'admin_id' => $adminId,
        ]);
    } else {
        $stmt = db()->prepare('SELECT * FROM projects WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $projectId]);
    }
    $row = $stmt->fetch();

    return $row ?: null;
}

function normalize_project_assignment_ids(array $projectIds): array
{
    $projectIds = array_values(array_unique(array_filter(
        array_map('intval', $projectIds),
        static fn (int $projectId): bool => $projectId > 0
    )));

    if ($projectIds === []) {
        return [];
    }

    $user = current_user();
    $adminId = project_scope_admin_id();
    $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
    $params = $projectIds;
    $sql = 'SELECT id FROM projects WHERE id IN (' . $placeholders . ') AND is_active = 1 AND approval_status = "verified"';
    if (($user['role'] ?? '') !== 'external_vendor' && $adminId !== null) {
        $sql .= ' AND admin_id = ?';
        $params[] = $adminId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $validLookup = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $projectId) {
        $validLookup[(int) $projectId] = true;
    }

    $normalized = [];
    foreach ($projectIds as $projectId) {
        if (isset($validLookup[$projectId])) {
            $normalized[] = $projectId;
        }
    }

    if (count($normalized) !== count($projectIds)) {
        throw new RuntimeException('Select only valid assigned projects.');
    }

    return $normalized;
}

function project_pay_basis_options(): array
{
    return [
        'hourly' => 'Hourly',
        'daily' => 'Daily',
    ];
}

function normalize_project_pay_basis(mixed $value): string
{
    $basis = strtolower(trim((string) $value));
    return array_key_exists($basis, project_pay_basis_options()) ? $basis : 'daily';
}

function project_assignment_payment_amount(array $project, array $sessionPay): float
{
    $rate = (float) ($project['project_daily_salary'] ?? 0);
    if ($rate <= 0) {
        return 0.0;
    }

    if (normalize_project_pay_basis($project['project_pay_basis'] ?? 'daily') === 'hourly') {
        return round($rate * max(0.0, (float) ($sessionPay['hours'] ?? 0)), 2);
    }

    $dayUnits = (float) ($sessionPay['day_units'] ?? 0);
    return round($rate * ($dayUnits > 0 ? $dayUnits : 1.0), 2);
}

function assigned_projects_for_employee(int $userId): array
{
    $stmt = db()->prepare('SELECT p.*, a.project_from, a.project_to, a.project_incentive, a.project_daily_salary, COALESCE(a.project_pay_basis, "daily") AS project_pay_basis
        FROM employee_project_assignments a
        INNER JOIN projects p ON p.id = a.project_id
        WHERE a.user_id = :user_id
        ORDER BY p.is_active DESC, p.project_name ASC, p.id DESC');
    $params = ['user_id' => $userId];
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function employee_project_workspace_projects(array $employee): array
{
    $assignedProjects = assigned_projects_for_employee((int) ($employee['id'] ?? 0));
    $projectLookup = [];
    foreach ($assignedProjects as $project) {
        $projectLookup[(int) ($project['id'] ?? 0)] = $project;
    }

    if (employee_is_project_coordinator($employee)) {
        $stmt = db()->prepare('SELECT p.*, NULL AS project_from, NULL AS project_to, 0 AS project_incentive, 0 AS project_daily_salary, "daily" AS project_pay_basis
            FROM projects p
            WHERE p.created_by_user_id = :created_by_user_id
            ORDER BY FIELD(p.approval_status, "pending", "verified", "rejected") ASC, p.is_active DESC, p.updated_at DESC, p.project_name ASC, p.id DESC');
        $stmt->execute(['created_by_user_id' => (int) ($employee['id'] ?? 0)]);
        foreach ($stmt->fetchAll() as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId > 0 && !isset($projectLookup[$projectId])) {
                $projectLookup[$projectId] = $project;
            }
        }
    }

    return array_values($projectLookup);
}

function employee_available_projects(array $employee): array
{
    $assignedProjects = assigned_projects_for_employee((int) ($employee['id'] ?? 0));

    return array_values(array_filter(
        $assignedProjects,
        static fn (array $project): bool => !empty($project['is_active'])
    ));
}

function project_is_available_for_date(array $project, string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $from = substr(trim((string) ($project['project_from'] ?? '')), 0, 10);
    $to = substr(trim((string) ($project['project_to'] ?? '')), 0, 10);

    if ($from !== '' && $date < $from) {
        return false;
    }
    if ($to !== '' && $date > $to) {
        return false;
    }

    return true;
}

function employee_available_projects_for_date(array $employee, string $date): array
{
    return array_values(array_filter(
        employee_available_projects($employee),
        static fn (array $project): bool => project_is_available_for_date($project, $date)
    ));
}

function employee_available_project_ids(array $employee): array
{
    return array_values(array_filter(array_map(
        static fn (array $project): int => (int) ($project['id'] ?? 0),
        assigned_projects_for_employee((int) ($employee['id'] ?? 0))
    )));
}

function employee_project_incentive_for_date(int $userId, int $projectId, string $date): float
{
    if ($userId <= 0 || $projectId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return 0.0;
    }

    $stmt = db()->prepare('SELECT project_incentive FROM employee_project_assignments
        WHERE user_id = :user_id
          AND project_id = :project_id
          AND (project_from IS NULL OR project_from <= :attend_date)
          AND (project_to IS NULL OR project_to >= :attend_date)
        LIMIT 1');
    $stmt->execute([
        'user_id' => $userId,
        'project_id' => $projectId,
        'attend_date' => $date,
    ]);

    return round((float) ($stmt->fetchColumn() ?: 0), 2);
}

function employee_project_daily_salary_for_date(int $userId, int $projectId, string $date): float
{
    if ($userId <= 0 || $projectId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return 0.0;
    }

    $stmt = db()->prepare('SELECT project_daily_salary FROM employee_project_assignments
        WHERE user_id = :user_id
          AND project_id = :project_id
          AND (project_from IS NULL OR project_from <= :attend_date)
          AND (project_to IS NULL OR project_to >= :attend_date)
        LIMIT 1');
    $stmt->execute([
        'user_id' => $userId,
        'project_id' => $projectId,
        'attend_date' => $date,
    ]);

    return round((float) ($stmt->fetchColumn() ?: 0), 2);
}

function assigned_project_incentive_total_for_month(int $userId, string $month): float
{
    if ($userId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        return 0.0;
    }

    [$start, $end] = month_bounds($month);
    $startDate = $start->format('Y-m-d');
    $endDate = $end->format('Y-m-d');
    $stmt = db()->prepare('SELECT project_from, project_to, project_incentive
        FROM employee_project_assignments
        WHERE user_id = :user_id
          AND project_incentive > 0
          AND (project_from IS NULL OR project_from <= :end_date)
          AND (project_to IS NULL OR project_to >= :start_date)');
    $stmt->execute([
        'user_id' => $userId,
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    $total = 0.0;
    foreach ($stmt->fetchAll() as $row) {
        $total += (float) ($row['project_incentive'] ?? 0);
    }

    return round($total, 2);
}

function assigned_project_payment_total_for_month(int $userId, string $month): float
{
    if ($userId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        return 0.0;
    }

    $total = 0.0;
    foreach (month_attendance_for_user($userId, $month) as $entry) {
        $record = is_array($entry['record'] ?? null) ? $entry['record'] : [];
        $attendDate = (string) ($record['attend_date'] ?? '');
        $sessions = is_array($entry['sessions'] ?? null) ? $entry['sessions'] : [];

        foreach ($sessions as $session) {
            if (!in_array((string) ($session['session_mode'] ?? ''), ['manual_pair', 'project_record'], true)) {
                continue;
            }
            if (!session_has_manual_in($session) || !session_has_manual_out($session)) {
                continue;
            }

            $project = null;
            foreach (employee_available_projects_for_date(['id' => $userId], $attendDate) as $availableProject) {
                if ((int) ($availableProject['id'] ?? 0) === (int) ($session['project_id'] ?? 0)) {
                    $project = $availableProject;
                    break;
                }
            }
            if (!$project) {
                continue;
            }

            $total += project_assignment_payment_amount($project, [
                'hours' => session_work_hours($session),
                'day_units' => attendance_day_portion_units((string) ($session['day_portion'] ?? 'Full Day')),
            ]);
        }
    }

    return round($total, 2);
}

function normalize_project_assignment_ranges(array $fromValues, array $toValues, array $projectIds, array $incentiveValues = [], array $dailySalaryValues = []): array
{
    $normalizedProjectIds = normalize_project_assignment_ids($projectIds);
    $ranges = [];

    foreach ($normalizedProjectIds as $projectId) {
        $from = trim((string) ($fromValues[$projectId] ?? ''));
        $to = trim((string) ($toValues[$projectId] ?? ''));
        $incentive = max(0.0, round((float) ($incentiveValues[$projectId] ?? 0), 2));
        $dailySalary = max(0.0, round((float) ($dailySalaryValues[$projectId] ?? 0), 2));
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';
        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $ranges[$projectId] = [
            'from' => $from,
            'to' => $to,
            'incentive' => $incentive,
            'daily_salary' => $dailySalary,
        ];
    }

    return $ranges;
}

function save_employee_project_assignments(int $userId, array $projectIds, array $projectRanges = []): array
{
    $normalizedProjectIds = normalize_project_assignment_ids($projectIds);
    $pdo = db();
    $manageTransaction = !$pdo->inTransaction();

    if ($manageTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $pdo->prepare('DELETE FROM employee_project_assignments WHERE user_id = :user_id')
            ->execute(['user_id' => $userId]);

        if ($normalizedProjectIds !== []) {
            $insert = $pdo->prepare('INSERT INTO employee_project_assignments (user_id, project_id, project_from, project_to, project_incentive, project_daily_salary, created_at)
                VALUES (:user_id, :project_id, :project_from, :project_to, :project_incentive, :project_daily_salary, :created_at)');

            foreach ($normalizedProjectIds as $projectId) {
                $range = $projectRanges[$projectId] ?? ['from' => '', 'to' => ''];
                $insert->execute([
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'project_from' => ($range['from'] ?? '') !== '' ? $range['from'] : null,
                    'project_to' => ($range['to'] ?? '') !== '' ? $range['to'] : null,
                    'project_incentive' => max(0.0, round((float) ($range['incentive'] ?? 0), 2)),
                    'project_daily_salary' => max(0.0, round((float) ($range['daily_salary'] ?? 0), 2)),
                    'created_at' => now(),
                ]);
            }
        }

        $pdo->prepare('UPDATE users SET use_assigned_projects = 1 WHERE id = :id')
            ->execute(['id' => $userId]);

        if ($manageTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($manageTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $normalizedProjectIds;
}

function save_employee_project_assignment(int $userId, int $projectId, array $projectRange = [], bool $allowUnverifiedProject = false): void
{
    if ($allowUnverifiedProject) {
        $project = project_by_id($projectId);
        $projectId = (int) ($project['id'] ?? 0);
    } else {
        $normalizedProjectIds = normalize_project_assignment_ids([$projectId]);
        $projectId = (int) ($normalizedProjectIds[0] ?? 0);
    }
    if ($userId <= 0 || $projectId <= 0) {
        throw new RuntimeException('Select a valid project assignment.');
    }

    $from = trim((string) ($projectRange['from'] ?? ''));
    $to = trim((string) ($projectRange['to'] ?? ''));
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';
    if ($from !== '' && $to !== '' && $from > $to) {
        [$from, $to] = [$to, $from];
    }

    db()->prepare('INSERT INTO employee_project_assignments (user_id, project_id, project_from, project_to, project_incentive, project_daily_salary, created_at)
        VALUES (:user_id, :project_id, :project_from, :project_to, :project_incentive, :project_daily_salary, :created_at)
        ON DUPLICATE KEY UPDATE
            project_from = VALUES(project_from),
            project_to = VALUES(project_to),
            project_incentive = VALUES(project_incentive),
            project_daily_salary = VALUES(project_daily_salary)')
        ->execute([
            'user_id' => $userId,
            'project_id' => $projectId,
            'project_from' => $from !== '' ? $from : null,
            'project_to' => $to !== '' ? $to : null,
            'project_incentive' => max(0.0, round((float) ($projectRange['incentive'] ?? 0), 2)),
            'project_daily_salary' => max(0.0, round((float) ($projectRange['daily_salary'] ?? 0), 2)),
            'created_at' => now(),
        ]);

    db()->prepare('UPDATE users SET use_assigned_projects = 1 WHERE id = :id')
        ->execute(['id' => $userId]);
}

function project_coordinator_assignable_employees(array $coordinator): array
{
    if (!employee_is_project_coordinator($coordinator) || empty($coordinator['admin_id'])) {
        return [];
    }

    $stmt = db()->prepare("SELECT * FROM users
        WHERE admin_id = :admin_id
          AND role IN ('employee', 'corporate_employee')
          AND id <> :coordinator_id
        ORDER BY FIELD(role, 'employee', 'corporate_employee'), name");
    $stmt->execute([
        'admin_id' => (int) $coordinator['admin_id'],
        'coordinator_id' => (int) ($coordinator['id'] ?? 0),
    ]);

    return $stmt->fetchAll();
}

function normalize_project_coordinator_assignment_ids(array $employeeIds, array $coordinator): array
{
    $employeeIds = array_values(array_unique(array_filter(
        array_map('intval', $employeeIds),
        static fn (int $employeeId): bool => $employeeId > 0
    )));
    if ($employeeIds === []) {
        return [];
    }

    $validLookup = [];
    foreach (project_coordinator_assignable_employees($coordinator) as $employee) {
        $validLookup[(int) ($employee['id'] ?? 0)] = true;
    }

    foreach ($employeeIds as $employeeId) {
        if (!isset($validLookup[$employeeId])) {
            throw new RuntimeException('Select only valid employees for this project.');
        }
    }

    return $employeeIds;
}

function contractual_project_setup_for_project(int $projectId, int $adminId): array
{
    if ($projectId <= 0 || $adminId <= 0) {
        return [
            'employee_ids' => [],
            'from' => '',
            'to' => '',
            'daily_salary' => 0.0,
            'pay_basis' => 'daily',
        ];
    }

    $stmt = db()->prepare("SELECT a.user_id, a.project_from, a.project_to, a.project_daily_salary, COALESCE(a.project_pay_basis, 'daily') AS project_pay_basis
        FROM employee_project_assignments a
        INNER JOIN users u ON u.id = a.user_id
        WHERE a.project_id = :project_id
          AND u.admin_id = :admin_id
          AND (u.role = 'corporate_employee' OR u.employee_type = 'corporate')
        ORDER BY u.name");
    $stmt->execute([
        'project_id' => $projectId,
        'admin_id' => $adminId,
    ]);

    $employeeIds = [];
    $from = '';
    $to = '';
    $dailySalary = 0.0;
    $payBasis = 'daily';
    foreach ($stmt->fetchAll() as $row) {
        $employeeIds[] = (int) ($row['user_id'] ?? 0);
        if ($from === '' && !empty($row['project_from'])) {
            $from = (string) $row['project_from'];
        }
        if ($to === '' && !empty($row['project_to'])) {
            $to = (string) $row['project_to'];
        }
        if ($dailySalary <= 0 && (float) ($row['project_daily_salary'] ?? 0) > 0) {
            $dailySalary = (float) $row['project_daily_salary'];
        }
        if (!empty($row['project_pay_basis'])) {
            $payBasis = normalize_project_pay_basis($row['project_pay_basis']);
        }
    }

    return [
        'employee_ids' => array_values(array_filter($employeeIds)),
        'from' => $from,
        'to' => $to,
        'daily_salary' => round($dailySalary, 2),
        'pay_basis' => $payBasis,
    ];
}

function save_contractual_project_setup(int $projectId, array $source, int $adminId): void
{
    if ($projectId <= 0 || $adminId <= 0) {
        return;
    }
    $project = project_by_id($projectId, $adminId);
    if (!$project) {
        throw new RuntimeException('Project not found.');
    }

    $employeeIds = array_values(array_unique(array_filter(
        array_map('intval', $source['contractual_employee_ids'] ?? []),
        static fn (int $id): bool => $id > 0
    )));
    $dailySalary = max(0.0, round((float) ($source['contractual_daily_salary'] ?? 0), 2));
    $payBasis = normalize_project_pay_basis($source['contractual_pay_basis'] ?? 'daily');
    $from = trim((string) ($source['contractual_project_from'] ?? ''));
    $to = trim((string) ($source['contractual_project_to'] ?? ''));
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';
    if ($from !== '' && $to !== '' && $from > $to) {
        [$from, $to] = [$to, $from];
    }

    if ($employeeIds !== [] && $dailySalary <= 0) {
        throw new RuntimeException('Hours must be greater than zero.');
    }

    $validEmployeeIds = [];
    if ($employeeIds !== []) {
        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $params = array_merge([$adminId], $employeeIds);
        $stmt = db()->prepare("SELECT id FROM users
            WHERE admin_id = ?
              AND (role = 'corporate_employee' OR employee_type = 'corporate')
              AND id IN ({$placeholders})");
        $stmt->execute($params);
        $validEmployeeIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if (count($validEmployeeIds) !== count($employeeIds)) {
            throw new RuntimeException('Select only valid contractual employees.');
        }
    }

    $pdo = db();
    $deleteSql = "DELETE a FROM employee_project_assignments a
        INNER JOIN users u ON u.id = a.user_id
        WHERE a.project_id = ?
          AND u.admin_id = ?
          AND (u.role = 'corporate_employee' OR u.employee_type = 'corporate')";
    $deleteParams = [$projectId, $adminId];
    if ($validEmployeeIds !== []) {
        $deleteSql .= ' AND a.user_id NOT IN (' . implode(',', array_fill(0, count($validEmployeeIds), '?')) . ')';
        $delete = $pdo->prepare($deleteSql);
        $delete->execute(array_merge($deleteParams, $validEmployeeIds));
    } else {
        $delete = $pdo->prepare($deleteSql);
        $delete->execute($deleteParams);
    }

    $insert = $pdo->prepare('INSERT INTO employee_project_assignments (user_id, project_id, project_from, project_to, project_incentive, project_daily_salary, project_pay_basis, created_at)
        VALUES (:user_id, :project_id, :project_from, :project_to, 0, :project_daily_salary, :project_pay_basis, :created_at)
        ON DUPLICATE KEY UPDATE
            project_from = VALUES(project_from),
            project_to = VALUES(project_to),
            project_daily_salary = VALUES(project_daily_salary),
            project_pay_basis = VALUES(project_pay_basis)');
    $flagAssigned = $pdo->prepare('UPDATE users SET use_assigned_projects = 1 WHERE id = :id');

    foreach ($validEmployeeIds as $employeeId) {
        $insert->execute([
            'user_id' => $employeeId,
            'project_id' => $projectId,
            'project_from' => $from !== '' ? $from : null,
            'project_to' => $to !== '' ? $to : null,
            'project_daily_salary' => $dailySalary,
            'project_pay_basis' => $payBasis,
            'created_at' => now(),
        ]);
        $flagAssigned->execute(['id' => $employeeId]);
    }
}

function normalize_project_payload(array $source): array
{
    $payload = [
        'project_name' => trim((string) ($source['project_name'] ?? '')),
        'vendor_name' => trim((string) ($source['vendor_name'] ?? '')),
        'college_name' => trim((string) ($source['college_name'] ?? '')),
        'location' => trim((string) ($source['location'] ?? '')),
        'total_days' => max(1, (int) ($source['total_days'] ?? 1)),
        'session_type' => trim((string) ($source['session_type'] ?? 'FULL_DAY')),
        'is_active' => !empty($source['is_active']) ? 1 : 0,
    ];

    if ($payload['project_name'] === '') {
        throw new RuntimeException('Project name is required.');
    }
    if ($payload['college_name'] === '') {
        throw new RuntimeException('College name is required.');
    }
    if ($payload['location'] === '') {
        throw new RuntimeException('Location is required.');
    }
    if (!in_array($payload['session_type'], project_session_types(), true)) {
        $payload['session_type'] = 'FULL_DAY';
    }

    return $payload;
}

function save_project(array $source, ?int $projectId = null): int
{
    $payload = normalize_project_payload($source);
    $adminId = project_scope_admin_id();
    if ($adminId === null) {
        throw new RuntimeException('An administrator must be signed in to manage projects.');
    }

    if ($projectId !== null && $projectId > 0) {
        $existing = project_by_id($projectId, $adminId);
        if (!$existing) {
            throw new RuntimeException('Project not found.');
        }

        db()->prepare('UPDATE projects
            SET project_name = :project_name,
                vendor_name = :vendor_name,
                college_name = :college_name,
                location = :location,
                total_days = :total_days,
                session_type = :session_type,
                is_active = :is_active
            WHERE id = :id AND admin_id = :admin_id')
            ->execute([
                'id' => $projectId,
                'admin_id' => $adminId,
                'project_name' => $payload['project_name'],
                'vendor_name' => $payload['vendor_name'] !== '' ? $payload['vendor_name'] : null,
                'college_name' => $payload['college_name'],
                'location' => $payload['location'],
                'total_days' => $payload['total_days'],
                'session_type' => $payload['session_type'],
                'is_active' => $payload['is_active'],
            ]);

        assign_project_code($projectId, $adminId);

        return $projectId;
    }

    db()->prepare('INSERT INTO projects (admin_id, project_name, vendor_name, college_name, location, total_days, session_type, is_active)
        VALUES (:admin_id, :project_name, :vendor_name, :college_name, :location, :total_days, :session_type, :is_active)')
        ->execute([
            'admin_id' => $adminId,
            'project_name' => $payload['project_name'],
            'vendor_name' => $payload['vendor_name'] !== '' ? $payload['vendor_name'] : null,
            'college_name' => $payload['college_name'],
            'location' => $payload['location'],
            'total_days' => $payload['total_days'],
            'session_type' => $payload['session_type'],
            'is_active' => $payload['is_active'],
        ]);

    $newProjectId = (int) db()->lastInsertId();
    if (empty($source['defer_project_code'])) {
        assign_project_code($newProjectId, $adminId);
    }

    return $newProjectId;
}

function project_code_for_id(int $projectId): string
{
    return 'P' . str_pad((string) $projectId, 3, '0', STR_PAD_LEFT);
}

function assign_project_code(int $projectId, ?int $adminId = null): string
{
    $adminId ??= project_scope_admin_id();
    if ($adminId === null || $projectId <= 0) {
        throw new RuntimeException('Unable to create project ID.');
    }

    $projectCode = project_code_for_id($projectId);
    db()->prepare('UPDATE projects
        SET project_code = :project_code
        WHERE id = :id
          AND admin_id = :admin_id
          AND (project_code IS NULL OR project_code = "" OR project_code LIKE "PRJ-%")')
        ->execute([
            'project_code' => $projectCode,
            'id' => $projectId,
            'admin_id' => $adminId,
        ]);

    return $projectCode;
}

function mark_project_pending_verification(int $projectId, int $creatorUserId): void
{
    $adminId = project_scope_admin_id();
    if ($adminId === null || $projectId <= 0 || $creatorUserId <= 0) {
        throw new RuntimeException('Unable to submit project for verification.');
    }

    db()->prepare('UPDATE projects
        SET approval_status = "pending",
            created_by_user_id = :created_by_user_id,
            project_code = NULL,
            is_active = 0
        WHERE id = :id AND admin_id = :admin_id')
        ->execute([
            'id' => $projectId,
            'admin_id' => $adminId,
            'created_by_user_id' => $creatorUserId,
        ]);
}

function verify_project(int $projectId): array
{
    $adminId = project_scope_admin_id();
    if ($adminId === null || $projectId <= 0) {
        throw new RuntimeException('Project not found.');
    }

    $stmt = db()->prepare('UPDATE projects
        SET approval_status = "verified",
            is_active = 1
        WHERE id = :id AND admin_id = :admin_id');
    $stmt->execute([
        'id' => $projectId,
        'admin_id' => $adminId,
    ]);

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Project not found.');
    }

    assign_project_code($projectId, $adminId);
    $project = project_by_id($projectId, $adminId);
    if (!$project) {
        throw new RuntimeException('Project not found.');
    }

    return $project;
}

function toggle_project_active(int $projectId): array
{
    $project = project_by_id($projectId);
    if (!$project) {
        throw new RuntimeException('Project not found.');
    }
    if ((string) ($project['approval_status'] ?? 'verified') !== 'verified') {
        throw new RuntimeException('Verify this project before changing active status.');
    }

    $nextState = !empty($project['is_active']) ? 0 : 1;
    db()->prepare('UPDATE projects SET is_active = :is_active WHERE id = :id AND admin_id = :admin_id')
        ->execute([
            'id' => $projectId,
            'admin_id' => (int) $project['admin_id'],
            'is_active' => $nextState,
        ]);

    $updated = project_by_id($projectId);
    if (!$updated) {
        throw new RuntimeException('Unable to update the project status.');
    }

    return $updated;
}

function delete_project(int $projectId): void
{
    $project = project_by_id($projectId);
    if (!$project) {
        throw new RuntimeException('Project not found.');
    }

    db()->prepare('DELETE FROM projects WHERE id = :id AND admin_id = :admin_id')
        ->execute([
            'id' => $projectId,
            'admin_id' => (int) $project['admin_id'],
        ]);
}
