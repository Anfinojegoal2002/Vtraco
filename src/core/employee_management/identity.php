<?php

declare(strict_types=1);

function employee_emp_id_exists(string $empId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE role IN ("employee", "corporate_employee") AND emp_id = :emp_id');
    $stmt->execute(['emp_id' => $empId]);
    return (int) $stmt->fetchColumn() > 0;
}


function generate_employee_emp_id(array $reserved = []): string
{
    $reservedLookup = array_fill_keys($reserved, true);

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $candidate = 'EMP' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        if (isset($reservedLookup[$candidate])) {
            continue;
        }
        if (!employee_emp_id_exists($candidate)) {
            return $candidate;
        }
    }

    $candidate = 'EMP' . date('ymdHis') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    while (isset($reservedLookup[$candidate]) || employee_emp_id_exists($candidate)) {
        $candidate = 'EMP' . date('ymdHis') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    }

    return $candidate;
}


function generated_employee_name(string $email = '', string $empId = ''): string
{
    $source = trim($email);
    if ($source !== '' && str_contains($source, '@')) {
        $source = strstr($source, '@', true) ?: $source;
    }

    $source = preg_replace('/[._\-]+/', ' ', $source) ?? $source;
    $source = preg_replace('/\s+/', ' ', trim($source)) ?? trim($source);
    if ($source !== '') {
        return ucwords(strtolower($source));
    }

    if (trim($empId) !== '') {
        return 'Employee ' . trim($empId);
    }

    return 'Employee';
}


function guessed_employee_name(array $row, array $columns, array $headerMap, string $email, string $empId): string
{
    if (($columns['name'] ?? null) !== null) {
        $name = trim((string) ($row[$columns['name']] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }

    foreach ($headerMap as $header => $index) {
        if (!str_contains($header, 'name')) {
            continue;
        }

        $value = trim((string) ($row[$index] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $knownIndexes = [];
    foreach (['emp_id', 'email', 'phone', 'shift', 'salary'] as $field) {
        if (($columns[$field] ?? null) !== null) {
            $knownIndexes[(int) $columns[$field]] = true;
        }
    }

    foreach ($row as $index => $value) {
        if (isset($knownIndexes[(int) $index])) {
            continue;
        }

        $candidate = trim((string) $value);
        if ($candidate === '' || str_contains($candidate, '@')) {
            continue;
        }

        if (preg_match('/[A-Za-z]/', $candidate)) {
            return $candidate;
        }
    }

    return generated_employee_name($email, $empId);
}


