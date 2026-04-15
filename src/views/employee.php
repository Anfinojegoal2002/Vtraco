<?php

declare(strict_types=1);

function render_employee_attendance(): void
{
    $employee = require_role('employee');
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $attendance = month_attendance_for_user((int) $employee['id'], $month);

    render_header('My Attendance');
    ?>
    <section class="banner employee-banner">
        <div class="employee-topbar">
            <div>
                <span class="eyebrow employee-workspace-badge">Employee Workspace</span>
                <h1><?= h($employee['name']) ?></h1>
                <p>Employee ID: <strong><?= h($employee['emp_id']) ?></strong></p>
                <p>Shift: <strong><?= h((string) (($employee['shift'] ?? '') ?: 'Not assigned')) ?></strong></p>
            </div>

        </div>
    </section>

    <div class="spacer"></div>
    <section class="page-title">
        <div>
            <span class="eyebrow">Monthly Attendance</span>
            <h2>Attendance Calendar</h2>
        </div>
        <form method="get" class="inline-actions">
            <input type="hidden" name="page" value="employee_attendance">
            <input type="month" name="month" value="<?= h($month) ?>">
            <button class="button solid" type="submit">Change Month</button>
        </form>
    </section>
    <div class="attendance-panel employee-attendance-panel">
        <?php render_calendar('employee', $employee, $month, $attendance); ?>
    </div>
    <?php
    render_footer();
}

