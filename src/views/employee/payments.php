<?php

declare(strict_types=1);

function render_employee_payments(): void
{
    $employee = require_role('corporate_employee');
    if (employee_is_vendor_trainer($employee)) {
        flash('error', 'Payment is not available for vendor trainers.');
        redirect_to('employee_attendance');
    }
    $requestDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['payment_date'] ?? ''))
        ? (string) $_GET['payment_date']
        : date('Y-m-d');
    $requestAmount = function_exists('contractual_payment_amount_due_for_date')
        ? contractual_payment_amount_due_for_date($employee, $requestDate)
        : 0.0;
    $currentRequest = function_exists('contractual_payment_request_for_employee_date')
        ? contractual_payment_request_for_employee_date((int) $employee['id'], $requestDate)
        : null;

    $stmt = db()->prepare('SELECT * FROM payments WHERE user_id = :user_id ORDER BY payment_date DESC, id DESC');
    $stmt->execute(['user_id' => (int) $employee['id']]);
    $payments = $stmt->fetchAll();
    $totalPaid = array_reduce($payments, static fn(float $sum, array $payment): float => $sum + (float) ($payment['amount'] ?? 0), 0.0);

    render_header('My Payment', 'employee-payments-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Contractual Employee - Payment</span>
            <h1>Payment</h1>
            <p>View payment records processed for your contractual work.</p>
        </div>
    </section>

    <section class="dashboard-grid">
        <div class="metric-card">
            <span class="eyebrow">Total Paid</span>
            <strong>Rs <?= h(number_format($totalPaid, 2)) ?></strong>
            <span>Across all payment records</span>
        </div>
        <div class="metric-card">
            <span class="eyebrow">Payments</span>
            <strong><?= count($payments) ?></strong>
            <span>Total records</span>
        </div>
    </section>

    <div class="spacer"></div>

    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Payment Request</span>
                <h2>Request Payment</h2>
                <p class="hint">Send a request to admin for completed project-record payment.</p>
            </div>
        </div>
        <div class="spacer"></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Selected Date</th>
                        <th>Available Amount</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <form method="get" class="inline-actions">
                                <input type="hidden" name="page" value="employee_payments">
                                <input type="date" name="payment_date" value="<?= h($requestDate) ?>">
                                <button class="button outline small" type="submit">Show</button>
                            </form>
                        </td>
                        <td>Rs <?= h(number_format($requestAmount, 2)) ?></td>
                        <td>
                            <?php if ($currentRequest): ?>
                                <span class="status-pill status-<?= h((string) ($currentRequest['status'] ?? 'PENDING')) ?>"><?= h(ucfirst(strtolower((string) ($currentRequest['status'] ?? 'PENDING')))) ?></span>
                            <?php else: ?>
                                <span class="status-pill status-Pending">Not Requested</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" id="contractual-payment-request-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="employee_contractual_payment_request">
                                <input type="hidden" name="request_date" value="<?= h($requestDate) ?>">
                                <textarea name="note" rows="2" placeholder="Optional note for admin"></textarea>
                            </form>
                        </td>
                        <td>
                            <button class="button solid small" form="contractual-payment-request-form" type="submit" <?= $requestAmount <= 0 || ($currentRequest && in_array((string) ($currentRequest['status'] ?? ''), ['PENDING', 'APPROVED'], true)) ? 'disabled' : '' ?>>Request</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="spacer"></div>

    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2>Payment History</h2>
                <span class="badge"><?= count($payments) ?> payment(s)</span>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Transfer</th>
                    <th>Transaction ID</th>
                    <th>Remarks</th>
                    <th>Proof</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($payments): ?>
                    <?php foreach ($payments as $payment): ?>
                        <?php
                        $methods = function_exists('payment_methods_for_record') ? payment_methods_for_record($payment) : [];
                        $proofUrl = !empty($payment['proof_path']) ? asset_url((string) $payment['proof_path']) : '';
                        ?>
                        <tr>
                            <td><?= h(date('d M Y', strtotime((string) ($payment['payment_date'] ?? date('Y-m-d'))))) ?></td>
                            <td><?= h((string) ($payment['payment_type'] ?? '-')) ?></td>
                            <td>Rs <?= h(number_format((float) ($payment['amount'] ?? 0), 2)) ?></td>
                            <td><?= h($methods !== [] && function_exists('payment_methods_label') ? payment_methods_label($methods) : (string) (($payment['bank_name'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($payment['transfer_mode'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($payment['transaction_id'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($payment['remarks'] ?? '') ?: '-')) ?></td>
                            <td>
                                <?php if ($proofUrl !== ''): ?>
                                    <a class="button ghost small" href="<?= h($proofUrl) ?>" target="_blank" rel="noopener">Open</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="muted center">No payment records are available yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php
    render_footer();
}


