<?php

declare(strict_types=1);

function normalize_csv_header(string $header): string
{
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
    $header = strtolower(trim($header));
    return preg_replace('/[^a-z0-9]+/', '', $header) ?? $header;
}


function normalize_import_phone(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '';
    }

    if (preg_match('/^\d+(?:\.0+)?$/', $phone) === 1) {
        return preg_replace('/\.0+$/', '', $phone) ?? $phone;
    }

    if (preg_match('/^\d+(?:\.\d+)?E[+-]?\d+$/i', $phone) === 1) {
        return number_format((float) $phone, 0, '', '');
    }

    return $phone;
}


function parse_employee_csv(string $path, string $originalName = '', bool $salaryRequired = true): array
{
    $sourceRows = attendance_report_rows($path, $originalName);
    if ($sourceRows === []) {
        throw new RuntimeException('Employee import file is empty.');
    }

    $aliases = [
        'emp_id' => ['empid', 'employeeid', 'employeecode', 'employeeno', 'employeenumber', 'empcode', 'staffid', 'staffcode', 'staffno', 'code'],
        'name' => ['name', 'employeename', 'employee', 'fullname', 'staffname', 'username', 'personname'],
        'email' => ['email', 'emailaddress', 'emailid', 'mail', 'mailid', 'mailaddress', 'officialemail', 'workemail'],
        'phone' => ['phonenumber', 'phone', 'mobilenumber', 'mobile', 'contactnumber', 'contact', 'mobileno', 'phoneno', 'contactno'],
        'shift' => ['shift', 'workshift', 'timeslot', 'timing'],
        'salary' => ['salary', 'monthlysalary', 'pay', 'amount', 'wage', 'salaryamount', 'monthlypay', 'basicpay', 'hourlyrate', 'hourlypay', 'rateperhour', 'hourlywage'],
    ];

    $headerMap = [];
    $columns = [];
    $headerRowIndex = null;
    $bestRequiredMatches = 0;
    foreach ($sourceRows as $rowIndex => $candidateHeader) {
        if (!is_array($candidateHeader) || $candidateHeader === []) {
            continue;
        }

        $candidateHeaderMap = [];
        foreach ($candidateHeader as $index => $column) {
            $normalizedColumn = normalize_csv_header((string) $column);
            if ($normalizedColumn !== '') {
                $candidateHeaderMap[$normalizedColumn] = $index;
            }
        }

        $candidateColumns = [];
        foreach ($aliases as $field => $possible) {
            foreach ($possible as $alias) {
                if (array_key_exists($alias, $candidateHeaderMap)) {
                    $candidateColumns[$field] = $candidateHeaderMap[$alias];
                    break;
                }
            }
        }

        $requiredMatches = 0;
        $requiredFields = $salaryRequired ? ['emp_id', 'name', 'email', 'phone', 'salary'] : ['emp_id', 'name', 'email', 'phone'];
        foreach ($requiredFields as $required) {
            if (array_key_exists($required, $candidateColumns)) {
                $requiredMatches++;
            }
        }

        if ($requiredMatches > $bestRequiredMatches) {
            $bestRequiredMatches = $requiredMatches;
            $headerRowIndex = $rowIndex;
            $headerMap = $candidateHeaderMap;
            $columns = $candidateColumns;
        }

        if ($requiredMatches === count($requiredFields)) {
            break;
        }
    }

    if ($headerRowIndex === null) {
        throw new RuntimeException('Could not find the employee header row. Use columns like Emp ID, Name, Email, Phone' . ($salaryRequired ? ', and Salary.' : '.'));
    }

    foreach (($salaryRequired ? ['emp_id', 'name', 'email', 'phone', 'salary'] : ['emp_id', 'name', 'email', 'phone']) as $required) {
        if (!array_key_exists($required, $columns)) {
            throw new RuntimeException('Missing required CSV column for ' . $required . '.');
        }
    }

    $columns['shift'] = $columns['shift'] ?? null;
    $columns['salary'] = $columns['salary'] ?? null;

    $rows = [];
    $reservedEmpIds = [];
    foreach (array_slice($sourceRows, $headerRowIndex + 1) as $offset => $row) {
        $rowNumber = $headerRowIndex + $offset + 2;
        $email = trim((string) (($columns['email'] !== null) ? ($row[$columns['email']] ?? '') : ''));
        $phone = normalize_import_phone((string) ($row[$columns['phone']] ?? ''));
        $salaryText = trim((string) (($columns['salary'] !== null) ? ($row[$columns['salary']] ?? '0') : '0'));
        $empId = trim((string) (($columns['emp_id'] !== null) ? ($row[$columns['emp_id']] ?? '') : ''));

        if ($email === '' && $phone === '' && $empId === '' && (!$salaryRequired || $salaryText === '')) {
            continue;
        }

        if ($empId === '') {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' is missing an employee ID.');
        }
        $reservedEmpIds[] = $empId;

        if ($email === '') {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' is missing an email address.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' has an invalid email address.');
        }
        if ($phone === '') {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' is missing a phone number.');
        }
        if (!is_numeric($salaryText) || (float) $salaryText < 0) {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' has an invalid salary value.');
        }

        $name = guessed_employee_name($row, $columns, $headerMap, $email, $empId);
        if ($name === '') {
            throw new RuntimeException('Employee CSV row ' . $rowNumber . ' is missing a name.');
        }

        $rows[] = [
            'emp_id' => $empId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'shift' => trim((string) (($columns['shift'] !== null) ? ($row[$columns['shift']] ?? '') : '')),
            'salary' => (float) $salaryText,
        ];
    }

    if (!$rows) {
        throw new RuntimeException('Employee import file has no usable rows.');
    }

    return $rows;
}

