<?php

declare(strict_types=1);

function render_employee_reimbursements(): void
{
    $employee = require_roles(['employee', 'corporate_employee']);
    if (employee_is_vendor_trainer($employee)) {
        flash('error', 'Reimbursement is not available for vendor trainers.');
        redirect_to('employee_attendance');
    }
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
            <p class="hint"><strong>Note:</strong> Once your employer marks a reimbursement as paid, you will receive your payslip by email from the employer.</p>
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
        <div class="reimbursement-calendar-scroll">
            <div class="calendar-grid reimbursement-calendar-grid">
                <?php foreach ($dayLabels as $label): ?>
                    <div class="weekday"><?= h($label) ?></div>
                <?php endforeach; ?>

                <?php for ($blank = 0; $blank < $startOffset; $blank++): ?>
                    <div class="day-card blank"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++):
                    $date = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), $day)->format('Y-m-d');
                    $isFuture = $date > $today;
                    $summary = $claimsByDate[$date] ?? ['count' => 0, 'total' => 0];
                    $count = (int) ($summary['count'] ?? 0);
                    $total = (float) ($summary['total'] ?? 0);
                    $dayCopy = $isFuture ? 'Locked' : ($count > 0 ? ($count . ' claim' . ($count > 1 ? 's' : '')) : 'Add claim');
                    ?>
                    <?php if ($isFuture): ?>
                        <div class="day-card static">
                            <span class="day-dot dot-Pending" aria-hidden="true"></span>
                            <span class="day-number"><?= sprintf('%02d', $day) ?></span>
                            <span class="day-copy"><?= h($dayCopy) ?></span>
                        </div>
                    <?php else: ?>
                        <button
                            class="day-card"
                            type="button"
                            data-modal-target="employee-reimbursement-modal"
                            data-reimbursement-date="<?= h($date) ?>"
                            data-reimbursement-display="<?= h(date('d M Y', strtotime($date))) ?>"
                        >
                            <span class="day-dot <?= $count > 0 ? 'dot-Present' : 'dot-Pending' ?>" aria-hidden="true"></span>
                            <span class="day-number"><?= sprintf('%02d', $day) ?></span>
                            <span class="day-copy"><?= h($dayCopy) ?></span>
                            <?php if ($count > 0): ?>
                                <span class="day-copy">Rs <?= h(number_format($total, 2)) ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
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
            <div class="list-item muted hidden" id="employee-reimbursement-locked-note">You can submit up to 3 reimbursement requests for this date.</div>
            <div class="spacer hidden" id="employee-reimbursement-locked-spacer"></div>
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
                    <label>Upload File (JPG/PDF, max 5MB)</label>
                    <div class="field-row">
                        <input type="file" name="attachment" accept=".jpg,.jpeg,.pdf,image/jpeg,application/pdf" required>
                    </div>
                    <small class="field-error"><span>!</span>Upload a JPG or PDF file up to 5MB.</small>
                </div>

                <button class="button solid" type="submit">Submit Reimbursement</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const claimsByDate = <?= json_encode($claimsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const modalDateLabel = document.getElementById('employee-reimbursement-date-label');
            const modalDateInput = document.getElementById('employee-reimbursement-date-input');
            const claimsList = document.getElementById('employee-reimbursement-existing-list');
            const form = document.getElementById('employee-reimbursement-form');
            const lockedNote = document.getElementById('employee-reimbursement-locked-note');
            const lockedSpacer = document.getElementById('employee-reimbursement-locked-spacer');
            const formControls = form ? Array.from(form.querySelectorAll('input, textarea, select, button')) : [];
            const submitButton = form ? form.querySelector('button[type="submit"]') : null;
            const submitButtonText = submitButton ? submitButton.textContent : 'Submit Reimbursement';

            if (form) {
                form.addEventListener('submit', (event) => {
                    // Prevent double-submit (fast clicks / slow uploads).
                    if (form.dataset.submitting === '1') {
                        event.preventDefault();
                        return;
                    }
                    form.dataset.submitting = '1';
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = 'Submitting...';
                    }
                });
            }

            document.querySelectorAll('[data-reimbursement-date]').forEach(button => {
                button.addEventListener('click', () => {
                    const date = button.dataset.reimbursementDate || '';
                    const displayDate = button.dataset.reimbursementDisplay || date;
                    const dayClaims = claimsByDate[date] || [];

                    if (form) {
                        form.reset();
                        form.dataset.submitting = '0';
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

                    const hasReachedLimit = dayClaims.length >= 3;

                    if (lockedNote && lockedSpacer) {
                        lockedNote.classList.toggle('hidden', !hasReachedLimit);
                        lockedSpacer.classList.toggle('hidden', !hasReachedLimit);
                    }
                    formControls.forEach(control => {
                        if (control && control.name === '_csrf') {
                            return;
                        }
                        if (control && control.type === 'hidden') {
                            return;
                        }
                        control.disabled = hasReachedLimit;
                    });
                    if (submitButton) {
                        submitButton.textContent = submitButtonText;
                        submitButton.disabled = hasReachedLimit;
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


