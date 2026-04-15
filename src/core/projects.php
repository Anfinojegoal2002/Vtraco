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

function projects(): array
{
    $stmt = db()->query('SELECT * FROM projects ORDER BY is_active DESC, updated_at DESC, project_name ASC, id DESC');
    return $stmt->fetchAll();
}

function active_projects(): array
{
    $stmt = db()->query('SELECT * FROM projects WHERE is_active = 1 ORDER BY project_name ASC, id DESC');
    return $stmt->fetchAll();
}

function project_by_id(int $projectId): ?array
{
    $stmt = db()->prepare('SELECT * FROM projects WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $projectId]);
    $row = $stmt->fetch();

    return $row ?: null;
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

    if ($projectId !== null && $projectId > 0) {
        $existing = project_by_id($projectId);
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
            WHERE id = :id')
            ->execute([
                'id' => $projectId,
                'project_name' => $payload['project_name'],
                'college_name' => $payload['college_name'],
                'location' => $payload['location'],
                'total_days' => $payload['total_days'],
                'session_type' => $payload['session_type'],
                'is_active' => $payload['is_active'],
            ]);

        return $projectId;
    }

    db()->prepare('INSERT INTO projects (project_name, college_name, location, total_days, session_type, is_active)
        VALUES (:project_name, :college_name, :location, :total_days, :session_type, :is_active)')
        ->execute([
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
    db()->prepare('UPDATE projects SET is_active = :is_active WHERE id = :id')
        ->execute([
            'id' => $projectId,
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

    db()->prepare('DELETE FROM projects WHERE id = :id')
        ->execute(['id' => $projectId]);
}
