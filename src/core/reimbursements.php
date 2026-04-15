<?php

declare(strict_types=1);

function reimbursement_categories(): array
{
    return ['FOOD', 'TRAVEL', 'ACCOMMODATION'];
}

function reimbursement_statuses(): array
{
    return ['PENDING', 'APPROVED', 'DENIED', 'PARTIALLY PAID', 'PAID'];
}

function reimbursement_payment_methods(): array
{
    return ['BANK_TRANSFER', 'UPI', 'CASH', 'CHEQUE', 'OTHER'];
}

function reimbursement_status_badge_class(string $status): string
{
    return match (strtoupper(trim($status))) {
        'DENIED' => 'denied',
        'APPROVED' => 'approved',
        'PARTIALLY PAID' => 'partially-paid',
        'PAID' => 'paid',
        default => 'pending',
    };
}

function reimbursement_status_label(string $status): string
{
    $status = strtoupper(trim($status));
    return in_array($status, reimbursement_statuses(), true) ? $status : 'PENDING';
}

function reimbursement_payment_method_label(string $method): string
{
    return match (strtoupper(trim($method))) {
        'BANK_TRANSFER' => 'Bank Transfer',
        'UPI' => 'UPI',
        'CASH' => 'Cash',
        'CHEQUE' => 'Cheque',
        default => 'Other',
    };
}

function reimbursement_storage_root(): string
{
    return dirname(UPLOAD_PATH) . '/reimbursements';
}

function reimbursement_relative_path(string $absolutePath): string
{
    $normalizedPath = str_replace('\\', '/', $absolutePath);
    $projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));

    return ltrim(str_replace($projectRoot, '', $normalizedPath), '/');
}

function reimbursement_admin_filter_params(array $source): array
{
    $employeeId = max(0, (int) ($source['filter_employee_id'] ?? $source['employee_id'] ?? 0));
    $category = strtoupper(trim((string) ($source['filter_category'] ?? $source['category'] ?? '')));

    $params = [];
    if ($employeeId > 0) {
        $params['employee_id'] = $employeeId;
    }
    if (in_array($category, reimbursement_categories(), true)) {
        $params['category'] = $category;
    }

    return $params;
}

function reimbursement_current_month(): string
{
    return date('Y-m');
}

function reimbursement_current_month_bounds(): array
{
    $month = reimbursement_current_month();
    $start = new DateTimeImmutable($month . '-01');
    $end = $start->modify('last day of this month');

    return [$start, $end];
}

function validate_reimbursement_expense_date(string $date): void
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new RuntimeException('Choose a valid reimbursement date.');
    }

    [$start, $end] = reimbursement_current_month_bounds();
    if ($date < $start->format('Y-m-d') || $date > $end->format('Y-m-d')) {
        throw new RuntimeException('Reimbursements can only be submitted for dates in the current month.');
    }

    if ($date > date('Y-m-d')) {
        throw new RuntimeException('Future dates are not allowed for reimbursement claims.');
    }
}

function validate_reimbursement_category(string $category): string
{
    $category = strtoupper(trim($category));
    if (!in_array($category, reimbursement_categories(), true)) {
        throw new RuntimeException('Choose a valid reimbursement category.');
    }

    return $category;
}

function validate_reimbursement_amount(string $amountText): float
{
    $amount = round((float) $amountText, 2);
    if ($amount <= 0) {
        throw new RuntimeException('Enter a reimbursement amount greater than zero.');
    }

    return $amount;
}

function validate_reimbursement_attachment_upload(array $file, string $label = 'reimbursement proof'): void
{
    validate_uploaded_file($file, ['jpg', 'jpeg', 'pdf'], 1024 * 1024, $label);

    $mime = uploaded_file_mime_type($file);
    if (!in_array($mime, ['image/jpeg', 'application/pdf'], true)) {
        throw new RuntimeException(ucfirst($label) . ' must be a JPG or PDF file.');
    }

    if (str_starts_with($mime, 'image/') && @getimagesize((string) ($file['tmp_name'] ?? '')) === false) {
        throw new RuntimeException(ucfirst($label) . ' is not a valid image.');
    }
}

function store_reimbursement_upload(array $file, string $folder, string $label = 'reimbursement proof'): array
{
    validate_reimbursement_attachment_upload($file, $label);

    $directory = reimbursement_storage_root() . '/' . trim($folder, '/');
    ensure_directory($directory);

    $extension = uploaded_file_extension($file) ?: 'jpg';
    $target = $directory . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($extension);
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('Unable to save the uploaded ' . $label . '.');
    }

    return [
        'path' => reimbursement_relative_path($target),
        'name' => (string) ($file['name'] ?? basename($target)),
        'mime' => uploaded_file_mime_type(['tmp_name' => $target]) ?: mime_content_type($target) ?: '',
    ];
}

