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
        $stmt = db()->prepare('SELECT * FROM projects WHERE admin_id = :admin_id ORDER BY is_active DESC, updated_at DESC, project_name ASC, id DESC');
        $stmt->execute(['admin_id' => $adminId]);
        return $stmt->fetchAll();
    }

    $stmt = db()->query('SELECT * FROM projects ORDER BY is_active DESC, updated_at DESC, project_name ASC, id DESC');
    return $stmt->fetchAll();
}

function active_projects(?int $adminId = null): array
{
    $adminId ??= project_scope_admin_id();
    if ($adminId !== null) {
        $stmt = db()->prepare('SELECT * FROM projects WHERE admin_id = :admin_id AND is_active = 1 ORDER BY project_name ASC, id DESC');
        $stmt->execute(['admin_id' => $adminId]);
        return $stmt->fetchAll();
    }

    $stmt = db()->query('SELECT * FROM projects WHERE is_active = 1 ORDER BY project_name ASC, id DESC');
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

    $adminId = project_scope_admin_id();
    $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
    $params = $projectIds;
    $sql = 'SELECT id FROM projects WHERE id IN (' . $placeholders . ')';
    if ($adminId !== null) {
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

function assigned_projects_for_employee(int $userId): array
{
    $adminId = project_scope_admin_id();
    $adminSql = $adminId !== null ? ' AND p.admin_id = :admin_id' : '';
    $stmt = db()->prepare('SELECT p.*, a.project_from, a.project_to, a.project_incentive
        FROM employee_project_assignments a
        INNER JOIN projects p ON p.id = a.project_id
        WHERE a.user_id = :user_id' . $adminSql . '
        ORDER BY p.is_active DESC, p.project_name ASC, p.id DESC');
    $params = ['user_id' => $userId];
    if ($adminId !== null) {
        $params['admin_id'] = $adminId;
    }
    $stmt->execute($params);

    return $stmt->fetchAll();
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

function normalize_project_assignment_ranges(array $fromValues, array $toValues, array $projectIds, array $incentiveValues = []): array
{
    $normalizedProjectIds = normalize_project_assignment_ids($projectIds);
    $ranges = [];

    foreach ($normalizedProjectIds as $projectId) {
        $from = trim((string) ($fromValues[$projectId] ?? ''));
        $to = trim((string) ($toValues[$projectId] ?? ''));
        $incentive = max(0.0, round((float) ($incentiveValues[$projectId] ?? 0), 2));
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';
        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $ranges[$projectId] = [
            'from' => $from,
            'to' => $to,
            'incentive' => $incentive,
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
            $insert = $pdo->prepare('INSERT INTO employee_project_assignments (user_id, project_id, project_from, project_to, project_incentive, created_at)
                VALUES (:user_id, :project_id, :project_from, :project_to, :project_incentive, :created_at)');

            foreach ($normalizedProjectIds as $projectId) {
                $range = $projectRanges[$projectId] ?? ['from' => '', 'to' => ''];
                $insert->execute([
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'project_from' => ($range['from'] ?? '') !== '' ? $range['from'] : null,
                    'project_to' => ($range['to'] ?? '') !== '' ? $range['to'] : null,
                    'project_incentive' => max(0.0, round((float) ($range['incentive'] ?? 0), 2)),
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

function normalize_project_payload(array $source): array
{
    $payload = [
        'project_name' => trim((string) ($source['project_name'] ?? '')),
        'college_name' => trim((string) ($source['college_name'] ?? '')),
        'location' => trim((string) ($source['location'] ?? '')),
        'total_days' => (int) ($source['total_days'] ?? 0),
        'session_type' => trim((string) ($source['session_type'] ?? '')),
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
    if ($payload['total_days'] <= 0) {
        throw new RuntimeException('Total days must be greater than zero.');
    }
    if (!in_array($payload['session_type'], project_session_types(), true)) {
        throw new RuntimeException('Choose a valid session type.');
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
                'college_name' => $payload['college_name'],
                'location' => $payload['location'],
                'total_days' => $payload['total_days'],
                'session_type' => $payload['session_type'],
                'is_active' => $payload['is_active'],
            ]);

        return $projectId;
    }

    db()->prepare('INSERT INTO projects (admin_id, project_name, college_name, location, total_days, session_type, is_active)
        VALUES (:admin_id, :project_name, :college_name, :location, :total_days, :session_type, :is_active)')
        ->execute([
            'admin_id' => $adminId,
            'project_name' => $payload['project_name'],
            'college_name' => $payload['college_name'],
            'location' => $payload['location'],
            'total_days' => $payload['total_days'],
            'session_type' => $payload['session_type'],
            'is_active' => $payload['is_active'],
        ]);

    return (int) db()->lastInsertId();
}

function toggle_project_active(int $projectId): array
{
    $project = project_by_id($projectId);
    if (!$project) {
        throw new RuntimeException('Project not found.');
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