function render_employee_reimbursements(): void
{
    $employee = require_role('employee');
    $month = reimbursement_current_month();
    $monthStart = new DateTimeImmutable($month . '-01');
    $monthEnd = $monthStart->modify('last day of this month');
    $today = date('Y-m-d');
    $claimsByDate = employee_reimbursements_by_date_map((int) $employee['id'], $month);
    $allClaims = employee_reimbursements_for_month((int) $employee['id'], $month);
    $startOffset = (int) $monthStart->format('w');
    $daysInMonth = (int) $monthEnd->format('j');
    $dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    $claimsPayload = [];
    foreach ($claimsByDate as $date => $meta) {
        $claimsPayload[$date] = array_map(static function (array $item): array {
            return [
                'category' => (string) $item['category'],
                'status' => (string) $item['status'],
                'amount_requested' => number_format((float) ($item['amount_requested'] ?? 0), 2, '.', ''),
                'amount_paid' => number_format((float) ($item['amount_paid'] ?? 0), 2, '.', ''),
                'remaining_balance' => number_format((float) ($item['remaining_balance'] ?? 0), 2, '.', ''),
                'expense_description' => (string) $item['expense_description'],
            ];
        }, $meta['items']);
    }

    render_header('My Reimbursements', 'employee-reimbursements-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Employee - Reimbursement</span>
            <h1>Monthly Reimbursement Calendar</h1>
            <p>Select any available date in the current month to submit a reimbursement. Future dates stay locked.</p>
        </div>
        <span class="badge"><?= h($monthStart->format('F Y')) ?></span>
    </section>

    <section class="section-block reimbursement-calendar-block">
        <div class="split">
            <div>
                <span class="eyebrow">Current Month Only</span>
                <h2>Reimbursement Dates</h2>
            </div>
            <div class="hint">Previous months are disabled for employee reimbursement submissions.</div>
        </div>
        <div class="spacer"></div>
        <div class="reimbursement-weekdays">
            <?php foreach ($dayLabels as $label): ?>
                <span><?= h($label) ?></span>
            <?php endforeach; ?>
        </div>
        <div class="reimbursement-calendar-grid">
            <?php for ($blank = 0; $blank < $startOffset; $blank++): ?>
                <div class="reimbursement-day placeholder" aria-hidden="true"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                $date = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), $day)->format('Y-m-d');
                $isFuture = $date > $today;
                $summary = $claimsByDate[$date] ?? ['count' => 0, 'total' => 0];
                $count = (int) ($summary['count'] ?? 0);
                $total = (float) ($summary['total'] ?? 0);
                ?>
                <?php if ($isFuture): ?>
                    <div class="reimbursement-day future">
                        <strong><?= $day ?></strong>
                        <span>Locked</span>
                    </div>
                <?php else: ?>
                    <button
                        class="reimbursement-day <?= $date === $today ? 'today' : '' ?>"
                        type="button"
                        data-modal-target="employee-reimbursement-modal"
                        data-reimbursement-date="<?= h($date) ?>"
                        data-reimbursement-display="<?= h(date('d M Y', strtotime($date))) ?>"
                    >
                        <strong><?= $day ?></strong>
                        <span><?= $count > 0 ? $count . ' claim' . ($count > 1 ? 's' : '') : 'Add claim' ?></span>
                        <?php if ($count > 0): ?>
                            <small>Rs <?= h(number_format($total, 2)) ?></small>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </section>

    <div class="spacer"></div>

    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Submitted This Month</span>
                <h2>Reimbursement Requests</h2>
            </div>
            <span class="badge"><?= count($allClaims) ?> submitted</span>
        </div>
        <div class="spacer"></div>
        <?php if ($allClaims): ?>
            <div class="reimbursement-history-list">
                <?php foreach ($allClaims as $claim): ?>
                    <article class="reimbursement-history-card">
                        <div class="split">
                            <div>
                                <strong><?= h(date('d M Y', strtotime((string) $claim['expense_date']))) ?> | <?= h((string) $claim['category']) ?></strong><br>
                                <span class="hint">Requested Rs <?= h(number_format((float) $claim['amount_requested'], 2)) ?></span>
                            </div>
                            <span class="status-pill reimbursement-status <?= h(reimbursement_status_badge_class((string) $claim['status'])) ?>"><?= h((string) $claim['status']) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <p><?= h((string) $claim['expense_description']) ?></p>
                        <div class="hint">Paid: Rs <?= h(number_format((float) $claim['amount_paid'], 2)) ?> | Remaining: Rs <?= h(number_format((float) $claim['remaining_balance'], 2)) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No reimbursement requests have been submitted in the current month yet.</div>
        <?php endif; ?>
    </section>

    <div class="modal" id="employee-reimbursement-modal">
        <div class="modal-card reimbursement-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Employee Reimbursement</span>
            <h2 id="employee-reimbursement-date-label">Select Date</h2>
            <div class="list reimbursement-existing-list" id="employee-reimbursement-existing-list">
                <div class="list-item muted">No reimbursement requests recorded for this date yet.</div>
            </div>
            <div class="spacer"></div>
            <form method="post" enctype="multipart/form-data" class="stack-form" id="employee-reimbursement-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="employee_submit_reimbursement">
                <input type="hidden" name="expense_date" id="employee-reimbursement-date-input">

                <div class="field">
                    <label>Category</label>
                    <div class="reimbursement-radio-group">
                        <?php foreach (reimbursement_categories() as $category): ?>
                            <label class="reimbursement-radio">
                                <input type="radio" name="category" value="<?= h($category) ?>" required>
                                <span><?= h($category) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="field-error"><span>!</span>Please choose a category.</small>
                </div>

                <div class="field">
                    <label>Expense Description</label>
                    <div class="field-row">
                        <textarea name="expense_description" rows="4" placeholder="Explain the expense briefly." required></textarea>
                    </div>
                    <small class="field-error"><span>!</span>Description is required.</small>
                </div>

                <div class="field">
                    <label>Amount</label>
                    <div class="field-row">
                        <input type="number" name="amount_requested" min="0.01" step="0.01" placeholder="Enter amount" required>
                    </div>
                    <small class="field-error"><span>!</span>Enter a valid amount.</small>
                </div>

                <div class="field">
                    <label>Upload File (JPG/PDF, max 1MB)</label>
                    <div class="field-row">
                        <input type="file" name="attachment" accept=".jpg,.jpeg,.pdf,image/jpeg,application/pdf" required>
                    </div>
                    <small class="field-error"><span>!</span>Upload a JPG or PDF file up to 1MB.</small>
                </div>

                <button class="button solid" type="submit">Submit Reimbursement</button>
            </form>
        </div>
    </div>

    <style>
        .reimbursement-calendar-block { overflow: hidden; }
        .reimbursement-weekdays { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; margin-bottom: 10px; }
        .reimbursement-weekdays span { text-align: center; font-weight: 700; color: #24346d; }
        .reimbursement-calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; }
        .reimbursement-day { min-height: 112px; padding: 14px 12px; border: 1px solid rgba(36, 52, 109, 0.14); border-radius: 18px; background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(243,246,255,0.95)); display: flex; flex-direction: column; justify-content: space-between; align-items: flex-start; color: #1f2f67; text-align: left; }
        .reimbursement-day.today { border-color: rgba(67, 56, 202, 0.35); box-shadow: 0 14px 26px rgba(67, 56, 202, 0.12); }
        .reimbursement-day.future { opacity: 0.58; cursor: not-allowed; background: #eef2ff; }
        .reimbursement-day.placeholder { visibility: hidden; min-height: 0; padding: 0; border: 0; }
        .reimbursement-day strong { font-size: 1.1rem; }
        .reimbursement-day small { color: #475569; font-weight: 700; }
        .reimbursement-history-list { display: grid; gap: 14px; }
        .reimbursement-history-card { padding: 18px; border-radius: 22px; background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(246,248,255,0.96)); border: 1px solid rgba(36, 52, 109, 0.1); }
        .reimbursement-modal-card { max-width: 760px; }
        .reimbursement-existing-list { max-height: 220px; overflow: auto; }
        .reimbursement-radio-group { display: flex; gap: 12px; flex-wrap: wrap; }
        .reimbursement-radio { display: inline-flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px solid rgba(36, 52, 109, 0.12); border-radius: 16px; cursor: pointer; background: rgba(248, 250, 255, 0.9); }
        .reimbursement-radio input { width: auto; min-height: auto; margin: 0; }
        .reimbursement-status.pending { background: #e5e7eb; color: #374151; }
        .reimbursement-status.approved { background: #fef3c7; color: #92400e; }
        .reimbursement-status.denied { background: #fee2e2; color: #b91c1c; }
        .reimbursement-status.partially-paid { background: #e0f2fe; color: #0369a1; }
        .reimbursement-status.paid { background: #dcfce7; color: #166534; }
        @media (max-width: 900px) {
            .reimbursement-calendar-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .reimbursement-weekdays { display: none; }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const claimsByDate = <?= json_encode($claimsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const modalDateLabel = document.getElementById('employee-reimbursement-date-label');
            const modalDateInput = document.getElementById('employee-reimbursement-date-input');
            const claimsList = document.getElementById('employee-reimbursement-existing-list');
            const form = document.getElementById('employee-reimbursement-form');

            document.querySelectorAll('[data-reimbursement-date]').forEach(button => {
                button.addEventListener('click', () => {
                    const date = button.dataset.reimbursementDate || '';
                    const displayDate = button.dataset.reimbursementDisplay || date;
                    const dayClaims = claimsByDate[date] || [];

                    if (form) {
                        form.reset();
                    }
                    if (modalDateInput) {
                        modalDateInput.value = date;
                    }
                    if (modalDateLabel) {
                        modalDateLabel.textContent = displayDate;
                    }
                    if (!claimsList) {
                        return;
                    }

                    if (!dayClaims.length) {
                        claimsList.innerHTML = '<div class="list-item muted">No reimbursement requests recorded for this date yet.</div>';
                        return;
                    }

                    claimsList.innerHTML = dayClaims.map(item => `
                        <div class="list-item">
                            <div class="split">
                                <div>
                                    <strong>${item.category}</strong><br>
                                    <span class="hint">Requested Rs ${item.amount_requested} | Paid Rs ${item.amount_paid}</span>
                                </div>
                                <span class="status-pill reimbursement-status ${String(item.status).toLowerCase().replace(/\s+/g, '-').replace('partially-paid', 'partially-paid')}">${item.status}</span>
                            </div>
                            <div class="spacer"></div>
                            <div class="hint">${item.expense_description}</div>
                            <div class="spacer"></div>
                            <div class="hint">Remaining Rs ${item.remaining_balance}</div>
                        </div>
                    `).join('');
                });
            });
        });
    </script>
    <?php
    render_footer();
}