function reimbursement_admin_query_base(): string
{
    return 'SELECT er.*, u.name AS employee_name, u.emp_id AS employee_emp_id, u.email AS employee_email
            FROM employee_reimbursements er
            JOIN users u ON u.id = er.user_id';
}

function admin_reimbursements(array $filters = []): array
{
    $admin = require_role('admin');
    $sql = reimbursement_admin_query_base() . ' WHERE er.admin_id = :admin_id';
    $params = ['admin_id' => (int) $admin['id']];

    if (!empty($filters['employee_id'])) {
        $sql .= ' AND er.user_id = :user_id';
        $params['user_id'] = (int) $filters['employee_id'];
    }

    if (!empty($filters['category']) && in_array((string) $filters['category'], reimbursement_categories(), true)) {
        $sql .= ' AND er.category = :category';
        $params['category'] = (string) $filters['category'];
    }

    $sql .= ' ORDER BY er.expense_date DESC, er.created_at DESC, er.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function admin_reimbursement_by_id(int $reimbursementId): ?array
{
    $admin = require_role('admin');
    $stmt = db()->prepare(reimbursement_admin_query_base() . ' WHERE er.id = :id AND er.admin_id = :admin_id LIMIT 1');
    $stmt->execute([
        'id' => $reimbursementId,
        'admin_id' => (int) $admin['id'],
    ]);

    $row = $stmt->fetch();
    return $row ?: null;
}

function employee_reimbursements_for_month(int $userId, string $month): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = reimbursement_current_month();
    }

    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');

    $stmt = db()->prepare('SELECT * FROM employee_reimbursements WHERE user_id = :user_id AND expense_date BETWEEN :start_date AND :end_date ORDER BY expense_date DESC, created_at DESC, id DESC');
    $stmt->execute([
        'user_id' => $userId,
        'start_date' => $start,
        'end_date' => $end,
    ]);

    return $stmt->fetchAll();
}

function employee_reimbursements_by_date_map(int $userId, string $month): array
{
    $items = employee_reimbursements_for_month($userId, $month);
    $map = [];

    foreach ($items as $item) {
        $date = (string) $item['expense_date'];
        if (!isset($map[$date])) {
            $map[$date] = [
                'count' => 0,
                'total' => 0.0,
                'items' => [],
            ];
        }

        $map[$date]['count']++;
        $map[$date]['total'] += (float) ($item['amount_requested'] ?? 0);
        $map[$date]['items'][] = $item;
    }

    return $map;
}

function employee_reimbursement_by_id(int $reimbursementId, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM employee_reimbursements WHERE id = :id AND user_id = :user_id LIMIT 1');
    $stmt->execute([
        'id' => $reimbursementId,
        'user_id' => $userId,
    ]);

    $row = $stmt->fetch();
    return $row ?: null;
}

function reimbursement_user_by_id(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);

    $row = $stmt->fetch();
    return $row ?: null;
}

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

