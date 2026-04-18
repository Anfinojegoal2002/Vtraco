<?php

declare(strict_types=1);

function payment_types(): array
{
    return ['SALARY', 'INCENTIVE', 'REIMBURSEMENT', 'OTHER'];
}

function payment_bank_names(): array
{
    return ['SBI', 'CANARA', 'IOB', 'CASH'];
}

function payment_primary_method(array $methods): string
{
    return (string) ($methods[0] ?? '');
}

function payment_methods_from_json(?string $json): array
{
    $json = trim((string) $json);
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $methods = [];
    foreach ($decoded as $method) {
        $method = strtoupper(trim((string) $method));
        if (in_array($method, payment_bank_names(), true) && !in_array($method, $methods, true)) {
            $methods[] = $method;
        }
    }

    return $methods;
}

function payment_methods_from_source(array $source, ?array $existingPayment = null): array
{
    $raw = $source['payment_methods'] ?? [];
    if (!is_array($raw)) {
        $raw = $raw !== '' ? [$raw] : [];
    }

    if ($raw === [] && $existingPayment) {
        $raw = payment_methods_for_record($existingPayment);
    }

    $methods = [];
    foreach ($raw as $method) {
        $method = strtoupper(trim((string) $method));
        if (!in_array($method, payment_bank_names(), true)) {
            continue;
        }
        if (!in_array($method, $methods, true)) {
            $methods[] = $method;
        }
    }

    return $methods;
}

function payment_methods_for_record(array $payment): array
{
    $methods = payment_methods_from_json((string) ($payment['payment_methods_json'] ?? ''));
    if ($methods !== []) {
        return $methods;
    }

    $legacyMethod = strtoupper(trim((string) ($payment['bank_name'] ?? '')));
    return in_array($legacyMethod, payment_bank_names(), true) ? [$legacyMethod] : [];
}

function payment_methods_label(array $methods): string
{
    return $methods !== [] ? implode(', ', $methods) : '-';
}

function payment_transfer_modes_map(): array
{
    return [
        'SBI' => ['IMPS', 'NEFT', 'CHEQUE'],
        'CANARA' => ['IMPS', 'NEFT', 'CHEQUE'],
        'IOB' => ['GPAY', 'PHONEPAY'],
        'CASH' => [],
    ];
}

function payment_transfer_modes(string $bankName): array
{
    $bankName = strtoupper(trim($bankName));
    $map = payment_transfer_modes_map();
    return $map[$bankName] ?? [];
}

function payment_available_transfer_modes(array $methods): array
{
    $modes = [];
    foreach ($methods as $method) {
        foreach (payment_transfer_modes($method) as $mode) {
            if (!in_array($mode, $modes, true)) {
                $modes[] = $mode;
            }
        }
    }

    if (count(array_filter($methods, static fn(string $method): bool => $method !== 'CASH')) > 1) {
        $modes[] = 'MIXED';
    }

    return $modes;
}

function payment_requires_transfer_mode(string $bankName): bool
{
    return payment_transfer_modes($bankName) !== [];
}

function payment_requires_transfer_mode_for_methods(array $methods): bool
{
    return payment_available_transfer_modes($methods) !== [];
}

function payment_requires_transaction_id(string $bankName): bool
{
    return strtoupper(trim($bankName)) !== 'CASH';
}

function payment_requires_transaction_id_for_methods(array $methods): bool
{
    foreach ($methods as $method) {
        if (payment_requires_transaction_id($method)) {
            return true;
        }
    }

    return false;
}

function payment_storage_root(): string
{
    return dirname(UPLOAD_PATH) . '/payments';
}

function payment_relative_path(string $absolutePath): string
{
    $normalizedPath = str_replace('\\', '/', $absolutePath);
    $projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));

    return ltrim(str_replace($projectRoot, '', $normalizedPath), '/');
}

function payment_upload_present(array $file): bool
{
    return isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
}

function validate_payment_proof_upload(array $file): void
{
    validate_uploaded_file($file, ['jpg', 'jpeg', 'png', 'pdf'], 2 * 1024 * 1024, 'payment proof');

    $mime = uploaded_file_mime_type($file);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
        throw new RuntimeException('Payment proof must be a JPG, PNG, or PDF file.');
    }

    if (str_starts_with($mime, 'image/') && @getimagesize((string) ($file['tmp_name'] ?? '')) === false) {
        throw new RuntimeException('Payment proof image is not valid.');
    }
}

