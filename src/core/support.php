<?php

declare(strict_types=1);

function now(): string
{
    return date('Y-m-d H:i:s');
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_path_url(): string
{
    $basePath = str_replace('\\', '/', dirname(BASE_URL));

    if ($basePath === '/' || $basePath === '.' || $basePath === '\\') {
        return '';
    }

    return rtrim($basePath, '/');
}

function asset_url(string $path): string
{
    return base_path_url() . '/' . ltrim($path, '/');
}


function user_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'E';
}

function redirect_to(string $page, array $params = []): void
{
    $query = http_build_query(array_merge(['page' => $page], $params));
    header('Location: ' . BASE_URL . '?' . $query);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $items;
}

function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_role(string $role): array
{
    $user = current_user();
    if (!$user || $user['role'] !== $role) {
        flash('error', 'Please sign in as ' . $role . ' to continue.');
        redirect_to('login', ['role' => $role]);
    }

    return $user;
}

function current_admin_id(): ?int
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        return null;
    }

    return (int) $user['id'];
}