function notify_admin_reimbursement_created(array $reimbursement, array $employee): void
{
    $admin = reimbursement_user_by_id((int) ($reimbursement['admin_id'] ?? 0));
    if (!$admin) {
        return;
    }

    create_notification_entry(
        (int) $admin['id'],
        'New reimbursement request',
        $employee['name'] . ' submitted a ' . strtolower((string) $reimbursement['category']) . ' reimbursement request for Rs ' . number_format((float) $reimbursement['amount_requested'], 2) . '.',
        'reimbursement',
        'employee_reimbursement',
        (int) $reimbursement['id'],
        (int) $employee['id']
    );

    if (filter_var((string) ($admin['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        send_reimbursement_created_email($admin, $employee, $reimbursement);
    }
}

function notify_employee_reimbursement_status(array $reimbursement, array $employee, ?float $paymentAmount = null): void
{
    create_notification_entry(
        (int) $employee['id'],
        'Reimbursement status updated',
        'Your reimbursement request for ' . date('d M Y', strtotime((string) $reimbursement['expense_date'])) . ' is now ' . reimbursement_status_label((string) $reimbursement['status']) . '.',
        'reimbursement',
        'employee_reimbursement',
        (int) $reimbursement['id'],
        current_user() ? (int) (current_user()['id'] ?? 0) : null
    );

    if (filter_var((string) ($employee['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        send_reimbursement_status_email($employee, $reimbursement, $paymentAmount);
    }
}

function create_employee_reimbursement(array $employee, string $date, array $source, array $file): array
{
    if (empty($employee['admin_id'])) {
        throw new RuntimeException('This employee is not linked to an admin account yet.');
    }

    validate_reimbursement_expense_date($date);

    $stmt = db()->prepare('SELECT COUNT(*) FROM employee_reimbursements WHERE user_id = :user_id AND expense_date = :expense_date');
    $stmt->execute([
        'user_id' => (int) $employee['id'],
        'expense_date' => $date,
    ]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('You have already submitted a reimbursement request for this date.');
    }

    $category = validate_reimbursement_category((string) ($source['category'] ?? ''));
    $description = trim((string) ($source['expense_description'] ?? ''));
    if ($description === '') {
        throw new RuntimeException('Expense description is required.');
    }

    $amountRequested = validate_reimbursement_amount((string) ($source['amount_requested'] ?? '0'));
    $attachment = store_reimbursement_upload($file, 'claims', 'reimbursement proof');

    try {
        db()->prepare('INSERT INTO employee_reimbursements (user_id, admin_id, expense_date, category, expense_description, amount_requested, amount_paid, remaining_balance, status, attachment_path, attachment_name, attachment_mime, payment_id, created_at, updated_at)
            VALUES (:user_id, :admin_id, :expense_date, :category, :expense_description, :amount_requested, 0, :remaining_balance, :status, :attachment_path, :attachment_name, :attachment_mime, NULL, :created_at, :updated_at)')
            ->execute([
                'user_id' => (int) $employee['id'],
                'admin_id' => (int) $employee['admin_id'],
                'expense_date' => $date,
                'category' => $category,
                'expense_description' => $description,
                'amount_requested' => $amountRequested,
                'remaining_balance' => $amountRequested,
                'status' => 'PENDING',
                'attachment_path' => $attachment['path'],
                'attachment_name' => $attachment['name'],
                'attachment_mime' => $attachment['mime'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    } catch (PDOException $exception) {
        // If the DB already has (or later gets) a UNIQUE(user_id, expense_date),
        // a double-submit can still race past the COUNT(*) check. Convert that into a friendly error.
        $sqlState = (string) ($exception->getCode() ?? '');
        $message = (string) ($exception->getMessage() ?? '');
        if ($sqlState === '23000' || stripos($message, 'Duplicate') !== false) {
            throw new RuntimeException('You have already submitted a reimbursement request for this date.');
        }
        throw $exception;
    }

    $createdId = (int) db()->lastInsertId();
    $created = employee_reimbursement_by_id($createdId, (int) $employee['id']);
    if (!$created) {
        throw new RuntimeException('Unable to load the newly created reimbursement.');
    }

    notify_admin_reimbursement_created($created, $employee);

    return $created;
}

function update_admin_reimbursement_status(int $reimbursementId, string $status): array
{
    $reimbursement = admin_reimbursement_by_id($reimbursementId);
    if (!$reimbursement) {
        throw new RuntimeException('Reimbursement request not found.');
    }

    $status = reimbursement_status_label($status);
    if (in_array($status, ['PAID', 'PARTIALLY PAID'], true)) {
        throw new RuntimeException('Use the dedicated payment flow for paid statuses.');
    }

    if ((float) ($reimbursement['amount_paid'] ?? 0) > 0 && in_array($status, ['PENDING', 'APPROVED', 'DENIED'], true)) {
        throw new RuntimeException('This reimbursement already has a recorded paid amount. Use the payment flow to finish it.');
    }

    db()->prepare('UPDATE employee_reimbursements SET status = :status, updated_at = :updated_at WHERE id = :id')
        ->execute([
            'status' => $status,
            'updated_at' => now(),
            'id' => $reimbursementId,
        ]);

    $updated = admin_reimbursement_by_id($reimbursementId);
    if (!$updated) {
        throw new RuntimeException('Unable to reload the reimbursement request.');
    }

    $employee = reimbursement_user_by_id((int) $updated['user_id']);
    if ($employee) {
        notify_employee_reimbursement_status($updated, $employee);
    }

    return $updated;
}

function mark_reimbursement_partially_paid(int $reimbursementId, float $partialAmount): array
{
    $reimbursement = admin_reimbursement_by_id($reimbursementId);
    if (!$reimbursement) {
        throw new RuntimeException('Reimbursement request not found.');
    }

    $partialAmount = round($partialAmount, 2);
    if ($partialAmount <= 0) {
        throw new RuntimeException('Enter a partial paid amount greater than zero.');
    }

    $remainingBefore = max((float) ($reimbursement['remaining_balance'] ?? 0), (float) ($reimbursement['amount_requested'] ?? 0) - (float) ($reimbursement['amount_paid'] ?? 0));
    if ($partialAmount >= $remainingBefore) {
        throw new RuntimeException('Partial amount must be less than the remaining balance. Use the PAID flow for the final settlement.');
    }

    $newAmountPaid = round((float) ($reimbursement['amount_paid'] ?? 0) + $partialAmount, 2);
    $newRemaining = round(max((float) ($reimbursement['amount_requested'] ?? 0) - $newAmountPaid, 0), 2);

    db()->prepare('UPDATE employee_reimbursements SET status = :status, amount_paid = :amount_paid, remaining_balance = :remaining_balance, updated_at = :updated_at WHERE id = :id')
        ->execute([
            'status' => 'PARTIALLY PAID',
            'amount_paid' => $newAmountPaid,
            'remaining_balance' => $newRemaining,
            'updated_at' => now(),
            'id' => $reimbursementId,
        ]);

    $updated = admin_reimbursement_by_id($reimbursementId);
    if (!$updated) {
        throw new RuntimeException('Unable to reload the reimbursement request.');
    }

    $employee = reimbursement_user_by_id((int) $updated['user_id']);
    if ($employee) {
        notify_employee_reimbursement_status($updated, $employee, $partialAmount);
    }

    return $updated;
}

function mark_reimbursement_paid(int $reimbursementId, array $source, array $file): array
{
    $reimbursement = admin_reimbursement_by_id($reimbursementId);
    if (!$reimbursement) {
        throw new RuntimeException('Reimbursement request not found.');
    }

    if (reimbursement_status_label((string) $reimbursement['status']) === 'PAID') {
        throw new RuntimeException('This reimbursement is already marked as paid.');
    }

    $paymentMethod = strtoupper(trim((string) ($source['payment_method'] ?? '')));
    if (!in_array($paymentMethod, reimbursement_payment_methods(), true)) {
        throw new RuntimeException('Choose a valid payment method.');
    }

    $bankDetails = trim((string) ($source['bank_details'] ?? ''));
    $transactionId = trim((string) ($source['transaction_id'] ?? ''));
    if ($bankDetails === '') {
        throw new RuntimeException('Bank details are required for paid reimbursements.');
    }
    if ($transactionId === '') {
        throw new RuntimeException('Transaction ID is required for paid reimbursements.');
    }

    $paymentAmount = round(max((float) ($reimbursement['remaining_balance'] ?? 0), (float) ($reimbursement['amount_requested'] ?? 0) - (float) ($reimbursement['amount_paid'] ?? 0)), 2);
    if ($paymentAmount <= 0) {
        $paymentAmount = round((float) ($reimbursement['amount_requested'] ?? 0), 2);
    }

    $proof = store_reimbursement_upload($file, 'payments', 'payment proof');

    db()->prepare('INSERT INTO reimbursement_payments (reimbursement_id, user_id, admin_id, amount_paid, payment_method, bank_details, transaction_id, proof_path, proof_name, proof_mime, created_at)
        VALUES (:reimbursement_id, :user_id, :admin_id, :amount_paid, :payment_method, :bank_details, :transaction_id, :proof_path, :proof_name, :proof_mime, :created_at)')
        ->execute([
            'reimbursement_id' => $reimbursementId,
            'user_id' => (int) $reimbursement['user_id'],
            'admin_id' => (int) $reimbursement['admin_id'],
            'amount_paid' => $paymentAmount,
            'payment_method' => $paymentMethod,
            'bank_details' => $bankDetails,
            'transaction_id' => $transactionId,
            'proof_path' => $proof['path'],
            'proof_name' => $proof['name'],
            'proof_mime' => $proof['mime'],
            'created_at' => now(),
        ]);

    $paymentId = (int) db()->lastInsertId();

    db()->prepare('UPDATE employee_reimbursements
        SET status = :status,
            amount_paid = :amount_paid,
            remaining_balance = 0,
            payment_id = :payment_id,
            updated_at = :updated_at
        WHERE id = :id')
        ->execute([
            'status' => 'PAID',
            'amount_paid' => round((float) ($reimbursement['amount_paid'] ?? 0) + $paymentAmount, 2),
            'payment_id' => $paymentId,
            'updated_at' => now(),
            'id' => $reimbursementId,
        ]);

    $updated = admin_reimbursement_by_id($reimbursementId);
    if (!$updated) {
        throw new RuntimeException('Unable to reload the reimbursement request.');
    }

    $employee = reimbursement_user_by_id((int) $updated['user_id']);
    if ($employee) {
        notify_employee_reimbursement_status($updated, $employee, $paymentAmount);
    }

    return $updated;
}