function store_payment_proof_upload(array $file): array
{
    validate_payment_proof_upload($file);
    ensure_directory(payment_storage_root());

    $extension = uploaded_file_extension($file) ?: 'pdf';
    $target = payment_storage_root() . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($extension);
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('Unable to save the payment proof.');
    }

    return [
        'path' => payment_relative_path($target),
        'name' => (string) ($file['name'] ?? basename($target)),
        'mime' => uploaded_file_mime_type(['tmp_name' => $target]) ?: mime_content_type($target) ?: '',
    ];
}

if (!function_exists('create_notification_entry')) {
    function create_notification_entry(int $userId, string $title, string $message, string $type = 'info', ?string $relatedType = null, ?int $relatedId = null, ?int $actorUserId = null): void
    {
        db()->prepare('INSERT INTO notifications (user_id, actor_user_id, title, message, type, related_type, related_id, is_read, created_at) VALUES (:user_id, :actor_user_id, :title, :message, :type, :related_type, :related_id, 0, :created_at)')
            ->execute([
                'user_id' => $userId,
                'actor_user_id' => $actorUserId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'created_at' => now(),
            ]);
    }
}

function payment_user_by_id(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function payment_filter_params(array $source): array
{
    $employeeId = max(0, (int) ($source['employee_id'] ?? $source['filter_employee_id'] ?? 0));
    $bankName = strtoupper(trim((string) ($source['bank_name'] ?? $source['filter_bank_name'] ?? '')));
    $paymentType = strtoupper(trim((string) ($source['payment_type'] ?? $source['filter_payment_type'] ?? '')));
    $fromDate = trim((string) ($source['from_date'] ?? $source['filter_from_date'] ?? ''));
    $toDate = trim((string) ($source['to_date'] ?? $source['filter_to_date'] ?? ''));
    $section = trim((string) ($source['section'] ?? $source['filter_section'] ?? 'request'));
    $requestMonth = trim((string) ($source['request_month'] ?? $source['filter_request_month'] ?? date('Y-m')));

    return [
        'employee_id' => $employeeId,
        'bank_name' => in_array($bankName, payment_bank_names(), true) ? $bankName : '',
        'payment_type' => in_array($paymentType, payment_types(), true) ? $paymentType : '',
        'from_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : '',
        'to_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) ? $toDate : '',
        'section' => in_array($section, ['request', 'payment', 'report'], true) ? $section : 'request',
        'request_month' => preg_match('/^\d{4}-\d{2}$/', $requestMonth) ? $requestMonth : date('Y-m'),
    ];
}

function approved_reimbursement_requests(?int $employeeId = null): array
{
    $admin = require_role('admin');
    $sql = 'SELECT er.*, u.name AS employee_name, u.emp_id AS employee_emp_id
            FROM employee_reimbursements er
            JOIN users u ON u.id = er.user_id
            WHERE er.admin_id = :admin_id
              AND er.status IN ("APPROVED", "PARTIALLY PAID")
              AND er.remaining_balance > 0';
    $params = ['admin_id' => (int) $admin['id']];

    if ($employeeId !== null && $employeeId > 0) {
        $sql .= ' AND er.user_id = :user_id';
        $params['user_id'] = $employeeId;
    }

    $sql .= ' ORDER BY er.expense_date DESC, er.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function incentive_totals_by_employee(): array
{
    $admin = require_role('admin');
    $stmt = db()->prepare('SELECT ar.user_id, COALESCE(SUM(r.incentive_earned), 0) AS total_incentive
        FROM attendance_records ar
        JOIN users u ON u.id = ar.user_id
        JOIN attendance_sessions s ON s.attendance_id = ar.id
        LEFT JOIN reimbursements r ON r.attendance_session_id = s.id
        WHERE u.admin_id = :admin_id
          AND u.role = :role
        GROUP BY ar.user_id');
    $stmt->execute([
        'admin_id' => (int) $admin['id'],
        'role' => current_manager_target_role(),
    ]);

    $totals = [];
    foreach ($stmt->fetchAll() as $row) {
        $totals[(int) $row['user_id']] = (float) ($row['total_incentive'] ?? 0);
    }

    return $totals;
}

function payment_month_bounds(string $month): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }

    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');

    return [$start, $end];
}

function paid_amount_for_employee_month(int $employeeId, string $paymentType, string $month): float
{
    $admin = require_role('admin');
    [$start, $end] = payment_month_bounds($month);
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments
        WHERE admin_id = :admin_id
          AND user_id = :user_id
          AND payment_type = :payment_type
          AND payment_date BETWEEN :start_date AND :end_date');
    $stmt->execute([
        'admin_id' => (int) $admin['id'],
        'user_id' => $employeeId,
        'payment_type' => strtoupper($paymentType),
        'start_date' => $start,
        'end_date' => $end,
    ]);

    return round((float) $stmt->fetchColumn(), 2);
}

function incentive_total_for_employee_month(int $employeeId, string $month): float
{
    [$start, $end] = payment_month_bounds($month);
    $stmt = db()->prepare('SELECT COALESCE(SUM(r.incentive_earned), 0)
        FROM attendance_records ar
        JOIN attendance_sessions s ON s.attendance_id = ar.id
        LEFT JOIN reimbursements r ON r.attendance_session_id = s.id
        WHERE ar.user_id = :user_id
          AND ar.attend_date BETWEEN :start_date AND :end_date');
    $stmt->execute([
        'user_id' => $employeeId,
        'start_date' => $start,
        'end_date' => $end,
    ]);

    return round((float) $stmt->fetchColumn(), 2);
}

function payment_request_rows(string $month): array
{
    $rows = [];

    foreach (employees() as $employee) {
        $employeeId = (int) ($employee['id'] ?? 0);
        $attendance = month_attendance_for_user($employeeId, $month);
        $salaryBreakdown = salary_breakdown_for_month((float) ($employee['salary'] ?? 0), $attendance);
        $salaryPaid = paid_amount_for_employee_month($employeeId, 'SALARY', $month);
        $salaryDue = round(max((float) ($salaryBreakdown['calculated_salary'] ?? 0) - $salaryPaid, 0), 2);
        $salaryErrors = [];

        if ((float) ($employee['salary'] ?? 0) <= 0) {
            $salaryErrors[] = 'Employee salary is missing.';
        }

        if ($salaryDue > 0 || $salaryErrors !== []) {
            $rows[] = [
                'request_key' => 'salary:' . $employeeId . ':' . $month,
                'request_type' => 'SALARY',
                'employee_id' => $employeeId,
                'employee_name' => (string) ($employee['name'] ?? ''),
                'employee_emp_id' => (string) ($employee['emp_id'] ?? ''),
                'amount' => $salaryDue,
                'status' => $salaryErrors !== [] ? 'Calculation Error' : 'Pending',
                'ready' => $salaryErrors === [] && $salaryDue > 0,
                'errors' => $salaryErrors,
                'request_month' => $month,
                'reimbursement_id' => null,
                'details' => [
                    'calculated_total' => (float) ($salaryBreakdown['calculated_salary'] ?? 0),
                    'already_paid' => $salaryPaid,
                    'payable_days' => (float) ($salaryBreakdown['payable_days'] ?? 0),
                    'working_days' => (int) ($salaryBreakdown['working_days'] ?? 0),
                ],
            ];
        }

        $incentiveTotal = incentive_total_for_employee_month($employeeId, $month);
        $incentivePaid = paid_amount_for_employee_month($employeeId, 'INCENTIVE', $month);
        $incentiveDue = round(max($incentiveTotal - $incentivePaid, 0), 2);

        if ($incentiveDue > 0) {
            $rows[] = [
                'request_key' => 'incentive:' . $employeeId . ':' . $month,
                'request_type' => 'INCENTIVE',
                'employee_id' => $employeeId,
                'employee_name' => (string) ($employee['name'] ?? ''),
                'employee_emp_id' => (string) ($employee['emp_id'] ?? ''),
                'amount' => $incentiveDue,
                'status' => 'Pending',
                'ready' => true,
                'errors' => [],
                'request_month' => $month,
                'reimbursement_id' => null,
                'details' => [
                    'calculated_total' => $incentiveTotal,
                    'already_paid' => $incentivePaid,
                ],
            ];
        }
    }

    $reimbursements = approved_reimbursement_requests();
    // Also fetch PENDING reimbursements for the "Request" tab if we want to show them there too.
    // However, the user said "in admin accounts tab in payment requests", and currently it shows APPROVED ones.
    // If we want to allow approving/rejecting from here, we should probably show PENDING ones too.
    
    // Let's get all reimbursements that are not PAID or DENIED.
    $stmt = db()->prepare('SELECT er.*, u.name AS employee_name, u.emp_id AS employee_emp_id
            FROM employee_reimbursements er
            JOIN users u ON u.id = er.user_id
            WHERE er.admin_id = :admin_id
              AND er.status NOT IN ("PAID", "DENIED")
              AND er.remaining_balance > 0
            ORDER BY er.expense_date DESC');
    $stmt->execute(['admin_id' => (int) require_role('admin')['id']]);
    $allReimbursements = $stmt->fetchAll();

    foreach ($allReimbursements as $reimbursement) {
        $rows[] = [
            'request_key' => 'reimbursement:' . (int) $reimbursement['id'],
            'request_type' => 'REIMBURSEMENT',
            'employee_id' => (int) ($reimbursement['user_id'] ?? 0),
            'employee_name' => (string) ($reimbursement['employee_name'] ?? ''),
            'employee_emp_id' => (string) ($reimbursement['employee_emp_id'] ?? ''),
            'amount' => round((float) ($reimbursement['remaining_balance'] ?? 0), 2),
            'status' => (string) ($reimbursement['status']),
            'ready' => (string) ($reimbursement['status']) === 'APPROVED' || (string) ($reimbursement['status']) === 'PARTIALLY PAID',
            'errors' => [],
            'request_month' => substr((string) ($reimbursement['expense_date'] ?? date('Y-m-d')), 0, 7),
            'reimbursement_id' => (int) ($reimbursement['id'] ?? 0),
            'details' => [
                'expense_date' => (string) ($reimbursement['expense_date'] ?? ''),
                'calculated_total' => (float) ($reimbursement['amount_requested'] ?? 0),
                'already_paid' => (float) ($reimbursement['amount_paid'] ?? 0),
            ],
        ];
    }

    // Fetch stored actions for Salary/Incentive
    $stmt = db()->prepare('SELECT request_key, status FROM payment_request_actions WHERE admin_id = :admin_id');
    $stmt->execute(['admin_id' => (int) require_role('admin')['id']]);
    $storedActions = [];
    foreach ($stmt->fetchAll() as $actionRow) {
        $storedActions[(string) $actionRow['request_key']] = (string) $actionRow['status'];
    }

    foreach ($rows as &$row) {
        if ($row['request_type'] === 'REIMBURSEMENT') {
            continue;
        }
        $key = (string) $row['request_key'];
        if (isset($storedActions[$key])) {
            $row['status'] = $storedActions[$key];
            $row['ready'] = $row['status'] === 'APPROVED' && empty($row['errors']);
        } else {
            $row['status'] = 'PENDING';
            $row['ready'] = false; // Must be approved first
        }
    }
    unset($row);

    usort($rows, static function (array $left, array $right): int {
        $leftName = strtolower((string) ($left['employee_name'] ?? ''));
        $rightName = strtolower((string) ($right['employee_name'] ?? ''));
        if ($leftName !== $rightName) {
            return $leftName <=> $rightName;
        }

        return strcmp((string) ($left['request_type'] ?? ''), (string) ($right['request_type'] ?? ''));
    });

    return $rows;
}

function payment_query_base(): string
{
    return 'SELECT p.*, u.name AS employee_name, u.emp_id AS employee_emp_id, u.email AS employee_email
            FROM payments p
            JOIN users u ON u.id = p.user_id';
}

function admin_payments(array $filters = []): array
{
    $admin = require_role('admin');
    $sql = payment_query_base() . ' WHERE p.admin_id = :admin_id';
    $params = ['admin_id' => (int) $admin['id']];

    if (!empty($filters['employee_id'])) {
        $sql .= ' AND p.user_id = :user_id';
        $params['user_id'] = (int) $filters['employee_id'];
    }
    if (!empty($filters['bank_name'])) {
        $sql .= ' AND p.bank_name = :bank_name';
        $params['bank_name'] = (string) $filters['bank_name'];
    }
    if (!empty($filters['payment_type'])) {
        $sql .= ' AND p.payment_type = :payment_type';
        $params['payment_type'] = (string) $filters['payment_type'];
    }
    if (!empty($filters['from_date'])) {
        $sql .= ' AND p.payment_date >= :from_date';
        $params['from_date'] = (string) $filters['from_date'];
    }
    if (!empty($filters['to_date'])) {
        $sql .= ' AND p.payment_date <= :to_date';
        $params['to_date'] = (string) $filters['to_date'];
    }

    $sql .= ' ORDER BY p.payment_date DESC, p.id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function admin_payment_by_id(int $paymentId): ?array
{
    $admin = require_role('admin');
    $stmt = db()->prepare(payment_query_base() . ' WHERE p.id = :id AND p.admin_id = :admin_id LIMIT 1');
    $stmt->execute([
        'id' => $paymentId,
        'admin_id' => (int) $admin['id'],
    ]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function payment_related_reimbursement_by_id(int $reimbursementId, int $adminId): ?array
{
    $stmt = db()->prepare('SELECT * FROM employee_reimbursements WHERE id = :id AND admin_id = :admin_id LIMIT 1');
    $stmt->execute([
        'id' => $reimbursementId,
        'admin_id' => $adminId,
    ]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function payment_redirect_query(array $filters): array
{
    $params = [
        'section' => (string) ($filters['section'] ?? 'request'),
        'request_month' => (string) ($filters['request_month'] ?? date('Y-m')),
    ];
    if (!empty($filters['employee_id'])) {
        $params['employee_id'] = (int) $filters['employee_id'];
    }
    if (!empty($filters['bank_name'])) {
        $params['bank_name'] = (string) $filters['bank_name'];
    }
    if (!empty($filters['payment_type'])) {
        $params['payment_type'] = (string) $filters['payment_type'];
    }
    if (!empty($filters['from_date'])) {
        $params['from_date'] = (string) $filters['from_date'];
    }
    if (!empty($filters['to_date'])) {
        $params['to_date'] = (string) $filters['to_date'];
    }

    return $params;
}

function normalize_payment_payload(array $source, ?array $existingPayment = null): array
{
    $admin = require_role('admin');
    $employeeId = max(0, (int) ($source['employee_id'] ?? ($existingPayment['user_id'] ?? 0)));
    $employee = employee_by_id($employeeId);
    if (!$employee) {
        throw new RuntimeException('Choose a valid employee.');
    }

    $paymentType = strtoupper(trim((string) ($source['payment_type'] ?? ($existingPayment['payment_type'] ?? ''))));
    if (!in_array($paymentType, payment_types(), true)) {
        throw new RuntimeException('Choose a valid payment type.');
    }

    $amount = round((float) ($source['amount'] ?? ($existingPayment['amount'] ?? 0)), 2);
    if ($amount <= 0) {
        throw new RuntimeException('Enter a payment amount greater than zero.');
    }

    $paymentMethods = payment_methods_from_source($source, $existingPayment);
    if ($paymentMethods === []) {
        throw new RuntimeException('Choose at least one payment method.');
    }
    $bankName = payment_primary_method($paymentMethods);

    $transferMode = strtoupper(trim((string) ($source['transfer_mode'] ?? ($existingPayment['transfer_mode'] ?? ''))));
    $allowedTransferModes = payment_available_transfer_modes($paymentMethods);
    if ($allowedTransferModes !== []) {
        if (!in_array($transferMode, $allowedTransferModes, true)) {
            throw new RuntimeException('Choose a valid transfer mode for the selected payment method.');
        }
    } else {
        $transferMode = null;
    }

    $transactionId = trim((string) ($source['transaction_id'] ?? ($existingPayment['transaction_id'] ?? '')));
    if (payment_requires_transaction_id_for_methods($paymentMethods) && $transactionId === '') {
        throw new RuntimeException('Transaction ID is required for the selected payment method.');
    }
    if (!payment_requires_transaction_id_for_methods($paymentMethods)) {
        $transactionId = null;
    }

    $paymentDate = trim((string) ($source['payment_date'] ?? ($existingPayment['payment_date'] ?? '')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
        throw new RuntimeException('Choose a valid payment date.');
    }

    $remarks = trim((string) ($source['remarks'] ?? ($existingPayment['remarks'] ?? '')));

    $reimbursementId = max(0, (int) ($source['reimbursement_id'] ?? ($existingPayment['reimbursement_id'] ?? 0)));
    if ($paymentType !== 'REIMBURSEMENT') {
        $reimbursementId = 0;
    }

    if ($paymentType === 'REIMBURSEMENT' && $reimbursementId > 0) {
        $reimbursement = payment_related_reimbursement_by_id($reimbursementId, (int) $admin['id']);
        if (!$reimbursement || (int) $reimbursement['user_id'] !== $employeeId) {
            throw new RuntimeException('Choose a valid approved reimbursement request.');
        }

        $existingAmount = $existingPayment && (int) ($existingPayment['reimbursement_id'] ?? 0) === $reimbursementId
            ? (float) ($existingPayment['amount'] ?? 0)
            : 0.0;
        $remainingBalance = round((float) ($reimbursement['remaining_balance'] ?? 0) + $existingAmount, 2);

        if ($remainingBalance <= 0) {
            throw new RuntimeException('This reimbursement request no longer has a remaining balance.');
        }
        if ($amount > $remainingBalance) {
            throw new RuntimeException('Payment amount cannot exceed the reimbursement remaining balance.');
        }
    }

    return [
        'user_id' => (int) $employee['id'],
        'admin_id' => (int) $admin['id'],
        'payment_type' => $paymentType,
        'amount' => $amount,
        'bank_name' => $bankName,
        'payment_methods_json' => json_encode($paymentMethods, JSON_UNESCAPED_SLASHES),
        'payment_methods' => $paymentMethods,
        'transfer_mode' => $transferMode,
        'transaction_id' => $transactionId,
        'payment_date' => $paymentDate,
        'remarks' => $remarks !== '' ? $remarks : null,
        'reimbursement_id' => $reimbursementId > 0 ? $reimbursementId : null,
    ];
}

function sync_reimbursement_from_payments(int $reimbursementId): void
{
    $reimbursement = payment_related_reimbursement_by_id($reimbursementId, current_admin_id() ?? 0);
    if (!$reimbursement) {
        return;
    }

    $stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) AS total_amount, MAX(id) AS latest_payment_id
        FROM payments
        WHERE reimbursement_id = :reimbursement_id');
    $stmt->execute(['reimbursement_id' => $reimbursementId]);
    $summary = $stmt->fetch() ?: [];

    $amountPaid = round((float) ($summary['total_amount'] ?? 0), 2);
    $remaining = round(max((float) ($reimbursement['amount_requested'] ?? 0) - $amountPaid, 0), 2);
    $latestPaymentId = !empty($summary['latest_payment_id']) ? (int) $summary['latest_payment_id'] : null;

    $status = 'APPROVED';
    if ($amountPaid <= 0) {
        $status = 'APPROVED';
    } elseif ($remaining > 0) {
        $status = 'PARTIALLY PAID';
    } else {
        $status = 'PAID';
    }

    db()->prepare('UPDATE employee_reimbursements
        SET amount_paid = :amount_paid,
            remaining_balance = :remaining_balance,
            status = :status,
            payment_id = :payment_id,
            updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            'amount_paid' => $amountPaid,
            'remaining_balance' => $remaining,
            'status' => $status,
            'payment_id' => $latestPaymentId,
            'updated_at' => now(),
            'id' => $reimbursementId,
        ]);
}

function notify_employee_payment_processed(array $payment): void
{
    $employee = payment_user_by_id((int) ($payment['user_id'] ?? 0));
    if (!$employee) {
        return;
    }

    $message = 'Your ' . strtolower((string) $payment['payment_type']) . ' payment of Rs ' . number_format((float) ($payment['amount'] ?? 0), 2) . ' has been processed.';
    create_notification_entry(
        (int) $employee['id'],
        'Payment processed',
        $message,
        'payment',
        'payment',
        (int) ($payment['id'] ?? 0),
        current_user() ? (int) (current_user()['id'] ?? 0) : null
    );

    if (filter_var((string) ($employee['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        send_payment_processed_email($employee, $payment);
    }
}

function create_payment(array $source, array $file = []): array
{
    $payload = normalize_payment_payload($source);
    $proof = null;
    if (payment_upload_present($file)) {
        $proof = store_payment_proof_upload($file);
    }

    db()->prepare('INSERT INTO payments (user_id, admin_id, payment_type, amount, bank_name, payment_methods_json, transfer_mode, transaction_id, payment_date, proof_path, proof_name, proof_mime, remarks, reimbursement_id, created_at, updated_at)
        VALUES (:user_id, :admin_id, :payment_type, :amount, :bank_name, :payment_methods_json, :transfer_mode, :transaction_id, :payment_date, :proof_path, :proof_name, :proof_mime, :remarks, :reimbursement_id, :created_at, :updated_at)')
        ->execute([
            'user_id' => $payload['user_id'],
            'admin_id' => $payload['admin_id'],
            'payment_type' => $payload['payment_type'],
            'amount' => $payload['amount'],
            'bank_name' => $payload['bank_name'],
            'payment_methods_json' => $payload['payment_methods_json'],
            'transfer_mode' => $payload['transfer_mode'],
            'transaction_id' => $payload['transaction_id'],
            'payment_date' => $payload['payment_date'],
            'proof_path' => $proof['path'] ?? null,
            'proof_name' => $proof['name'] ?? null,
            'proof_mime' => $proof['mime'] ?? null,
            'remarks' => $payload['remarks'],
            'reimbursement_id' => $payload['reimbursement_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $payment = admin_payment_by_id((int) db()->lastInsertId());
    if (!$payment) {
        throw new RuntimeException('Unable to load the newly created payment.');
    }

    if (!empty($payment['reimbursement_id'])) {
        sync_reimbursement_from_payments((int) $payment['reimbursement_id']);
        $payment = admin_payment_by_id((int) $payment['id']) ?: $payment;
    }

    notify_employee_payment_processed($payment);

    return $payment;
}

function update_payment(int $paymentId, array $source, array $file = []): array
{
    $existing = admin_payment_by_id($paymentId);
    if (!$existing) {
        throw new RuntimeException('Payment record not found.');
    }

    $oldReimbursementId = !empty($existing['reimbursement_id']) ? (int) $existing['reimbursement_id'] : 0;
    $payload = normalize_payment_payload($source, $existing);
    $proof = [
        'path' => $existing['proof_path'] ?? null,
        'name' => $existing['proof_name'] ?? null,
        'mime' => $existing['proof_mime'] ?? null,
    ];

    if (payment_upload_present($file)) {
        $proof = store_payment_proof_upload($file);
    }

    db()->prepare('UPDATE payments
        SET user_id = :user_id,
            payment_type = :payment_type,
            amount = :amount,
            bank_name = :bank_name,
            payment_methods_json = :payment_methods_json,
            transfer_mode = :transfer_mode,
            transaction_id = :transaction_id,
            payment_date = :payment_date,
            proof_path = :proof_path,
            proof_name = :proof_name,
            proof_mime = :proof_mime,
            remarks = :remarks,
            reimbursement_id = :reimbursement_id,
            updated_at = :updated_at
        WHERE id = :id AND admin_id = :admin_id')
        ->execute([
            'user_id' => $payload['user_id'],
            'payment_type' => $payload['payment_type'],
            'amount' => $payload['amount'],
            'bank_name' => $payload['bank_name'],
            'payment_methods_json' => $payload['payment_methods_json'],
            'transfer_mode' => $payload['transfer_mode'],
            'transaction_id' => $payload['transaction_id'],
            'payment_date' => $payload['payment_date'],
            'proof_path' => $proof['path'],
            'proof_name' => $proof['name'],
            'proof_mime' => $proof['mime'],
            'remarks' => $payload['remarks'],
            'reimbursement_id' => $payload['reimbursement_id'],
            'updated_at' => now(),
            'id' => $paymentId,
            'admin_id' => $payload['admin_id'],
        ]);

    if ($oldReimbursementId > 0) {
        sync_reimbursement_from_payments($oldReimbursementId);
    }
    if (!empty($payload['reimbursement_id'])) {
        sync_reimbursement_from_payments((int) $payload['reimbursement_id']);
    }

    $updated = admin_payment_by_id($paymentId);
    if (!$updated) {
        throw new RuntimeException('Unable to reload the payment record.');
    }

    return $updated;
}

function delete_payment(int $paymentId): void
{
    $payment = admin_payment_by_id($paymentId);
    if (!$payment) {
        throw new RuntimeException('Payment record not found.');
    }

    $reimbursementId = !empty($payment['reimbursement_id']) ? (int) $payment['reimbursement_id'] : 0;
    db()->prepare('DELETE FROM payments WHERE id = :id AND admin_id = :admin_id')
        ->execute([
            'id' => $paymentId,
            'admin_id' => (int) $payment['admin_id'],
        ]);

    if ($reimbursementId > 0) {
        sync_reimbursement_from_payments($reimbursementId);
    }
}

function stream_payment_payslip_pdf(array $payment): void
{
    require_once __DIR__ . '/../../vendor/autoload.php';
    $dompdf = new \Dompdf\Dompdf();
    $paymentMethods = payment_methods_for_record($payment);

    ob_start();
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 13px; }
            .sheet { border: 1px solid #cbd5e1; border-radius: 16px; padding: 24px; }
            h1 { margin: 0 0 18px; font-size: 22px; color: #1d4ed8; }
            .grid { width: 100%; border-collapse: collapse; }
            .grid td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
            .grid td:first-child { width: 35%; font-weight: 700; color: #475569; }
            .muted { color: #64748b; margin-top: 16px; }
        </style>
    </head>
    <body>
        <div class="sheet">
            <h1>Payment Payslip</h1>
            <table class="grid">
                <tr><td>Employee Name</td><td><?= h((string) $payment['employee_name']) ?></td></tr>
                <tr><td>Employee ID</td><td><?= h((string) $payment['employee_emp_id']) ?></td></tr>
                <tr><td>Payment Type</td><td><?= h((string) $payment['payment_type']) ?></td></tr>
                <tr><td>Amount</td><td>Rs <?= h(number_format((float) $payment['amount'], 2)) ?></td></tr>
                <tr><td>Date</td><td><?= h(date('d M Y', strtotime((string) $payment['payment_date']))) ?></td></tr>
                <tr><td>Payment Method(s)</td><td><?= h(payment_methods_label($paymentMethods)) ?></td></tr>
                <tr><td>Transfer Mode</td><td><?= h((string) (($payment['transfer_mode'] ?? '') ?: 'N/A')) ?></td></tr>
                <tr><td>Transaction ID</td><td><?= h((string) (($payment['transaction_id'] ?? '') ?: 'N/A')) ?></td></tr>
                <tr><td>Remarks</td><td><?= h((string) (($payment['remarks'] ?? '') ?: 'N/A')) ?></td></tr>
            </table>
            <p class="muted">Generated on <?= h(date('d M Y H:i')) ?>.</p>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('payment_payslip_' . (int) $payment['id'] . '.pdf');
    exit;
}

function update_payment_request_status(string $requestKey, string $status): void
{
    $admin = require_role('admin');
    $status = strtoupper(trim($status));
    if (!in_array($status, ['APPROVED', 'REJECTED', 'PENDING'], true)) {
        throw new RuntimeException('Invalid status for payment request.');
    }

    if (str_starts_with($requestKey, 'reimbursement:')) {
        $id = (int) str_replace('reimbursement:', '', $requestKey);
        db()->prepare('UPDATE employee_reimbursements SET status = :status, updated_at = :updated_at WHERE id = :id AND admin_id = :admin_id')
            ->execute([
                'status' => $status === 'REJECTED' ? 'DENIED' : $status,
                'updated_at' => now(),
                'id' => $id,
                'admin_id' => (int) $admin['id'],
            ]);
        return;
    }

    db()->prepare('INSERT INTO payment_request_actions (request_key, status, admin_id, created_at, updated_at)
        VALUES (:request_key, :status, :admin_id, :created_at, :updated_at)
        ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at)')
        ->execute([
            'request_key' => $requestKey,
            'status' => $status,
            'admin_id' => (int) $admin['id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
}
