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

function payment_requires_transfer_mode(string $bankName): bool
{
    return payment_transfer_modes($bankName) !== [];
}

function payment_requires_transaction_id(string $bankName): bool
{
    return strtoupper(trim($bankName)) !== 'CASH';
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

    return [
        'employee_id' => $employeeId,
        'bank_name' => in_array($bankName, payment_bank_names(), true) ? $bankName : '',
        'payment_type' => in_array($paymentType, payment_types(), true) ? $paymentType : '',
        'from_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : '',
        'to_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) ? $toDate : '',
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
    $params = [];
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

    $bankName = strtoupper(trim((string) ($source['bank_name'] ?? ($existingPayment['bank_name'] ?? ''))));
    if (!in_array($bankName, payment_bank_names(), true)) {
        throw new RuntimeException('Choose a valid bank name.');
    }

    $transferMode = strtoupper(trim((string) ($source['transfer_mode'] ?? ($existingPayment['transfer_mode'] ?? ''))));
    $allowedTransferModes = payment_transfer_modes($bankName);
    if ($allowedTransferModes !== []) {
        if (!in_array($transferMode, $allowedTransferModes, true)) {
            throw new RuntimeException('Choose a valid transfer mode for the selected bank.');
        }
    } else {
        $transferMode = null;
    }

    $transactionId = trim((string) ($source['transaction_id'] ?? ($existingPayment['transaction_id'] ?? '')));
    if (payment_requires_transaction_id($bankName) && $transactionId === '') {
        throw new RuntimeException('Transaction ID is required for the selected bank.');
    }
    if (!payment_requires_transaction_id($bankName)) {
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

    db()->prepare('INSERT INTO payments (user_id, admin_id, payment_type, amount, bank_name, transfer_mode, transaction_id, payment_date, proof_path, proof_name, proof_mime, remarks, reimbursement_id, created_at, updated_at)
        VALUES (:user_id, :admin_id, :payment_type, :amount, :bank_name, :transfer_mode, :transaction_id, :payment_date, :proof_path, :proof_name, :proof_mime, :remarks, :reimbursement_id, :created_at, :updated_at)')
        ->execute([
            'user_id' => $payload['user_id'],
            'admin_id' => $payload['admin_id'],
            'payment_type' => $payload['payment_type'],
            'amount' => $payload['amount'],
            'bank_name' => $payload['bank_name'],
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
                <tr><td>Bank</td><td><?= h((string) $payment['bank_name']) ?></td></tr>
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
