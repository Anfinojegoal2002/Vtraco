<?php

declare(strict_types=1);

function render_admin_dashboard(): void
{
    require_roles(['admin', 'freelancer']);
    $snapshot = attendance_snapshot_for_date();
    $counts = $snapshot['counts'];
    $details = $snapshot['details'];
    $displayDate = date('d M Y', strtotime($snapshot['date']));

    $user = current_user();
    $isFreelancer = ($user['role'] ?? '') === 'freelancer';
    $label = 'Employee';
    $dashboardReimbursements = $isFreelancer ? [] : admin_recent_reimbursements(12, null, 24);

    render_header($isFreelancer ? 'Employee Dashboard' : 'Admin Dashboard');
    ?>
    <!-- Only reimbursement section is shown. Salary and incentive payment options are removed as requested. -->
    <?php if (!$isFreelancer): ?>
        <section class="section-block dashboard-reimbursement-register">
            <div class="split">
                <div>
                    <span class="eyebrow">Reimbursement Register</span>
                    <h2>Last 24 Hours</h2>
                    <p class="hint">Requests automatically leave this dashboard view after 24 hours.</p>
                </div>
                <div class="action-bar">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="export_reimbursements_excel">
                        <button class="button outline small" type="submit">Download Excel</button>
                    </form>
                    <a class="button outline small" href="<?= h(BASE_URL) ?>?page=admin_reimbursements">View All</a>
                </div>
            </div>
            <div class="spacer"></div>
            <div class="table-wrap dashboard-excel-wrap">
                <table class="dashboard-excel-table">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Date</th>
                            <th>Emp ID</th>
                            <th>Employee Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Requested</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Proof</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($dashboardReimbursements): ?>
                            <?php foreach ($dashboardReimbursements as $index => $row): ?>
                                <?php $status = reimbursement_status_label((string) ($row['status'] ?? '')); ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= h(date('d-m-Y', strtotime((string) ($row['expense_date'] ?? 'now')))) ?></td>
                                    <td><?= h((string) ($row['employee_emp_id'] ?? '-')) ?></td>
                                    <td><?= h((string) ($row['employee_name'] ?? '-')) ?></td>
                                    <td><?= h((string) ($row['category'] ?? '-')) ?></td>
                                    <td><?= h((string) ($row['expense_description'] ?? '-')) ?></td>
                                    <td>Rs <?= h(number_format((float) ($row['amount_requested'] ?? 0), 2)) ?></td>
                                    <td>Rs <?= h(number_format((float) ($row['amount_paid'] ?? 0), 2)) ?></td>
                                    <td>Rs <?= h(number_format((float) ($row['remaining_balance'] ?? 0), 2)) ?></td>
                                    <td><span class="status-pill reimbursement-status <?= h(reimbursement_status_badge_class($status)) ?>"><?= h($status) ?></span></td>
                                    <td>
                                        <?php $proofUrl = reimbursement_attachment_url($row); ?>
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
                                <td colspan="11" class="muted center">No reimbursement requests in the last 24 hours.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <style>
            .dashboard-reimbursement-register { overflow: hidden; }
            .dashboard-excel-wrap { overflow-x: auto; border: 1px solid rgba(30,41,59,0.12); border-radius: 8px; background: #ffffff; }
            .dashboard-excel-table { min-width: 1120px; width: 100%; border-collapse: collapse; font-size: 0.86rem; }
            .dashboard-excel-table th,
            .dashboard-excel-table td { border: 1px solid rgba(30,41,59,0.16); padding: 9px 10px; text-align: left; vertical-align: top; background: #ffffff; }
            .dashboard-excel-table th { background: #eaf1ff; color: #172554; font-weight: 800; white-space: nowrap; }
            .dashboard-excel-table tbody tr:nth-child(even) td { background: #f8fafc; }
            .dashboard-excel-table td:nth-child(1),
            .dashboard-excel-table td:nth-child(7),
            .dashboard-excel-table td:nth-child(8),
            .dashboard-excel-table td:nth-child(9) { white-space: nowrap; }
        </style>
    <?php endif; ?>

    <?php
    render_footer();
}

function render_corporate_dashboard(): void
{
    $freelancer = require_role('freelancer');

    $snapshot = attendance_snapshot_for_date();
    $counts = $snapshot['counts'];
    $details = $snapshot['details'];
    $displayDate = date('d M Y', strtotime($snapshot['date']));

    render_header('Employee Dashboard', 'corporate-dashboard-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Employee Dashboard</span>
            <h1>Employee Dashboard</h1>
            <p>Track attendance for <?= h($displayDate) ?>.</p>
        </div>
    </section>

    <section class="dashboard-grid">
        <div class="metric-card">
            <span class="eyebrow">Employee</span>
            <strong><?= employee_count() ?></strong>
            <span>Total Employees</span>
        </div>
        <div class="metric-card">
            <span class="eyebrow">Today</span>
            <strong><?= (int) ($counts['Present'] ?? 0) ?></strong>
            <span>Present</span>
        </div>
        <div class="metric-card">
            <span class="eyebrow">Today</span>
            <strong><?= (int) ($counts['Absent'] ?? 0) ?></strong>
            <span>Absent</span>
        </div>
    </section>

    <?php
    render_footer();
}

function render_vendor_dashboard(): void
{
    $vendor = require_role('external_vendor');

    $snapshot = attendance_snapshot_for_date();
    $counts = $snapshot['counts'];
    $displayDate = date('d M Y', strtotime($snapshot['date']));

    render_header('Vendor Dashboard', 'vendor-dashboard-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Vendor Dashboard</span>
            <h1>Vendor Dashboard</h1>
            <p>Track attendance for <?= h($displayDate) ?>.</p>
        </div>
    </section>

    <section class="dashboard-grid">
        <div class="metric-card">
            <span class="eyebrow">Employee</span>
            <strong><?= employee_count() ?></strong>
            <span>Total Employees</span>
        </div>
        <div class="metric-card">
            <span class="eyebrow">Today</span>
            <strong><?= (int) ($counts['Present'] ?? 0) ?></strong>
            <span>Present</span>
        </div>
        <div class="metric-card">
            <span class="eyebrow">Today</span>
            <strong><?= (int) ($counts['Absent'] ?? 0) ?></strong>
            <span>Absent</span>
        </div>
    </section>
    <?php
    render_footer();
}

function render_vendor_payments(): void
{
    $vendor = require_role('external_vendor');
    $selectedEmployeeId = max(0, (int) ($_GET['employee_id'] ?? 0));
    $selectedDate = trim((string) ($_GET['payment_date'] ?? date('Y-m-d')));
    $selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) ? $selectedDate : date('Y-m-d');
    $payView = strtolower(trim((string) ($_GET['pay_view'] ?? 'invoice')));
    $payView = in_array($payView, ['invoice', 'reimbursement'], true) ? $payView : 'invoice';

    $employees = employees();
    $employeeIds = array_map(static fn(array $employee): int => (int) ($employee['id'] ?? 0), $employees);
    if ($selectedEmployeeId > 0 && !in_array($selectedEmployeeId, $employeeIds, true)) {
        $selectedEmployeeId = 0;
    }

    $projectPaymentRows = [];
    foreach ($employees as $employee) {
        if ($selectedEmployeeId > 0 && (int) ($employee['id'] ?? 0) !== $selectedEmployeeId) {
            continue;
        }

        foreach (employee_available_projects_for_date($employee, $selectedDate) as $project) {
            $sessionSalary = project_session_salary_for_date($employee, (int) ($project['id'] ?? 0), $selectedDate);
            $paymentAmount = (float) ($sessionSalary['amount'] ?? 0);
            if ($paymentAmount <= 0) {
                $paymentAmount = project_assignment_payment_amount($project, $sessionSalary);
            }
            $projectPaymentRows[] = [
                'employee' => $employee,
                'project' => $project,
                'payment' => round($paymentAmount, 2),
                'hours' => round((float) ($sessionSalary['hours'] ?? 0), 2),
            ];
        }
    }
    $invoiceRequests = vendor_payment_invoice_requests((int) $vendor['id'], [
        'user_id' => $selectedEmployeeId,
        'invoice_date' => $selectedDate,
    ]);
    $requestLookup = [];
    foreach ($invoiceRequests as $requestRow) {
        $key = (int) ($requestRow['user_id'] ?? 0) . ':' . (int) ($requestRow['project_id'] ?? 0) . ':' . (string) ($requestRow['invoice_date'] ?? '');
        $requestLookup[$key] = $requestRow;
    }

    render_header('Payment', 'vendor-payments-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Vendor Dashboard</span>
            <h1>Payment</h1>
            <p>Select trainer payments and request invoice approval from admin.</p>
        </div>
    </section>

    <section class="section-block accounts-filter-shell">
        <form method="get" class="accounts-history-filter-grid">
            <input type="hidden" name="page" value="vendor_payments">
            <input type="hidden" name="pay_view" value="<?= h($payView) ?>">
            <div class="field">
                <label>Select Trainer</label>
                <select name="employee_id" required>
                    <option value="">Select trainer</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?= (int) ($employee['id'] ?? 0) ?>" <?= $selectedEmployeeId === (int) ($employee['id'] ?? 0) ? 'selected' : '' ?>><?= h((string) ($employee['name'] ?? '')) ?> (<?= h((string) (($employee['emp_id'] ?? '') ?: '-')) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Date</label>
                <input type="date" name="payment_date" value="<?= h($selectedDate) ?>" required>
            </div>
            <div class="accounts-toolbar-actions">
                <button class="button solid" type="submit">Show Payment</button>
                <a class="button outline" href="<?= h(BASE_URL) ?>?page=vendor_payments">Reset</a>
            </div>
        </form>
    </section>

    <div class="spacer"></div>
    <section class="section-block accounts-tabs-panel">
        <nav class="employee-tabs inline" aria-label="Vendor payment options">
            <a class="tab-link <?= $payView === 'invoice' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?<?= h(http_build_query([
                'page' => 'vendor_payments',
                'pay_view' => 'invoice',
                'employee_id' => $selectedEmployeeId ?: '',
                'payment_date' => $selectedDate,
            ])) ?>">Invoice Pay</a>
            <a class="tab-link <?= $payView === 'reimbursement' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?<?= h(http_build_query([
                'page' => 'vendor_payments',
                'pay_view' => 'reimbursement',
                'employee_id' => $selectedEmployeeId ?: '',
                'payment_date' => $selectedDate,
            ])) ?>">Reimbursement</a>
        </nav>
    </section>

    <div class="spacer"></div>
    <form method="post" id="vendor-payment-request-form" class="vendor-payment-request-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="vendor_request_payment_invoice">
        <?php if ($payView === 'invoice'): ?>
        <section class="table-wrap vendor-payment-table-wrap">
            <div class="data-toolbar">
                <div class="split">
                    <h2>Invoice Pay</h2>
                    <span class="badge"><?= count($projectPaymentRows) ?> record(s)</span>
                </div>
            </div>
            <table class="vendor-payment-table">
                <thead>
                    <tr>
                        <th>Select</th>
                        <th>Invoice</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($projectPaymentRows): ?>
                        <?php foreach ($projectPaymentRows as $index => $row): ?>
                            <?php
                                $employee = $row['employee'];
                                $project = $row['project'];
                                $lookupKey = (int) ($employee['id'] ?? 0) . ':' . (int) ($project['id'] ?? 0) . ':' . $selectedDate;
                                $existingRequest = $requestLookup[$lookupKey] ?? null;
                                $requestStatus = strtoupper((string) ($existingRequest['status'] ?? ''));
                                $paymentAmount = round((float) ($row['payment'] ?? 0), 2);
                                $invoicePayload = [
                                    'title' => $existingRequest ? 'Requested Invoice' : 'Invoice Preview',
                                    'trainer' => (string) ($employee['name'] ?? ''),
                                    'empId' => (string) (($employee['emp_id'] ?? '') ?: '-'),
                                    'project' => (string) ($project['project_name'] ?? ''),
                                    'date' => date('d M Y', strtotime($selectedDate)),
                                    'amount' => number_format($paymentAmount, 2),
                                    'hours' => (float) ($row['hours'] ?? 0) > 0 ? number_format((float) ($row['hours'] ?? 0), 2) : '',
                                    'status' => $existingRequest ? $requestStatus : 'NOT REQUESTED',
                                    'invoiceId' => $existingRequest ? (string) ($existingRequest['id'] ?? '') : '',
                                    'requestedAt' => $existingRequest && !empty($existingRequest['created_at']) ? date('d M Y, h:i A', strtotime((string) $existingRequest['created_at'])) : '',
                                ];
                                $itemValue = implode('|', [
                                    (int) ($employee['id'] ?? 0),
                                    (int) ($project['id'] ?? 0),
                                    $selectedDate,
                                    number_format($paymentAmount, 2, '.', ''),
                                ]);
                            ?>
                            <tr class="vendor-payment-row<?= $existingRequest ? ' is-requested' : '' ?>" data-payment-row>
                                <td data-label="Select">
                                    <input
                                        type="checkbox"
                                        class="vendor-invoice-checkbox"
                                        <?= $existingRequest ? 'disabled' : 'name="invoice_items[]"' ?>
                                        value="<?= h($itemValue) ?>"
                                        <?= $existingRequest ? '' : 'data-vendor-payment-checkbox' ?>
                                        data-trainer="<?= h((string) ($employee['name'] ?? '')) ?>"
                                        data-project="<?= h((string) ($project['project_name'] ?? '')) ?>"
                                        data-date="<?= h(date('d M Y', strtotime($selectedDate))) ?>"
                                        data-amount="<?= h(number_format($paymentAmount, 2, '.', '')) ?>"
                                    >
                                </td>
                                <td data-label="Invoice">
                                    <div class="vendor-invoice-cell">
                                        <button class="button outline small" type="button" data-vendor-invoice-view="<?= h(json_encode($invoicePayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">View</button>
                                        <?php if ($existingRequest): ?>
                                            <small class="hint"><?= h($requestStatus) ?><?= !empty($existingRequest['id']) ? ' #' . (int) $existingRequest['id'] : '' ?></small>
                                        <?php else: ?>
                                            <small class="hint">Ready to request</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Payment">
                                    <strong><?= h((string) ($employee['name'] ?? '')) ?></strong>
                                    <span><?= h((string) ($project['project_name'] ?? '')) ?></span>
                                    <small class="hint"><?= h(date('d M Y', strtotime($selectedDate))) ?></small>
                                    <strong class="vendor-session-salary">Rs <?= h(number_format($paymentAmount, 2)) ?></strong>
                                    <?php if ((float) ($row['hours'] ?? 0) > 0): ?>
                                        <small class="hint"><?= h(number_format((float) ($row['hours'] ?? 0), 2)) ?> hour(s)</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="muted center"><?= $selectedEmployeeId > 0 ? 'No project payment found for this trainer and date.' : 'Select a trainer and date to view payment.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <div class="vendor-payment-action-bar" data-vendor-payment-bar>
            <div>
                <strong><span data-vendor-selected-count>0</span> selected</strong>
                <span>Total: <b>Rs <span data-vendor-selected-total>0.00</span></b></span>
            </div>
            <button class="button solid" type="button" data-vendor-next disabled>Next &rarr;</button>
        </div>

        <div class="vendor-payment-modal" data-vendor-payment-modal hidden>
            <div class="vendor-payment-modal-card">
                <button class="modal-close" type="button" data-vendor-modal-close>&times;</button>
                <div data-vendor-payment-review>
                    <span class="eyebrow">Payment Request</span>
                    <h2>Review Invoice Request</h2>
                    <div class="vendor-payment-summary-list" data-vendor-summary-list></div>
                    <div class="vendor-payment-total-row">
                        <span>Total Requested Pay</span>
                        <strong>Rs <span data-vendor-modal-total>0.00</span></strong>
                    </div>
                    <button class="button solid vendor-request-payment-button" type="button" data-vendor-request-payment>Request Payment</button>
                </div>
                <div class="vendor-payment-success hidden" data-vendor-payment-success>
                    <div class="vendor-success-icon" aria-hidden="true">&#10003;</div>
                    <h2>Payment request sent successfully!</h2>
                    <p>The admin will review and process your invoice.</p>
                </div>
            </div>
        </div>
        <div class="vendor-payment-modal" data-vendor-invoice-modal hidden>
            <div class="vendor-payment-modal-card">
                <button class="modal-close" type="button" data-vendor-invoice-close>&times;</button>
                <span class="eyebrow">Invoice</span>
                <h2 data-vendor-invoice-title>Requested Invoice</h2>
                <div class="vendor-invoice-detail-grid">
                    <div><strong>Trainer</strong><span data-vendor-invoice-trainer>-</span></div>
                    <div><strong>Emp ID</strong><span data-vendor-invoice-emp>-</span></div>
                    <div><strong>Project</strong><span data-vendor-invoice-project>-</span></div>
                    <div><strong>Date</strong><span data-vendor-invoice-date>-</span></div>
                    <div><strong>Status</strong><span data-vendor-invoice-status>-</span></div>
                    <div><strong>Invoice ID</strong><span data-vendor-invoice-id>-</span></div>
                    <div><strong>Requested At</strong><span data-vendor-invoice-requested>-</span></div>
                    <div><strong>Hours</strong><span data-vendor-invoice-hours>-</span></div>
                    <div class="vendor-invoice-detail-amount"><strong>Payment</strong><span>Rs <b data-vendor-invoice-amount>0.00</b></span></div>
                </div>
            </div>
        </div>
        <?php else: ?>
            <section class="table-wrap vendor-payment-table-wrap">
                <div class="data-toolbar">
                    <div class="split">
                        <h2>Reimbursement</h2>
                        <span class="badge">0 record(s)</span>
                    </div>
                </div>
                <div class="list-item muted" style="display:block; padding:16px;">No reimbursement items are available for this vendor payment view.</div>
            </section>
        <?php endif; ?>
    </form>

    <script>
        (() => {
            const form = document.getElementById('vendor-payment-request-form');
            if (!form) return;

            const checkboxes = Array.from(form.querySelectorAll('[data-vendor-payment-checkbox]'));
            const nextButton = form.querySelector('[data-vendor-next]');
            const selectedCount = form.querySelector('[data-vendor-selected-count]');
            const selectedTotal = form.querySelector('[data-vendor-selected-total]');
            const modal = form.querySelector('[data-vendor-payment-modal]');
            const modalClose = form.querySelector('[data-vendor-modal-close]');
            const summaryList = form.querySelector('[data-vendor-summary-list]');
            const modalTotal = form.querySelector('[data-vendor-modal-total]');
            const requestButton = form.querySelector('[data-vendor-request-payment]');
            const reviewPanel = form.querySelector('[data-vendor-payment-review]');
            const successPanel = form.querySelector('[data-vendor-payment-success]');
            const invoiceModal = form.querySelector('[data-vendor-invoice-modal]');
            const invoiceClose = form.querySelector('[data-vendor-invoice-close]');
            const invoiceFields = {
                title: form.querySelector('[data-vendor-invoice-title]'),
                trainer: form.querySelector('[data-vendor-invoice-trainer]'),
                empId: form.querySelector('[data-vendor-invoice-emp]'),
                project: form.querySelector('[data-vendor-invoice-project]'),
                date: form.querySelector('[data-vendor-invoice-date]'),
                status: form.querySelector('[data-vendor-invoice-status]'),
                invoiceId: form.querySelector('[data-vendor-invoice-id]'),
                requestedAt: form.querySelector('[data-vendor-invoice-requested]'),
                hours: form.querySelector('[data-vendor-invoice-hours]'),
                amount: form.querySelector('[data-vendor-invoice-amount]')
            };

            const money = (value) => Number(value || 0).toFixed(2);
            const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
            const selected = () => checkboxes.filter((checkbox) => checkbox.checked);

            const updateState = () => {
                let total = 0;
                checkboxes.forEach((checkbox) => {
                    const row = checkbox.closest('[data-payment-row]');
                    if (row) row.classList.toggle('selected', checkbox.checked);
                    if (checkbox.checked) total += Number(checkbox.dataset.amount || 0);
                });
                const count = selected().length;
                if (selectedCount) selectedCount.textContent = String(count);
                if (selectedTotal) selectedTotal.textContent = money(total);
                if (nextButton) nextButton.disabled = count === 0;
            };

            const openModal = () => {
                const rows = selected();
                if (!rows.length || !modal || !summaryList) return;
                let total = 0;
                summaryList.innerHTML = rows.map((checkbox) => {
                    const amount = Number(checkbox.dataset.amount || 0);
                    total += amount;
                    return `
                        <article class="vendor-payment-summary-item">
                            <div>
                                <strong>${escapeHtml(checkbox.dataset.trainer)}</strong>
                                <span>${escapeHtml(checkbox.dataset.project)}</span>
                                <small>${escapeHtml(checkbox.dataset.date)}</small>
                            </div>
                            <b>Rs ${money(amount)}</b>
                        </article>
                    `;
                }).join('');
                if (modalTotal) modalTotal.textContent = money(total);
                if (reviewPanel) reviewPanel.classList.remove('hidden');
                if (successPanel) successPanel.classList.add('hidden');
                modal.hidden = false;
            };

            const closeModal = () => {
                if (modal) modal.hidden = true;
            };
            const closeInvoiceModal = () => {
                if (invoiceModal) invoiceModal.hidden = true;
            };
            const setInvoiceText = (key, value) => {
                if (invoiceFields[key]) invoiceFields[key].textContent = value || '-';
            };
            const openInvoiceModal = (payload) => {
                if (!invoiceModal) return;
                setInvoiceText('title', payload.title || 'Requested Invoice');
                setInvoiceText('trainer', payload.trainer);
                setInvoiceText('empId', payload.empId);
                setInvoiceText('project', payload.project);
                setInvoiceText('date', payload.date);
                setInvoiceText('status', payload.status);
                setInvoiceText('invoiceId', payload.invoiceId);
                setInvoiceText('requestedAt', payload.requestedAt);
                setInvoiceText('hours', payload.hours);
                setInvoiceText('amount', payload.amount || '0.00');
                invoiceModal.hidden = false;
            };

            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateState));
            form.querySelectorAll('[data-vendor-invoice-view]').forEach((button) => {
                button.addEventListener('click', () => {
                    try {
                        openInvoiceModal(JSON.parse(button.dataset.vendorInvoiceView || '{}'));
                    } catch (error) {
                        openInvoiceModal({});
                    }
                });
            });
            if (nextButton) nextButton.addEventListener('click', openModal);
            if (modalClose) modalClose.addEventListener('click', closeModal);
            if (invoiceClose) invoiceClose.addEventListener('click', closeInvoiceModal);
            if (modal) {
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) closeModal();
                });
            }
            if (invoiceModal) {
                invoiceModal.addEventListener('click', (event) => {
                    if (event.target === invoiceModal) closeInvoiceModal();
                });
            }
            if (requestButton) {
                requestButton.addEventListener('click', () => {
                    if (reviewPanel) reviewPanel.classList.add('hidden');
                    if (successPanel) successPanel.classList.remove('hidden');
                    setTimeout(() => form.submit(), 900);
                });
            }

            updateState();
        })();
    </script>
    <?php
    render_footer();
}

function render_employee_rules_detail_modal(array $employee, array $rules, array $projects, string $modalId): void
{
    $shift = normalize_shift_selection((string) ($employee['shift'] ?? ''));
    $rulesHtml = rules_explanation_html($rules);
    $employeeRange = (!empty($rules['employee_from']) || !empty($rules['employee_to']))
        ? rule_date_range_label((string) $rules['employee_from'], (string) $rules['employee_to'])
        : 'Not assigned';
    $projectSessionRange = (!empty($rules['project_session_from']) || !empty($rules['project_session_to']))
        ? rule_date_range_label((string) $rules['project_session_from'], (string) $rules['project_session_to'])
        : 'Not assigned';
    $displayValue = static function (array $source, string $key): string {
        $value = trim((string) ($source[$key] ?? ''));
        return $value !== '' ? $value : '-';
    };
    $dateValue = static function (array $source, string $key): string {
        $value = trim((string) ($source[$key] ?? ''));
        return $value !== '' ? date('d M Y', strtotime($value)) : '-';
    };
    $documentLabels = [
        'aadhaar_card' => 'Aadhaar Card',
        'pan_card' => 'PAN Card',
        'profile_photo' => 'Profile Photo',
        'qualification_certificate' => 'Qualification Certificate',
        'bank_proof' => 'Bank Proof',
        'resume' => 'Resume',
    ];
    ?>
    <div class="modal" id="<?= h($modalId) ?>">
        <div class="modal-card employee-rules-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Employee Details</span>
            <h2><?= h((string) $employee['name']) ?></h2>
            <div class="rules-detail-grid">
                <section class="rules-detail-panel">
                    <h3>Account Details</h3>
                    <div class="session-detail-grid compact-detail-grid">
                        <div class="session-detail-row"><strong>Emp ID</strong><span><?= h($displayValue($employee, 'emp_id')) ?></span></div>
                        <div class="session-detail-row"><strong>Name</strong><span><?= h($displayValue($employee, 'name')) ?></span></div>
                        <div class="session-detail-row"><strong>Email</strong><span><?= h($displayValue($employee, 'email')) ?></span></div>
                        <div class="session-detail-row"><strong>Phone</strong><span><?= h($displayValue($employee, 'phone')) ?></span></div>
                        <div class="session-detail-row"><strong>Role</strong><span><?= h(user_role_label((string) ($employee['role'] ?? 'employee'))) ?></span></div>
                        <div class="session-detail-row"><strong>Status</strong><span><?= h((string) ($employee['status'] ?? 'ACTIVE')) ?></span></div>
                        <div class="session-detail-row"><strong>Profile</strong><span><?= h(ucfirst((string) ($employee['profile_status'] ?? 'incomplete'))) ?></span></div>
                        <div class="session-detail-row"><strong>Joined</strong><span><?= h($dateValue($employee, 'created_at')) ?></span></div>
                    </div>
                </section>
                <section class="rules-detail-panel">
                    <h3>Personal Details</h3>
                    <div class="session-detail-grid compact-detail-grid">
                        <div class="session-detail-row"><strong>Date of Birth</strong><span><?= h($dateValue($employee, 'date_of_birth')) ?></span></div>
                        <div class="session-detail-row"><strong>Gender</strong><span><?= h($displayValue($employee, 'gender')) ?></span></div>
                        <div class="session-detail-row"><strong>Address</strong><span><?= h($displayValue($employee, 'address')) ?></span></div>
                        <div class="session-detail-row"><strong>Qualification</strong><span><?= h($displayValue($employee, 'highest_qualification')) ?></span></div>
                        <div class="session-detail-row"><strong>Languages</strong><span><?= h($displayValue($employee, 'languages_known')) ?></span></div>
                        <div class="session-detail-row"><strong>Technical Skills</strong><span><?= h($displayValue($employee, 'technical_skills')) ?></span></div>
                    </div>
                </section>
                <section class="rules-detail-panel">
                    <h3>Job Details</h3>
                    <div class="session-detail-grid compact-detail-grid">
                        <div class="session-detail-row"><strong>Designation</strong><span><?= h($displayValue($employee, 'designation')) ?></span></div>
                        <div class="session-detail-row"><strong>Employee Type</strong><span><?= h($displayValue($employee, 'employee_type')) ?></span></div>
                        <div class="session-detail-row"><strong>Date of Joining</strong><span><?= h($dateValue($employee, 'date_of_joining')) ?></span></div>
                        <div class="session-detail-row"><strong>Salary</strong><span><?= h(number_format((float) ($employee['salary'] ?? 0), 2)) ?></span></div>
                        <div class="session-detail-row"><strong>Recruiter</strong><span><?= h($displayValue($employee, 'recruiter_name')) ?></span></div>
                        <div class="session-detail-row"><strong>Recruited Through</strong><span><?= h($displayValue($employee, 'recruited_through')) ?></span></div>
                        <div class="session-detail-row"><strong>Training Experience</strong><span><?= h($displayValue($employee, 'training_experience_years')) ?></span></div>
                    </div>
                </section>
                <section class="rules-detail-panel">
                    <h3>Bank Details</h3>
                    <div class="session-detail-grid compact-detail-grid">
                        <div class="session-detail-row"><strong>Bank Name</strong><span><?= h($displayValue($employee, 'bank_name')) ?></span></div>
                        <div class="session-detail-row"><strong>Account Number</strong><span><?= h($displayValue($employee, 'bank_account_no')) ?></span></div>
                        <div class="session-detail-row"><strong>IFSC Code</strong><span><?= h($displayValue($employee, 'bank_ifsc_code')) ?></span></div>
                        <div class="session-detail-row"><strong>Account Holder</strong><span><?= h($displayValue($employee, 'account_holder_name')) ?></span></div>
                    </div>
                </section>
                <section class="rules-detail-panel">
                    <h3>Time Allocation</h3>
                    <div class="session-detail-grid">
                        <div class="session-detail-row"><strong>Shift Timing</strong><span><?= h($shift !== '' ? str_replace('-', ' - ', $shift) : 'Not assigned') ?></span></div>
                        <div class="session-detail-row"><strong>Attendance Rules</strong><span><?= $rulesHtml !== '' ? $rulesHtml : 'No rules assigned' ?></span></div>
                    </div>
                </section>
                <section class="rules-detail-panel">
                    <h3>Project Allocation</h3>
                    <?php if ($projects): ?>
                        <div class="list">
                            <?php foreach ($projects as $project): ?>
                                <?php $range = project_assignment_mail_range((string) ($project['project_from'] ?? ''), (string) ($project['project_to'] ?? '')); ?>
                                <div class="list-item">
                                    <div class="split">
                                        <strong><?= h((string) ($project['project_name'] ?? 'Project')) ?></strong>
                                        <span class="status-pill status-<?= !empty($project['is_active']) ? 'Active' : 'Inactive' ?>"><?= !empty($project['is_active']) ? 'Active' : 'Inactive' ?></span>
                                    </div>
                                    <div class="session-detail-grid compact-detail-grid">
                                        <div class="session-detail-row"><strong>College</strong><span><?= h((string) (($project['college_name'] ?? '') ?: '-')) ?></span></div>
                                        <div class="session-detail-row"><strong>Location</strong><span><?= h((string) (($project['location'] ?? '') ?: '-')) ?></span></div>
                                        <div class="session-detail-row"><strong>Date Range</strong><span><?= h($range !== '' ? $range : 'Not assigned') ?></span></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="list-item muted">No projects assigned.</div>
                    <?php endif; ?>
                </section>
                <section class="rules-detail-panel">
                    <h3>Documents</h3>
                    <div class="session-detail-grid compact-detail-grid">
                        <?php foreach ($documentLabels as $documentKey => $documentLabel): ?>
                            <?php
                            $path = trim((string) ($employee[$documentKey . '_path'] ?? ''));
                            $name = trim((string) ($employee[$documentKey . '_name'] ?? ''));
                            $url = $path !== '' ? BASE_URL . '/' . ltrim(str_replace('\\', '/', $path), '/') : '';
                            ?>
                            <div class="session-detail-row">
                                <strong><?= h($documentLabel) ?></strong>
                                <span>
                                    <?php if ($url !== ''): ?>
                                        <a href="<?= h($url) ?>" target="_blank" rel="noopener"><?= h($name !== '' ? $name : 'View file') ?></a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <?php
}

function render_power_access_fields(array $defaults = []): void
{
    ?>
    <div class="power-access-rule">
        <label class="power-access-main">
            <input type="checkbox" name="power_access" value="1" <?= !empty($defaults['power_access']) ? 'checked' : '' ?>>
            <span>Power access</span>
        </label>
        <div class="power-access-copy">
            <strong>Track Attendance</strong>
            <small class="hint">Choose which people this employee can handle.</small>
        </div>
        <div class="power-scope-grid">
            <?php foreach (power_attendance_scope_options() as $scopeKey => $scopeLabel): ?>
                <label class="power-scope-option">
                    <input type="checkbox" name="power_attendance_scopes[]" value="<?= h($scopeKey) ?>" <?= !empty($defaults['power_attendance_' . $scopeKey]) ? 'checked' : '' ?>>
                    <span><?= h($scopeLabel) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="power-access-copy">
            <strong>Employee</strong>
            <small class="hint">Choose which employee pages this employee can handle.</small>
        </div>
        <div class="power-scope-grid">
            <?php foreach (power_team_scope_options() as $scopeKey => $scopeLabel): ?>
                <label class="power-scope-option">
                    <input type="checkbox" name="power_team_scopes[]" value="<?= h($scopeKey) ?>" <?= !empty($defaults['power_team_' . $scopeKey]) ? 'checked' : '' ?>>
                    <span><?= h($scopeLabel) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="power-access-copy">
            <strong>Accounts</strong>
            <small class="hint">Choose which account work this employee can handle.</small>
        </div>
        <div class="power-scope-grid">
            <?php foreach (power_accounts_scope_options() as $scopeKey => $scopeLabel): ?>
                <label class="power-scope-option">
                    <input type="checkbox" name="power_account_scopes[]" value="<?= h($scopeKey) ?>" <?= !empty($defaults['power_accounts_' . $scopeKey]) || (!empty($defaults['power_accounts']) && empty($defaults['power_accounts_verify']) && empty($defaults['power_accounts_pay']) && empty($defaults['power_accounts_history'])) ? 'checked' : '' ?>>
                    <span><?= h($scopeLabel) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function render_rules_editor(array $existing = [], ?string $submitLabel = null, bool $allowBlankShift = false, bool $includeProjectPicker = true, bool $includeRuleDetails = true, bool $includeEmployeeDateRange = false, bool $includeManualPunch = true, bool $includeBiometricPunch = true, bool $showCustomTimeFields = true): void
{
    $postedShiftOptions = array_map(
        static function (array $timing): string {
            return date('h:i A', strtotime((string) $timing['start_time'])) . ' - ' . date('h:i A', strtotime((string) $timing['end_time']));
        },
        shift_timings()
    );
    $shiftOptions = array_values(array_unique(array_merge(standard_shift_options(), $postedShiftOptions)));
    $defaults = array_merge([
        'manual_punch_in' => false,
        'manual_punch_out' => false,
        'manual_out_count' => 0,
        'biometric_punch_in' => false,
        'biometric_punch_out' => false,
        'power_access' => false,
        'power_attendance_employee' => false,
        'power_attendance_trainer' => false,
        'power_attendance_freelancer' => false,
        'power_attendance_vendor' => false,
        'power_attendance_vendor_trainer' => false,
        'power_team_employee' => false,
        'power_team_freelancer' => false,
        'power_team_vendor' => false,
        'power_projects' => false,
        'power_accounts' => false,
        'power_accounts_verify' => false,
        'power_accounts_pay' => false,
        'power_accounts_history' => false,
        'project_session_from' => '',
        'project_session_to' => '',
        'shift_from' => '',
        'shift_to' => '',
        'employee_from' => '',
        'employee_to' => '',
        'shift' => $shiftOptions[0] ?? '',
    ], $existing);
    $selectedShift = normalize_shift_selection((string) ($defaults['shift'] ?? ''));
    if ($selectedShift !== '' && !in_array($selectedShift, $shiftOptions, true)) {
        $shiftOptions[] = $selectedShift;
    }
    ?>
    <div class="rules-box">
        <div class="field hidden">
            <label>Shift Timing</label>
            <div class="field-row">
                <select name="shift">
                    <?php if ($allowBlankShift): ?>
                        <option value="" <?= (string) ($defaults['shift'] ?? '') === '' ? 'selected' : '' ?>>Keep current shift</option>
                    <?php endif; ?>
                    <?php foreach ($shiftOptions as $shiftOption): ?>
                        <option value="<?= h($shiftOption) ?>" <?= normalize_shift_selection((string) ($defaults['shift'] ?? '')) === $shiftOption ? 'selected' : '' ?>><?= h(str_replace('-', '–', $shiftOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php if ($showCustomTimeFields): ?>
            <div class="reports-filter-grid">
                <div class="field">
                    <label>From Time</label>
                    <div class="field-row"><input type="time" name="custom_shift_start_time" required></div>
                    <small class="field-error"><span>!</span>From time is required.</small>
                </div>
                <div class="field">
                    <label>To Time</label>
                    <div class="field-row"><input type="time" name="custom_shift_end_time" required></div>
                    <small class="field-error"><span>!</span>To time is required.</small>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($includeManualPunch && ($defaults['manual_punch_in'] || $defaults['manual_punch_out'])): ?>
            <input type="hidden" name="manual_punch" value="1">
        <?php endif; ?>
        <?php if ($includeBiometricPunch && ($defaults['biometric_punch_in'] || $defaults['biometric_punch_out'])): ?>
            <input type="hidden" name="biometric_punch" value="1">
        <?php endif; ?>
        <?php if ($includeRuleDetails): ?>
            <div class="inline-actions admin-rules-top-actions">
                <button class="button outline small" type="button" data-add-manual-slot data-target="#manual-out-count">+ Add Manual Punch</button>
            </div>
            <?php render_power_access_fields($defaults); ?>
            <div class="split align-end admin-rules-footer">
                <label>Manual punch slots<input id="manual-out-count" type="number" min="0" name="manual_out_count" value="<?= h((string) $defaults['manual_out_count']) ?>"></label>
                <label>Project Session From<input type="date" name="project_session_from" value="<?= h((string) ($defaults['project_session_from'] ?? '')) ?>"></label>
                <label>Project Session To<input type="date" name="project_session_to" value="<?= h((string) ($defaults['project_session_to'] ?? '')) ?>"></label>
                <label>Employee From<input type="date" name="employee_from" value="<?= h((string) ($defaults['employee_from'] ?? '')) ?>"></label>
                <label>Employee To<input type="date" name="employee_to" value="<?= h((string) ($defaults['employee_to'] ?? '')) ?>"></label>
                <?php if ($submitLabel !== null): ?>
                    <div class="inline-actions">
                        <button class="button solid" type="submit" data-rule-submit><?= h($submitLabel) ?></button>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($includeEmployeeDateRange): ?>
            <div class="split align-end admin-rules-footer">
                <label>Shift Timing From<input type="date" name="shift_from" value="<?= h((string) ($defaults['shift_from'] ?? '')) ?>"></label>
                <label>Shift Timing To<input type="date" name="shift_to" value="<?= h((string) ($defaults['shift_to'] ?? '')) ?>"></label>
                <span class="admin-rules-footer-break" aria-hidden="true"></span>
                <label>Employee From<input type="date" name="employee_from" value="<?= h((string) ($defaults['employee_from'] ?? '')) ?>"></label>
                <label>Employee To<input type="date" name="employee_to" value="<?= h((string) ($defaults['employee_to'] ?? '')) ?>"></label>
                <?php if ($submitLabel !== null): ?>
                    <div class="inline-actions">
                        <button class="button solid" type="submit" data-rule-submit><?= h($submitLabel) ?></button>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($submitLabel !== null): ?>
            <div class="inline-actions">
                <button class="button solid" type="submit" data-rule-submit><?= h($submitLabel) ?></button>
            </div>
        <?php endif; ?>
        <div class="spacer"></div>
        <?php if ($includeProjectPicker): ?>
            <?php render_project_assignment_picker(); ?>
        <?php endif; ?>
    </div>
    <?php
}

function render_employee_assignment_picker(array $employees, string $optionsId, string $tagsId, string $heading = 'Select Employees'): void
{
    ?>
    <div class="employee-picker">
        <div class="split">
            <strong><?= h($heading) ?></strong>
            <span class="hint">Search and pick one or more employees</span>
        </div>
        <input type="text" placeholder="Search employees..." data-employee-filter="<?= h($optionsId) ?>">
        <div class="tag-list" id="<?= h($tagsId) ?>"></div>
        <div class="employee-options" id="<?= h($optionsId) ?>" data-tag-source="<?= h($tagsId) ?>">
            <?php foreach ($employees as $employee): ?>
                <label class="employee-option">
                    <input type="checkbox" name="employee_ids[]" value="<?= (int) $employee['id'] ?>" data-label="<?= h($employee['name']) ?>">
                    <span><?= h($employee['name']) ?> (<?= h($employee['emp_id']) ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function render_project_assignment_picker(array $selectedProjectIds = [], string $filterId = 'project-assignment-options', array $assignmentRanges = []): void
{
    $allProjects = active_projects();
    $selectedLookup = array_fill_keys(array_map('intval', $selectedProjectIds), true);
    ?>
    <div class="employee-picker">
        <div class="split">
            <strong>Assigned Projects</strong>
            <span class="hint">Employees see these projects in Manual Punch Out.</span>
        </div>
        <?php if ($allProjects): ?>
            <input type="text" placeholder="Search projects..." data-employee-filter="<?= h($filterId) ?>">
            <div class="employee-options" id="<?= h($filterId) ?>">
                <?php foreach ($allProjects as $project): ?>
                    <?php
                    $projectId = (int) ($project['id'] ?? 0);
                    $statusLabel = !empty($project['is_active']) ? 'Active' : 'Inactive';
                    $detailParts = array_values(array_filter([
                        trim((string) ($project['college_name'] ?? '')),
                        trim((string) ($project['location'] ?? '')),
                        $statusLabel,
                    ]));
                    $range = $assignmentRanges[$projectId] ?? [];
                    $dateFrom = (string) ($range['from'] ?? '');
                    $dateTo = (string) ($range['to'] ?? '');
                    $projectIncentive = number_format((float) ($range['incentive'] ?? 0), 2, '.', '');
                    $projectDailySalary = number_format((float) ($range['daily_salary'] ?? 0), 2, '.', '');
                    $isChecked = isset($selectedLookup[$projectId]);
                    ?>
                    <div class="project-option-card">
                        <label class="employee-option">
                            <input type="checkbox" name="project_ids[]" value="<?= $projectId ?>" data-label="<?= h((string) ($project['project_name'] ?? '')) ?>" data-project-date-toggle <?= $isChecked ? 'checked' : '' ?>>
                            <span>
                                <?= h((string) ($project['project_name'] ?? '')) ?><br>
                                <small class="hint"><?= h(implode(' | ', $detailParts)) ?></small>
                            </span>
                        </label>
                        <div class="project-date-range<?= $isChecked ? '' : ' hidden' ?>" data-project-date-fields>
                            <label>From<input type="date" name="project_from[<?= $projectId ?>]" value="<?= h($dateFrom) ?>"></label>
                            <label>To<input type="date" name="project_to[<?= $projectId ?>]" value="<?= h($dateTo) ?>"></label>
                            <label>Incentive Per Session<input type="number" min="0" step="0.01" name="project_incentive[<?= $projectId ?>]" value="<?= h($projectIncentive) ?>" placeholder="0.00"></label>
                            <label>Contractual Daily Salary<input type="number" min="0" step="0.01" name="project_daily_salary[<?= $projectId ?>]" value="<?= h($projectDailySalary) ?>" placeholder="0.00"></label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No verified active projects are available yet. Add or verify projects first in the Projects page.</div>
        <?php endif; ?>
    </div>
    <?php
}

function vendor_project_lookup(): array
{
    $vendorProjects = db()->query("SELECT vendor_name, project_name FROM projects WHERE TRIM(COALESCE(vendor_name, '')) <> '' ORDER BY project_name ASC")->fetchAll();
    $vendorProjectLookup = [];
    foreach ($vendorProjects as $project) {
        $vendorKey = strtolower(trim((string) ($project['vendor_name'] ?? '')));
        $projectName = trim((string) ($project['project_name'] ?? ''));
        if ($vendorKey !== '' && $projectName !== '') {
            $vendorProjectLookup[$vendorKey][$projectName] = true;
        }
    }

    return $vendorProjectLookup;
}

function render_vendor_accounts_table(array $vendors, string $tableId, string $emptyId, bool $showSearch = false, string $returnPage = 'admin_employees'): void
{
    $returnPage = in_array($returnPage, ['admin_employees', 'admin_vendors'], true) ? $returnPage : 'admin_employees';
    $vendorProjectLookup = vendor_project_lookup();
    $vendorTrainerLookup = [];
    if ($vendors) {
        $vendorIds = array_values(array_filter(array_map(static fn(array $vendor): int => (int) ($vendor['id'] ?? 0), $vendors)));
        if ($vendorIds) {
            $placeholders = implode(',', array_fill(0, count($vendorIds), '?'));
            $trainerStmt = db()->prepare("SELECT admin_id, name, emp_id, email, phone FROM users WHERE admin_id IN ($placeholders) AND role IN ('employee', 'corporate_employee') ORDER BY name");
            $trainerStmt->execute($vendorIds);
            foreach ($trainerStmt->fetchAll() as $trainer) {
                $vendorTrainerLookup[(int) ($trainer['admin_id'] ?? 0)][] = $trainer;
            }
        }
    }
    ?>
    <div class="data-toolbar">
        <div class="split">
            <h2>Vendor Accounts</h2>
            <span class="badge"><?= count($vendors) ?> total</span>
        </div>
        <?php if ($showSearch): ?>
            <div class="data-toolbar-right">
                <div class="data-toolbar-search">
                    <input type="text" placeholder="Search by project, company, mail, or phone..." data-table-filter="<?= h($tableId) ?>" data-empty-target="<?= h($emptyId) ?>">
                </div>
            </div>
        <?php endif; ?>
    </div>
    <table>
        <thead>
            <tr>
                <th>Project</th>
                <th>Company</th>
                <th>P.No</th>
                <th>Mail ID</th>
                <th>Modify</th>
            </tr>
        </thead>
        <tbody id="<?= h($tableId) ?>">
            <?php foreach ($vendors as $vendor): ?>
                <?php
                    $vendorId = (int) ($vendor['id'] ?? 0);
                    $vendorKey = strtolower(trim((string) ($vendor['name'] ?? '')));
                    $projectNames = array_keys($vendorProjectLookup[$vendorKey] ?? []);
                    $projectLabel = $projectNames ? implode(', ', $projectNames) : '-';
                    $searchText = strtolower(implode(' ', [
                        $projectLabel,
                        (string) $vendor['name'],
                        (string) $vendor['email'],
                        (string) $vendor['phone'],
                    ]));
                ?>
                <tr data-filter-row data-filter-text="<?= h($searchText) ?>">
                    <td data-label="Project"><?= h($projectLabel) ?></td>
                    <td data-label="Company"><strong><?= h($vendor['name']) ?></strong></td>
                    <td data-label="P.No"><?= h($vendor['phone']) ?></td>
                    <td data-label="Mail ID"><?= h($vendor['email']) ?></td>
                    <td data-label="Modify">
                        <div class="inline-actions team-modify-actions">
                            <button class="button ghost small" type="button" data-modal-target="<?= h($tableId) ?>-view-modal-<?= $vendorId ?>">View</button>
                            <button class="button outline small" type="button" data-confirm-vendor-delete data-vendor-delete-modal="<?= h($tableId) ?>-delete-modal" data-vendor-id="<?= $vendorId ?>" data-vendor-name="<?= h((string) $vendor['name']) ?>">Delete</button>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="admin_vendor_status_update">
                                <input type="hidden" name="vendor_id" value="<?= $vendorId ?>">
                                <input type="hidden" name="redirect_page" value="<?= h($returnPage) ?>">
                                <?php $isVendorBlocked = strtoupper((string) ($vendor['status'] ?? 'ACTIVE')) === 'BLOCKED'; ?>
                                <input type="hidden" name="status" value="<?= $isVendorBlocked ? 'ACTIVE' : 'BLOCKED' ?>">
                                <button class="button <?= $isVendorBlocked ? 'solid' : 'outline' ?> small" type="submit"><?= $isVendorBlocked ? 'Activate' : 'Inactive' ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php foreach ($vendors as $vendor): ?>
        <?php
            $vendorId = (int) ($vendor['id'] ?? 0);
            $vendorKey = strtolower(trim((string) ($vendor['name'] ?? '')));
            $projectNames = array_keys($vendorProjectLookup[$vendorKey] ?? []);
            $projectLabel = $projectNames ? implode(', ', $projectNames) : '-';
            $trainers = $vendorTrainerLookup[$vendorId] ?? [];
        ?>
        <div class="modal" id="<?= h($tableId) ?>-view-modal-<?= $vendorId ?>">
            <div class="modal-card employee-rules-modal-card">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Vendor Account</span>
                <h2><?= h((string) $vendor['name']) ?></h2>
                <div class="session-detail-grid">
                    <div class="session-detail-row"><strong>Project</strong><span><?= h($projectLabel) ?></span></div>
                    <div class="session-detail-row"><strong>Company</strong><span><?= h((string) $vendor['name']) ?></span></div>
                    <div class="session-detail-row"><strong>P.No</strong><span><?= h((string) $vendor['phone']) ?></span></div>
                    <div class="session-detail-row"><strong>Mail ID</strong><span><?= h((string) $vendor['email']) ?></span></div>
                    <div class="session-detail-row"><strong>Status</strong><span><?= h((string) ($vendor['status'] ?? 'ACTIVE')) ?></span></div>
                </div>
                <div class="spacer"></div>
                <h3>Vendor Trainers</h3>
                <?php if ($trainers): ?>
                    <div class="table-wrap compact-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>P.No</th>
                                    <th>Mail ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trainers as $trainer): ?>
                                    <tr>
                                        <td data-label="Name"><?= h((string) ($trainer['name'] ?? '')) ?></td>
                                        <td data-label="P.No"><?= h((string) ($trainer['phone'] ?? '')) ?></td>
                                        <td data-label="Mail ID"><?= h((string) ($trainer['email'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="list-item muted" style="display:block;">No trainers found for this vendor.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$vendors): ?>
        <div class="list-item muted table-empty-state" style="display: block;">No vendor accounts found.</div>
    <?php endif; ?>
    <div class="list-item muted hidden table-empty-state" id="<?= h($emptyId) ?>">No vendors match your search.</div>
    <div class="modal" id="<?= h($tableId) ?>-delete-modal">
        <div class="modal-card" style="max-width:560px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Confirm Delete</span>
            <h2>Delete Vendor Account</h2>
            <p>This will permanently remove <strong data-vendor-delete-name>this vendor</strong> and its trainers.</p>
            <form method="post" class="inline-actions">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_vendor_delete">
                <input type="hidden" name="vendor_id" value="">
                <input type="hidden" name="redirect_page" value="<?= h($returnPage) ?>">
                <button class="button outline" type="button" data-close-modal>Cancel</button>
                <button class="button secondary" type="submit">Delete Vendor</button>
            </form>
        </div>
    </div>
    <?php
}

function render_admin_employees(): void
{
    require_power_team_access(['admin', 'freelancer', 'external_vendor']);
    $editEmployee = null;
    $stage = $_GET['stage'] ?? null;
    $pendingEmployee = $_SESSION['pending_employee'] ?? null;
    $pendingCsv = $_SESSION['pending_csv_import'] ?? null;

    if (isset($_GET['edit'])) {
        $editId = (int) $_GET['edit'];
        $editEmployee = employee_by_id($editId);
    }

    $allEmployees = employees();
    $defaultAssignedProjectIds = array_values(array_filter(array_map(
        static fn (array $project): int => (int) ($project['id'] ?? 0),
        active_projects()
    )));
    $editEmployeeProjectIds = $editEmployee ? employee_available_project_ids($editEmployee) : [];

    $user = current_user();
    $isFreelancer = ($user['role'] ?? '') === 'freelancer';
    $isVendor = ($user['role'] ?? '') === 'external_vendor';
    $isPowerEmployee = employee_has_power_access($user);
    $teamPowerScopes = $isPowerEmployee ? employee_power_team_scopes($user) : [];
    $teamScopeToType = [
        'employee' => 'regular',
        'freelancer' => 'corporate',
        'vendor' => 'vendor',
    ];
    $allowedEmployeeTypes = $isPowerEmployee
        ? array_values(array_intersect_key($teamScopeToType, array_fill_keys($teamPowerScopes, true)))
        : ['regular', 'vendor', 'corporate'];
    if (!$allowedEmployeeTypes) {
        $allowedEmployeeTypes = ['regular'];
    }
    $employeeType = $_GET['type'] ?? 'regular';
    $employeeType = in_array($employeeType, ['regular', 'vendor', 'corporate'], true) ? $employeeType : 'regular';
    if ($isPowerEmployee && !in_array($employeeType, $allowedEmployeeTypes, true)) {
        $employeeType = $allowedEmployeeTypes[0];
    }
    if ($isVendor) {
        $employeeType = 'vendor';
    }
    $teamAdminId = (int) (current_admin_id() ?? ($user['id'] ?? 0));
    $usesHourlyRate = $isFreelancer;
    $isVendorTrainerView = $isVendor || $employeeType === 'vendor';
    $isContractualEmployeeView = $employeeType === 'corporate' && !$isVendor && !$isFreelancer;
    $isCompactEmployeeTable = $isContractualEmployeeView;
    $showVendorAccountsOnly = $employeeType === 'vendor' && !$isVendor;
    $label = $showVendorAccountsOnly ? 'Vendor Accounts' : ($isFreelancer ? 'Employee' : ($isVendor ? 'Vendor Employees' : 'Employee'));
    $singularLabel = $showVendorAccountsOnly ? 'Vendor Account' : 'Employee';

    render_header($label);

    $canCreateEmployees = $isVendor || $employeeType !== 'vendor';
    $employeeOwnerLabel = $isVendor ? 'your vendor account' : 'this administrator';
    $employeeIntro = $showVendorAccountsOnly
        ? 'Manage the external vendor accounts registered for projects.'
        : ($canCreateEmployees
            ? 'Add ' . strtolower($label) . ' manually, import a CSV batch, update records, and manage only the ' . strtolower($label) . ' assigned to ' . $employeeOwnerLabel . '.'
            : 'View vendor employees assigned by each vendor. Vendor employees can only be added by the vendor.');
    $vendorCreatedPopup = null;
    if ($employeeType === 'vendor' && !empty($_SESSION['vendor_created_popup']) && is_array($_SESSION['vendor_created_popup'])) {
        $vendorCreatedPopup = $_SESSION['vendor_created_popup'];
        unset($_SESSION['vendor_created_popup']);
    }
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow"><?= ($isFreelancer || $isVendor) ? h($label) : ('Admin - ' . h($label)) ?></span>
            <h1><?= h($label) ?></h1>
            <p><?= h($employeeIntro) ?></p>
        </div>
        <?php if ($canCreateEmployees): ?>
        <div class="action-bar">
            <button class="button outline" type="button" data-modal-target="employee-csv-modal">Bulk Import</button>
            <button class="button solid" type="button" data-modal-target="add-employee-modal">Add <?= h($singularLabel) ?></button>
        </div>
        <?php endif; ?>
    </section>
    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2><?= $showVendorAccountsOnly ? 'Vendor Accounts' : 'Your ' . h($label) ?></h2>
                <span class="badge" id="admin-employees-count"><?php
                    $vendorRegistrations = [];
                    $freelancerRegistrations = [];
                    $filteredEmployees = [];
                    if ($employeeType === 'vendor' && !$isVendor) {
                        $vendorRegistrations = db()->query("SELECT * FROM users WHERE role = 'external_vendor' ORDER BY name")->fetchAll();
                        if (!empty($_GET['vendor_id'])) {
                            // Vendor-added employees have role='employee' and admin_id=vendor's user id
                            $vEmpStmt = db()->prepare("SELECT * FROM users WHERE role IN ('employee', 'corporate_employee') AND admin_id = ? ORDER BY name");
                            $vEmpStmt->execute([(int)$_GET['vendor_id']]);
                            $filteredEmployees = $vEmpStmt->fetchAll();
                        }
                    } elseif ($employeeType === 'corporate' && !$isVendor && !$isFreelancer) {
                        $contractualStmt = db()->prepare("SELECT * FROM users WHERE admin_id = :admin_id AND (role = 'corporate_employee' OR employee_type = 'corporate') ORDER BY created_at DESC, name");
                        $contractualStmt->execute(['admin_id' => $teamAdminId]);
                        $filteredEmployees = $contractualStmt->fetchAll();
                    } else {
                        if ($isVendor || $isFreelancer) {
                            $filteredEmployees = $allEmployees;
                        } else {
                            $filteredEmployees = array_filter($allEmployees, function($emp) use ($employeeType) {
                                $type = (string) ($emp['employee_type'] ?? 'regular');
                                if ($employeeType === 'regular') {
                                    return ($type === 'regular' || $type === '') && (string) ($emp['role'] ?? '') !== 'corporate_employee';
                                }
                                if ($employeeType === 'corporate') {
                                    return $type === 'corporate' || (string) ($emp['role'] ?? '') === 'corporate_employee';
                                }
                                return $type === $employeeType;
                            });
                        }
                    }
                    $employeeCount = $showVendorAccountsOnly ? count($vendorRegistrations) : count($filteredEmployees);
                    echo $employeeCount;
                ?> total</span>
            </div>
            <div class="data-toolbar-right">
                <?php if (!$isFreelancer && !$isVendor): ?>
                    <nav class="employee-tabs inline" aria-label="Employee type filters">
                        <?php if (in_array('regular', $allowedEmployeeTypes, true)): ?>
                            <a href="<?= h(BASE_URL) ?>?page=admin_employees&type=regular" class="tab-link <?= $employeeType === 'regular' ? 'active' : '' ?>">Employee</a>
                        <?php endif; ?>
                        <?php if (in_array('vendor', $allowedEmployeeTypes, true)): ?>
                            <a href="<?= h(BASE_URL) ?>?page=admin_employees&type=vendor" class="tab-link <?= $employeeType === 'vendor' ? 'active' : '' ?>">Vendor</a>
                        <?php endif; ?>
                        <?php if (in_array('corporate', $allowedEmployeeTypes, true)): ?>
                            <a href="<?= h(BASE_URL) ?>?page=admin_employees&type=corporate" class="tab-link <?= $employeeType === 'corporate' ? 'active' : '' ?>">Contractual Employee</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
                <?php if (!$showVendorAccountsOnly): ?>
                    <div class="data-toolbar-search">
                        <input type="text" placeholder="<?= h($isVendorTrainerView ? 'Search by ID, name, email, phone, role, or designation...' : 'Search by ID, name, email, phone, role, shift, or rule...') ?>" data-table-filter="admin-employees-table" data-empty-target="admin-employees-empty" data-count-target="admin-employees-count">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($employeeType === 'vendor' && !$isVendor): ?>
        <section class="section-block scroll-panel" style="margin-bottom: 20px; padding: 15px; border-radius: 12px;">
            <div class="split" style="margin-bottom: 16px;">
                <div>
                    <span class="eyebrow">Vendor Directory</span>
                    <h3 style="margin-bottom: 6px;">Vendor Accounts</h3>
                    <p class="hint" style="margin: 0;">Manage the external vendors registered for projects.</p>
                </div>
                <button class="button solid" type="button" data-modal-target="vendor-register-modal">Vendor Register</button>
            </div>
            <?php render_vendor_accounts_table($vendorRegistrations, 'admin-vendor-directory-table', 'admin-vendor-directory-empty', true); ?>
        </section>
        <?php endif; ?>
        <?php if (!$showVendorAccountsOnly): ?>
        <?php if (!$isVendor): ?>
            <?php
                $profileReviewStmt = db()->prepare("SELECT * FROM users
                    WHERE profile_status IN ('pending', 'rejected')
                      AND role IN ('employee', 'corporate_employee')
                      AND (admin_id = :admin_id OR (role = 'corporate_employee' AND (admin_id IS NULL OR admin_id = 0)))
                    ORDER BY FIELD(profile_status, 'pending', 'rejected'), name");
                $profileReviewStmt->execute(['admin_id' => $teamAdminId]);
                $profileReviewEmployees = $profileReviewStmt->fetchAll();
                $profileChangeStmt = db()->prepare("SELECT details_json, created_at FROM activity_logs WHERE target_user_id = :target_user_id AND action = 'employee_profile_updated' ORDER BY created_at DESC, id DESC LIMIT 1");
            ?>
            <?php if ($profileReviewEmployees): ?>
                <section class="section-block scroll-panel" style="margin-bottom: 20px;">
                    <div class="split">
                        <div>
                            <span class="eyebrow">Employee Onboarding</span>
                            <h2>Profile Verification Queue</h2>
                            <p class="hint">Review submitted employee details and documents before dashboard access is unlocked.</p>
                        </div>
                    </div>
                    <div class="list">
                        <?php foreach ($profileReviewEmployees as $reviewEmployee): ?>
                            <?php
                                $documents = [
                                    'aadhaar_card' => 'Aadhaar',
                                    'pan_card' => 'PAN',
                                    'profile_photo' => 'Photo',
                                    'qualification_certificate' => 'Qualification',
                                    'bank_proof' => 'Bank Proof',
                                    'resume' => 'Resume',
                                ];
                                $changedFields = [];
                                if (!empty($reviewEmployee['profile_changed_fields_json'])) {
                                    $decodedChangedFields = json_decode((string) $reviewEmployee['profile_changed_fields_json'], true);
                                    $changedFields = is_array($decodedChangedFields) ? array_values(array_filter(array_map('strval', $decodedChangedFields))) : [];
                                }
                                $changedAt = (string) ($reviewEmployee['profile_changed_at'] ?? '');
                                if (!$changedFields) {
                                    $profileChangeStmt->execute(['target_user_id' => (int) ($reviewEmployee['id'] ?? 0)]);
                                    $profileChangeRow = $profileChangeStmt->fetch() ?: [];
                                    $profileChangeDetails = [];
                                    if (!empty($profileChangeRow['details_json'])) {
                                        $decodedDetails = json_decode((string) $profileChangeRow['details_json'], true);
                                        $profileChangeDetails = is_array($decodedDetails) ? $decodedDetails : [];
                                    }
                                    $fallbackChangedFields = $profileChangeDetails['changed_fields'] ?? [];
                                    $changedFields = is_array($fallbackChangedFields) ? array_values(array_filter(array_map('strval', $fallbackChangedFields))) : [];
                                    $changedAt = (string) ($profileChangeRow['created_at'] ?? $changedAt);
                                }
                            ?>
                            <div class="list-item">
                                <div class="split">
                                    <div>
                                        <strong><?= h((string) $reviewEmployee['name']) ?></strong>
                                        <p class="hint"><?= h((string) ($reviewEmployee['emp_id'] ?? '')) ?> | <?= h((string) ($reviewEmployee['designation'] ?? '-')) ?> | <?= h(ucfirst((string) ($reviewEmployee['profile_status'] ?? 'pending'))) ?></p>
                                        <p class="hint">DOB: <?= h((string) ($reviewEmployee['date_of_birth'] ?? '-')) ?> | Qualification: <?= h((string) ($reviewEmployee['highest_qualification'] ?? '-')) ?> | Bank: <?= h((string) ($reviewEmployee['bank_name'] ?? '-')) ?></p>
                                        <div class="inline-actions" style="margin: 8px 0 10px;">
                                            <span class="hint"><strong><?= $changedFields ? 'Changed:' : 'Submitted:' ?></strong></span>
                                            <?php if ($changedFields): ?>
                                                <?php foreach ($changedFields as $changedField): ?>
                                                    <span class="badge"><?= h((string) $changedField) ?></span>
                                                <?php endforeach; ?>
                                                <?php if ($changedAt !== ''): ?>
                                                    <span class="hint"><?= h(date('d M Y h:i A', strtotime($changedAt))) ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge">Full Profile Review</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="inline-actions">
                                            <?php foreach ($documents as $field => $label): ?>
                                                <?php if (!empty($reviewEmployee[$field . '_path'])): ?>
                                                    <a class="button ghost small" href="<?= h(public_file_path((string) $reviewEmployee[$field . '_path'])) ?>" target="_blank" rel="noopener"><?= h($label) ?></a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (($reviewEmployee['profile_status'] ?? '') === 'rejected' && trim((string) ($reviewEmployee['profile_rejection_reason'] ?? '')) !== ''): ?>
                                            <p class="hint"><strong>Last rejection:</strong> <?= h((string) $reviewEmployee['profile_rejection_reason']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <form method="post" class="stack-form" style="min-width:280px;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="admin_review_employee_profile">
                                        <input type="hidden" name="user_id" value="<?= (int) $reviewEmployee['id'] ?>">
                                        <textarea name="rejection_reason" placeholder="Reason if rejecting"></textarea>
                                        <div class="inline-actions">
                                            <button class="button solid small" type="submit" name="decision" value="approve">Approve</button>
                                            <button class="button outline small" type="submit" name="decision" value="reject">Reject</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
        <table class="employee-list-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Name</th>
                    <?php if (!$isCompactEmployeeTable): ?>
                        <th>Emp ID</th>
                    <?php endif; ?>
                    <th><?= $isCompactEmployeeTable ? 'P.No' : 'Phone' ?></th>
                    <th>Mail ID</th>
                    <?php if (!$isCompactEmployeeTable): ?>
                        <th>Login Time</th>
                        <th>Logout Time</th>
                        <th>Powers</th>
                    <?php endif; ?>
                    <th>Modify</th>
                </tr>
            </thead>
            <tbody id="admin-employees-table">
                <?php 
                foreach ($filteredEmployees as $employee):
                    $rules = employee_rules((int) $employee['id']);
                    $rulesMarkup = rules_summary($rules);
                    $assignedProjects = assigned_projects_for_employee((int) $employee['id']);
                    $displayProjects = $isContractualEmployeeView
                        ? employee_available_projects_for_date($employee, date('Y-m-d'))
                        : $assignedProjects;
                    $projectSearchText = strtolower(trim(implode(' ', array_map(static function (array $project): string {
                        return implode(' ', [
                            (string) ($project['project_name'] ?? ''),
                            (string) ($project['college_name'] ?? ''),
                            (string) ($project['location'] ?? ''),
                            (string) ($project['project_from'] ?? ''),
                            (string) ($project['project_to'] ?? ''),
                        ]);
                    }, $displayProjects))));
                    $projectLabel = trim(implode(', ', array_values(array_unique(array_filter(array_map(static function (array $project): string {
                        return trim((string) ($project['project_name'] ?? ''));
                    }, $displayProjects))))));
                    $rulesText = strtolower(trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<br>', ' ', $rulesMarkup)))));
                    $employeeId = (int) $employee['id'];
                    $rulesModalId = 'employee-rules-modal-' . $employeeId;
                    $projectAssignModalId = 'employee-project-assign-modal-' . $employeeId;
                    $roleLabel = user_role_label((string) ($employee['role'] ?? 'employee'));
                    $designationLabel = trim((string) ($employee['designation'] ?? '')) ?: '-';
                    $powersLabel = employee_power_summary($employee, $rules);
                    $profileStatus = trim((string) ($employee['profile_status'] ?? 'incomplete')) ?: 'incomplete';
                    $shiftWindow = shift_window_for_employee($employee);
                    $loginLabel = !empty($shiftWindow['start_time']) ? date('h:i A', strtotime((string) $shiftWindow['start_time'])) : '-';
                    $logoutLabel = !empty($shiftWindow['end_time']) ? date('h:i A', strtotime((string) $shiftWindow['end_time'])) : '-';
                    $isApprovedEmployee = strtoupper((string) ($employee['status'] ?? 'ACTIVE')) === 'ACTIVE'
                        && strtolower((string) ($employee['profile_status'] ?? '')) === 'verified';
                    $searchText = strtolower(implode(' ', [
                        (string) $employee['emp_id'],
                        (string) $employee['name'],
                        (string) $employee['email'],
                        (string) $employee['phone'],
                        $roleLabel,
                        (string) ($employee['role'] ?? ''),
                        $designationLabel,
                        $powersLabel,
                        $profileStatus,
                        (string) ($employee['shift'] ?? ''),
                        $rulesText,
                        $projectSearchText,
                        $loginLabel,
                        $logoutLabel,
                    ]));
                ?>
                    <tr data-filter-row data-filter-text="<?= h($searchText) ?>">
                        <td data-label="Project">
                            <div class="team-project-cell">
                                <?php if ($projectLabel !== ''): ?>
                                    <span><?= h($projectLabel) ?></span>
                                <?php endif; ?>
                                <?php if ($canCreateEmployees): ?>
                                    <button class="button ghost small" type="button" data-modal-target="<?= h($projectAssignModalId) ?>">Assign</button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Name"><?= h($employee['name']) ?></td>
                        <?php if (!$isCompactEmployeeTable): ?>
                            <td data-label="Emp ID"><?= h($employee['emp_id']) ?></td>
                        <?php endif; ?>
                        <td data-label="<?= $isCompactEmployeeTable ? 'P.No' : 'Phone' ?>"><?= h($employee['phone']) ?></td>
                        <td data-label="Mail ID"><?= h($employee['email']) ?></td>
                        <?php if (!$isCompactEmployeeTable): ?>
                            <td data-label="Login Time"><?= h($loginLabel) ?></td>
                            <td data-label="Logout Time"><?= h($logoutLabel) ?></td>
                            <td data-label="Powers"><?= h($powersLabel) ?></td>
                        <?php endif; ?>
                        <td data-label="Modify">
                            <?php if ($canCreateEmployees): ?>
                                <div class="inline-actions team-modify-actions">
                                    <button class="button ghost small" type="button" data-modal-target="<?= h($rulesModalId) ?>">View</button>
                                    <a class="button ghost small" href="<?= h(BASE_URL) ?>?page=admin_employees&type=<?= h($employeeType) ?>&edit=<?= (int) $employee['id'] ?>">Edit</a>
                                    <button class="button outline small" type="button" data-confirm-delete data-user-id="<?= (int) $employee['id'] ?>" data-user-name="<?= h($employee['name']) ?>">Delete</button>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="employee_status_update">
                                        <input type="hidden" name="user_id" value="<?= (int) $employee['id'] ?>">
                                        <input type="hidden" name="status" value="BLOCKED">
                                        <button class="button outline small" type="submit">Inactive</button>
                                    </form>
                                    <?php if (!$isApprovedEmployee): ?>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="employee_status_update">
                                            <input type="hidden" name="user_id" value="<?= (int) $employee['id'] ?>">
                                            <input type="hidden" name="status" value="ACTIVE">
                                            <button class="button solid small" type="submit">Approve</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="hint">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (($employeeType === 'vendor' && !$isVendor && empty($_GET['vendor_id']))): ?>
            <div class="list-item muted" style="display:block; padding: 16px;">Select a vendor from the dropdown above to view their employees.</div>
        <?php elseif (!$filteredEmployees): ?>
            <div class="list-item muted" style="display:block; padding: 16px;">No employees found.</div>
        <?php endif; ?>
        <div class="list-item muted hidden table-empty-state" id="admin-employees-empty">No records match your search.</div>
        <?php endif; ?>
    </section>
    <?php if (!$showVendorAccountsOnly): ?>
        <?php foreach ($filteredEmployees as $employee): ?>
            <?php
                $employeeId = (int) $employee['id'];
                $assignedProjects = assigned_projects_for_employee($employeeId);
                render_employee_rules_detail_modal($employee, employee_rules($employeeId), $assignedProjects, 'employee-rules-modal-' . $employeeId);
                $assignmentRanges = [];
                foreach ($assignedProjects as $assignedProject) {
                    $assignedProjectId = (int) ($assignedProject['id'] ?? 0);
                    if ($assignedProjectId <= 0) {
                        continue;
                    }
                    $assignmentRanges[$assignedProjectId] = [
                        'from' => (string) ($assignedProject['project_from'] ?? ''),
                        'to' => (string) ($assignedProject['project_to'] ?? ''),
                        'incentive' => (float) ($assignedProject['project_incentive'] ?? 0),
                        'daily_salary' => (float) ($assignedProject['project_daily_salary'] ?? 0),
                    ];
                }
                $selectedProjectIds = array_keys($assignmentRanges);
            ?>
            <?php if ($canCreateEmployees): ?>
                <div class="modal" id="employee-project-assign-modal-<?= $employeeId ?>">
                    <div class="modal-card employee-rules-modal-card">
                        <button class="modal-close" type="button" data-close-modal>&times;</button>
                        <span class="eyebrow">Team Project</span>
                        <h2>Assign Projects</h2>
                        <p class="hint"><?= h((string) ($employee['name'] ?? 'Team member')) ?> will see selected projects in Manual Punch Out.</p>
                        <form method="post" class="stack-form" data-project-allocation-form>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="admin_employee_project_assign">
                            <input type="hidden" name="user_id" value="<?= $employeeId ?>">
                            <input type="hidden" name="return_type" value="<?= h($employeeType) ?>">
                            <?php render_project_assignment_picker($selectedProjectIds, 'team-project-assignment-options-' . $employeeId, $assignmentRanges); ?>
                            <div class="inline-actions">
                                <button class="button outline" type="button" data-close-modal>Cancel</button>
                                <button class="button solid" type="submit">Save Project Assignment</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!$showVendorAccountsOnly && $editEmployee): ?>
        <?php
            $editEmployeeType = ((string) ($editEmployee['employee_type'] ?? '')) === 'corporate' || ((string) ($editEmployee['role'] ?? '')) === 'corporate_employee'
                ? 'corporate'
                : (((string) ($editEmployee['employee_type'] ?? '')) === 'vendor' ? 'vendor' : 'regular');
            $editUsesManagedFields = in_array($editEmployeeType, ['corporate', 'vendor'], true) || $isFreelancer || $isVendor;
            $editHideCompensationField = $editEmployeeType === 'vendor' || $isVendor || ($editEmployeeType === 'corporate' && !$isFreelancer);
            $editTypeLabel = $editEmployeeType === 'corporate'
                ? 'Contractual Employee'
                : ($editEmployeeType === 'vendor' ? 'Vendor Trainer' : $singularLabel);
        ?>
        <div class="modal open" id="edit-employee-modal" data-open-on-load>
            <div class="modal-card" style="max-width:720px;">
                <button class="modal-close" type="button" data-close-modal onclick="window.location='<?= h(BASE_URL) ?>?page=admin_employees&type=<?= h($employeeType) ?>'">&times;</button>
                <span class="eyebrow">Edit <?= h($editTypeLabel) ?></span>
                <h2><?= h($editEmployee['name']) ?></h2>
                <form method="post" class="stack-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="employee_update">
                    <input type="hidden" name="user_id" value="<?= (int) $editEmployee['id'] ?>">
                    <div class="reports-filter-grid">
                        <label class="<?= $editUsesManagedFields ? 'hidden' : '' ?>" data-contractual-hidden-field data-contractual-emp-id-field>Emp ID<input type="text" name="emp_id" value="<?= h($editEmployee['emp_id']) ?>" <?= $editUsesManagedFields ? 'disabled' : 'required' ?>></label>
                        <?php if ($editUsesManagedFields): ?>
                            <input type="hidden" name="emp_id" value="<?= h($editEmployee['emp_id']) ?>">
                        <?php endif; ?>
                        <label>Name<input type="text" name="name" value="<?= h($editEmployee['name']) ?>" required></label>
                        <label>Email<input type="email" name="email" value="<?= h($editEmployee['email']) ?>" required></label>
                        <label>Phone Number<input type="text" name="phone" value="<?= h($editEmployee['phone']) ?>" required></label>
                        <?php if (!$isVendorTrainerView): ?>
                            <?php
                                $editSelectedShift = normalize_shift_selection((string) ($editEmployee['shift'] ?? ''));
                                $editShiftWindow = shift_window_from_label($editSelectedShift);
                                $editShiftStart = shift_time_input_value((string) ($editShiftWindow['start_time'] ?? ''));
                                $editShiftEnd = shift_time_input_value((string) ($editShiftWindow['end_time'] ?? ''));
                            ?>
                            <input type="hidden" name="shift" value="<?= h($editSelectedShift) ?>">
                            <label class="<?= $editUsesManagedFields ? 'hidden' : '' ?>" data-contractual-hidden-field>Shift From Time<input type="time" name="shift_start_time" value="<?= h($editShiftStart) ?>" <?= $editUsesManagedFields ? 'disabled' : '' ?>></label>
                            <label class="<?= $editUsesManagedFields ? 'hidden' : '' ?>" data-contractual-hidden-field>Shift To Time<input type="time" name="shift_end_time" value="<?= h($editShiftEnd) ?>" <?= $editUsesManagedFields ? 'disabled' : '' ?>></label>
                            <label class="<?= $editHideCompensationField ? 'hidden' : '' ?>"<?= $usesHourlyRate ? '' : ' data-contractual-hidden-field' ?>><?= $usesHourlyRate ? 'Hourly Rate' : 'Salary' ?><input type="number" step="0.01" min="0" name="salary" value="<?= h((string) $editEmployee['salary']) ?>" <?= $editHideCompensationField ? 'disabled' : 'required' ?>></label>
                        <?php endif; ?>
                        <label class="<?= $editUsesManagedFields ? 'hidden' : '' ?>" data-contractual-hidden-field>Recruiter Name<input type="text" name="recruiter_name" value="<?= h((string) ($editEmployee['recruiter_name'] ?? '')) ?>" <?= $editUsesManagedFields ? 'disabled' : 'required' ?>></label>
                        <?php if ($editUsesManagedFields): ?>
                            <input type="hidden" name="recruited_through" value="<?= h((string) ($editEmployee['recruited_through'] ?? '')) ?>">
                        <?php else: ?>
                            <label>Recruited Through<input type="text" name="recruited_through" value="<?= h((string) ($editEmployee['recruited_through'] ?? '')) ?>" required></label>
                        <?php endif; ?>
                        <label class="<?= $editUsesManagedFields ? 'hidden' : '' ?>" data-contractual-hidden-field>Designation
                            <select name="designation" <?= $editUsesManagedFields ? 'disabled' : 'required' ?>>
                                <?php foreach (employee_designation_groups() as $groupLabel => $options): ?>
                                    <optgroup label="<?= h($groupLabel) ?>">
                                        <?php foreach ($options as $value => $optionLabel): ?>
                                            <option value="<?= h($value) ?>" <?= ((string) ($editEmployee['designation'] ?? '')) === $value ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php if ($editUsesManagedFields): ?>
                            <input type="hidden" name="designation" value="<?= h($editEmployeeType === 'corporate' ? 'Contractual' : ($editEmployeeType === 'vendor' ? 'Vendor' : (string) ($editEmployee['designation'] ?? ''))) ?>">
                            <input type="hidden" name="date_of_joining" value="<?= h((string) (($editEmployee['date_of_joining'] ?? '') ?: date('Y-m-d'))) ?>">
                        <?php endif; ?>
                        <label class="<?= $editUsesManagedFields ? 'hidden' : '' ?>" data-contractual-hidden-field>Date of Joining<input type="date" name="date_of_joining" value="<?= h((string) ($editEmployee['date_of_joining'] ?? '')) ?>" <?= $editUsesManagedFields ? 'disabled' : 'required' ?>></label>
                        <?php if ($isFreelancer): ?>
                            <input type="hidden" name="employee_type" value="corporate" data-employee-type-select>
                        <?php elseif ($isVendor): ?>
                            <input type="hidden" name="employee_type" value="vendor" data-employee-type-select>
                        <?php elseif ($editUsesManagedFields): ?>
                            <input type="hidden" name="employee_type" value="<?= h($editEmployeeType) ?>" data-employee-type-select>
                        <?php else: ?>
                            <label>Employee Type<select name="employee_type" data-employee-type-select><option value="regular" <?= $editEmployeeType === 'regular' ? 'selected' : '' ?>>Regular Employee</option><option value="corporate" <?= $editEmployeeType === 'corporate' ? 'selected' : '' ?>>Contractual Employee</option></select></label>
                        <?php endif; ?>
                    </div>
                    <div class="inline-actions">
                        <button class="button solid" type="submit">Save Changes</button>
                        <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_employees&type=<?= h($employeeType) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($employeeType === 'vendor' && !$isVendor): ?>
    <div class="modal" id="vendor-register-modal">
        <div class="modal-card" style="max-width:720px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Vendor Registration</span>
            <h2>Add Vendor Account</h2>
            <p>Create the vendor account here. After saving, select that vendor from the list to manage their employees.</p>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_create_vendor">
                <input type="hidden" name="redirect_page" value="admin_employees">
                <div class="reports-filter-grid">
                    <div class="field">
                        <label>Name of the Company</label>
                        <div class="field-row"><input type="text" name="name" placeholder="Company name" required></div>
                        <small class="field-error"><span>!</span>Company name is required.</small>
                    </div>
                    <div class="field">
                        <label>Company Mail ID</label>
                        <div class="field-row"><input type="email" name="email" placeholder="vendor@company.com" required></div>
                        <small class="field-error"><span>!</span>Enter a valid company mail ID.</small>
                    </div>
                    <div class="field">
                        <label>Company Phone Number</label>
                        <div class="field-row"><input type="text" name="phone" placeholder="Phone number" required></div>
                        <small class="field-error"><span>!</span>Company phone number is required.</small>
                    </div>
                </div>
                <p class="hint">The vendor password will be created the same way as employee passwords and sent to the vendor email automatically.</p>
                <button class="button solid" type="submit">Create Vendor Account</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($employeeType === 'vendor' && !$isVendor): ?>
    <div class="modal <?= $vendorCreatedPopup ? 'open' : '' ?>" id="vendor-created-modal" <?= $vendorCreatedPopup ? 'data-open-on-load' : '' ?>>
        <div class="modal-card" style="max-width:640px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Vendor Created</span>
            <h2>Vendor Account Ready</h2>
            <?php if ($vendorCreatedPopup): ?>
                <p>The vendor account has been created. The password is shown below for admin reference.</p>
                <div class="reports-filter-grid">
                    <div class="field">
                        <label>Vendor Name</label>
                        <div class="field-row"><input type="text" value="<?= h((string) ($vendorCreatedPopup['name'] ?? '')) ?>" readonly></div>
                    </div>
                    <div class="field">
                        <label>Vendor Email</label>
                        <div class="field-row"><input type="text" value="<?= h((string) ($vendorCreatedPopup['email'] ?? '')) ?>" readonly></div>
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <div class="field-row"><input type="text" value="<?= h((string) ($vendorCreatedPopup['password'] ?? '')) ?>" readonly></div>
                    </div>
                </div>
                <p class="hint">
                    <?= !empty($vendorCreatedPopup['mail_sent'])
                        ? 'The password was also sent to the vendor email.'
                        : 'Email delivery was not confirmed. Check storage/emails/' . h((string) ($vendorCreatedPopup['mail_log'] ?? '')) . (((string) ($vendorCreatedPopup['mail_error'] ?? '')) !== '' ? ' | Error: ' . h((string) $vendorCreatedPopup['mail_error']) : '') . '.' ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="modal <?= $stage === 'manual_rules' ? 'open' : '' ?>" id="add-employee-modal" <?= $stage === 'manual_rules' ? 'data-open-on-load' : '' ?>>
        <div class="modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <?php if ($stage === 'manual_rules' && $pendingEmployee): ?>
                <div class="steps">
                    <span class="step-pill">Step 1 of 2</span>
                    <span class="step-pill active">Step 2 of 2</span>
                </div>
                <div class="list-item">
                    <strong><?= h($pendingEmployee['name']) ?></strong><br>
                    <?= h($pendingEmployee['emp_id']) ?> | <?= h($pendingEmployee['email']) ?>
                </div>
                <form method="post" class="stack-form" data-rule-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="employee_manual_submit">
                    <h3>Rules Assignment</h3>
                    <?php render_rules_editor([
                        'shift' => (string) ($pendingEmployee['shift'] ?? standard_shift_options()[0]),
                        'manual_punch_in' => true,
                        'manual_punch_out' => true,
                        'manual_out_count' => 1,
                    ], null, false, false, false, false, true, true, (string) ($pendingEmployee['employee_type'] ?? '') !== 'corporate'); ?>
                    <button class="button solid" type="submit" data-rule-submit>Submit</button>
                </form>
            <?php else: ?>
                <div class="steps">
                    <span class="step-pill active">Step 1 of 2</span>
                    <span class="step-pill">Step 2 of 2</span>
                </div>
                <h2>Add <?= h($singularLabel) ?></h2>
                <form method="post" class="stack-form" data-validate data-watch-required>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="employee_manual_next">
                    <?php if ($employeeType === 'vendor' && !empty($_GET['vendor_id'])): ?>
                        <input type="hidden" name="vendor_id" value="<?= (int) $_GET['vendor_id'] ?>">
                    <?php endif; ?>
                    <div class="reports-filter-grid">
                        <div class="field<?= ($employeeType === 'corporate' || $employeeType === 'vendor' || $isFreelancer || $isVendor) ? ' hidden' : '' ?>" data-contractual-hidden-field data-contractual-emp-id-field><label>Emp ID</label><div class="field-row"><input type="text" name="emp_id" <?= ($employeeType === 'corporate' || $employeeType === 'vendor' || $isFreelancer || $isVendor) ? 'disabled' : 'required' ?>></div><small class="field-error"><span>!</span>Emp ID is required.</small></div>
                        <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                        <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" required></div><small class="field-error"><span>!</span>Phone number required.</small></div>
                        <div class="field"><label>Mail ID</label><div class="field-row"><input type="email" name="email" required></div><small class="field-error"><span>!</span>Valid mail ID required.</small></div>
                        <div class="field<?= ($employeeType === 'vendor' || $isVendor) ? ' hidden' : '' ?>"><label>Sourced Through</label><div class="field-row"><input type="text" name="recruited_through" <?= ($employeeType === 'vendor' || $isVendor) ? 'disabled' : 'required' ?>></div><small class="field-error"><span>!</span>Source is required.</small></div>
                        <?php $hideCompensationField = ($employeeType === 'vendor' || $isVendor || ($employeeType === 'corporate' && !$isFreelancer)); ?>
                        <div class="field<?= $hideCompensationField ? ' hidden' : '' ?>"<?= $usesHourlyRate ? '' : ' data-contractual-hidden-field' ?>><label><?= $usesHourlyRate ? 'Hourly Rate' : 'Salary' ?></label><div class="field-row"><input type="number" step="0.01" min="0" name="salary" <?= $hideCompensationField ? 'disabled' : 'required' ?>></div><small class="field-error"><span>!</span><?= $usesHourlyRate ? 'Hourly rate is required.' : 'Salary is required.' ?></small></div>
                        <div class="field<?= ($employeeType === 'corporate' || $employeeType === 'vendor' || $isFreelancer || $isVendor) ? ' hidden' : '' ?>" data-contractual-hidden-field><label>Recruiter Name</label><div class="field-row"><input type="text" name="recruiter_name" <?= ($employeeType === 'corporate' || $employeeType === 'vendor' || $isFreelancer || $isVendor) ? 'disabled' : 'required' ?>></div><small class="field-error"><span>!</span>Recruiter name is required.</small></div>
                        <div class="field<?= ($employeeType === 'corporate' || $employeeType === 'vendor' || $isFreelancer || $isVendor) ? ' hidden' : '' ?>" data-contractual-hidden-field><label>Designation</label><div class="field-row"><input type="text" name="designation" <?= ($employeeType === 'corporate' || $employeeType === 'vendor' || $isFreelancer || $isVendor) ? 'disabled' : 'required' ?>></div><small class="field-error"><span>!</span>Designation is required.</small></div>
                        <div class="field<?= ($employeeType === 'corporate' || $employeeType === 'vendor' || $isFreelancer || $isVendor) ? ' hidden' : '' ?>" data-contractual-hidden-field><label>Date of Joining</label><div class="field-row"><input type="date" name="date_of_joining" <?= ($employeeType === 'corporate' || $employeeType === 'vendor' || $isFreelancer || $isVendor) ? 'disabled' : 'required' ?>></div><small class="field-error"><span>!</span>Date of joining is required.</small></div>
                        <?php if ($isFreelancer || $employeeType === 'corporate'): ?>
                            <input type="hidden" name="employee_type" value="corporate" data-employee-type-select>
                        <?php elseif ($isVendor || $employeeType === 'vendor'): ?>
                            <input type="hidden" name="employee_type" value="vendor" data-employee-type-select>
                        <?php else: ?>
                            <input type="hidden" name="employee_type" value="regular" data-employee-type-select>
                        <?php endif; ?>
                    </div>
                    <button class="button solid" type="submit" data-required-submit>Next</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="modal <?= $stage === 'csv_rules' ? 'open' : '' ?>" id="employee-csv-modal" <?= $stage === 'csv_rules' ? 'data-open-on-load' : '' ?>>
        <div class="modal-card" style="max-width:720px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <?php if ($stage === 'csv_rules' && $pendingCsv): ?>
                <div class="steps">
                    <span class="step-pill">Step 1 of 2</span>
                    <span class="step-pill active">Step 2 of 2</span>
                </div>
                <span class="eyebrow">Bulk Import</span>
                <h2>Assign Rules to Imported <?= h($label) ?></h2>
                <p><?= count($pendingCsv) ?> <?= h(strtolower($singularLabel)) ?> row(s) are ready. Review the sample below, then choose the rules to apply to every imported <?= h(strtolower($singularLabel)) ?>.</p>
                <div class="preview-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th><?= $usesHourlyRate ? 'Hourly Rate' : 'Salary' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($pendingCsv, 0, 5) as $row): ?>
                                <tr>
                                    <td><?= h((string) ($row['emp_id'] ?? '')) ?></td>
                                    <td><?= h((string) ($row['name'] ?? '')) ?></td>
                                    <td><?= h((string) ($row['email'] ?? '')) ?></td>
                                    <td><?= h((string) ($row['phone'] ?? '')) ?></td>
                                    <td><?= h(number_format((float) ($row['salary'] ?? 0), 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <form method="post" class="stack-form" data-rule-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="employee_csv_submit">
                    <h3>Employee Allocation</h3>
                    <?php render_rules_editor([
                        'shift' => standard_shift_options()[0],
                    ], null, false, false, false, false, false, false); ?>
                    <div class="inline-actions">
                        <button class="button outline" type="submit" name="action" value="employee_csv_cancel">Cancel</button>
                        <button class="button solid" type="submit" data-rule-submit>Import <?= h($label) ?></button>
                    </div>
                </form>
            <?php else: ?>
                <div class="steps">
                    <span class="step-pill active">Step 1 of 2</span>
                    <span class="step-pill">Step 2 of 2</span>
                </div>
                <span class="eyebrow">Bulk Import</span>
                <h2>Import <?= h($label) ?> from CSV</h2>
                <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="employee_csv_upload">
                    <?php if ($employeeType === 'vendor' && !empty($_GET['vendor_id'])): ?>
                        <input type="hidden" name="vendor_id" value="<?= (int) $_GET['vendor_id'] ?>">
                    <?php endif; ?>
                    <div class="reports-filter-grid">
                        <?php if ($isFreelancer): ?>
                            <input type="hidden" name="employee_type" value="corporate">
                        <?php elseif ($isVendor): ?>
                            <input type="hidden" name="employee_type" value="vendor">
                        <?php else: ?>
                            <div class="field"><label>Employee Type</label><div class="field-row"><select name="employee_type" required><option value="regular" <?= $employeeType === 'regular' ? 'selected' : '' ?>>Regular Employee</option><option value="corporate" <?= $employeeType === 'corporate' ? 'selected' : '' ?>>Contractual Employee</option></select></div><small class="field-error"><span>!</span>Employee type is required.</small></div>
                        <?php endif; ?>
                    </div>
                    <label class="upload-drop">
                        <strong>Select <?= h(strtolower($singularLabel ?? 'Employee')) ?> file</strong>
                        <p>Upload a `.xlsx`, `.xls`, `.csv`, or `.txt` file with <?= h(strtolower($singularLabel ?? 'Employee')) ?> details. Required columns are ID, Name, Email, Phone<?= ($employeeType === 'vendor' || $isVendor) ? '' : ', and ' . ($usesHourlyRate ? 'Hourly Rate' : 'Salary') ?>.</p>
                        <input type="file" name="csv_file" accept=".xlsx,.xls,.csv,.txt" required>
                    </label>
                    <button class="button solid" type="submit">Import File</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="delete-employee-modal">
        <div class="modal-card" style="max-width:560px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Confirm Delete</span>
            <h2>Delete <?= h($singularLabel) ?></h2>
            <p>This will permanently remove <strong data-delete-name><?= h(strtolower($singularLabel)) ?></strong> and related attendance records.</p>
            <form method="post" class="inline-actions">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="employee_delete">
                <input type="hidden" name="user_id" value="">
                <button class="button outline" type="button" data-close-modal>Cancel</button>
                <button class="button secondary" type="submit">Delete <?= h($singularLabel) ?></button>
            </form>
        </div>
    </div>

    <?php
    render_footer();
}

function render_admin_projects(): void
{
    $user = require_power_projects_access(['admin', 'external_vendor']);
    $isVendor = ($user['role'] ?? '') === 'external_vendor';

    if ($isVendor) {
        $projectAssignableEmployees = project_assignable_employees();

        render_header('Projects');
        ?>
        <section class="page-title">
            <div>
                <span class="eyebrow">Vendor - Projects</span>
                <h1>Projects</h1>
                <p>Assign verified active projects to your employees.</p>
            </div>
        </section>

        <section class="section-block scroll-panel rules-assignment-panel">
            <div class="split rules-section-head">
                <div>
                    <span class="eyebrow">Project Allocation</span>
                    <h2>Assign Project</h2>
                </div>
                <span class="hint">Choose employee names and verified active projects.</span>
            </div>
            <form method="post" class="stack-form" data-rule-form data-employee-form data-project-allocation-form>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="apply_rules">
                <input type="hidden" name="allocation_type" value="project">
                <?php render_employee_assignment_picker($projectAssignableEmployees, 'vendor-project-employee-options', 'vendor-project-selected-employee-tags', 'Select Names'); ?>
                <?php render_project_assignment_picker([], 'vendor-project-allocation-options'); ?>
                <div class="inline-actions">
                    <button class="button solid" type="submit" data-rule-submit>Save Project Allocation</button>
                </div>
            </form>
        </section>
        <?php
        render_footer();
        return;
    }

    $allProjects = projects();
    $stage = (string) ($_GET['stage'] ?? '');
    $editProject = isset($_GET['edit']) ? project_by_id((int) $_GET['edit']) : null;
    $projectDraft = $_SESSION['project_form'] ?? null;
    unset($_SESSION['project_form']);

    $formValues = project_form_defaults();
    if ($editProject) {
        $formValues = array_merge($formValues, $editProject);
    }
    if (is_array($projectDraft)) {
        $formValues = array_merge($formValues, $projectDraft);
    }

    $isEditing = $editProject !== null;
    $shouldOpenModal = $stage === 'create' || $isEditing;
    $shouldOpenContractualModal = $stage === 'contractual_create';
    $shouldOpenVendorModal = $stage === 'vendor_create';
    $modalTitle = $isEditing ? 'Edit Project' : 'Add Project';
    $submitLabel = $isEditing ? 'Save Project' : 'Add Project';
    $currentAdminId = (int) ($user['id'] ?? 0);
    $confirmationProjectId = $stage === 'contractual_confirm' ? (int) ($_GET['project_id'] ?? 0) : 0;
    $confirmationProject = $confirmationProjectId > 0 ? project_by_id($confirmationProjectId, $currentAdminId) : null;
    $confirmationAssignments = [];
    if ($confirmationProject) {
        $confirmationStmt = db()->prepare("SELECT u.*, a.project_from, a.project_to, a.project_daily_salary, COALESCE(a.project_pay_basis, 'daily') AS project_pay_basis
            FROM employee_project_assignments a
            INNER JOIN users u ON u.id = a.user_id
            WHERE a.project_id = :project_id
              AND u.admin_id = :admin_id
              AND (u.role = 'corporate_employee' OR u.employee_type = 'corporate')
            ORDER BY u.name");
        $confirmationStmt->execute([
            'project_id' => $confirmationProjectId,
            'admin_id' => $currentAdminId,
        ]);
        $confirmationAssignments = $confirmationStmt->fetchAll();
    }
    $shouldOpenContractualConfirmModal = $confirmationProject !== null;
    $projectAssignableEmployees = project_assignable_employees();
    $contractualEmployeesStmt = db()->prepare("SELECT * FROM users WHERE admin_id = :admin_id AND (role = 'corporate_employee' OR employee_type = 'corporate') ORDER BY name");
    $contractualEmployeesStmt->execute(['admin_id' => $currentAdminId]);
    $contractualEmployees = $contractualEmployeesStmt->fetchAll();
    $contractualSetup = $isEditing
        ? contractual_project_setup_for_project((int) ($formValues['id'] ?? 0), $currentAdminId)
        : [
            'employee_ids' => [],
            'from' => '',
            'to' => '',
            'daily_salary' => 0.0,
            'pay_basis' => 'daily',
        ];
    if (is_array($projectDraft)) {
        $contractualSetup = [
            'employee_ids' => array_map('intval', $projectDraft['contractual_employee_ids'] ?? []),
            'from' => (string) ($projectDraft['contractual_project_from'] ?? ''),
            'to' => (string) ($projectDraft['contractual_project_to'] ?? ''),
            'daily_salary' => (float) ($projectDraft['contractual_daily_salary'] ?? 0),
            'pay_basis' => (string) ($projectDraft['contractual_pay_basis'] ?? 'daily'),
        ];
    }
    $selectedContractualLookup = array_fill_keys(array_map('intval', $contractualSetup['employee_ids'] ?? []), true);
    $ongoingProjects = [];
    $completedProjects = [];
    foreach ($allProjects as $project) {
        $approvalStatus = (string) ($project['approval_status'] ?? 'verified');
        if ($approvalStatus !== 'pending' && empty($project['is_active'])) {
            $completedProjects[] = $project;
        } else {
            $ongoingProjects[] = $project;
        }
    }
    $projectAssignedTrainerLookup = [];
    $projectTrainerRecordLookup = [];
    $projectIds = array_values(array_filter(array_map(static fn(array $project): int => (int) ($project['id'] ?? 0), $allProjects)));
    if ($projectIds) {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $trainerStmt = db()->prepare("SELECT a.project_id, a.user_id AS assignment_user_id, a.project_from, a.project_to, u.*
            FROM employee_project_assignments a
            INNER JOIN users u ON u.id = a.user_id
            WHERE a.project_id IN ($placeholders)
              AND u.role IN ('employee', 'corporate_employee')
            ORDER BY u.name");
        $trainerStmt->execute($projectIds);
        foreach ($trainerStmt->fetchAll() as $trainer) {
            $projectAssignedTrainerLookup[(int) ($trainer['project_id'] ?? 0)][] = $trainer;
        }

        $recordStmt = db()->prepare("SELECT s.*, ar.user_id, ar.attend_date
            FROM attendance_sessions s
            INNER JOIN attendance_records ar ON ar.id = s.attendance_id
            WHERE s.project_id IN ($placeholders)
              AND s.session_mode = 'project_record'
            ORDER BY ar.attend_date DESC, s.id DESC");
        $recordStmt->execute($projectIds);
        foreach ($recordStmt->fetchAll() as $record) {
            $lookupKey = (int) ($record['project_id'] ?? 0) . ':' . (int) ($record['user_id'] ?? 0);
            $projectTrainerRecordLookup[$lookupKey][] = $record;
        }
    }

    render_header('Projects');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow"><?= $isVendor ? 'Vendor - Projects' : 'Admin - Projects' ?></span>
            <h1>Projects</h1>
            <p>Create and manage project colleges and locations from one place. Deactivated projects stay saved, become currently unavailable, and can be activated again later.</p>
        </div>
        <div class="action-bar">
            <button class="button solid" type="button" data-modal-target="project-type-modal">Add Project</button>
        </div>
    </section>

    <section class="project-status-columns">
        <?php foreach ([['title' => 'Ongoing', 'projects' => $ongoingProjects], ['title' => 'Completed', 'projects' => $completedProjects]] as $projectGroup): ?>
            <div class="table-wrap project-status-column">
                <div class="data-toolbar">
                    <div class="split">
                        <h2><?= h($projectGroup['title']) ?></h2>
                        <span class="badge"><?= count($projectGroup['projects']) ?> total</span>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Project ID</th>
                            <th>Project Name</th>
                            <th>Vendor</th>
                            <th>College</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projectGroup['projects'] as $project): ?>
                            <?php
                            $approvalStatus = (string) ($project['approval_status'] ?? 'verified');
                            $statusLabel = $approvalStatus === 'pending'
                                ? 'Pending Verification'
                                : (!empty($project['is_active']) ? 'Active' : 'Inactive');
                            $statusClass = $approvalStatus === 'pending'
                                ? 'Pending'
                                : (!empty($project['is_active']) ? 'Active' : 'Inactive');
                            $projectTrainerModalId = 'project-trainers-modal-' . (int) $project['id'];
                            ?>
                            <tr>
                                <td class="project-click-cell" data-modal-target="<?= h($projectTrainerModalId) ?>"><?= h((string) (($project['project_code'] ?? '') ?: 'After Verify')) ?></td>
                                <td class="project-click-cell" data-modal-target="<?= h($projectTrainerModalId) ?>">
                                    <button class="project-name-trigger" type="button" data-modal-target="<?= h($projectTrainerModalId) ?>"><?= h((string) $project['project_name']) ?></button>
                                    <p class="hint"><?= h((string) (($project['location'] ?? '') ?: '-')) ?> | <?= h((string) (($project['created_by_name'] ?? '') ?: 'Admin')) ?></p>
                                </td>
                                <td class="project-click-cell" data-modal-target="<?= h($projectTrainerModalId) ?>"><?= h((string) (($project['vendor_name'] ?? '') ?: '-')) ?></td>
                                <td class="project-click-cell" data-modal-target="<?= h($projectTrainerModalId) ?>"><?= h((string) $project['college_name']) ?></td>
                                <td class="project-click-cell" data-modal-target="<?= h($projectTrainerModalId) ?>"><span class="status-pill status-<?= h($statusClass) ?>"><?= h($statusLabel) ?></span></td>
                                <td>
                                    <div class="inline-actions">
                                        <a class="button ghost small" href="<?= h(BASE_URL) ?>?page=admin_projects&edit=<?= (int) $project['id'] ?>">Edit</a>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="project_delete">
                                            <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                            <button class="button secondary small" type="submit" onclick="return confirm('Delete this project?');">Delete</button>
                                        </form>
                                        <?php if ($approvalStatus === 'pending' && !$isVendor): ?>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="project_verify">
                                                <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                                <button class="button solid small" type="submit">Verify</button>
                                            </form>
                                        <?php elseif ($approvalStatus === 'pending'): ?>
                                            <span class="status-pill status-Pending">Waiting</span>
                                        <?php else: ?>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="project_toggle_active">
                                                <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                                <button class="button outline small" type="submit"><?= !empty($project['is_active']) ? 'Inactive' : 'Activate' ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$projectGroup['projects']): ?>
                    <div class="list-item muted table-empty-state">No <?= h(strtolower($projectGroup['title'])) ?> projects found.</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <?php foreach ($allProjects as $project): ?>
        <?php
            $projectId = (int) ($project['id'] ?? 0);
            $assignedTrainers = $projectAssignedTrainerLookup[$projectId] ?? [];
        ?>
        <div class="modal" id="project-trainers-modal-<?= $projectId ?>">
            <div class="modal-card employee-rules-modal-card">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Assigned Trainers</span>
                <h2><?= h((string) ($project['project_name'] ?? 'Project')) ?></h2>
                <p class="hint"><?= h((string) (($project['college_name'] ?? '') ?: '-')) ?> | <?= h((string) (($project['location'] ?? '') ?: '-')) ?></p>
                <h3>Currently Assigned</h3>
                <?php if ($assignedTrainers): ?>
                    <div class="project-trainer-list">
                        <div class="project-trainer-list-head" aria-hidden="true">
                            <span>Emp ID</span>
                            <span>Name</span>
                            <span>Project</span>
                        </div>
                        <?php foreach ($assignedTrainers as $trainer): ?>
                            <?php
                            $trainerProjectRecords = $projectTrainerRecordLookup[$projectId . ':' . (int) ($trainer['id'] ?? 0)] ?? [];
                            ?>
                            <details class="project-trainer-detail">
                                <summary>
                                    <span><?= h((string) (($trainer['emp_id'] ?? '') ?: '-')) ?></span>
                                    <span><?= h((string) (($trainer['name'] ?? '') ?: '-')) ?></span>
                                    <span><?= h((string) ($project['project_name'] ?? 'Project')) ?></span>
                                </summary>
                                <div class="project-trainer-profile">
                                    <div class="rules-detail-grid project-trainer-grid">
                                        <section class="rules-detail-panel project-records-panel">
                                            <div class="project-records-section-head">
                                                <div>
                                                    <span class="eyebrow">Project Records</span>
                                                    <h3><?= h((string) (($trainer['name'] ?? '') ?: 'Trainer')) ?></h3>
                                                    <p class="hint"><?= h((string) (($trainer['emp_id'] ?? '') ?: '-')) ?> | <?= h((string) ($project['project_name'] ?? 'Project')) ?></p>
                                                </div>
                                                <span class="badge"><?= count($trainerProjectRecords) ?> submitted</span>
                                            </div>
                                            <?php if ($trainerProjectRecords): ?>
                                                <div class="project-record-table-wrap">
                                                    <table class="project-record-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Sn</th>
                                                                <th>Date</th>
                                                                <th>College</th>
                                                                <th>Subject</th>
                                                                <th>Day Type</th>
                                                                <th>Topics Handled</th>
                                                                <th>Total</th>
                                                                <th>Present</th>
                                                                <th>Absent</th>
                                                                <th>Location</th>
                                                                <th>GPS Photo</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($trainerProjectRecords as $recordIndex => $record): ?>
                                                                <?php
                                                                $totalStudents = (int) ($record['total_students'] ?? 0);
                                                                $presentStudents = (int) ($record['present_students'] ?? 0);
                                                                $absentStudents = max(0, $totalStudents - $presentStudents);
                                                                $recordDate = !empty($record['attend_date']) ? date('d M Y', strtotime((string) $record['attend_date'])) : '-';
                                                                $gpsPhotoPath = trim((string) ($record['punch_in_path'] ?? ''));
                                                                $gpsPhotoUrl = $gpsPhotoPath !== '' ? public_file_path($gpsPhotoPath) : '';
                                                                ?>
                                                                <tr>
                                                                    <td data-label="#"><?= (int) ($recordIndex + 1) ?></td>
                                                                    <td data-label="Date"><?= h($recordDate) ?></td>
                                                                    <td data-label="College"><?= h((string) (($record['college_name'] ?? '') ?: '-')) ?></td>
                                                                    <td data-label="Subject"><?= h((string) (($record['session_name'] ?? '') ?: '-')) ?></td>
                                                                    <td data-label="Day Type"><?= h((string) (($record['day_portion'] ?? '') ?: 'Full Day')) ?></td>
                                                                    <td data-label="Topics Handled" class="project-record-topic-cell"><?= h((string) (($record['topics_handled'] ?? '') ?: '-')) ?></td>
                                                                    <td data-label="Total"><?= h((string) $totalStudents) ?></td>
                                                                    <td data-label="Present"><?= h((string) $presentStudents) ?></td>
                                                                    <td data-label="Absent"><?= h((string) $absentStudents) ?></td>
                                                                    <td data-label="Location"><?= h((string) (($record['location'] ?? '') ?: '-')) ?></td>
                                                                    <td data-label="GPS Photo"><?php if ($gpsPhotoUrl !== ''): ?><a class="project-record-photo-link" href="<?= h($gpsPhotoUrl) ?>" target="_blank" rel="noopener">View</a><?php else: ?>-<?php endif; ?></td>
                                                                    <td data-label="Status"><span class="status-pill status-Present">Present</span></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="list-item muted">No project records submitted for this trainer and project.</div>
                                            <?php endif; ?>
                                        </section>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="list-item muted" style="display:block;">No trainers are assigned to this project.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($confirmationProject): ?>
        <div class="modal <?= $shouldOpenContractualConfirmModal ? 'open' : '' ?>" id="contractual-confirmation-modal" <?= $shouldOpenContractualConfirmModal ? 'data-open-on-load' : '' ?>>
            <div class="modal-card project-confirmation-modal-card">
                <a class="modal-close" href="<?= h(BASE_URL) ?>?page=admin_projects">&times;</a>
                <span class="eyebrow">Review Template</span>
                <h2>Confirm Project Letter</h2>
                <?php if ($confirmationAssignments): ?>
                    <p class="hint">Review and edit the confirmation letter template below. It will be sent to the selected contractual employee after you confirm.</p>
                    <form method="post" class="stack-form" data-project-confirmation-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="contractual_project_send_confirmations">
                        <input type="hidden" name="project_id" value="<?= (int) ($confirmationProject['id'] ?? 0) ?>">
                        <div class="project-confirmation-preview-list">
                        <?php foreach ($confirmationAssignments as $assignment): ?>
                            <?php $assignmentId = (int) ($assignment['id'] ?? 0); ?>
                            <section class="project-confirmation-preview">
                                <div class="split">
                                    <strong><?= h((string) ($assignment['name'] ?? 'Contractual Employee')) ?></strong>
                                    <span class="badge"><?= h((string) ($assignment['emp_id'] ?? '')) ?></span>
                                </div>
                                <div class="project-confirmation-letter" contenteditable="true" spellcheck="true" data-confirmation-editor="<?= $assignmentId ?>">
                                    <?= contractual_confirmation_template_preview_html($assignment, $confirmationProject, $assignment, $user) ?>
                                </div>
                                <div class="signature-upload-grid">
                                    <label class="signature-upload-control">
                                        <span>Upload Authorized Signature</span>
                                        <input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-signature-upload="<?= $assignmentId ?>" data-signature-type="authorized">
                                    </label>
                                    <label class="signature-upload-control">
                                        <span>Upload Trainer Signature</span>
                                        <input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-signature-upload="<?= $assignmentId ?>" data-signature-type="trainer">
                                    </label>
                                </div>
                                <input type="hidden" name="confirmation_html[<?= $assignmentId ?>]" data-confirmation-html="<?= $assignmentId ?>">
                            </section>
                        <?php endforeach; ?>
                        </div>
                        <div class="inline-actions project-modal-actions">
                            <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_projects">Cancel</a>
                            <button class="button solid" type="submit">Confirm & Send to Contractual Employee</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p>No contractual employees are assigned to this project.</p>
                    <div class="inline-actions project-modal-actions">
                        <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_projects">Close</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal" id="project-type-modal">
        <div class="modal-card project-type-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Project Type</span>
            <h2>Add Project</h2>
            <p class="hint">Choose who this project is for, then fill the matching project details.</p>
            <div class="project-type-grid">
                <button class="project-type-option" type="button" data-switch-modal-target="project-modal">
                    <strong>Admin Employees</strong>
                    <span>Create a regular project for admin-side employees and trainers.</span>
                </button>
                <button class="project-type-option" type="button" data-switch-modal-target="contractual-project-modal">
                    <strong>Contractual Employees</strong>
                    <span>Assign contractual employees with hourly or daily rate setup.</span>
                </button>
                <button class="project-type-option" type="button" data-switch-modal-target="vendor-project-modal">
                    <strong>Vendor</strong>
                    <span>Create a vendor project with vendor, college, and location details.</span>
                </button>
            </div>
        </div>
    </div>

    <div class="modal <?= $shouldOpenModal ? 'open' : '' ?>" id="project-modal" <?= $shouldOpenModal ? 'data-open-on-load' : '' ?>>
        <div class="modal-card project-modal-card">
            <?php if ($isEditing): ?>
                <button class="modal-close" type="button" data-close-modal onclick="window.location='<?= h(BASE_URL) ?>?page=admin_projects'">&times;</button>
            <?php else: ?>
                <button class="modal-close" type="button" data-close-modal>&times;</button>
            <?php endif; ?>
            <span class="eyebrow">Project Setup</span>
            <h2><?= h($modalTitle) ?></h2>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="project_save">
                <input type="hidden" name="project_id" value="<?= (int) ($formValues['id'] ?? 0) ?>">
                <div class="reports-filter-grid">
                    <label>Project Name
                        <input type="text" name="project_name" value="<?= h((string) ($formValues['project_name'] ?? '')) ?>" placeholder="Summer Skill Development Program" required>
                    </label>
                    <label>Vendor
                        <input type="text" name="vendor_name" value="<?= h((string) ($formValues['vendor_name'] ?? '')) ?>" placeholder="Vendor name">
                    </label>
                    <label>College Name
                        <input type="text" name="college_name" value="<?= h((string) ($formValues['college_name'] ?? '')) ?>" placeholder="ABC Engineering College" required>
                    </label>
                    <label>Location
                        <input type="text" name="location" value="<?= h((string) ($formValues['location'] ?? '')) ?>" placeholder="Ahmedabad, Gujarat" required>
                    </label>
                    <label class="project-checkbox-field">Active
                        <input type="checkbox" name="is_active" value="1" <?= !empty($formValues['is_active']) ? 'checked' : '' ?>>
                    </label>
                </div>
                <div class="inline-actions project-modal-actions">
                    <?php if ($isEditing): ?>
                        <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_projects">Cancel</a>
                    <?php else: ?>
                        <button class="button outline" type="button" data-close-modal>Cancel</button>
                    <?php endif; ?>
                    <button class="button solid" type="submit"><?= h($submitLabel) ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal <?= $shouldOpenVendorModal ? 'open' : '' ?>" id="vendor-project-modal" <?= $shouldOpenVendorModal ? 'data-open-on-load' : '' ?>>
        <div class="modal-card project-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Vendor Project</span>
            <h2>Add Vendor Project</h2>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="project_save">
                <input type="hidden" name="project_kind" value="vendor">
                <input type="hidden" name="project_id" value="0">
                <div class="reports-filter-grid">
                    <label>Project Name
                        <input type="text" name="project_name" value="<?= h((string) ($formValues['project_name'] ?? '')) ?>" placeholder="Vendor training program" required>
                    </label>
                    <label>Vendor
                        <?php $vendorAccounts = db()->query("SELECT id, name, company_name FROM users WHERE role = 'external_vendor' AND status = 'ACTIVE' ORDER BY COALESCE(NULLIF(company_name, ''), name), name")->fetchAll(); ?>
                        <?php if ($vendorAccounts): ?>
                            <select name="vendor_id" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendorAccounts as $vendorAccount): ?>
                                    <?php $vendorAccountName = (string) (($vendorAccount['company_name'] ?? '') ?: ($vendorAccount['name'] ?? '')); ?>
                                    <option value="<?= (int) $vendorAccount['id'] ?>" <?= (int) ($formValues['vendor_id'] ?? 0) === (int) $vendorAccount['id'] ? 'selected' : '' ?>><?= h($vendorAccountName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="vendor_name" value="<?= h((string) ($formValues['vendor_name'] ?? '')) ?>" placeholder="Vendor name" required>
                        <?php endif; ?>
                    </label>
                    <label>College Name
                        <input type="text" name="college_name" value="<?= h((string) ($formValues['college_name'] ?? '')) ?>" placeholder="ABC Engineering College" required>
                    </label>
                    <label>Location
                        <input type="text" name="location" value="<?= h((string) ($formValues['location'] ?? '')) ?>" placeholder="Ahmedabad, Gujarat" required>
                    </label>
                    <label class="project-checkbox-field">Active
                        <input type="checkbox" name="is_active" value="1" <?= !empty($formValues['is_active']) ? 'checked' : '' ?>>
                    </label>
                </div>
                <div class="inline-actions project-modal-actions">
                    <button class="button outline" type="button" data-close-modal>Cancel</button>
                    <button class="button solid" type="submit">Add Vendor Project</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal <?= $shouldOpenContractualModal ? 'open' : '' ?>" id="contractual-project-modal" <?= $shouldOpenContractualModal ? 'data-open-on-load' : '' ?>>
        <div class="modal-card project-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Contractual Project</span>
            <h2>Add Contractual Project</h2>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="project_save">
                <input type="hidden" name="project_kind" value="contractual">
                <input type="hidden" name="project_id" value="0">
                <div class="reports-filter-grid">
                    <label>Project Name
                        <input type="text" name="project_name" value="<?= h((string) ($formValues['project_name'] ?? '')) ?>" placeholder="Contractual training program" required>
                    </label>
                    <label>Vendor
                        <input type="text" name="vendor_name" value="<?= h((string) ($formValues['vendor_name'] ?? '')) ?>" placeholder="Vendor name">
                    </label>
                    <label>College Name
                        <input type="text" name="college_name" value="<?= h((string) ($formValues['college_name'] ?? '')) ?>" placeholder="ABC Engineering College" required>
                    </label>
                    <label>Location
                        <input type="text" name="location" value="<?= h((string) ($formValues['location'] ?? '')) ?>" placeholder="Ahmedabad, Gujarat" required>
                    </label>
                    <label class="project-checkbox-field">Active
                        <input type="checkbox" name="is_active" value="1" <?= !empty($formValues['is_active']) ? 'checked' : '' ?>>
                    </label>
                </div>
                <div class="employee-picker">
                    <div class="split">
                        <strong>Contractual Employees</strong>
                        <span class="hint">Assign this project and hours to selected contractual employees.</span>
                    </div>
                    <?php if ($contractualEmployees): ?>
                        <input type="text" placeholder="Search contractual employees..." data-employee-filter="contractual-project-employee-options">
                        <div class="tag-list" id="contractual-project-selected-tags"></div>
                        <div class="employee-options" id="contractual-project-employee-options" data-tag-source="contractual-project-selected-tags">
                            <?php foreach ($contractualEmployees as $contractualEmployee): ?>
                                <?php $contractualEmployeeId = (int) ($contractualEmployee['id'] ?? 0); ?>
                                <label class="employee-option">
                                    <input type="checkbox" name="contractual_employee_ids[]" value="<?= $contractualEmployeeId ?>" data-label="<?= h((string) ($contractualEmployee['name'] ?? '')) ?>" <?= isset($selectedContractualLookup[$contractualEmployeeId]) ? 'checked' : '' ?>>
                                    <span><?= h((string) ($contractualEmployee['name'] ?? '')) ?> (<?= h((string) ($contractualEmployee['emp_id'] ?? '')) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="reports-filter-grid">
                            <label>Payment Basis
                                <select name="contractual_pay_basis" required>
                                    <?php foreach (project_pay_basis_options() as $basisValue => $basisLabel): ?>
                                        <option value="<?= h($basisValue) ?>" <?= normalize_project_pay_basis($contractualSetup['pay_basis'] ?? 'daily') === $basisValue ? 'selected' : '' ?>><?= h($basisLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Project From
                                <input type="date" name="contractual_project_from" value="<?= h((string) ($contractualSetup['from'] ?? '')) ?>">
                            </label>
                            <label>Project To
                                <input type="date" name="contractual_project_to" value="<?= h((string) ($contractualSetup['to'] ?? '')) ?>">
                            </label>
                            <label>Rate (per hour/day)
                                <input type="number" name="contractual_daily_salary" min="0.01" step="0.01" value="<?= h(number_format((float) ($contractualSetup['daily_salary'] ?? 0), 2, '.', '')) ?>" placeholder="0.00" required>
                            </label>
                        </div>
                    <?php else: ?>
                        <div class="list-item muted">No contractual employees are available yet.</div>
                    <?php endif; ?>
                </div>
                <div class="inline-actions project-modal-actions">
                    <button class="button outline" type="button" data-close-modal>Cancel</button>
                    <button class="button solid" type="submit" <?= $contractualEmployees ? '' : 'disabled' ?>>Add Contractual Project</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    render_footer();
}

function render_admin_rules(): void 
{
    require_role('admin');
    $projectAssignableEmployees = project_assignable_employees();
    render_header('Rules', 'admin-rules-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Rules</span>
            <h1>Rules Workspace</h1>
            <p>Handle employee power access and project allocation from one page without switching screens.</p>
        </div>
    </section>
    <section class="rules-workspace-grid">
        <div class="rules-left-stack">
            <section class="section-block scroll-panel rules-assignment-panel">
                <div class="split rules-section-head">
                    <div>
                        <span class="eyebrow">Power Access</span>
                        <h2>Power Access</h2>
                    </div>
                    <span class="hint">Assign employee access to admin-side tabs and attendance people groups.</span>
                </div>
                <form method="post" class="stack-form" data-rule-form data-employee-form data-power-access-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply_rules">
                    <input type="hidden" name="allocation_type" value="power">
                    <?php render_employee_assignment_picker($projectAssignableEmployees, 'power-employee-options', 'power-selected-employee-tags'); ?>
                    <?php render_power_access_fields(); ?>
                    <div class="inline-actions">
                        <button class="button outline danger" type="submit" name="power_access_action" value="remove" data-rule-submit>Remove Power Access</button>
                        <button class="button solid" type="submit" name="power_access_action" value="save" data-rule-submit>Save Power Access</button>
                    </div>
                </form>
            </section>
        </div>
        <div class="rules-side-stack">
            <section class="section-block scroll-panel rules-assignment-panel">
                <div class="split rules-section-head">
                    <div>
                        <span class="eyebrow">Project Allocation</span>
                        <h2>Project Allocation</h2>
                    </div>
                    <span class="hint">Assign project access for selected employees.</span>
                </div>
                <form method="post" class="stack-form" data-rule-form data-employee-form data-project-allocation-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply_rules">
                    <input type="hidden" name="allocation_type" value="project">
                    <?php render_employee_assignment_picker($projectAssignableEmployees, 'project-employee-options', 'project-selected-employee-tags'); ?>
                    <?php render_project_assignment_picker([], 'project-allocation-options'); ?>
                    <div class="inline-actions">
                        <button class="button solid" type="submit" data-rule-submit>Save Project Allocation</button>
                    </div>
                </form>
            </section>
        </div>
    </section>
    <?php
    render_footer();
}

function calendar_payload(string $context, array $employee, string $date, array $recordBlock, array $reimbursementMeta = [], ?string $month = null, string $viewMode = 'attendance'): string
{
    $rules = employee_rules((int) $employee['id']);
    $month = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : substr($date, 0, 7);
    $canUseReimbursement = !employee_is_vendor_trainer($employee);
    $sessions = array_map(static function (array $session): array {
        $sessionPhotoData = report_photo_data_uri_from_row([
            'manual_punch_in_photo_data' => $session['punch_in_photo'] ?? null,
            'manual_punch_in_photo_mime' => $session['punch_in_photo_mime'] ?? '',
            'manual_punch_in_photo' => $session['punch_in_path'] ?? '',
        ]);
        $session['punch_in_path'] = $sessionPhotoData !== '' ? $sessionPhotoData : public_file_path((string) ($session['punch_in_path'] ?? ''));
        unset($session['punch_in_photo'], $session['punch_in_photo_mime']);
        return $session;
    }, $recordBlock['sessions'] ?? []);
    $reimbursementItems = array_map(static function (array $item): array {
        return [
            'category' => (string) ($item['category'] ?? ''),
            'status' => (string) ($item['status'] ?? ''),
            'amount_requested' => number_format((float) ($item['amount_requested'] ?? 0), 2, '.', ''),
            'amount_paid' => number_format((float) ($item['amount_paid'] ?? 0), 2, '.', ''),
            'remaining_balance' => number_format((float) ($item['remaining_balance'] ?? 0), 2, '.', ''),
            'expense_description' => (string) ($item['expense_description'] ?? ''),
        ];
    }, $reimbursementMeta['items'] ?? []);

    $recordPhotoData = report_photo_data_uri_from_row([
        'manual_punch_in_photo_data' => $recordBlock['record']['punch_in_photo'] ?? null,
        'manual_punch_in_photo_mime' => $recordBlock['record']['punch_in_photo_mime'] ?? '',
        'manual_punch_in_photo' => $recordBlock['record']['punch_in_path'] ?? '',
    ]);

    return h(json_encode([
        'context' => $context,
        'employee_id' => (int) $employee['id'],
        'date' => $date,
        'display_date' => date('d M Y', strtotime($date)),
        'status' => $recordBlock['record']['status'],
        'sessions' => array_values($sessions),
        'punch_in_time' => $recordBlock['record']['punch_in_time'],
        'punch_in_path' => $recordPhotoData !== '' ? $recordPhotoData : public_file_path((string) ($recordBlock['record']['punch_in_path'] ?? '')),
        'punch_in_lat' => $recordBlock['record']['punch_in_lat'],
        'punch_in_lng' => $recordBlock['record']['punch_in_lng'],
        'punch_out_time' => $recordBlock['record']['punch_out_time'] ?? '',
        'leave_reason' => $recordBlock['record']['leave_reason'],
        'biometric_in_time' => $recordBlock['record']['biometric_in_time'],
        'biometric_out_time' => $recordBlock['record']['biometric_out_time'],
        'rule_manual_in' => $rules['manual_punch_in'],
        'rule_manual_out' => $rules['manual_punch_out'],
        'manual_out_count' => $rules['manual_out_count'],
        'manual_out_slots' => $rules['manual_out_slots'],
        'view_mode' => $viewMode,
        'rule_bio_in' => $rules['biometric_punch_in'],
        'rule_bio_out' => $rules['biometric_punch_out'],
        'reimbursement' => [
            'available' => $canUseReimbursement,
            'count' => (int) ($reimbursementMeta['count'] ?? 0),
            'total' => number_format((float) ($reimbursementMeta['total'] ?? 0), 2, '.', ''),
            'current_month' => $month === date('Y-m'),
            'future' => $date > date('Y-m-d'),
            'locked' => (int) ($reimbursementMeta['count'] ?? 0) >= 3,
            'items' => $reimbursementItems,
        ],
    ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
}

function calendar_sheet_time(?string $time): string
{
    $time = trim((string) $time);
    if ($time === '') {
        return '00:00';
    }

    $timestamp = strtotime($time);
    return $timestamp !== false ? date('H:i', $timestamp) : $time;
}

function calendar_sheet_project_label(array $entry): string
{
    $names = [];
    foreach (($entry['sessions'] ?? []) as $session) {
        $name = trim((string) (($session['project_name'] ?? '') ?: ($session['session_name'] ?? '') ?: ($session['slot_name'] ?? '')));
        if ($name !== '' && !in_array($name, $names, true)) {
            $names[] = $name;
        }
    }

    return $names ? implode(', ', array_slice($names, 0, 2)) : 'NIL';
}

function calendar_sheet_shift_label(array $employee, array $monthAttendance): string
{
    $window = shift_window_for_employee($employee);
    if ($window !== null) {
        return calendar_sheet_time($window['start_time'] ?? '') . ' TO ' . calendar_sheet_time($window['end_time'] ?? '');
    }

    return '09:00 TO 17:00';
}

function calendar_sheet_display_status(string $date, array $entry, string $status): string
{
    return attendance_status_for_counts($date, $entry);
}

function render_calendar(string $context, array $employee, string $month, array $monthAttendance, array $calendarMeta = []): void
{
    [$start] = month_bounds($month);
    $offset = (int) $start->format('w');
    $trailingBlankDays = (7 - (($offset + count($monthAttendance)) % 7)) % 7;
    $weekRows = (int) max(4, min(6, (int) ceil(($offset + count($monthAttendance)) / 7)));
    $showSummary = !array_key_exists('show_summary', $calendarMeta) || !empty($calendarMeta['show_summary']);
    $compact = !empty($calendarMeta['compact']);
    $reimbursementsByDate = $calendarMeta['reimbursements_by_date'] ?? [];
    $viewMode = $calendarMeta['view_mode'] ?? 'attendance';
    $calendarActionsHtml = (string) ($calendarMeta['calendar_actions_html'] ?? '');
    $employeeTypeKey = strtolower(trim((string) ($employee['employee_type'] ?? '')));
    $employeeRoleKey = strtolower(trim((string) ($employee['role'] ?? '')));
    $isVendorTrainer = employee_is_vendor_trainer($employee);
    $usesSessionAttendance = in_array($employeeTypeKey, ['vendor', 'corporate'], true) || $employeeRoleKey === 'corporate_employee';
    $salaryBreakdown = employee_salary_breakdown_for_month($employee, $monthAttendance);
    $halfDayCount = (float) ($salaryBreakdown['half_day_count'] ?? 0);
    $halfDaySalary = (float) ($salaryBreakdown['half_day_salary'] ?? 0);
    $halfDayLabel = $usesSessionAttendance ? 'Half Sessions' : 'Half Days';
    $halfDaySalaryLabel = $usesSessionAttendance ? 'Half Session Salary' : 'Half Day Salary';
    $showHalfDaySalaryCard = false;
    $canUseReimbursement = !$isVendorTrainer;
    $reimbursementSummary = $canUseReimbursement
        ? employee_reimbursement_month_summary((int) ($employee['id'] ?? 0), $month)
        : ['count' => 0, 'requested_total' => 0, 'approved_total' => 0, 'paid_total' => 0];
    $usesVendorHourlySalary = $employeeTypeKey === 'vendor';
    $usesHourlySalary = employee_is_freelancer_managed($employee);
    $usesPaymentSummary = $usesHourlySalary || $employeeTypeKey === 'corporate' || $employeeRoleKey === 'corporate_employee';
    $salaryPaidAmount = $usesPaymentSummary
        ? paid_amount_for_employee_month_by_admin((int) ($employee['id'] ?? 0), 'SALARY', $month, !empty($employee['admin_id']) ? (int) $employee['admin_id'] : null)
        : 0.0;
    $salaryActualAmount = (float) ($salaryBreakdown['calculated_salary'] ?? 0);
    if ($usesPaymentSummary && !$usesHourlySalary) {
        $salaryActualAmount = max($salaryActualAmount, assigned_project_payment_total_for_month((int) ($employee['id'] ?? 0), $month));
    }
    $salaryPendingAmount = round(max($salaryActualAmount - $salaryPaidAmount, 0), 2);
    $attendanceSummaryCounts = attendance_counts($monthAttendance);
    $sheetFullDayCount = $usesSessionAttendance
        ? (float) ($salaryBreakdown['full_sessions'] ?? 0)
        : (float) ($attendanceSummaryCounts['present'] ?? 0);
    $sheetHalfDayCount = (float) ($salaryBreakdown['half_day_count'] ?? ($attendanceSummaryCounts['half_day'] ?? 0));
    $sheetPresentDays = (float) ($salaryBreakdown['payable_days'] ?? 0);
    $sheetWorkingDays = (float) ($salaryBreakdown['working_days'] ?? ($attendanceSummaryCounts['working_days'] ?? 0));
    $sheetShiftLabel = calendar_sheet_shift_label($employee, $monthAttendance);
    $monthShortLabel = strtoupper($start->format('M'));
    ?>
    <div class="calendar-shell calendar-shell-sheet<?= $compact ? ' calendar-shell-compact' : '' ?>">
        <?php if ($calendarActionsHtml !== ''): ?>
            <div class="calendar-top-actions">
                <?= $calendarActionsHtml ?>
            </div>
        <?php endif; ?>
        <div class="calendar-legend" aria-label="Attendance legend">
            <?php if ($viewMode === 'reimbursement'): ?>
                <span class="legend-chip"><span class="legend-swatch legend-reimbursement" style="background-color: #6366f1;"></span>Reimbursement Claim</span>
            <?php elseif ($usesSessionAttendance): ?>
                <span class="legend-chip"><span class="legend-swatch legend-present"></span>Completed Session</span>
                <span class="legend-chip"><span class="legend-swatch legend-half-day"></span>Half Session</span>
            <?php else: ?>
                <span class="legend-chip"><span class="legend-swatch legend-present"></span>Present</span>
                <span class="legend-chip"><span class="legend-swatch legend-absent"></span>Absent</span>
                <span class="legend-chip"><span class="legend-swatch legend-half-day"></span>Half Day</span>
                <span class="legend-chip"><span class="legend-swatch legend-leave"></span>Leave</span>
                <span class="legend-chip"><span class="legend-swatch legend-week-off"></span>Week Off</span>
            <?php endif; ?>
        </div>
        <div class="calendar-grid calendar-grid-sheet<?= $compact ? ' calendar-grid-compact' : '' ?>"<?= $compact ? ' style="--calendar-week-rows: ' . $weekRows . ';"' : '' ?>>
            <div class="calendar-sheet-title">ACTUAL WORK TIME - <?= h($sheetShiftLabel) ?></div>
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                <div class="weekday"><?= h($weekday) ?></div>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $offset; $i++): ?>
                <div class="day-card blank"></div>
            <?php endfor; ?>
            <?php foreach ($monthAttendance as $date => $entry): ?>
                <?php $status = (string) ($entry['record']['status'] ?? ''); ?>
                <?php if (!empty($entry['record']['sandwich_week_off_absent'])) {
                    $status = 'Absent';
                } ?>
                <?php if ($status === 'Pending') { $status = 'Half Day'; } ?>
                <?php if ($viewMode !== 'reimbursement') { $status = calendar_sheet_display_status($date, $entry, $status); } ?>
                <?php $statusClass = str_replace(' ', '-', $status); ?>
                <?php $dayCopy = in_array($status, ['Week Off', 'Absent'], true) ? $status : ''; ?>
                <?php $isAdminChanged = trim((string) ($entry['record']['admin_override_status'] ?? '')) !== '' && trim((string) ($entry['record']['admin_override_status'] ?? '')) !== 'Pending'; ?>
                <?php
                    $manualMarkedBy = trim((string) ($entry['record']['admin_override_by_name'] ?? ''));
                    if ($manualMarkedBy === '') {
                        $manualMarkedBy = 'Admin/HR';
                    }
                    $manualMarkedAtRaw = trim((string) (($entry['record']['admin_override_at'] ?? '') ?: ($entry['record']['updated_at'] ?? '')));
                    $manualMarkedAt = $manualMarkedAtRaw !== '' ? date('d M Y, h:i A', strtotime($manualMarkedAtRaw)) : 'time not recorded';
                    $manualMarkedTitle = 'Manually marked by ' . $manualMarkedBy . ' on ' . $manualMarkedAt;
                ?>
                <?php if ($usesSessionAttendance && $viewMode !== 'reimbursement'): ?>
                    <?php $vendorSessionDisplay = vendor_session_display_for_entry($entry); ?>
                    <?php $statusClass = (string) ($vendorSessionDisplay['status_class'] ?? ''); ?>
                    <?php $dayCopy = (string) ($vendorSessionDisplay['copy'] ?? ''); ?>
                <?php endif; ?>
                <?php 
                    $dayCardClass = 'day-card';
                    if ($viewMode !== 'reimbursement' && $statusClass !== '') {
                        $dayCardClass .= ' day-card-' . $statusClass;
                    }
                ?>
                <?php $isEmployeeWeekOff = $context === 'employee' && ($status === 'Week Off') && empty($entry['record']['sandwich_week_off_absent']); ?>
                <?php $reimbursementMeta = $reimbursementsByDate[$date] ?? ['count' => 0, 'total' => 0.0, 'items' => []]; ?>
                <?php
                    $dayProjectPayment = 0.0;
                    $workTimes = attendance_resolved_work_times($entry['record'] ?? [], $entry['sessions'] ?? []);
                    $sheetLogin = calendar_sheet_time($workTimes['in_time'] ?? null);
                    $sheetLogout = calendar_sheet_time($workTimes['out_time'] ?? null);
                    $sheetProject = calendar_sheet_project_label($entry);
                    if ($usesPaymentSummary && $viewMode !== 'reimbursement') {
                        foreach (employee_available_projects_for_date($employee, $date) as $availableProject) {
                            if ($usesVendorHourlySalary) {
                                $sessionPay = project_session_salary_for_date($employee, (int) ($availableProject['id'] ?? 0), $date);
                                $sessionAmount = (float) ($sessionPay['amount'] ?? 0);
                                $dayProjectPayment += $sessionAmount > 0 ? $sessionAmount : project_assignment_payment_amount($availableProject, $sessionPay);
                            } else {
                                $sessionPay = project_session_salary_for_date($employee, (int) ($availableProject['id'] ?? 0), $date);
                                if ((int) ($sessionPay['completed_sessions'] ?? 0) <= 0) {
                                    continue;
                                }
                                $dayProjectPayment += project_assignment_payment_amount($availableProject, $sessionPay);
                            }
                        }
                        $dayProjectPayment = round($dayProjectPayment, 2);
                    }
                ?>
                <?php if ($isEmployeeWeekOff): ?>
                    <div class="<?= h($dayCardClass) ?> static<?= $compact ? ' compact' : '' ?>"<?= $isAdminChanged ? ' title="' . h($manualMarkedTitle) . '"' : '' ?>>
                        <?php if ($viewMode === 'reimbursement'): ?>
                            <?php if ($reimbursementMeta['count'] > 0): ?>
                                <span class="day-dot" aria-hidden="true" style="background-color: #6366f1;"></span>
                                <span class="day-number"><?= date('d', strtotime($date)) ?></span>
                            <?php else: ?>
                                <span class="day-number"><?= date('d', strtotime($date)) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($statusClass !== ''): ?>
                                <span class="day-dot dot-<?= h($statusClass) ?>" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="day-number<?= ($viewMode !== 'reimbursement' && $statusClass !== '') ? ' day-number-' . h($statusClass) : '' ?>"><?= h($monthShortLabel . '-' . (int) date('j', strtotime($date))) ?></span>
                            <span class="day-copy">log in: <?= h($sheetLogin) ?></span>
                            <span class="day-copy">logout: <?= h($sheetLogout) ?></span>
                            <span class="day-copy">project: <?= h($sheetProject) ?></span>
                            <?php if ($dayProjectPayment > 0): ?>
                                <span class="day-copy">Rs <?= h(number_format($dayProjectPayment, 2)) ?></span>
                            <?php endif; ?>
                            <?php if ($canUseReimbursement && !empty($reimbursementMeta['count'])): ?>
                                <span class="day-badge reimbursement">R <?= (int) $reimbursementMeta['count'] ?></span>
                            <?php endif; ?>
                            <?php if ($isAdminChanged): ?>
                                <span class="day-badge admin-change" title="<?= h($manualMarkedTitle) ?>">M</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <button class="<?= h($dayCardClass) ?><?= $compact ? ' compact' : '' ?>" type="button"<?= $isAdminChanged ? ' title="' . h($manualMarkedTitle) . '"' : '' ?> data-attendance="<?= calendar_payload($context, $employee, $date, $entry, $reimbursementMeta, $month, $viewMode) ?>">
                        <?php if ($viewMode === 'reimbursement'): ?>
                            <?php if ($reimbursementMeta['count'] > 0): ?>
                                <span class="day-dot" aria-hidden="true" style="background-color: #6366f1;"></span>
                                <span class="day-number"><?= date('d', strtotime($date)) ?></span>
                            <?php else: ?>
                                <span class="day-number"><?= date('d', strtotime($date)) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($statusClass !== ''): ?>
                                <span class="day-dot dot-<?= h($statusClass) ?>" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="day-number<?= ($viewMode !== 'reimbursement' && $statusClass !== '') ? ' day-number-' . h($statusClass) : '' ?>"><?= h($monthShortLabel . '-' . (int) date('j', strtotime($date))) ?></span>
                            <span class="day-copy">log in: <?= h($sheetLogin) ?></span>
                            <span class="day-copy">logout: <?= h($sheetLogout) ?></span>
                            <span class="day-copy">project: <?= h($sheetProject) ?></span>
                            <?php if ($dayProjectPayment > 0): ?>
                                <span class="day-copy">Rs <?= h(number_format($dayProjectPayment, 2)) ?></span>
                            <?php endif; ?>
                            <?php if ($canUseReimbursement && !empty($reimbursementMeta['count'])): ?>
                                <span class="day-badge reimbursement">R <?= (int) $reimbursementMeta['count'] ?></span>
                            <?php endif; ?>
                            <?php if ($isAdminChanged): ?>
                                <span class="day-badge admin-change" title="<?= h($manualMarkedTitle) ?>">M</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $trailingBlankDays; $i++): ?>
                <div class="day-card blank"></div>
            <?php endfor; ?>
        </div>
        <?php if ($showSummary && !$isVendorTrainer): ?>
            <?php if ($usesPaymentSummary): ?>
                <div class="freelancer-attendance-summary">
                    <div class="split">
                        <div>
                            <span class="eyebrow">Payment</span>
                            <h3>Payment</h3>
                        </div>
                    </div>
                    <div class="calendar-summary">
                        <div class="summary-card"><strong>Rs <?= number_format($salaryPendingAmount, 2) ?></strong><span>Pending</span></div>
                        <div class="summary-card"><strong>Rs <?= number_format($salaryActualAmount, 2) ?></strong><span>Actual</span></div>
                        <?php if ($showHalfDaySalaryCard): ?>
                            <div class="summary-card"><strong>Rs <?= number_format($halfDaySalary, 2) ?></strong><span><?= h($halfDaySalaryLabel) ?></span></div>
                        <?php endif; ?>
                        <div class="summary-card summary-highlight"><strong>Rs <?= number_format($salaryPaidAmount, 2) ?></strong><span>Paid</span></div>
                    </div>
                    <?php if ($canUseReimbursement): ?>
                        <div class="split freelancer-summary-subhead">
                            <div>
                                <span class="eyebrow">Reimbursement</span>
                                <h3>Reimbursement</h3>
                            </div>
                        </div>
                        <div class="calendar-summary">
                            <div class="summary-card"><strong>Rs <?= number_format((float) ($reimbursementSummary['requested_total'] ?? 0), 2) ?></strong><span>Requested</span></div>
                            <div class="summary-card"><strong>Rs <?= number_format((float) ($reimbursementSummary['approved_total'] ?? 0), 2) ?></strong><span>Approval</span></div>
                            <div class="summary-card summary-highlight"><strong>Rs <?= number_format((float) ($reimbursementSummary['paid_total'] ?? 0), 2) ?></strong><span>Paid</span></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="calendar-summary">
                    <?php if ($showHalfDaySalaryCard): ?>
                        <div class="summary-card"><strong>Rs <?= number_format($halfDaySalary, 2) ?></strong><span><?= h($halfDaySalaryLabel) ?></span></div>
                    <?php endif; ?>
                    <div class="summary-card summary-highlight"><strong>Rs <?= number_format((float) ($salaryBreakdown['calculated_salary'] ?? 0), 2) ?></strong><span>Salary</span></div>
                    <?php if ($canUseReimbursement): ?>
                        <div class="summary-card"><strong><?= (int) ($reimbursementSummary['count'] ?? 0) ?></strong><span>Reimbursement</span></div>
                        <div class="summary-card"><strong>Rs <?= number_format((float) ($reimbursementSummary['requested_total'] ?? 0), 2) ?></strong><span>Requested</span></div>
                        <div class="summary-card"><strong>Rs <?= number_format((float) ($reimbursementSummary['approved_total'] ?? 0), 2) ?></strong><span>Approved</span></div>
                        <div class="summary-card"><strong>Rs <?= number_format((float) ($reimbursementSummary['paid_total'] ?? 0), 2) ?></strong><span>Paid</span></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($viewMode !== 'reimbursement' && $employeeTypeKey !== 'corporate' && $employeeRoleKey !== 'corporate_employee'): ?>
                <div class="calendar-summary calendar-summary-attendance-extra">
                    <div class="summary-card"><strong><?= number_format($sheetFullDayCount, 2) ?></strong><span>Full day</span></div>
                    <div class="summary-card"><strong><?= number_format($sheetHalfDayCount, 2) ?></strong><span>Half day</span></div>
                    <div class="summary-card"><strong><?= number_format($sheetPresentDays, 2) ?></strong><span>Total present days</span></div>
                    <div class="summary-card"><strong><?= number_format($sheetWorkingDays, 2) ?></strong><span>Total working days</span></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

function render_admin_shift(): void
{
    require_role('admin');
    $timings = shift_timings();

    render_header('Shift Management');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Shift</span>
            <h1>Shift Management</h1>
            <p>Admin can post shift timings manually on this page.</p>
        </div>
    </section>

    <section class="section-block">
        <span class="eyebrow">Manual Entry</span>
        <h2>Post Shift Timing</h2>
        <p class="hint">Manual shifts used here: 9:00 AM to 6:00 PM and 10:30 AM to 8:30 PM. You can post either one directly or enter a custom time below.</p>
        <div class="inline-actions">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_add_shift_timing">
                <input type="hidden" name="redirect_page" value="admin_shift">
                <input type="hidden" name="shift_from" value="<?= h(date('Y-m-d')) ?>">
                <input type="hidden" name="shift_to" value="<?= h(date('Y-m-d')) ?>">
                <input type="hidden" name="start_time" value="09:00">
                <input type="hidden" name="end_time" value="18:00">
                <button class="button outline" type="submit">Add 9:00 AM - 6:00 PM</button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_add_shift_timing">
                <input type="hidden" name="redirect_page" value="admin_shift">
                <input type="hidden" name="shift_from" value="<?= h(date('Y-m-d')) ?>">
                <input type="hidden" name="shift_to" value="<?= h(date('Y-m-d')) ?>">
                <input type="hidden" name="start_time" value="10:30">
                <input type="hidden" name="end_time" value="20:30">
                <button class="button outline" type="submit">Add 10:30 AM - 8:30 PM</button>
            </form>
        </div>
        <div class="spacer"></div>
        <form method="post" class="stack-form" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_add_shift_timing">
            <input type="hidden" name="redirect_page" value="admin_shift">
            <div class="reports-filter-grid">
                <label>Start Time<input type="time" name="start_time" required></label>
                <label>End Time<input type="time" name="end_time" required></label>
            </div>
            <button class="button solid" type="submit">Post Shift Timing</button>
        </form>
    </section>

    <div class="spacer"></div>
    <section class="table-wrap">
        <div class="split">
            <h2>Posted Shift Timings</h2>
            <span class="badge"><?= count($timings) ?> total</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date From</th>
                    <th>Date To</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Posted On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timings as $timing): ?>
                    <tr>
                        <td><?= h(!empty($timing['shift_from']) ? date('d M Y', strtotime((string) $timing['shift_from'])) : '-') ?></td>
                        <td><?= h(!empty($timing['shift_to']) ? date('d M Y', strtotime((string) $timing['shift_to'])) : '-') ?></td>
                        <td><?= h(date('h:i A', strtotime((string) $timing['start_time']))) ?></td>
                        <td><?= h(date('h:i A', strtotime((string) $timing['end_time']))) ?></td>
                        <td><?= h(date('d M Y h:i A', strtotime((string) $timing['created_at']))) ?></td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="admin_delete_shift_timing">
                                <input type="hidden" name="shift_id" value="<?= (int) $timing['id'] ?>">
                                <button class="button outline small" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$timings): ?>
            <div class="list-item muted">No shift timings posted yet.</div>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}

function render_admin_attendance(): void
{
    require_power_attendance_access(['admin', 'freelancer', 'external_vendor']);
    $user = current_user();
    $isPowerEmployee = employee_has_power_access($user);
    $powerScopes = $isPowerEmployee ? employee_power_attendance_scopes($user) : [];
    $attendanceTypes = [
        'employee' => 'Employee',
        'freelancer' => 'Contractual Employee',
        'vendor' => 'Vendor',
    ];
    $allowedAttendanceTypes = $isPowerEmployee
        ? array_values(array_intersect(array_keys($attendanceTypes), $powerScopes))
        : array_keys($attendanceTypes);
    if (!$allowedAttendanceTypes) {
        $allowedAttendanceTypes = ['employee'];
    }
    $employeeType = (string) ($_GET['type'] ?? ($allowedAttendanceTypes[0] ?? 'employee'));
    $legacyTypeMap = ['regular' => 'employee', 'corporate' => 'freelancer'];
    $employeeType = $legacyTypeMap[$employeeType] ?? $employeeType;
    $employeeType = in_array($employeeType, $allowedAttendanceTypes, true) ? $employeeType : $allowedAttendanceTypes[0];
    $view = $_GET['view'] ?? 'attendance';

    $isFreelancer = ($user['role'] ?? '') === 'freelancer';
    $isVendor = ($user['role'] ?? '') === 'external_vendor';
    $currentAdminId = current_admin_id() ?? (int) ($user['id'] ?? 0);
    $canViewReimbursements = !$isFreelancer && !$isVendor && !$isPowerEmployee && $employeeType === 'employee';

    if (!$canViewReimbursements) {
        $view = 'attendance';
    }

    // Build registrations lists and filtered employees based on type
    $vendorRegistrations = [];
    $freelancerRegistrations = [];
    $filteredEmployees = [];

    if ($employeeType === 'vendor' && !$isFreelancer && !$isVendor) {
        $vendorRegistrations = db()->query("SELECT * FROM users WHERE role = 'external_vendor' ORDER BY name")->fetchAll();
        if (!empty($_GET['vendor_id'])) {
            $vEmpStmt = db()->prepare("SELECT * FROM users WHERE role IN ('employee', 'corporate_employee') AND admin_id = ? ORDER BY name");
            $vEmpStmt->execute([(int)$_GET['vendor_id']]);
            $filteredEmployees = $vEmpStmt->fetchAll();
        }
    } elseif ($employeeType === 'freelancer' && !$isFreelancer && !$isVendor) {
        $contractualStmt = db()->prepare("SELECT * FROM users WHERE admin_id = :admin_id AND (role = 'corporate_employee' OR employee_type = 'corporate') ORDER BY created_at DESC, name");
        $contractualStmt->execute(['admin_id' => $currentAdminId]);
        $filteredEmployees = $contractualStmt->fetchAll();
    } elseif ($employeeType === 'vendor_trainer' && !$isFreelancer && !$isVendor) {
        $vendorIds = db()->query("SELECT id FROM users WHERE role = 'external_vendor'")->fetchAll(PDO::FETCH_COLUMN);
        $params = ['admin_id' => $currentAdminId];
        $vendorFilter = '';
        if ($vendorIds) {
            $vendorPlaceholders = implode(',', array_fill(0, count($vendorIds), '?'));
            $vendorFilter = " OR u.admin_id IN ($vendorPlaceholders)";
        }
        $stmt = db()->prepare("SELECT u.* FROM users u
            WHERE u.role IN ('employee', 'corporate_employee')
              AND ((u.admin_id = ? AND (u.employee_type = 'vendor' OR u.designation = 'Vendor')){$vendorFilter})
            ORDER BY u.name");
        $stmt->execute(array_merge([(int) $currentAdminId], array_map('intval', $vendorIds)));
        $filteredEmployees = $stmt->fetchAll();
    } elseif ($employeeType === 'trainer' && !$isFreelancer && !$isVendor) {
        $stmt = db()->prepare("SELECT * FROM users WHERE admin_id = :admin_id AND role = 'employee' AND designation IN ('In-house Trainer', 'Project Coordinator') ORDER BY name");
        $stmt->execute(['admin_id' => $currentAdminId]);
        $filteredEmployees = $stmt->fetchAll();
    } else {
        $allEmployees = employees();
        $filteredEmployees = array_values(array_filter($allEmployees, function($emp) use ($employeeType, $isVendor, $isFreelancer) {
            // Member portal users should see all employees linked to their account.
            if ($isVendor || $isFreelancer) {
                return true;
            }
            $type = (string) ($emp['employee_type'] ?? 'regular');
            if ($employeeType === 'employee') {
                return ($type === 'regular' || $type === '') && !employee_is_in_house_trainer($emp) && !employee_is_project_coordinator($emp);
            }
            return $type === $employeeType;
        }));
    }

    $fallbackEmployee = $filteredEmployees[0] ?? null;
    $selectedId = (int) ($_GET['employee_id'] ?? ($fallbackEmployee['id'] ?? 0));
    // For vendor/corporate with no selection yet, don't auto-select
    if ($employeeType === 'vendor' && empty($_GET['vendor_id'])) {
        $employee = null;
    } else {
        $employee = $selectedId ? (employee_by_id($selectedId) ?: ($filteredEmployees[0] ?? null)) : ($filteredEmployees[0] ?? null);
        // If employee_by_id is scoped to admin, look in filteredEmployees directly
        if (!$employee && $filteredEmployees) {
            foreach ($filteredEmployees as $fe) {
                if ((int)$fe['id'] === $selectedId) { $employee = $fe; break; }
            }
            if (!$employee) $employee = $filteredEmployees[0];
        }
    }
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    if ($view === 'attendance' && !$isFreelancer && !$isVendor) {
        auto_sync_etime_attendance_for_month($month);
    }

    render_header('Track Attendance', 'admin-employee-log-page');
    ?>
    <section class="page-title">
        <div>
            <h1><?= $view === 'reimbursement' ? 'Reimbursement Calendar' : 'Track Attendance Calendar' ?></h1>
        </div>
    </section>

    <?php if (!$isFreelancer && !$isVendor): ?>
    <section class="employee-tabs-section">
        <nav class="employee-tabs">
            <?php foreach ($allowedAttendanceTypes as $typeKey): ?>
                <a href="<?= h(BASE_URL) ?>?page=admin_employee_log&type=<?= h($typeKey) ?>&view=<?= h($view) ?>" class="tab-link <?= $employeeType === $typeKey ? 'active' : '' ?>"><?= h($attendanceTypes[$typeKey] ?? ucwords(str_replace('_', ' ', $typeKey))) ?></a>
            <?php endforeach; ?>
        </nav>
    </section>
    <?php endif; ?>

    <section class="section-block scroll-panel">
        <?php if ($employeeType === 'vendor'): ?>
        <form method="get" class="form-grid" style="margin-bottom: 12px;">
            <input type="hidden" name="page" value="admin_employee_log">
            <input type="hidden" name="type" value="vendor">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <label>Vendor
                <select name="vendor_id" onchange="this.form.submit()">
                    <option value="">-- Select Vendor --</option>
                    <?php foreach ($vendorRegistrations as $vr): ?>
                        <option value="<?= (int) $vr['id'] ?>" <?= ((int)($_GET['vendor_id'] ?? 0) === (int)$vr['id']) ? 'selected' : '' ?>><?= h($vr['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
        <?php endif; ?>
        <form method="get" class="form-grid">
            <input type="hidden" name="page" value="admin_employee_log">
            <input type="hidden" name="type" value="<?= h($employeeType) ?>">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <?php if ($employeeType === 'vendor' && !empty($_GET['vendor_id'])): ?>
                <input type="hidden" name="vendor_id" value="<?= (int)$_GET['vendor_id'] ?>">
            <?php endif; ?>
            <label><?= h($attendanceTypes[$employeeType] ?? 'Employee') ?>
                <select name="employee_id">
                    <option value="">-- Select <?= h($attendanceTypes[$employeeType] ?? 'Employee') ?> --</option>
                    <?php foreach ($filteredEmployees as $emp): ?>
                        <option value="<?= (int) $emp['id'] ?>" <?= $employee && (int) $employee['id'] === (int) $emp['id'] ? 'selected' : '' ?>><?= h($emp['name']) ?> (<?= h($emp['emp_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Month<input type="month" name="month" value="<?= h($month) ?>"></label>
            <div class="split align-end">
                <button class="button solid" type="submit">View</button>
                <?php if ($view === 'attendance'): ?>
                    <button class="button outline" type="button" data-modal-target="attendance-import-modal">Bulk Import</button>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <div class="modal" id="attendance-import-modal">
        <div class="modal-card" style="max-width:720px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Track Attendance Import</span>
            <h2>Bulk Import Track Attendance</h2>
            <p>Upload the attendance report from Excel or CSV. The importer matches employees by Empcode and reads Date, INTime, OUTTime, Status, and Remark to mark the employee calendar.</p>
            <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_attendance_csv_upload">
                <input type="hidden" name="return_page" value="admin_employee_log">
                <input type="hidden" name="return_type" value="<?= h($employeeType) ?>">
                <input type="hidden" name="return_view" value="<?= h($view) ?>">
                <input type="hidden" name="return_employee_id" value="<?= (int) ($employee['id'] ?? 0) ?>">
                <input type="hidden" name="return_month" value="<?= h($month) ?>">
                <?php if ($employeeType === 'vendor' && !empty($_GET['vendor_id'])): ?>
                <input type="hidden" name="return_vendor_id" value="<?= (int) $_GET['vendor_id'] ?>">
                <?php endif; ?>
                <label class="upload-drop">
                    <strong>Select attendance file</strong>
                        <p>You can upload `.xlsx`, `.xls`, `.csv`, or `.txt` attendance exports. Employee rows are matched by Empcode. If the file has a `Date` column, each row is marked on that date in the calendar. If not, the importer uses the detected report date or the attendance date below.</p>
                    <input type="file" name="attendance_csv" accept=".xlsx,.xls,.csv,.txt" required>
                </label>
                <label>Attendance Date (optional)<input type="date" name="attendance_date"></label>
                <button class="button solid" type="submit">Import Attendance</button>
            </form>
        </div>
    </div>

    <div class="spacer"></div>
    <?php if ($employeeType === 'vendor' && empty($_GET['vendor_id'])): ?>
        <section class="section-block"><p class="hint">Select a vendor above to view their employee attendance.</p></section>
    <?php elseif ($employee): ?>
        <div class="attendance-panel" style="display: block; height: auto; overflow: visible;">
            <?php 
                ob_start();
                ?>
                <a href="<?= h(BASE_URL) ?>?page=admin_employee_log&type=<?= h($employeeType) ?>&view=attendance" class="button <?= $view === 'attendance' ? 'solid' : 'outline' ?>">Track Attendance Calendar</a>
                <?php
                $calendarActionsHtml = ob_get_clean();
                $reimbursements = employee_reimbursements_by_date_map((int) $employee['id'], $month);
                render_calendar('admin', $employee, $month, month_attendance_for_user((int) $employee['id'], $month), [
                    'compact' => false,
                    'reimbursements_by_date' => $reimbursements,
                    'view_mode' => $view,
                    'calendar_actions_html' => $calendarActionsHtml,
                ]); 
            ?>
        </div>
    <?php else: ?>
        <section class="section-block"><p>No employees found. Please select a different filter.</p></section>
    <?php endif; ?>

    <?php
    render_footer();
}

function render_admin_profile_settings(): void
{
    $admin = require_role('admin');
    $memberSince = !empty($admin['created_at']) ? date('d M Y', strtotime((string) $admin['created_at'])) : 'Recently added';
    $biometricIntegration = biometric_integration_for_admin((int) $admin['id']);
    $biometricBaseUrl = (string) ($biometricIntegration['base_url'] ?? 'https://api.etimeoffice.com/api/');
    $biometricCorporateId = (string) ($biometricIntegration['corporate_id'] ?? 'karyoun');
    $biometricUsername = (string) ($biometricIntegration['username'] ?? 'Arun');
    $biometricEnabled = $biometricIntegration ? !empty($biometricIntegration['is_enabled']) : true;
    $biometricLastSync = !empty($biometricIntegration['last_sync_at'])
        ? date('d M Y, h:i A', strtotime((string) $biometricIntegration['last_sync_at']))
        : 'Not synced yet';
    $biometricLastTest = !empty($biometricIntegration['last_test_at'])
        ? date('d M Y, h:i A', strtotime((string) $biometricIntegration['last_test_at']))
        : 'Not tested yet';

    render_header('Profile Settings');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Profile</span>
            <h1>Profile Settings</h1>
            <p>Manage your admin account details and update your sign-in password from one place.</p>
        </div>
    </section>

    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Account Overview</span>
                <h2><?= h((string) $admin['name']) ?></h2>
            </div>
            <span class="badge">Administrator</span>
        </div>
        <div class="profile-settings-grid">
            <div class="list-item profile-settings-wide">
                <strong>Email</strong>
                <span><?= h((string) $admin['email']) ?></span>
            </div>
            <div class="list-item">
                <strong>Phone</strong>
                <span><?= h((string) (($admin['phone'] ?? '') ?: 'Not added')) ?></span>
            </div>
            <div class="list-item">
                <strong>Member Since</strong>
                <span><?= h($memberSince) ?></span>
            </div>
            <div class="list-item">
                <strong>Role</strong>
                <span>Admin</span>
            </div>
        </div>
    </section>

    <div class="spacer"></div>

    <section class="section-block">
        <span class="eyebrow">Update Details</span>
        <h2>Account Information</h2>
        <form method="post" class="stack-form" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_profile_update">
            <div class="field">
                <label>Name</label>
                <div class="field-row"><input type="text" name="name" value="<?= h((string) $admin['name']) ?>" required></div>
                <small class="field-error"><span>!</span>Name is required.</small>
            </div>
            <div class="field">
                <label>Email</label>
                <div class="field-row"><input type="email" name="email" value="<?= h((string) $admin['email']) ?>" required></div>
                <small class="field-error"><span>!</span>Valid email required.</small>
            </div>
            <div class="field">
                <label>Phone Number</label>
                <div class="field-row"><input type="text" name="phone" value="<?= h((string) ($admin['phone'] ?? '')) ?>"></div>
            </div>
            <button class="button solid" type="submit">Save Profile</button>
        </form>
    </section>

    <div class="spacer"></div>

    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Biometric Integration</span>
                <h2>eTime Office</h2>
                <p>Connect this admin account to eTime Office so Track Attendance can mark biometric IN/OUT records automatically.</p>
            </div>
            <span class="badge"><?= $biometricEnabled ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <form method="post" class="stack-form" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_biometric_integration_save">
            <label class="checkbox-line">
                <input type="checkbox" name="is_enabled" value="1" <?= $biometricEnabled ? 'checked' : '' ?>>
                <span>Enable automatic eTime Office attendance sync</span>
            </label>
            <div class="reports-filter-grid">
                <div class="field">
                    <label>API Base URL</label>
                    <div class="field-row"><input type="url" name="base_url" value="<?= h($biometricBaseUrl) ?>" required></div>
                    <small class="field-error"><span>!</span>Base URL is required.</small>
                </div>
                <div class="field">
                    <label>Corporate ID</label>
                    <div class="field-row"><input type="text" name="corporate_id" value="<?= h($biometricCorporateId) ?>" required></div>
                    <small class="field-error"><span>!</span>Corporate ID is required.</small>
                </div>
                <div class="field">
                    <label>Username</label>
                    <div class="field-row"><input type="text" name="username" value="<?= h($biometricUsername) ?>" required></div>
                    <small class="field-error"><span>!</span>Username is required.</small>
                </div>
                <div class="field">
                    <label>Password</label>
                    <div class="field-row"><input type="password" name="password" autocomplete="new-password" placeholder="<?= $biometricIntegration ? 'Leave blank to keep saved password' : 'Enter eTime password' ?>" <?= $biometricIntegration ? '' : 'required' ?>></div>
                    <small class="field-error"><span>!</span>Password is required.</small>
                </div>
            </div>
            <div class="profile-settings-grid">
                <div class="list-item">
                    <strong>Last Sync</strong>
                    <span><?= h($biometricLastSync) ?></span>
                </div>
                <div class="list-item">
                    <strong>Last Test</strong>
                    <span><?= h($biometricLastTest) ?></span>
                </div>
            </div>
            <div class="inline-actions">
                <button class="button solid" type="submit" name="integration_mode" value="save">Save Integration</button>
                <button class="button outline" type="submit" name="integration_mode" value="test">Test Connection</button>
            </div>
        </form>
    </section>

    <div class="spacer"></div>

    <section class="section-block">
        <span class="eyebrow">Security</span>
        <h2>Change Password</h2>
        <form method="post" class="stack-form" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_change_password">
            <div class="field">
                <label>Current Password</label>
                <div class="field-row">
                    <input id="admin-current-password" type="password" name="current_password" placeholder="Enter current password" required>
                    <button class="password-toggle" type="button" data-password-toggle="admin-current-password">Show</button>
                </div>
                <small class="field-error"><span>!</span>Current password is required.</small>
            </div>
            <div class="field">
                <label>New Password</label>
                <div class="field-row">
                    <input id="admin-new-password" type="password" name="new_password" minlength="8" placeholder="Minimum 8 characters with letters and numbers" required>
                    <button class="password-toggle" type="button" data-password-toggle="admin-new-password">Show</button>
                </div>
                <small class="field-error"><span>!</span>Password must be at least 8 characters and include a letter and number.</small>
            </div>
            <div class="field">
                <label>Confirm Password</label>
                <div class="field-row">
                    <input id="admin-confirm-password" type="password" name="confirm_password" minlength="8" placeholder="Repeat new password" required>
                    <button class="password-toggle" type="button" data-password-toggle="admin-confirm-password">Show</button>
                </div>
                <small class="field-error"><span>!</span>Please confirm the new password.</small>
            </div>
            <button class="button solid" type="submit">Update Password</button>
        </form>
    </section>
    <?php
    render_footer();
}

function render_admin_reports(): void
{
    require_role('admin');

    $filters = [
        'employee_ids' => $_POST['employee_ids'] ?? [],
        'project_ids' => $_POST['project_ids'] ?? [],
        'from_date' => $_POST['from_date'] ?? date('Y-m-01'),
        'to_date' => $_POST['to_date'] ?? date('Y-m-d'),
    ];

    $reportData = get_attendance_report_data($filters);
    $allEmployees = employees();
    $allProjects = active_projects();

    render_header('Reports', 'admin-reports-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Reports</span>
            <h1>Track Attendance Reports</h1>
            <p>Filter by employees, projects, and date range to review full attendance logs, including manual project entries and biometric punches.</p>
        </div>
    </section>

    <section class="section-block reports-filters">
        <form method="post" id="reports-filter-form" class="stack-form reports-filter-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="filter_reports">
            
            <div class="reports-filter-grid">
                <!-- Employee Multi-Select -->
                <div class="field">
                    <label>Employee Multi-Select</label>
                    <div class="multi-select-picker">
                        <input type="text" placeholder="Search employees..." data-multi-filter="employee-report-options">
                        <div class="multi-options scroll-panel" id="employee-report-options">
                            <?php foreach ($allEmployees as $emp): ?>
                                <label class="multi-option">
                                    <input type="checkbox" name="employee_ids[]" value="<?= (int)$emp['id'] ?>" <?= in_array((int)$emp['id'], array_map('intval', $filters['employee_ids'])) ? 'checked' : '' ?>>
                                    <span><?= h($emp['name']) ?> (<?= h($emp['emp_id']) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Project Multi-Select -->
                <div class="field">
                    <label>Project Multi-Select <span class="hint">(optional)</span></label>
                    <div class="multi-select-picker">
                        <input type="text" placeholder="Search projects..." data-multi-filter="project-report-options">
                        <div class="multi-options scroll-panel" id="project-report-options">
                            <?php foreach ($allProjects as $proj): ?>
                                <label class="multi-option">
                                    <input type="checkbox" name="project_ids[]" value="<?= (int)$proj['id'] ?>" <?= in_array((int)$proj['id'], array_map('intval', $filters['project_ids'])) ? 'checked' : '' ?>>
                                    <span><?= h($proj['project_name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reports-date-grid">
                <div class="field">
                    <label>From Date</label>
                    <input type="date" name="from_date" value="<?= h($filters['from_date']) ?>" required>
                </div>
                <div class="field">
                    <label>To Date</label>
                    <input type="date" name="to_date" value="<?= h($filters['to_date']) ?>" required>
                </div>
                <div class="field align-end">
                    <button class="button solid" type="submit">Apply Filters</button>
                </div>
            </div>
        </form>
    </section>

    <div class="spacer"></div>

    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2>Report Results</h2>
                <div class="action-bar report-actions">
                    <form method="post" style="display:inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="export_reports_csv">
                        <?php foreach($filters['employee_ids'] as $id): ?><input type="hidden" name="employee_ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>
                        <?php foreach($filters['project_ids'] as $id): ?><input type="hidden" name="project_ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>
                        <input type="hidden" name="from_date" value="<?= h($filters['from_date']) ?>">
                        <input type="hidden" name="to_date" value="<?= h($filters['to_date']) ?>">
                        <button class="button outline small" type="submit">Download CSV</button>
                    </form>
                    <form method="post" style="display:inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="export_reports_pdf">
                        <?php foreach($filters['employee_ids'] as $id): ?><input type="hidden" name="employee_ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>
                        <?php foreach($filters['project_ids'] as $id): ?><input type="hidden" name="project_ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>
                        <input type="hidden" name="from_date" value="<?= h($filters['from_date']) ?>">
                        <input type="hidden" name="to_date" value="<?= h($filters['to_date']) ?>">
                        <button class="button outline small" type="submit">Download PDF</button>
                    </form>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee Name</th>
                    <th>Source</th>
                    <th>Project Name</th>
                    <th>Slot</th>
                    <th>Session Type</th>
                    <th>Attendance Status</th>
                    <th>Manual Punch In</th>
                    <th>Manual Punch In Photo</th>
                    <th>Manual Punch Out</th>
                    <th>Biometric Punch In</th>
                    <th>Biometric Punch Out</th>
                    <th>Total Students</th>
                    <th>Present Students</th>
                    <th>Topics Handled</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reportData): ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><?= h(date('d M Y', strtotime((string)$row['date']))) ?></td>
                            <td><?= h((string)$row['employee_name']) ?></td>
                            <td><?= h((string)(($row['attendance_source'] ?? '') ?: 'Attendance')) ?></td>
                            <td><?= h((string)($row['project_name'] ?: '-')) ?></td>
                            <td><?= h((string)($row['slot_name'] ?: '-')) ?></td>
                            <td><?= h(project_session_label((string)$row['session_type'])) ?></td>
                            <td>
                                <?php $statusClass = str_replace(' ', '-', (string)$row['attendance_status']); ?>
                                <span class="status-pill status-<?= h($statusClass) ?>"><?= h((string)$row['attendance_status']) ?></span>
                            </td>
                            <td><?= h((string) (($row['manual_punch_in'] ?? '') ?: '-')) ?></td>
                            <td>
                                <?php $manualPunchPhoto = report_photo_url($row['manual_punch_in_photo'] ?? ''); ?>
                                <?php $manualPunchPhotoData = report_photo_data_uri_from_row($row); ?>
                                <?php if ($manualPunchPhotoData !== ''): ?>
                                    <img class="report-punch-photo" src="<?= h($manualPunchPhotoData) ?>" alt="Manual punch in photo">
                                <?php elseif ($manualPunchPhoto !== ''): ?>
                                    <a href="<?= h($manualPunchPhoto) ?>" target="_blank" rel="noopener">
                                        <img class="report-punch-photo" src="<?= h($manualPunchPhoto) ?>" alt="Manual punch in photo">
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= h((string) (($row['manual_punch_out'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['biometric_punch_in'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['biometric_punch_out'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['total_students'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['present_students'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['topics_handled'] ?? '') ?: '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="15" class="muted center">No records found for the selected filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
    <style>
        .multi-select-picker { border: 1px solid #ddd; padding: 10px; border-radius: 8px; background: #fff; }
        .multi-options { max-height: 150px; margin-top: 10px; border-top: 1px solid #eee; padding-top: 5px; }
        .multi-option { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 10px 12px; cursor: pointer; border-radius: 12px; }
        .multi-option span { min-width: 0; }
        .multi-option input { order: 2; width: auto; min-height: auto; margin: 0; }
        .multi-option:hover { background: #f9f9f9; }
        .reports-filter-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px; align-items: start; }
        .reports-date-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-top: 1rem; }
        .reports-actions { display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 1rem; }
        .report-punch-photo { width: 54px; height: 54px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(30,41,59,0.12); background: #eef2ff; display: block; }
        @media print {
            .admin-sidebar, .reports-actions, .eyebrow, .spacer, .flash-stack { display: none !important; }
            .admin-main { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            table { font-size: 10px !important; }
            .status-pill { border: none !important; padding: 0 !important; }
        }
        @media (max-width: 900px) {
            .reports-filter-grid, .reports-date-grid { grid-template-columns: 1fr; }
            .reports-actions { justify-content: stretch; }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-multi-filter]').forEach(filterInput => {
                filterInput.addEventListener('input', () => {
                    const list = document.getElementById(filterInput.dataset.multiFilter);
                    if (!list) return;
                    const term = filterInput.value.toLowerCase();
                    list.querySelectorAll('.multi-option').forEach(option => {
                        const label = option.textContent.toLowerCase();
                        option.style.display = label.includes(term) ? '' : 'none';
                    });
                });
            });
        });
    </script>
    <?php
    render_footer();
}

function render_admin_reimbursements(): void
{
    require_role('admin');

    $filters = [
        'employee_id' => max(0, (int) ($_GET['employee_id'] ?? 0)),
        'category' => strtoupper(trim((string) ($_GET['category'] ?? ''))),
    ];
    if (!in_array($filters['category'], reimbursement_categories(), true)) {
        $filters['category'] = '';
    }

    $allEmployees = employees();
    $items = admin_reimbursements($filters);
    $statusOptions = reimbursement_statuses();
    $bankNames = payment_bank_names();
    $transferModesMap = payment_transfer_modes_map();

    render_header('Reimbursement', 'admin-reimbursements-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Reimbursement</span>
            <h1>Reimbursement Requests</h1>
            <p>Review employee reimbursement claims, preview uploaded proof, and move each request through approval and payment.</p>
        </div>
    </section>

    <section class="section-block reimbursement-filters-panel">
        <form method="get" class="stack-form">
            <input type="hidden" name="page" value="admin_reimbursements">
            <div class="admin-reimbursement-filter-grid">
                <div class="field">
                    <label>Employee</label>
                    <select name="employee_id">
                        <option value="0">All Employees</option>
                        <?php foreach ($allEmployees as $employee): ?>
                            <option value="<?= (int) $employee['id'] ?>" <?= (int) $filters['employee_id'] === (int) $employee['id'] ? 'selected' : '' ?>>
                                <?= h((string) $employee['name']) ?> (<?= h((string) $employee['emp_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach (reimbursement_categories() as $category): ?>
                            <option value="<?= h($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= h($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="reimbursement-filter-actions">
                    <button class="button solid" type="submit">Apply Filters</button>
                    <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_reimbursements">Reset</a>
                </div>
            </div>
        </form>
    </section>

    <div class="spacer"></div>

    <?php if ($items): ?>
        <section class="admin-reimbursement-card-grid">
            <?php foreach ($items as $item):
                $badgeClass = reimbursement_status_badge_class((string) $item['status']);
                $previewPayload = [
                    'employee' => (string) $item['employee_name'] . ' (' . (string) $item['employee_emp_id'] . ')',
                    'category' => (string) $item['category'],
                    'status' => (string) $item['status'],
                    'amount' => number_format((float) $item['amount_requested'], 2),
                    'description' => (string) $item['expense_description'],
                    'attachmentName' => (string) ($item['attachment_name'] ?? ''),
                    'attachmentUrl' => reimbursement_attachment_url($item),
                    'attachmentMime' => (string) ($item['attachment_mime'] ?? ''),
                ];
                ?>
                <article class="reimbursement-admin-card">
                    <div class="split">
                        <div>
                            <span class="eyebrow">Employee</span>
                            <h2><?= h((string) $item['employee_name']) ?></h2>
                            <p class="hint"><?= h((string) $item['employee_emp_id']) ?></p>
                        </div>
                        <span class="status-pill reimbursement-status <?= h($badgeClass) ?>"><?= h((string) $item['status']) ?></span>
                    </div>

                    <div class="reimbursement-admin-meta">
                        <div class="reimbursement-meta-chip">
                            <strong>Category</strong>
                            <span><?= h((string) $item['category']) ?></span>
                        </div>
                        <div class="reimbursement-meta-chip">
                            <strong>Requested Amount</strong>
                            <span>Rs <?= h(number_format((float) $item['amount_requested'], 2)) ?></span>
                        </div>
                        <div class="reimbursement-meta-chip">
                            <strong>Paid Amount</strong>
                            <span>Rs <?= h(number_format((float) $item['amount_paid'], 2)) ?></span>
                        </div>
                        <div class="reimbursement-meta-chip">
                            <strong>Remaining Balance</strong>
                            <span>Rs <?= h(number_format((float) $item['remaining_balance'], 2)) ?></span>
                        </div>
                    </div>

                    <div class="spacer"></div>
                    <p class="reimbursement-description"><?= h((string) $item['expense_description']) ?></p>

                    <div class="spacer"></div>
                    <div class="split">
                        <span class="badge"><?= h(date('d M Y', strtotime((string) $item['expense_date']))) ?></span>
                        <button
                            class="button outline small"
                            type="button"
                            data-reimbursement-preview="<?= h(json_encode($previewPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>"
                        >
                            View Details
                        </button>
                    </div>

                    <div class="spacer"></div>
                    <form
                        method="post"
                        class="stack-form reimbursement-status-form"
                        data-reimbursement-status-form
                        data-reimbursement-id="<?= (int) $item['id'] ?>"
                        data-reimbursement-user-id="<?= (int) $item['user_id'] ?>"
                        data-reimbursement-remaining="<?= h(number_format((float) $item['remaining_balance'], 2, '.', '')) ?>"
                        data-filter-employee="<?= (int) $filters['employee_id'] ?>"
                        data-filter-category="<?= h($filters['category']) ?>"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="admin_update_reimbursement_status">
                        <input type="hidden" name="reimbursement_id" value="<?= (int) $item['id'] ?>">
                        <input type="hidden" name="filter_employee_id" value="<?= (int) $filters['employee_id'] ?>">
                        <input type="hidden" name="filter_category" value="<?= h($filters['category']) ?>">
                        <div class="reimbursement-status-row">
                            <label class="reimbursement-status-label">
                                <span>Status</span>
                                <select name="status" data-reimbursement-status-select>
                                    <?php foreach ($statusOptions as $status): ?>
                                        <option value="<?= h($status) ?>" <?= (string) $item['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="button solid small" type="submit">Apply</button>
                        </div>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <section class="section-block">
            <div class="list-item muted">No reimbursement requests matched the selected filters.</div>
        </section>
    <?php endif; ?>

    <div class="modal" id="reimbursement-preview-modal">
        <div class="modal-card reimbursement-preview-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div id="reimbursement-preview-content"></div>
        </div>
    </div>

    <div class="modal" id="reimbursement-payment-modal">
        <div class="modal-card" style="max-width:920px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Accounts Payment</span>
            <h2 id="reimbursement-payment-title">Reimbursement Payment</h2>
            <p id="reimbursement-payment-copy" class="hint">Capture the payment details to settle this reimbursement request.</p>
            <form method="post" enctype="multipart/form-data" class="stack-form" id="reimbursement-payment-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_record_reimbursement_payment">
                <input type="hidden" name="reimbursement_id" id="reimbursement-payment-reimbursement-id">
                <input type="hidden" name="filter_employee_id" id="reimbursement-payment-filter-employee">
                <input type="hidden" name="filter_category" id="reimbursement-payment-filter-category">

                <div class="accounts-payment-grid">
                    <div class="field">
                        <label>Amount</label>
                        <div class="field-row">
                            <input type="number" name="amount" id="reimbursement-payment-amount" min="0.01" step="0.01" required>
                        </div>
                        <small class="field-error"><span>!</span>Amount is required.</small>
                    </div>

                    <div class="field">
                        <label>Bank Name</label>
                        <div class="field-row">
                            <select name="bank_name" id="reimbursement-payment-bank" required>
                                <option value="" selected disabled>Select bank</option>
                                <?php foreach ($bankNames as $bankName): ?>
                                    <option value="<?= h($bankName) ?>"><?= h($bankName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="field-error"><span>!</span>Bank name is required.</small>
                    </div>

                    <div class="field" id="reimbursement-payment-transfer-mode-field">
                        <label>Transfer Mode</label>
                        <div class="field-row">
                            <select name="transfer_mode" id="reimbursement-payment-transfer-mode">
                                <option value="" selected disabled>Select transfer mode</option>
                            </select>
                        </div>
                        <small class="field-error"><span>!</span>Transfer mode is required for the selected bank.</small>
                    </div>

                    <div class="field" id="reimbursement-payment-transaction-id-field">
                        <label>Transaction ID</label>
                        <div class="field-row">
                            <input type="text" name="transaction_id" id="reimbursement-payment-transaction-id">
                        </div>
                        <small class="field-error"><span>!</span>Transaction ID is required unless the payment is cash.</small>
                    </div>

                    <div class="field">
                        <label>Payment Date</label>
                        <div class="field-row">
                            <input type="date" name="payment_date" id="reimbursement-payment-date" required>
                        </div>
                        <small class="field-error"><span>!</span>Payment date is required.</small>
                    </div>

                    <div class="field">
                        <label>Proof Upload</label>
                        <div class="field-row">
                            <input type="file" name="proof_upload" id="reimbursement-payment-proof" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
                        </div>
                        <small class="hint">Accepted formats: JPG, PNG, PDF.</small>
                    </div>
                </div>

                <div class="field">
                    <label>Remarks</label>
                    <div class="field-row">
                        <textarea name="remarks" id="reimbursement-payment-remarks" rows="3" placeholder="Optional notes for this payment"></textarea>
                    </div>
                </div>

                <button class="button solid" type="submit" id="reimbursement-payment-submit">Save Payment</button>
            </form>
        </div>
    </div>

    <style>
        .admin-reimbursement-filter-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; align-items: end; }
        .reimbursement-filter-actions { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
        .admin-reimbursement-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; }
        .reimbursement-admin-card { padding: 22px; border-radius: 28px; background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(243,246,255,0.96)); border: 1px solid rgba(36, 52, 109, 0.1); box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08); }
        .reimbursement-admin-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
        .reimbursement-meta-chip { padding: 12px 14px; border-radius: 18px; background: rgba(236, 242, 255, 0.72); display: grid; gap: 4px; }
        .reimbursement-description { color: #1e293b; margin: 0; }
        .reimbursement-status.pending { background: #e5e7eb; color: #374151; }
        .reimbursement-status.approved { background: #fef3c7; color: #92400e; }
        .reimbursement-status.denied { background: #fee2e2; color: #b91c1c; }
        .reimbursement-status.partially-paid { background: #e0f2fe; color: #0369a1; }
        .reimbursement-status.paid { background: #dcfce7; color: #166534; }
        .reimbursement-status-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: end; }
        .reimbursement-status-label { display: grid; gap: 8px; }
        .reimbursement-preview-modal-card { max-width: 860px; }
        .reimbursement-preview-frame { width: 100%; min-height: 420px; border: 0; border-radius: 18px; background: #f8fafc; }
        .reimbursement-preview-image { max-width: 100%; border-radius: 18px; display: block; }
        @media (max-width: 900px) {
            .admin-reimbursement-filter-grid { grid-template-columns: 1fr; }
            .reimbursement-filter-actions { justify-content: stretch; }
            .reimbursement-admin-meta { grid-template-columns: 1fr; }
            .reimbursement-status-row { grid-template-columns: 1fr; }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const transferModesMap = <?= json_encode($transferModesMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const openPageModal = id => {
                const target = document.getElementById(id);
                if (target) {
                    target.classList.add('open');
                }
            };

            const paymentModal = document.getElementById('reimbursement-payment-modal');
            const paymentForm = document.getElementById('reimbursement-payment-form');
            const paymentTitle = document.getElementById('reimbursement-payment-title');
            const paymentCopy = document.getElementById('reimbursement-payment-copy');
            const paymentIdInput = document.getElementById('reimbursement-payment-reimbursement-id');
            const paymentFilterEmployee = document.getElementById('reimbursement-payment-filter-employee');
            const paymentFilterCategory = document.getElementById('reimbursement-payment-filter-category');
            const amountInput = document.getElementById('reimbursement-payment-amount');
            const bankSelect = document.getElementById('reimbursement-payment-bank');
            const transferField = document.getElementById('reimbursement-payment-transfer-mode-field');
            const transferSelect = document.getElementById('reimbursement-payment-transfer-mode');
            const txnField = document.getElementById('reimbursement-payment-transaction-id-field');
            const txnInput = document.getElementById('reimbursement-payment-transaction-id');
            const dateInput = document.getElementById('reimbursement-payment-date');

            const setSelectValue = (select, value) => {
                if (!select) return;
                const target = String(value ?? '');
                const option = Array.from(select.options).find(opt => String(opt.value) === target);
                if (option) {
                    select.value = target;
                } else {
                    select.value = '';
                }
            };

            const updateTransferModes = selectedMode => {
                if (!bankSelect || !transferField || !transferSelect || !txnInput || !txnField) return;
                const bank = String(bankSelect.value || '').toUpperCase();
                const modes = Array.isArray(transferModesMap[bank]) ? transferModesMap[bank] : [];
                const requiresTransfer = modes.length > 0;
                const requiresTxn = bank !== 'CASH';

                transferField.classList.toggle('hidden', !requiresTransfer);
                transferSelect.required = requiresTransfer;
                transferSelect.disabled = !requiresTransfer;

                txnInput.required = requiresTxn;
                txnInput.disabled = !requiresTxn;
                txnField.classList.toggle('hidden', !requiresTxn);
                if (!requiresTxn) {
                    txnInput.value = '';
                }

                if (!requiresTransfer) {
                    transferSelect.innerHTML = '<option value="" selected disabled>Select transfer mode</option>';
                    return;
                }

                transferSelect.innerHTML = '<option value=\"\" selected disabled>Select transfer mode</option>' +
                    modes.map(mode => `<option value=\"${mode}\">${mode}</option>`).join('');
                if (selectedMode) {
                    setSelectValue(transferSelect, selectedMode);
                }
            };

            if (bankSelect) {
                bankSelect.addEventListener('change', () => updateTransferModes(''));
            }

            document.querySelectorAll('[data-reimbursement-preview]').forEach(button => {
                button.addEventListener('click', () => {
                    const preview = JSON.parse(button.dataset.reimbursementPreview || '{}');
                    const content = document.getElementById('reimbursement-preview-content');
                    if (!content) {
                        return;
                    }

                    const isPdf = String(preview.attachmentMime || '').toLowerCase() === 'application/pdf';
                    const media = isPdf
                        ? `<iframe class="reimbursement-preview-frame" src="${preview.attachmentUrl}"></iframe>`
                        : `<img class="reimbursement-preview-image" src="${preview.attachmentUrl}" alt="Reimbursement proof preview">`;

                    content.innerHTML = `
                        <span class="eyebrow">Attachment Preview</span>
                        <h2>${preview.employee}</h2>
                        <p><strong>Category:</strong> ${preview.category} | <strong>Status:</strong> ${preview.status}</p>
                        <p><strong>Requested Amount:</strong> Rs ${preview.amount}</p>
                        <p>${preview.description}</p>
                        <div class="spacer"></div>
                        ${media}
                        <div class="spacer"></div>
                        <a class="button outline" href="${preview.attachmentUrl}" target="_blank" rel="noopener">Open File</a>
                    `;
                    openPageModal('reimbursement-preview-modal');
                });
            });

            document.querySelectorAll('[data-reimbursement-status-form]').forEach(form => {
                form.addEventListener('submit', event => {
                    const select = form.querySelector('[data-reimbursement-status-select]');
                    if (!select) {
                        return;
                    }

                    if (select.value === 'PARTIALLY PAID' || select.value === 'PAID') {
                        event.preventDefault();
                        const remaining = Number(form.dataset.reimbursementRemaining || 0);
                        const mode = String(select.value || '');

                        if (paymentIdInput) paymentIdInput.value = form.dataset.reimbursementId || '';
                        if (paymentFilterEmployee) paymentFilterEmployee.value = form.dataset.filterEmployee || '';
                        if (paymentFilterCategory) paymentFilterCategory.value = form.dataset.filterCategory || '';

                        if (paymentTitle) {
                            paymentTitle.textContent = mode === 'PAID' ? 'Mark Reimbursement as Paid' : 'Record Partial Reimbursement';
                        }
                        if (paymentCopy) {
                            paymentCopy.textContent = mode === 'PAID'
                                ? `Settlement amount for this payment: Rs ${remaining.toFixed(2)}.`
                                : `Enter the partial payment amount (must be less than or equal to remaining): Rs ${remaining.toFixed(2)}.`;
                        }

                        if (paymentForm) {
                            paymentForm.reset();
                        }
                        if (dateInput) {
                            const today = new Date();
                            const yyyy = today.getFullYear();
                            const mm = String(today.getMonth() + 1).padStart(2, '0');
                            const dd = String(today.getDate()).padStart(2, '0');
                            dateInput.value = `${yyyy}-${mm}-${dd}`;
                        }

                        if (amountInput) {
                            amountInput.max = remaining > 0 ? String(remaining.toFixed(2)) : '';
                            amountInput.readOnly = mode === 'PAID';
                            amountInput.value = mode === 'PAID' ? String(remaining.toFixed(2)) : '';
                        }

                        // Reset conditional banking fields.
                        if (bankSelect) {
                            bankSelect.value = '';
                        }
                        if (txnInput) {
                            txnInput.value = '';
                        }
                        updateTransferModes('');

                        openPageModal('reimbursement-payment-modal');
                    }
                });
            });
        });
    </script>
    <?php
    render_footer();
}

function render_admin_accounts_legacy(): void
{
    require_role('admin');

    $filters = payment_filter_params($_GET);
    $section = (string) ($filters['section'] ?? 'request');
    $requestMonth = (string) ($filters['request_month'] ?? date('Y-m'));

    if (!empty($_GET['download_payslip_id'])) {
        $payment = admin_payment_by_id((int) $_GET['download_payslip_id']);
        if (!$payment) {
            flash('error', 'Payment record not found for payslip download.');
            redirect_to('admin_accounts', payment_redirect_query($filters));
        }

        stream_payment_payslip_pdf($payment);
    }

    $allEmployees = employees();
    $paymentTypes = payment_types();
    $paymentMethods = payment_bank_names();
    $accountsProcessPaymentBanks = ['SBI', 'CANARA', 'IOB', 'CASH'];
    $payrollPaymentMethods = ['UPI', 'CASH'];
    $transferModesMap = payment_transfer_modes_map();
    $items = admin_payments($filters);
    $requestRows = payment_request_rows($requestMonth);
    $reportQuery = payment_redirect_query(array_merge($filters, ['section' => 'report']));
    $tabQueryBase = ['page' => 'admin_accounts', 'request_month' => $requestMonth];
    $modalDefaults = [
        'payment_id' => 0,
        'employee_id' => 0,
        'payment_type' => '',
        'amount' => '',
        'payment_methods' => [],
        'transfer_mode' => '',
        'transaction_id' => '',
        'payment_date' => date('Y-m-d'),
        'remarks' => '',
        'reimbursement_id' => 0,
        'proof_name' => '',
        'request_valid' => false,
    ];

    render_header('Accounts', 'admin-accounts-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Accounts</span>
            <h1>Accounts</h1>
            <p>Review payment requests, process valid payouts, and audit completed transactions from one place.</p>
        </div>
    </section>

    <section class="section-block accounts-tabs-panel">
        <nav class="employee-tabs inline" aria-label="Accounts sections">
            <a class="tab-link <?= $section === 'request' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?<?= h(http_build_query(array_merge($tabQueryBase, ['section' => 'request']))) ?>">Payment Request</a>
            <a class="tab-link <?= $section === 'payment' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?<?= h(http_build_query(array_merge($tabQueryBase, ['section' => 'payment']))) ?>">Payment</a>
            <a class="tab-link <?= $section === 'report' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?<?= h(http_build_query(array_merge($tabQueryBase, ['section' => 'report']))) ?>">Accounts Report</a>
        </nav>
    </section>

    <?php if (in_array($section, ['request', 'payment'], true)): ?>
        <div class="spacer"></div>
        <section class="section-block">
            <form method="get" class="accounts-month-form">
                <input type="hidden" name="page" value="admin_accounts">
                <input type="hidden" name="section" value="<?= h($section) ?>">
                <label>Request Month<input type="month" name="request_month" value="<?= h($requestMonth) ?>"></label>
                <button class="button solid" type="submit">Apply Month</button>
            </form>
        </section>

        <div class="spacer"></div>
        <section class="table-wrap">
            <div class="data-toolbar">
                <div class="split">
                    <h2><?= $section === 'request' ? 'Payment Requests' : 'Ready for Payment' ?></h2>
                    <span class="badge"><?= count($requestRows) ?> request(s)</span>
                </div>
                <?php if ($section === 'payment'): ?>
                    <button class="button solid" type="button" data-payment-open-create data-modal-target="accounts-payment-modal">Record Manual Payment</button>
                <?php endif; ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Request Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requestRows): ?>
                        <?php foreach ($requestRows as $row):
                            $requestPayload = [
                                'payment_id' => 0,
                                'employee_id' => (int) $row['employee_id'],
                                'employee_salary' => number_format((float) ($row['employee_salary'] ?? 0), 2, '.', ''),
                                'payment_type' => (string) $row['request_type'],
                                'amount' => number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                                'payment_methods' => [],
                                'transfer_mode' => '',
                                'transaction_id' => '',
                                'payment_date' => date('Y-m-d'),
                                'remarks' => (string) $row['request_type'] . ' request for ' . $requestMonth,
                                'reimbursement_id' => (int) ($row['reimbursement_id'] ?? 0),
                                'proof_name' => '',
                                'request_valid' => !empty($row['ready']),
                                'request_key' => (string) ($row['request_key'] ?? ''),
                            ];
                            ?>
                            <tr>
                                <td>
                                    <?= h((string) $row['employee_name']) ?><br>
                                    <span class="hint"><?= h((string) ($row['employee_emp_id'] ?: 'Employee')) ?></span><br>
                                    <span class="hint">Salary: Rs <?= h(number_format((float) ($row['employee_salary'] ?? 0), 2)) ?></span>
                                </td>
                                <td><?= h((string) $row['request_type']) ?></td>
                                <td>Rs <?= h(number_format((float) ($row['amount'] ?? 0), 2)) ?></td>
                                <td>
                                    <span class="status-pill <?= !empty($row['ready']) ? 'status-Present' : 'status-Absent' ?>"><?= h((string) $row['status']) ?></span>
                                    <?php if (!empty($row['errors'])): ?>
                                        <div class="hint"><?= h(implode(' ', $row['errors'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($section === 'payment'): ?>
                                        <button
                                            class="button solid small"
                                            type="button"
                                            data-payment-request="<?= h(json_encode($requestPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>"
                                            <?= !empty($row['ready']) ? '' : 'disabled' ?>
                                        >
                                            Pay
                                        </button>
                                    <?php else: ?>
                                        <div class="payment-action-row">
                                            <?php if ($row['status'] !== 'APPROVED' && $row['status'] !== 'PAID'): ?>
                                                <form method="post" style="display:inline;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="admin_approve_payment_request">
                                                    <input type="hidden" name="request_key" value="<?= h($row['request_key']) ?>">
                                                    <input type="hidden" name="filter_section" value="<?= h($section) ?>">
                                                    <input type="hidden" name="filter_request_month" value="<?= h($requestMonth) ?>">
                                                    <button class="button solid small" type="submit" <?= empty($row['errors']) ? '' : 'disabled' ?>>Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($row['status'] !== 'REJECTED' && $row['status'] !== 'DENIED' && $row['status'] !== 'PAID'): ?>
                                                <form method="post" style="display:inline;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="admin_reject_payment_request">
                                                    <input type="hidden" name="request_key" value="<?= h($row['request_key']) ?>">
                                                    <input type="hidden" name="filter_section" value="<?= h($section) ?>">
                                                    <input type="hidden" name="filter_request_month" value="<?= h($requestMonth) ?>">
                                                    <button class="button outline small" type="submit">Reject</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($row['status'] === 'APPROVED' || $row['status'] === 'REJECTED' || $row['status'] === 'DENIED'): ?>
                                                <span class="hint"><?= h($row['status']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="muted center">No payment requests are available for the selected month.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php else: ?>
        <div class="spacer"></div>
        <section class="section-block accounts-filters-panel">
            <form method="get" class="stack-form">
                <input type="hidden" name="page" value="admin_accounts">
                <input type="hidden" name="section" value="report">
                <input type="hidden" name="request_month" value="<?= h($requestMonth) ?>">
                <div class="accounts-report-filter-grid">
                    <div class="field">
                        <label>Employee</label>
                        <select name="employee_id">
                            <option value="0">All Employees</option>
                            <?php foreach ($allEmployees as $employee): ?>
                                <option value="<?= (int) $employee['id'] ?>" <?= (int) $filters['employee_id'] === (int) $employee['id'] ? 'selected' : '' ?>><?= h((string) $employee['name']) ?> (<?= h((string) $employee['emp_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Payment Type</label>
                        <select name="payment_type">
                            <option value="">All Payment Types</option>
                            <?php foreach ($paymentTypes as $paymentType): ?>
                                <option value="<?= h($paymentType) ?>" <?= $filters['payment_type'] === $paymentType ? 'selected' : '' ?>><?= h($paymentType) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>From Date</label>
                        <input type="date" name="from_date" value="<?= h((string) $filters['from_date']) ?>">
                    </div>
                    <div class="field">
                        <label>To Date</label>
                        <input type="date" name="to_date" value="<?= h((string) $filters['to_date']) ?>">
                    </div>
                    <div class="accounts-filter-actions">
                        <button class="button solid" type="submit">Apply Filters</button>
                        <a class="button outline" href="<?= h(BASE_URL) ?>?<?= h(http_build_query(array_merge($tabQueryBase, ['section' => 'report']))) ?>">Reset</a>
                    </div>
                </div>
            </form>
        </section>

        <div class="spacer"></div>
        <section class="table-wrap">
            <div class="data-toolbar">
                <div class="split">
                    <h2>Accounts Report</h2>
                    <span class="badge"><?= count($items) ?> payment(s)</span>
                </div>
                <button class="button solid" type="button" data-payment-open-create data-modal-target="accounts-payment-modal">Record Payment</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Payment Type</th>
                        <th>Amount</th>
                        <th>Payment Method(s)</th>
                        <th>Transaction ID</th>
                        <th>Date of Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items): ?>
                        <?php foreach ($items as $paymentRow):
                            $proofUrl = !empty($paymentRow['proof_path']) ? asset_url((string) $paymentRow['proof_path']) : '';
                            $methods = payment_methods_for_record($paymentRow);
                            $payslipUrl = BASE_URL . '?' . http_build_query(array_merge([
                                'page' => 'admin_accounts',
                                'download_payslip_id' => (int) $paymentRow['id'],
                            ], $reportQuery));
                            $editPayload = [
                                'payment_id' => (int) $paymentRow['id'],
                                'employee_id' => (int) $paymentRow['user_id'],
                                'payment_type' => (string) $paymentRow['payment_type'],
                                'amount' => number_format((float) ($paymentRow['amount'] ?? 0), 2, '.', ''),
                                'payment_methods' => $methods,
                                'transfer_mode' => (string) ($paymentRow['transfer_mode'] ?? ''),
                                'transaction_id' => (string) ($paymentRow['transaction_id'] ?? ''),
                                'payment_date' => (string) $paymentRow['payment_date'],
                                'remarks' => (string) ($paymentRow['remarks'] ?? ''),
                                'reimbursement_id' => (int) ($paymentRow['reimbursement_id'] ?? 0),
                                'proof_name' => (string) ($paymentRow['proof_name'] ?? ''),
                                'request_valid' => true,
                            ];
                            ?>
                            <tr>
                                <td><?= h((string) $paymentRow['employee_name']) ?></td>
                                <td><?= h((string) $paymentRow['payment_type']) ?></td>
                                <td>Rs <?= h(number_format((float) ($paymentRow['amount'] ?? 0), 2)) ?></td>
                                <td><?= h(payment_methods_label($methods)) ?></td>
                                <td><?= h((string) (($paymentRow['transaction_id'] ?? '') ?: '-')) ?></td>
                                <td><?= h(date('d M Y', strtotime((string) $paymentRow['payment_date']))) ?></td>
                                <td>
                                    <div class="payment-action-row">
                                        <button class="button outline small" type="button" data-payment-edit="<?= h(json_encode($editPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">Edit</button>
                                        <form method="post" onsubmit="return confirm('Delete this payment record?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="admin_delete_payment">
                                            <input type="hidden" name="payment_id" value="<?= (int) $paymentRow['id'] ?>">
                                            <input type="hidden" name="filter_section" value="report">
                                            <input type="hidden" name="filter_request_month" value="<?= h($requestMonth) ?>">
                                            <input type="hidden" name="filter_employee_id" value="<?= (int) $filters['employee_id'] ?>">
                                            <input type="hidden" name="filter_payment_type" value="<?= h((string) $filters['payment_type']) ?>">
                                            <input type="hidden" name="filter_from_date" value="<?= h((string) $filters['from_date']) ?>">
                                            <input type="hidden" name="filter_to_date" value="<?= h((string) $filters['to_date']) ?>">
                                            <button class="button ghost small" type="submit">Delete</button>
                                        </form>
                                        <a class="button outline small" href="<?= h($payslipUrl) ?>">Payslip</a>
                                        <?php if ($proofUrl !== ''): ?>
                                            <a class="button outline small" href="<?= h($proofUrl) ?>" target="_blank" rel="noopener">Proof</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="muted center">No payments found for the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <div class="modal" id="accounts-payment-modal">
        <div class="modal-card accounts-payment-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Accounts Payment</span>
            <h2 id="accounts-payment-modal-title">Process Payment</h2>
            <p class="hint" id="accounts-payment-modal-copy">Select a valid payment request or record a manual payment.</p>
            <form method="post" enctype="multipart/form-data" class="stack-form" id="accounts-payment-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_save_payment">
                <input type="hidden" name="payment_id" id="accounts-payment-id" value="0">
                <input type="hidden" name="filter_section" value="<?= h($section) ?>">
                <input type="hidden" name="filter_request_month" value="<?= h($requestMonth) ?>">
                <input type="hidden" name="filter_employee_id" value="<?= (int) $filters['employee_id'] ?>">
                <input type="hidden" name="filter_payment_type" value="<?= h((string) $filters['payment_type']) ?>">
                <input type="hidden" name="filter_from_date" value="<?= h((string) $filters['from_date']) ?>">
                <input type="hidden" name="filter_to_date" value="<?= h((string) $filters['to_date']) ?>">

                <div class="accounts-payment-grid">
                    <div class="field">
                        <label>Employee</label>
                        <div class="field-row">
                            <input type="hidden" name="employee_id" id="accounts-payment-employee">
                            <input type="text" id="accounts-payment-employee-display" readonly>
                        </div>
                        <small class="hint" id="accounts-employee-salary-note">Salary: Rs 0.00</small>
                    </div>

                    <div class="field">
                        <label>Payment Type</label>
                        <div class="field-row">
                            <input type="hidden" name="payment_type" id="accounts-payment-type">
                            <input type="text" id="accounts-payment-type-display" readonly>
                        </div>
                    </div>

                    <div class="field accounts-conditional-field hidden" id="accounts-reimbursement-field">
                        <label>Approved Reimbursement</label>
                        <div class="field-row">
                            <select name="reimbursement_id" id="accounts-reimbursement-select">
                                <option value="">Select approved reimbursement request</option>
                            </select>
                        </div>
                        <small class="hint" id="accounts-reimbursement-note">Choose a linked reimbursement request when settling a claim.</small>
                    </div>

                    <div class="field accounts-conditional-field hidden" id="accounts-incentive-field">
                        <label>Calculated Incentive</label>
                        <div class="field-row">
                            <input type="text" id="accounts-incentive-amount" readonly>
                        </div>
                    </div>

                    <div class="field">
                        <label>Amount</label>
                        <div class="field-row">
                            <input type="number" name="amount" id="accounts-payment-amount" min="0.01" step="0.01" required>
                        </div>
                    </div>

                    <div class="field accounts-method-field">
                        <label>Bank Name</label>
                        <div class="field-row">
                            <select name="payment_methods" id="accounts-bank-name" required>
                                <option value="" selected disabled>Select bank</option>
                                <?php foreach ($accountsProcessPaymentBanks as $method): ?>
                                    <option value="<?= h($method) ?>"><?= h($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field" id="accounts-transfer-mode-field">
                        <label>Transfer Mode</label>
                        <div class="field-row">
                            <select name="transfer_mode" id="accounts-transfer-mode">
                                <option value="" selected disabled>Select transfer mode</option>
                            </select>
                        </div>
                    </div>

                    <div class="field" id="accounts-transaction-id-field">
                        <label>Transaction ID</label>
                        <div class="field-row">
                            <input type="text" name="transaction_id" id="accounts-transaction-id">
                        </div>
                    </div>

                    <div class="field">
                        <label>Payment Date</label>
                        <div class="field-row">
                            <input type="date" name="payment_date" id="accounts-payment-date" required>
                        </div>
                    </div>

                    <div class="field">
                        <label>Proof Upload</label>
                        <div class="field-row">
                            <input type="file" name="proof_upload" id="accounts-proof-upload" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
                        </div>
                        <small class="hint" id="accounts-proof-help">Accepted formats: JPG, PNG, PDF.</small>
                    </div>
                </div>

                <div class="field">
                    <label>Remarks</label>
                    <div class="field-row">
                        <textarea name="remarks" id="accounts-payment-remarks" rows="3" placeholder="Optional notes for this payment"></textarea>
                    </div>
                </div>

                <div class="list-item accounts-calculation-note" id="accounts-calculation-note">Choose a request or fill the payment form to continue.</div>
                <button class="button solid" type="submit" id="accounts-payment-submit-button">Pay</button>
            </form>
        </div>
    </div>

    <style>
        .accounts-month-form,
        .accounts-report-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; align-items: end; }
        .accounts-tabs-panel { padding-bottom: 18px; }
        .accounts-tabs-panel .employee-tabs { display: flex; gap: 10px; flex-wrap: wrap; }
        .accounts-payment-card { width: min(920px, 100%); }
        .accounts-payment-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .accounts-conditional-field.hidden,
        #accounts-transfer-mode-field.hidden,
        #accounts-transaction-id-field.hidden { display: none; }
        .accounts-method-field { grid-column: 1 / -1; }
        .payment-method-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
        .payment-method-option { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px solid rgba(36, 52, 109, 0.12); border-radius: 16px; background: rgba(248, 250, 255, 0.9); }
        .payment-method-option input { width: auto; min-height: auto; margin: 0; }
        .payment-action-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .payment-action-row form { margin: 0; }
        .accounts-calculation-note { border: 1px solid rgba(36, 52, 109, 0.08); background: rgba(248, 250, 255, 0.82); }
        @media (max-width: 1100px) {
            .accounts-month-form,
            .accounts-report-filter-grid,
            .accounts-payment-grid { grid-template-columns: 1fr 1fr; }
            .payment-method-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 760px) {
            .accounts-month-form,
            .accounts-report-filter-grid,
            .accounts-payment-grid,
            .payment-method-grid { grid-template-columns: 1fr; }
            .payment-action-row { flex-direction: column; align-items: stretch; }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const requestRows = <?= json_encode($requestRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const transferModeMap = <?= json_encode($transferModesMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const createDefaults = <?= json_encode($modalDefaults, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const employeeLabelMap = <?= json_encode(array_reduce($allEmployees, static function (array $carry, array $employee): array {
                $carry[(string) ((int) ($employee['id'] ?? 0))] = (string) ($employee['name'] ?? '') . ' (' . (string) ($employee['emp_id'] ?? '') . ')';
                return $carry;
            }, []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const employeeSalaryMap = <?= json_encode(array_reduce($allEmployees, static function (array $carry, array $employee): array {
                $carry[(string) ((int) ($employee['id'] ?? 0))] = (float) ($employee['salary'] ?? 0);
                return $carry;
            }, []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const form = document.getElementById('accounts-payment-form');
            const modalTitle = document.getElementById('accounts-payment-modal-title');
            const modalCopy = document.getElementById('accounts-payment-modal-copy');
            const submitButton = document.getElementById('accounts-payment-submit-button');
            const paymentIdInput = document.getElementById('accounts-payment-id');
            const employeeInput = document.getElementById('accounts-payment-employee');
            const employeeDisplay = document.getElementById('accounts-payment-employee-display');
            const employeeSalaryNote = document.getElementById('accounts-employee-salary-note');
            const paymentTypeInput = document.getElementById('accounts-payment-type');
            const paymentTypeDisplay = document.getElementById('accounts-payment-type-display');
            const reimbursementField = document.getElementById('accounts-reimbursement-field');
            const reimbursementSelect = document.getElementById('accounts-reimbursement-select');
            const reimbursementNote = document.getElementById('accounts-reimbursement-note');
            const incentiveField = document.getElementById('accounts-incentive-field');
            const incentiveAmountInput = document.getElementById('accounts-incentive-amount');
            const amountInput = document.getElementById('accounts-payment-amount');
            const bankSelect = document.getElementById('accounts-bank-name');
            const transferModeField = document.getElementById('accounts-transfer-mode-field');
            const transferModeSelect = document.getElementById('accounts-transfer-mode');
            const transactionField = document.getElementById('accounts-transaction-id-field');
            const transactionInput = document.getElementById('accounts-transaction-id');
            const paymentDateInput = document.getElementById('accounts-payment-date');
            const remarksInput = document.getElementById('accounts-payment-remarks');
            const calculationNote = document.getElementById('accounts-calculation-note');
            const proofHelp = document.getElementById('accounts-proof-help');

            const openPageModal = id => {
                const target = document.getElementById(id);
                if (target) {
                    target.classList.add('open');
                }
            };

            const selectedMethods = () => {
                const value = String(bankSelect ? (bankSelect.value || '') : '');
                return value !== '' ? [value] : [];
            };

            const setMethods = methods => {
                const values = Array.isArray(methods) ? methods.map(String) : [];
                if (bankSelect) {
                    bankSelect.value = values[0] || '';
                }
            };

            const setSelectValue = (select, value) => {
                const normalized = String(value ?? '');
                Array.from(select.options).forEach(option => {
                    option.selected = option.value === normalized;
                });
            };

            const updateEmployeeSalaryNote = preferredSalary => {
                const employeeId = String(employeeInput ? (employeeInput.value || '') : '');
                const mappedSalary = employeeId !== '' && Object.prototype.hasOwnProperty.call(employeeSalaryMap, employeeId)
                    ? Number(employeeSalaryMap[employeeId] || 0)
                    : 0;
                const salary = preferredSalary !== undefined && preferredSalary !== null && preferredSalary !== ''
                    ? Number(preferredSalary || 0)
                    : mappedSalary;
                employeeSalaryNote.textContent = `Salary: Rs ${salary.toFixed(2)}`;
            };

            const updateTransferModes = preferredValue => {
                const methods = selectedMethods();
                const modeSet = [];
                methods.forEach(method => {
                    (transferModeMap[method] || []).forEach(mode => {
                        if (!modeSet.includes(mode)) {
                            modeSet.push(mode);
                        }
                    });
                });
                if (methods.filter(method => method !== 'CASH').length > 1 && !modeSet.includes('MIXED')) {
                    modeSet.push('MIXED');
                }

                const previousValue = String(preferredValue ?? transferModeSelect.value ?? '');
                transferModeSelect.innerHTML = '';
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = modeSet.length ? 'Select transfer mode' : 'Not required';
                placeholder.selected = previousValue === '';
                placeholder.disabled = modeSet.length > 0;
                transferModeSelect.appendChild(placeholder);

                modeSet.forEach(mode => {
                    const option = document.createElement('option');
                    option.value = mode;
                    option.textContent = mode;
                    option.selected = previousValue === mode;
                    transferModeSelect.appendChild(option);
                });

                transferModeField.classList.toggle('hidden', modeSet.length === 0);
                transferModeSelect.required = modeSet.length > 0;
                transferModeSelect.disabled = modeSet.length === 0;
                const requiresTransaction = methods.some(method => method !== 'CASH');
                transactionField.classList.toggle('hidden', !requiresTransaction);
                transactionInput.required = requiresTransaction;
                transactionInput.disabled = !requiresTransaction;
                if (!requiresTransaction) {
                    transactionInput.value = '';
                }
            };

            const populateReimbursementOptions = selectedId => {
                const employeeId = Number(employeeInput ? (employeeInput.value || 0) : 0);
                const reimbursementRows = requestRows.filter(row => row.request_type === 'REIMBURSEMENT' && (!employeeId || Number(row.employee_id) === employeeId));
                reimbursementSelect.innerHTML = '<option value="">Select approved reimbursement request</option>';

                reimbursementRows.forEach(row => {
                    const option = document.createElement('option');
                    option.value = String(row.reimbursement_id || 0);
                    option.textContent = `${row.employee_name} - ${row.request_type} - Rs ${Number(row.amount || 0).toFixed(2)}`;
                    option.selected = String(selectedId || '') === String(row.reimbursement_id || 0);
                    reimbursementSelect.appendChild(option);
                });

                const selectedRow = reimbursementRows.find(row => String(row.reimbursement_id || 0) === String(selectedId || ''));
                reimbursementNote.textContent = selectedRow
                    ? `Outstanding amount: Rs ${Number(selectedRow.amount || 0).toFixed(2)}`
                    : 'Choose a linked reimbursement request when settling a claim.';
            };

            const syncFormState = () => {
                const paymentType = String(paymentTypeInput ? (paymentTypeInput.value || '') : '');
                const methods = selectedMethods();
                const showReimbursement = paymentType === 'REIMBURSEMENT';
                const showIncentive = paymentType === 'INCENTIVE';
                updateEmployeeSalaryNote();
                reimbursementField.classList.toggle('hidden', !showReimbursement);
                incentiveField.classList.toggle('hidden', !showIncentive);
                if (showReimbursement) {
                    populateReimbursementOptions(reimbursementSelect.value || '');
                } else {
                    reimbursementSelect.innerHTML = '<option value="">Select approved reimbursement request</option>';
                }
                if (incentiveAmountInput) {
                    incentiveAmountInput.value = showIncentive ? `Rs ${Number(amountInput.value || 0).toFixed(2)}` : '';
                }

                updateTransferModes(transferModeSelect.value || '');

                const isValid = String(employeeInput ? (employeeInput.value || '') : '') !== ''
                    && paymentType !== ''
                    && Number(amountInput.value || 0) > 0
                    && methods.length > 0
                    && (!transferModeSelect.required || String(transferModeSelect.value || '') !== '')
                    && (!transactionInput.required || String(transactionInput.value || '').trim() !== '');

                submitButton.disabled = !isValid;
                calculationNote.textContent = isValid
                    ? 'Payment calculation is valid. You can complete this payout now.'
                    : 'Complete the required payment details before the Pay button is enabled.';
            };

            const fillForm = payload => {
                paymentIdInput.value = String(payload.payment_id || 0);
                if (employeeInput) {
                    employeeInput.value = String(payload.employee_id || '');
                }
                if (employeeDisplay) {
                    employeeDisplay.value = payload.employee_label || employeeLabelMap[String(payload.employee_id || '')] || '';
                }
                if (paymentTypeInput) {
                    paymentTypeInput.value = String(payload.payment_type || '');
                }
                if (paymentTypeDisplay) {
                    paymentTypeDisplay.value = String(payload.payment_type || '');
                }
                amountInput.value = payload.amount || '';
                setMethods(payload.payment_methods || []);
                setSelectValue(transferModeSelect, payload.transfer_mode || '');
                transactionInput.value = payload.transaction_id || '';
                paymentDateInput.value = payload.payment_date || '';
                remarksInput.value = payload.remarks || '';
                proofHelp.textContent = payload.proof_name
                    ? `Current proof: ${payload.proof_name}. Upload a new file only if you want to replace it.`
                    : 'Accepted formats: JPG, PNG, PDF.';
                updateEmployeeSalaryNote(payload.employee_salary);
                populateReimbursementOptions(payload.reimbursement_id || '');
                reimbursementSelect.value = String(payload.reimbursement_id || '');
                syncFormState();
            };

            const resetCreateForm = () => {
                form.reset();
                fillForm(createDefaults);
                modalTitle.textContent = 'Process Payment';
                modalCopy.textContent = 'Select a valid payment request or record a manual payment.';
                submitButton.textContent = 'Pay';
            };

            document.querySelectorAll('[data-payment-request]').forEach(button => {
                button.addEventListener('click', () => {
                    const payload = JSON.parse(button.dataset.paymentRequest || '{}');
                    if ((!Array.isArray(payload.payment_methods) || payload.payment_methods.length === 0) && payload.request_valid) {
                        payload.payment_methods = ['CASH'];
                    }
                    modalTitle.textContent = 'Pay Request';
                    modalCopy.textContent = 'The form has been prefilled from the selected payment request.';
                    submitButton.textContent = 'Pay';
                    fillForm(payload);
                    openPageModal('accounts-payment-modal');
                });
            });

            document.querySelectorAll('[data-payment-edit]').forEach(button => {
                button.addEventListener('click', () => {
                    const payload = JSON.parse(button.dataset.paymentEdit || '{}');
                    modalTitle.textContent = 'Edit Payment';
                    modalCopy.textContent = 'Update the payment details below. Existing proof will stay unless you upload a replacement.';
                    submitButton.textContent = 'Update Payment';
                    fillForm(payload);
                    openPageModal('accounts-payment-modal');
                });
            });

            document.querySelectorAll('[data-payment-open-create]').forEach(button => {
                button.addEventListener('click', () => {
                    resetCreateForm();
                    openPageModal('accounts-payment-modal');
                });
            });

            if (bankSelect) {
                bankSelect.addEventListener('change', syncFormState);
            }
            reimbursementSelect.addEventListener('change', syncFormState);
            amountInput.addEventListener('input', syncFormState);
            transferModeSelect.addEventListener('change', syncFormState);
            transactionInput.addEventListener('input', syncFormState);

            resetCreateForm();
        });
    </script>
    <?php
    render_footer();
}

function render_admin_accounts(): void
{
    $accountsUser = require_power_accounts_access(['admin']);
    $currentUser = current_user();
    $isDelegatedAccounts = $currentUser && in_array((string) ($currentUser['role'] ?? ''), ['employee', 'corporate_employee'], true);
    $allowedAccountSections = ['approval', 'pay', 'history'];
    if ($isDelegatedAccounts) {
        $scopeToSection = ['verify' => 'approval', 'pay' => 'pay', 'history' => 'history'];
        $allowedAccountSections = [];
        foreach (employee_power_account_scopes($currentUser) as $scope) {
            if (isset($scopeToSection[$scope])) {
                $allowedAccountSections[] = $scopeToSection[$scope];
            }
        }
        $allowedAccountSections = array_values(array_unique($allowedAccountSections));
    }

    $filters = payment_filter_params($_GET);
    $section = match ((string) ($filters['section'] ?? 'approval')) {
        'request' => 'approval',
        'payment' => 'pay',
        'report' => 'history',
        default => (string) ($filters['section'] ?? 'approval'),
    };
    if (!in_array($section, $allowedAccountSections, true)) {
        $section = $allowedAccountSections[0] ?? 'approval';
        $filters['section'] = $section;
    }
    $requestMonth = (string) ($filters['request_month'] ?? date('Y-m'));
    $approvalType = 'REIMBURSEMENT';
    if (in_array((string) ($filters['approval_type'] ?? ''), ['SALARY', 'REIMBURSEMENT', 'CONTRACTUAL'], true)) {
        $approvalType = (string) $filters['approval_type'];
    }
    $approvalScope = (string) ($filters['approval_scope'] ?? 'employee');
    $payGroup = (string) ($filters['pay_group'] ?? 'employee');
    $payTypes = $payGroup === 'freelancer' ? ['SALARY'] : ['REIMBURSEMENT'];
    if ($section === 'pay') {
        $filters['pay_types'] = $payTypes;
    }

    if (!empty($_GET['download_payslip_id'])) {
        require_power_accounts_access(['admin'], 'history');
        $payment = admin_payment_by_id((int) $_GET['download_payslip_id']);
        if (!$payment) {
            flash('error', 'Payment record not found for payslip download.');
            redirect_to('admin_accounts', payment_redirect_query($filters));
        }

        stream_payment_payslip_pdf($payment);
    }

    $approvalRows = accounts_approval_rows($requestMonth, $approvalType, $approvalScope);
    $payGroups = accounts_pay_group_rows($requestMonth, $payGroup, $payTypes);
    $historyRows = accounts_payment_history_rows($filters);
    $vendorAccounts = accounts_vendor_accounts();
    $paymentMethods = payment_bank_names();
    $accountsPayrollBanks = ['SBI', 'CANARA', 'IOB', 'CASH'];
    $payrollPaymentMethods = ['UPI', 'CASH'];
    $transferModesMap = payment_transfer_modes_map();
    $accountScopes = ['employee', 'vendor', 'freelancer'];

    $allEmployees = [];
    foreach ($accountScopes as $scope) {
        foreach (accounts_scope_members($scope) as $employee) {
            $allEmployees[(int) ($employee['id'] ?? 0)] = $employee;
        }
    }
    $allEmployees = array_values($allEmployees);
    usort($allEmployees, static fn(array $left, array $right): int => strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')));

    $historyQuery = payment_redirect_query(array_merge($filters, ['section' => 'history']));
    $tabQueryBase = ['page' => 'admin_accounts', 'request_month' => $requestMonth];
    $accountTabConfig = [
        'approval' => [
            'label' => 'Verify',
            'query' => ['section' => 'approval', 'approval_type' => $approvalType],
        ],
        'pay' => [
            'label' => 'Pay',
            'query' => ['section' => 'pay', 'pay_group' => $payGroup, 'pay_types' => $payTypes],
        ],
        'history' => [
            'label' => 'Pay History',
            'query' => ['section' => 'history'],
        ],
    ];

    render_header('Accounts', 'admin-accounts-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Accounts</span>
            <h1>Accounts</h1>
            <p>Review approvals, process payouts, and audit pay history from one place.</p>
        </div>
    </section>

    <section class="section-block accounts-tabs-panel">
        <nav class="employee-tabs inline" aria-label="Accounts sections">
            <?php foreach ($accountTabConfig as $tabSection => $tab): ?>
                <?php if (!in_array($tabSection, $allowedAccountSections, true)) {
                    continue;
                } ?>
                <a class="tab-link <?= $section === $tabSection ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?<?= h(http_build_query(array_merge($tabQueryBase, $tab['query']))) ?>"><?= h($tab['label']) ?></a>
            <?php endforeach; ?>
        </nav>
    </section>

    <?php if ($section === 'approval'): ?>
        <div class="spacer"></div>
        <section class="section-block accounts-filter-shell">
            <form method="get" class="accounts-toolbar-grid">
                <input type="hidden" name="page" value="admin_accounts">
                <input type="hidden" name="section" value="approval">
                <div class="field">
                    <label>Request Month</label>
                    <input type="month" name="request_month" value="<?= h($requestMonth) ?>">
                </div>
                <div class="field">
                    <label>Type</label>
                    <select name="approval_type">
                        <option value="SALARY" <?= $approvalType === 'SALARY' ? 'selected' : '' ?>>Salary</option>
                        <option value="REIMBURSEMENT" <?= $approvalType === 'REIMBURSEMENT' ? 'selected' : '' ?>>Reimbursement</option>
                        <option value="INCENTIVE" disabled>Incentive</option>
                        <option value="CONTRACTUAL" <?= $approvalType === 'CONTRACTUAL' ? 'selected' : '' ?>>Contractual Employee Pay</option>
                    </select>
                </div>
                <div class="accounts-toolbar-actions">
                    <button class="button solid" type="submit">Apply</button>
                </div>
            </form>
            <div class="spacer"></div>
            <nav class="employee-tabs" aria-label="Approval scopes">
                <?php foreach ($accountScopes as $scope): ?>
                    <a class="tab-link <?= $approvalScope === $scope ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?<?= h(http_build_query(array_merge($tabQueryBase, ['section' => 'approval', 'approval_type' => $approvalType, 'approval_scope' => $scope]))) ?>"><?= h(accounts_scope_label($scope)) ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($approvalScope === 'vendor' && $vendorAccounts): ?>
                <div class="spacer"></div>
                <div class="field">
                    <label>External Vendor</label>
                    <select disabled>
                        <?php foreach ($vendorAccounts as $vendor): ?>
                            <option><?= h((string) ($vendor['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </section>

        <div class="spacer"></div>
        <section class="table-wrap">
            <div class="data-toolbar">
                <div class="split">
                    <h2><?= $approvalType === 'REIMBURSEMENT' ? 'Reimbursement Approval Queue' : ($approvalType === 'CONTRACTUAL' ? 'Contractual Payment Requests' : ($approvalType === 'SALARY' ? 'Salary Approval Queue' : 'Approval Queue')) ?></h2>
                    <span class="badge"><?= count($approvalRows) ?> item(s)</span>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th><?= $approvalType === 'REIMBURSEMENT' ? 'Amount Requested' : ($approvalType === 'SALARY' ? 'Salary Requested' : 'Particular') ?></th>
                        <?php if ($approvalType === 'CONTRACTUAL'): ?>
                            <th>Selected Date</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($approvalRows): ?>
                        <?php foreach ($approvalRows as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['employee_emp_id'] ?? '-')) ?></td>
                                <td><?= h((string) ($row['employee_name'] ?? '')) ?></td>
                                <td>
                                    <?php if ($approvalType === 'REIMBURSEMENT'): ?>
                                        Rs <?= h(number_format((float) ($row['amount_requested'] ?? 0), 2)) ?>
                                        <div class="hint"><?= h((string) ($row['category'] ?? '')) ?></div>
                                    <?php else: ?>
                                        <?= h((string) ($row['request_type'] ?? '')) ?><br>
                                        <span class="hint">Rs <?= h(number_format((float) ($row['amount'] ?? 0), 2)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($approvalType === 'CONTRACTUAL'): ?>
                                    <td><?= !empty($row['request_date']) ? h(date('d M Y', strtotime((string) $row['request_date']))) : '-' ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($approvalType === 'REIMBURSEMENT'): ?>
                                        <div class="payment-action-row">
                                            <a
                                                class="button solid small"
                                                href="javascript:void(0)"
                                                role="button"
                                                data-reimbursement-id="<?= (int) ($row['id'] ?? 0) ?>"
                                                data-employee-name="<?= h((string) ($row['employee_name'] ?? '')) ?>"
                                                data-amount-requested="<?= h(number_format((float) ($row['amount_requested'] ?? 0), 2, '.', '')) ?>"
                                                data-particular="<?= h((string) ($row['category'] ?? '')) ?>"
                                                data-details="<?= h((string) ($row['expense_description'] ?? '')) ?>"
                                                data-proof-url="<?= h(reimbursement_attachment_url($row)) ?>"
                                                data-proof-mime="<?= h((string) ($row['attachment_mime'] ?? '')) ?>"
                                                onclick="return window.openAccountsApproval(event, this);"
                                            >Approve</a>
                                            <form method="post" onsubmit="return confirm('Deny this reimbursement request?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="admin_deny_reimbursement">
                                                <input type="hidden" name="reimbursement_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                                <input type="hidden" name="filter_request_month" value="<?= h($requestMonth) ?>">
                                                <button class="button outline small" type="submit">Deny</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="payment-action-row">
                                            <a
                                                class="button solid small"
                                                href="javascript:void(0)"
                                                role="button"
                                                data-approval-mode="request"
                                                data-request-key="<?= h((string) ($row['request_key'] ?? '')) ?>"
                                                data-employee-name="<?= h((string) ($row['employee_name'] ?? '')) ?>"
                                                data-amount-requested="<?= h(number_format((float) ($row['amount'] ?? 0), 2, '.', '')) ?>"
                                                data-particular="<?= h((string) ($row['request_type'] ?? '')) ?>"
                                                data-details="<?= h(($approvalType === 'CONTRACTUAL' && !empty($row['request_date']) ? 'Selected date: ' . date('d M Y', strtotime((string) $row['request_date'])) . '. ' : '') . (trim((string) ($row['note'] ?? '')) !== '' ? (string) $row['note'] : ('Approval request for ' . $requestMonth))) ?>"
                                                data-proof-url=""
                                                data-proof-mime=""
                                                onclick="return window.openAccountsApproval(event, this);"
                                            >Approve</a>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="admin_reject_payment_request">
                                                <input type="hidden" name="request_key" value="<?= h((string) ($row['request_key'] ?? '')) ?>">
                                                <input type="hidden" name="filter_section" value="approval">
                                                <input type="hidden" name="filter_request_month" value="<?= h($requestMonth) ?>">
                                                <input type="hidden" name="filter_approval_type" value="<?= h($approvalType) ?>">
                                                <button class="button outline small" type="submit">Deny</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="<?= $approvalType === 'CONTRACTUAL' ? 5 : 4 ?>" class="muted center"><?= $approvalScope === 'vendor' ? 'No pending vendor reimbursement requests are available for the selected filters.' : 'No approval items are available for the selected filters.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php elseif ($section === 'pay'): ?>
        <div class="spacer"></div>
        <section class="section-block accounts-filter-shell">
            <form method="get" class="stack-form">
                <input type="hidden" name="page" value="admin_accounts">
                <input type="hidden" name="section" value="pay">
                <input type="hidden" name="pay_types_submitted" value="1">
                <?php foreach ($payTypes as $payType): ?>
                    <input type="hidden" name="pay_types[]" value="<?= h($payType) ?>">
                <?php endforeach; ?>
                <div class="accounts-toolbar-grid">
                    <div class="field">
                        <label>Pay Month</label>
                        <input type="month" name="request_month" value="<?= h($requestMonth) ?>">
                    </div>
                    <div class="field">
                        <label>Type</label>
                        <div class="accounts-type-grid">
                            <span class="accounts-type-option"><?= h($payGroup === 'freelancer' ? 'Salary' : 'Reimbursement') ?></span>
                        </div>
                    </div>
                    <div class="accounts-toolbar-actions">
                        <button class="button solid" type="submit">Apply</button>
                    </div>
                </div>
                <div class="spacer"></div>
                <nav class="employee-tabs" aria-label="Pay scopes" data-pay-scope-tabs>
                    <?php foreach ($accountScopes as $scope): ?>
                        <?php $scopePayTypes = $scope === 'freelancer' ? ['SALARY'] : ['REIMBURSEMENT']; ?>
                        <a class="tab-link <?= $payGroup === $scope ? 'active' : '' ?>" data-pay-scope-link="<?= h($scope) ?>" href="<?= h(BASE_URL) ?>?<?= h(http_build_query(array_merge($tabQueryBase, ['section' => 'pay', 'pay_group' => $scope, 'pay_types' => $scopePayTypes]))) ?>"><?= h(accounts_scope_label($scope)) ?></a>
                    <?php endforeach; ?>
                </nav>
                <?php if ($payGroup === 'vendor' && $vendorAccounts): ?>
                    <div class="spacer"></div>
                    <div class="field">
                        <label>External Vendor</label>
                        <select disabled>
                            <?php foreach ($vendorAccounts as $vendor): ?>
                                <option><?= h((string) ($vendor['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </form>
        </section>

        <div class="spacer"></div>
        <section class="accounts-group-grid">
            <?php if ($payGroups): ?>
                <?php foreach ($payGroups as $group): ?>
                    <?php $groupPayload = [
                        'employee_id' => (int) ($group['employee_id'] ?? 0),
                        'employee_name' => (string) ($group['employee_name'] ?? ''),
                        'employee_emp_id' => (string) ($group['employee_emp_id'] ?? ''),
                        'items' => $group['items'] ?? [],
                    ]; ?>
                    <?php $isVendorReimbursementPay = $payGroup === 'vendor' && in_array('REIMBURSEMENT', $payTypes, true); ?>
                    <article class="section-block accounts-group-card" data-pay-card="<?= h((string) ($group['employee_id'] ?? 0)) ?>">
                        <div class="data-toolbar accounts-group-head">
                            <div class="accounts-group-copy">
                                <h2><?= h((string) ($group['employee_name'] ?? '')) ?></h2>
                                <p class="hint"><?= h((string) ($group['employee_emp_id'] ?? '')) ?><?= !empty($group['vendor_name']) ? ' • ' . h((string) $group['vendor_name']) : '' ?></p>
                            </div>
                            <button class="button solid" type="button" data-pay-open="<?= h(json_encode($groupPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">Pay</button>
                        </div>
                        <div class="table-wrap accounts-pay-table-wrap">
                            <table class="accounts-pay-table">
                                <thead>
                                    <tr>
                                        <?php if ($isVendorReimbursementPay): ?>
                                            <th>Name of Vendor Trainer</th>
                                            <th>Project</th>
                                            <th>Date</th>
                                            <th>Pay</th>
                                            <th class="accounts-pay-check">Select</th>
                                        <?php else: ?>
                                            <th class="accounts-pay-check">Select</th>
                                            <th>Type of Pay</th>
                                            <th>Particular</th>
                                            <th>Amount</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($group['items'] ?? []) as $index => $item): ?>
                                        <?php
                                            $itemMeta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
                                            $paymentType = strtoupper(trim((string) ($item['payment_type'] ?? '')));
                                            $paymentTypeLabel = $paymentType !== '' ? ucwords(strtolower(str_replace('_', ' ', $paymentType))) : 'Payment';
                                            $particular = trim((string) ($item['label'] ?? ''));
                                            if ($particular === '') {
                                                $particular = $paymentTypeLabel;
                                            }
                                            if ($paymentType === 'SALARY' && preg_match('/^\d{4}-\d{2}$/', $requestMonth)) {
                                                $particular = $paymentTypeLabel . ' - ' . date('F Y', strtotime($requestMonth . '-01'));
                                            }
                                            $vendorProject = trim((string) ($itemMeta['project_name'] ?? '')) ?: '-';
                                            $vendorDate = trim((string) ($itemMeta['expense_date'] ?? ''));
                                            $vendorDateLabel = $vendorDate !== '' ? date('d M Y', strtotime($vendorDate)) : '-';
                                        ?>
                                        <tr>
                                            <?php if ($isVendorReimbursementPay): ?>
                                                <td data-label="Name of Vendor Trainer"><?= h((string) ($group['employee_name'] ?? '')) ?></td>
                                                <td data-label="Project"><?= h($vendorProject) ?></td>
                                                <td data-label="Date"><?= h($vendorDateLabel) ?></td>
                                                <td data-label="Pay" class="accounts-pay-amount">Rs <?= h(number_format((float) ($item['actual_amount'] ?? 0), 2)) ?></td>
                                                <td class="accounts-pay-check" data-label="Select">
                                                    <input type="checkbox" class="accounts-pay-select" data-item-index="<?= (int) $index ?>" checked>
                                                </td>
                                            <?php else: ?>
                                                <td class="accounts-pay-check" data-label="Select">
                                                    <input type="checkbox" class="accounts-pay-select" data-item-index="<?= (int) $index ?>" checked>
                                                </td>
                                                <td data-label="Type of Pay"><?= h($paymentTypeLabel) ?></td>
                                                <td data-label="Particular"><?= h($particular) ?></td>
                                                <td data-label="Amount" class="accounts-pay-amount">Rs <?= h(number_format((float) ($item['actual_amount'] ?? 0), 2)) ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <section class="section-block"><p class="muted center">No payable items are available for the selected scope and filters.</p></section>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <div class="spacer"></div>
        <section class="section-block accounts-filter-shell">
            <form method="get" class="stack-form">
                <input type="hidden" name="page" value="admin_accounts">
                <input type="hidden" name="section" value="history">
                <input type="hidden" name="pay_group" value="<?= h($payGroup) ?>">
                <div class="accounts-history-filter-grid">
                    <div class="field">
                        <label>Account</label>
                        <select name="history_accounts">
                            <option value="">All accounts</option>
                            <?php foreach (payment_bank_names() as $account): ?>
                                <option value="<?= h($account) ?>" <?= (($filters['history_accounts'][0] ?? '') === $account) ? 'selected' : '' ?>><?= h($account) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Employee</label>
                        <select name="history_employee_ids">
                            <option value="">All employees</option>
                            <?php foreach ($allEmployees as $employee): ?>
                                <option value="<?= (int) ($employee['id'] ?? 0) ?>" <?= (((int) ($filters['history_employee_ids'][0] ?? 0)) === (int) ($employee['id'] ?? 0)) ? 'selected' : '' ?>><?= h((string) ($employee['name'] ?? '')) ?> (<?= h((string) ($employee['emp_id'] ?? '')) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>From Date</label>
                        <input type="date" name="from_date" value="<?= h((string) ($filters['from_date'] ?? '')) ?>">
                    </div>
                    <div class="field">
                        <label>To Date</label>
                        <input type="date" name="to_date" value="<?= h((string) ($filters['to_date'] ?? '')) ?>">
                    </div>
                    <div class="accounts-toolbar-actions">
                        <button class="button solid" type="submit">Apply Filters</button>
                        <a class="button outline" href="<?= h(BASE_URL) ?>?<?= h(http_build_query(array_merge($tabQueryBase, ['section' => 'history']))) ?>">Reset</a>
                    </div>
                </div>
                <div class="spacer"></div>
                <nav class="employee-tabs" aria-label="History scopes" data-history-scope-tabs>
                    <?php foreach ($accountScopes as $scope): ?>
                        <a class="tab-link <?= $payGroup === $scope ? 'active' : '' ?>" data-history-scope-link="<?= h($scope) ?>" href="<?= h(BASE_URL) ?>?<?= h(http_build_query(array_merge($tabQueryBase, [
                            'section' => 'history',
                            'pay_group' => $scope,
                            'history_accounts' => $filters['history_accounts'] ?? [],
                            'history_employee_ids' => $filters['history_employee_ids'] ?? [],
                            'history_vendor_ids' => $filters['history_vendor_ids'] ?? [],
                            'from_date' => $filters['from_date'] ?? '',
                            'to_date' => $filters['to_date'] ?? '',
                        ]))) ?>"><?= h(accounts_scope_label($scope)) ?></a>
                    <?php endforeach; ?>
                </nav>
                <?php if ($payGroup === 'vendor' && $vendorAccounts): ?>
                    <div class="spacer"></div>
                    <div class="field">
                        <label>External Vendor</label>
                        <select disabled>
                            <?php foreach ($vendorAccounts as $vendor): ?>
                                <option><?= h((string) ($vendor['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </form>
        </section>

        <div class="spacer"></div>
        <section class="table-wrap">
            <div class="data-toolbar">
                <div class="split">
                    <h2>Pay History</h2>
                    <span class="badge"><?= count($historyRows) ?> payment(s)</span>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date of Payment</th>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>Paid Amount</th>
                        <th>Particular</th>
                        <th>Account</th>
                        <th>Method</th>
                        <th>Proof of Payment</th>
                        <th>Challan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($historyRows): ?>
                        <?php foreach ($historyRows as $paymentRow):
                            $proofUrl = !empty($paymentRow['proof_path']) ? asset_url((string) $paymentRow['proof_path']) : '';
                            $methods = payment_methods_for_record($paymentRow);
                            $transferModeLabel = strtoupper(trim((string) ($paymentRow['transfer_mode'] ?? '')));
                            $methodLabel = $transferModeLabel !== ''
                                ? $transferModeLabel
                                : (in_array('CASH', $methods, true) ? 'Cash' : payment_methods_label($methods));
                            $payslipUrl = BASE_URL . '?' . http_build_query(array_merge([
                                'page' => 'admin_accounts',
                                'download_payslip_id' => (int) $paymentRow['id'],
                            ], $historyQuery));
                            $proofMime = (string) ($paymentRow['proof_mime'] ?? '');
                            ?>
                            <tr>
                                <td><?= h(date('d M Y', strtotime((string) ($paymentRow['payment_date'] ?? date('Y-m-d'))))) ?></td>
                                <td><?= h((string) ($paymentRow['employee_emp_id'] ?? '-')) ?></td>
                                <td><?= h((string) ($paymentRow['employee_name'] ?? '')) ?></td>
                                <td>Rs <?= h(number_format((float) ($paymentRow['amount'] ?? 0), 2)) ?></td>
                                <td>
                                    <?php foreach (payment_breakdown_summary_lines($paymentRow) as $line): ?>
                                        <div><?= h($line) ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= h((string) ($paymentRow['bank_name'] ?? '-')) ?></td>
                                <td><?= h($methodLabel) ?></td>
                                <td>
                                    <?php if ($proofUrl !== ''): ?>
                                        <?php if (str_starts_with($proofMime, 'image/')): ?>
                                            <img class="accounts-proof-thumb" src="<?= h($proofUrl) ?>" alt="Payment proof preview">
                                        <?php endif; ?>
                                        <div class="payment-action-row">
                                            <a class="button outline small" href="<?= h($proofUrl) ?>" target="_blank" rel="noopener">View</a>
                                            <a class="button outline small" href="<?= h($proofUrl) ?>" download>Download</a>
                                        </div>
                                    <?php else: ?>
                                        <span class="hint">No proof</span>
                                    <?php endif; ?>
                                </td>
                                <td><a class="button outline small" href="<?= h($payslipUrl) ?>">Download Payslip</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="muted center">No payments found for the selected filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <div class="modal" id="accounts-approval-modal">
        <div class="modal-card accounts-approval-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Approval</span>
            <h2>Step 1: Review Request</h2>
            <form method="post" class="stack-form" id="accounts-approval-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_approve_reimbursement" id="accounts-approval-action">
                <input type="hidden" name="reimbursement_id" id="accounts-approval-reimbursement-id" value="0">
                <input type="hidden" name="request_key" id="accounts-approval-request-key" value="">
                <input type="hidden" name="filter_section" value="approval">
                <input type="hidden" name="filter_approval_type" id="accounts-approval-type" value="<?= h($approvalType) ?>">
                <input type="hidden" name="filter_request_month" value="<?= h($requestMonth) ?>">
                <div class="accounts-approval-summary">
                    <div class="list-item"><strong>Total Amount:</strong> Rs <span id="accounts-approval-total">0.00</span></div>
                    <div class="list-item"><strong>Particular:</strong> <span id="accounts-approval-particular">-</span></div>
                    <div class="list-item"><strong>Details:</strong> <span id="accounts-approval-details">-</span></div>
                </div>
                <div id="accounts-approval-breakdown" class="accounts-breakdown-grid">
                    <div class="accounts-breakdown-card">
                        <strong>Proof</strong>
                        <div id="accounts-approval-proof-wrap" class="accounts-approval-proof-wrap">
                            <p class="hint">No proof uploaded.</p>
                        </div>
                    </div>
                    <div class="accounts-breakdown-card">
                        <strong>Edit Amount</strong>
                        <label style="margin-top:10px;">
                            <input type="number" name="approved_amount[REIMBURSEMENT]" id="accounts-approval-edit-amount" min="0" step="0.01" value="0.00" required>
                        </label>
                    </div>
                    <div class="accounts-breakdown-card">
                        <strong>Approved Amount</strong>
                        <div class="list-item"><strong>Final Amount:</strong> Rs <span id="accounts-approval-final-amount">0.00</span></div>
                    </div>
                </div>
                <button class="button solid" type="button" id="accounts-approval-next" onclick="window.goToAccountsApprovalConfirm(); return false;">Next</button>
            </form>
        </div>
    </div>

    <div class="modal" id="accounts-approval-confirm-modal">
        <div class="modal-card accounts-approval-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Confirmation</span>
            <h2>Step 2: Confirm Approval</h2>
            <div class="accounts-approval-summary">
                <div class="list-item"><strong>Employee Name:</strong> <span id="accounts-approval-confirm-employee">-</span></div>
                <div class="list-item"><strong>Approved Amount:</strong> Rs <span id="accounts-approval-confirm-amount">0.00</span></div>
            </div>
            <p id="accounts-approval-confirm-copy"></p>
            <div class="payment-action-row">
                <button class="button solid" type="button" id="accounts-approval-confirm-submit">Approve</button>
                <button class="button outline" type="button" data-close-modal>Cancel</button>
            </div>
        </div>
    </div>

    <div class="modal accounts-proof-viewer-modal" id="accounts-proof-viewer-modal">
        <div class="accounts-proof-viewer-card">
            <button class="modal-close accounts-proof-viewer-close" type="button" data-close-modal>&times;</button>
            <img id="accounts-proof-viewer-image" src="" alt="Reimbursement proof full view">
        </div>
    </div>

    <div class="modal" id="accounts-allocation-modal">
        <div class="modal-card accounts-payment-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Allocation</span>
            <h2>Payment Allocation</h2>
            <form class="stack-form" id="accounts-allocation-form">
                <div class="accounts-payment-grid">
                    <div class="field">
                        <label>Employee ID</label>
                        <input type="text" id="accounts-allocation-emp-id" readonly>
                    </div>
                    <div class="field">
                        <label>Employee Name</label>
                        <input type="text" id="accounts-allocation-emp-name" readonly>
                    </div>
                </div>
                <div id="accounts-allocation-rows" class="accounts-breakdown-grid"></div>
                <div class="accounts-total-bar">
                    <div class="list-item"><strong>Total Actual:</strong> Rs <span id="accounts-total-actual">0.00</span></div>
                    <div class="list-item"><strong>Total Payable:</strong> Rs <span id="accounts-total-payable">0.00</span></div>
                </div>
                <button class="button solid" type="button" id="accounts-allocation-next">Next</button>
            </form>
        </div>
    </div>

    <div class="modal" id="accounts-payroll-payment-modal">
        <div class="modal-card accounts-payment-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Payment Form</span>
            <h2>Process Payment</h2>
            <form method="post" enctype="multipart/form-data" class="stack-form" id="accounts-payroll-payment-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_process_accounts_payment">
                <input type="hidden" name="filter_section" value="pay">
                <input type="hidden" name="filter_request_month" value="<?= h($requestMonth) ?>">
                <input type="hidden" name="filter_pay_group" value="<?= h($payGroup) ?>">
                <?php foreach (($filters['pay_types'] ?? []) as $type): ?>
                    <input type="hidden" name="filter_pay_types[]" value="<?= h($type) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="employee_id" id="accounts-payroll-payment-employee-id" value="0">
                <div class="accounts-payment-grid">
                    <div class="field">
                        <label>Employee ID</label>
                        <input type="text" id="accounts-payroll-payment-employee-code" readonly>
                    </div>
                    <div class="field">
                        <label>Employee Name</label>
                        <input type="text" id="accounts-payroll-payment-employee-name" readonly>
                    </div>
                </div>
                <div id="accounts-payroll-payment-breakdown-hidden"></div>
                <div class="accounts-payment-grid">
                    <div class="field">
                        <label>Payment Type</label>
                        <input type="text" id="accounts-payroll-payment-type" readonly>
                    </div>
                    <div class="field accounts-conditional-field hidden" id="accounts-payroll-reimbursement-field">
                        <label>Select approved reimbursement request</label>
                        <select id="accounts-payroll-reimbursement-select">
                            <option value="">Select approved reimbursement request</option>
                        </select>
                    </div>
                    <div class="field accounts-conditional-field hidden" id="accounts-payroll-incentive-field">
                        <label>Calculated Incentive</label>
                        <input type="text" id="accounts-payroll-incentive-amount" readonly>
                    </div>
                    <div class="field">
                        <label>Amount</label>
                        <input type="number" name="amount" id="accounts-payroll-payment-amount" min="0.01" step="0.01" required readonly>
                    </div>
                    <div class="field">
                        <label>Bank Name</label>
                        <select name="payment_methods" id="accounts-payroll-bank-name" required>
                            <option value="" selected disabled>Select bank</option>
                            <?php foreach ($accountsPayrollBanks as $method): ?>
                                <option value="<?= h($method) ?>"><?= h($method) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" id="accounts-payroll-transfer-mode-field">
                        <label id="accounts-payroll-transfer-mode-label">Transfer Mode</label>
                        <select name="transfer_mode" id="accounts-payroll-transfer-mode">
                            <option value="" selected disabled>Select transfer mode</option>
                        </select>
                    </div>
                    <div class="field" id="accounts-payroll-transaction-id-field">
                        <label>Transaction ID</label>
                        <input type="text" name="transaction_id" id="accounts-payroll-transaction-id">
                    </div>
                    <div class="field">
                        <label>Payment Date</label>
                        <input type="date" name="payment_date" id="accounts-payroll-payment-date" value="<?= h(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="field">
                        <label>Proof Upload</label>
                        <input type="file" name="proof_upload" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
                    </div>
                </div>
                <div class="field">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="3" placeholder="Optional notes for this payment"></textarea>
                </div>
                <button class="button solid" type="submit">Pay</button>
            </form>
        </div>
    </div>

    <style>
        .accounts-tabs-panel { padding-bottom: 18px; }
        .accounts-tabs-panel .employee-tabs { display: flex; gap: 10px; flex-wrap: wrap; }
        .accounts-filter-shell { padding-bottom: 18px; }
        .accounts-toolbar-grid,
        .accounts-history-filter-grid { display: grid; gap: 16px; align-items: end; grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .accounts-history-filter-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
        .accounts-toolbar-actions { display: flex; gap: 10px; align-items: end; }
        .accounts-filter-shell .accounts-toolbar-grid { grid-template-columns: minmax(220px, 1fr) minmax(420px, 1.4fr) auto; }
        .accounts-filter-shell .accounts-toolbar-grid .field { min-width: 0; }
        .accounts-type-grid { display: flex; flex-wrap: wrap; gap: 10px; }
        .accounts-type-option { display: inline-flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px solid rgba(36, 52, 109, 0.12); border-radius: 16px; background: rgba(248, 250, 255, 0.9); white-space: nowrap; }
        .accounts-type-option input { width: auto; min-height: auto; margin: 0; }
        .accounts-group-grid { display: grid; gap: 18px; }
        .accounts-group-card { padding: 22px; display: flex; flex-direction: column; }
        .accounts-group-head { align-items: center; gap: 14px; margin-bottom: 10px; }
        .accounts-group-copy h2 { margin-bottom: 4px; }
        .accounts-pay-table-wrap { padding: 0; border-radius: 18px; box-shadow: none; overflow-x: auto; }
        .accounts-pay-table { min-width: 680px; width: 100%; }
        .accounts-pay-table th,
        .accounts-pay-table td { vertical-align: middle; }
        .accounts-pay-table th { white-space: nowrap; }
        .accounts-pay-table tbody tr:hover { background: rgba(79, 70, 229, 0.04); }
        .accounts-pay-amount { white-space: nowrap; font-weight: 700; color: #23346d; }
        .accounts-pay-check { text-align: center; width: 88px; }
        .accounts-pay-check input { width: 18px; height: 18px; }
        .accounts-payment-card,
        .accounts-approval-card { width: min(920px, 100%); }
        .accounts-payment-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .accounts-method-field { grid-column: 1 / -1; }
        .payment-method-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
        .payment-method-option { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px solid rgba(36, 52, 109, 0.12); border-radius: 16px; background: rgba(248, 250, 255, 0.9); }
        .payment-method-option input { width: auto; min-height: auto; margin: 0; }
        .payment-action-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .payment-action-row form { margin: 0; }
        .accounts-breakdown-grid { display: grid; gap: 14px; }
        .accounts-breakdown-card { border: 1px solid rgba(36, 52, 109, 0.1); border-radius: 16px; padding: 14px; background: rgba(248, 250, 255, 0.72); }
        .accounts-breakdown-card embed,
        .accounts-breakdown-card img { width: 100%; max-height: 180px; object-fit: cover; border-radius: 12px; margin-top: 10px; }
        .accounts-approval-proof-wrap img { max-height: 320px; object-fit: contain; background: #fff; cursor: zoom-in; }
        .accounts-approval-proof-wrap embed { min-height: 420px; }
        .accounts-approval-summary { display: grid; gap: 12px; }
        .accounts-total-bar { display: grid; gap: 14px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .accounts-proof-thumb { width: 54px; height: 54px; object-fit: cover; border-radius: 10px; display: block; margin-bottom: 8px; }
        .accounts-proof-viewer-modal { z-index: 220; background: rgba(8, 13, 31, 0.84); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
        .accounts-proof-viewer-card { width: min(1180px, calc(100vw - 32px)); height: min(92vh, 920px); position: relative; display: flex; align-items: center; justify-content: center; }
        .accounts-proof-viewer-card img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 14px; background: #fff; box-shadow: 0 24px 80px rgba(0, 0, 0, 0.42); }
        .accounts-proof-viewer-close { right: 0; top: 0; background: rgba(255, 255, 255, 0.92); color: #172554; z-index: 2; }
        @media (max-width: 1100px) {
            .accounts-filter-shell .accounts-toolbar-grid { grid-template-columns: 1fr 1fr; }
            .accounts-history-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .payment-method-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 760px) {
            .accounts-toolbar-grid,
            .accounts-history-filter-grid,
            .accounts-payment-grid,
            .accounts-total-bar,
            .payment-method-grid { grid-template-columns: 1fr; }
            .accounts-filter-shell .accounts-toolbar-grid { grid-template-columns: 1fr; }
            .payment-action-row { flex-direction: column; align-items: stretch; }
            .accounts-pay-table { min-width: 560px; }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const transferModeMap = <?= json_encode($transferModesMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const approvalForm = document.getElementById('accounts-approval-form');
            const approvalBreakdown = document.getElementById('accounts-approval-breakdown');
            const approvalTotal = document.getElementById('accounts-approval-total');
            const approvalParticular = document.getElementById('accounts-approval-particular');
            const approvalDetails = document.getElementById('accounts-approval-details');
            const approvalProofWrap = document.getElementById('accounts-approval-proof-wrap');
            const approvalAmountInput = document.getElementById('accounts-approval-edit-amount');
            const approvalFinalAmount = document.getElementById('accounts-approval-final-amount');
            const approvalActionInput = document.getElementById('accounts-approval-action');
            const approvalRequestKeyInput = document.getElementById('accounts-approval-request-key');
            const approvalTypeInput = document.getElementById('accounts-approval-type');
            const approvalId = document.getElementById('accounts-approval-reimbursement-id');
            const approvalNext = document.getElementById('accounts-approval-next');
            const approvalConfirmCopy = document.getElementById('accounts-approval-confirm-copy');
            const approvalConfirmEmployee = document.getElementById('accounts-approval-confirm-employee');
            const approvalConfirmAmount = document.getElementById('accounts-approval-confirm-amount');
            const approvalConfirmSubmit = document.getElementById('accounts-approval-confirm-submit');
            const proofViewerModal = document.getElementById('accounts-proof-viewer-modal');
            const proofViewerImage = document.getElementById('accounts-proof-viewer-image');
            const allocationForm = document.getElementById('accounts-allocation-form');
            const allocationRows = document.getElementById('accounts-allocation-rows');
            const totalActual = document.getElementById('accounts-total-actual');
            const totalPayable = document.getElementById('accounts-total-payable');
            const paymentForm = document.getElementById('accounts-payroll-payment-form');
            const paymentEmployeeId = document.getElementById('accounts-payroll-payment-employee-id');
            const paymentEmployeeCode = document.getElementById('accounts-payroll-payment-employee-code');
            const paymentEmployeeName = document.getElementById('accounts-payroll-payment-employee-name');
            const paymentBreakdownHidden = document.getElementById('accounts-payroll-payment-breakdown-hidden');
            const paymentTypeDisplay = document.getElementById('accounts-payroll-payment-type');
            const paymentAmountInput = document.getElementById('accounts-payroll-payment-amount');
            const paymentBankSelect = document.getElementById('accounts-payroll-bank-name');
            const payrollReimbursementField = document.getElementById('accounts-payroll-reimbursement-field');
            const payrollReimbursementSelect = document.getElementById('accounts-payroll-reimbursement-select');
            const payrollIncentiveField = document.getElementById('accounts-payroll-incentive-field');
            const payrollIncentiveAmount = document.getElementById('accounts-payroll-incentive-amount');
            const transferModeField = document.getElementById('accounts-payroll-transfer-mode-field');
            const transferModeLabel = document.getElementById('accounts-payroll-transfer-mode-label');
            const transferModeSelect = document.getElementById('accounts-payroll-transfer-mode');
            const transactionField = document.getElementById('accounts-payroll-transaction-id-field');
            const transactionInput = document.getElementById('accounts-payroll-transaction-id');
            const payFilterForm = document.querySelector('form.stack-form input[name="section"][value="pay"]')?.closest('form');
            const historyFilterForm = document.querySelector('form.stack-form input[name="section"][value="history"]')?.closest('form');

            const openModal = id => {
                const target = document.getElementById(id);
                if (target) {
                    target.classList.add('open');
                }
            };

            const closeModal = id => {
                const target = document.getElementById(id);
                if (target) {
                    target.classList.remove('open');
                }
            };

            document.querySelectorAll('[data-close-modal]').forEach(button => {
                button.addEventListener('click', () => {
                    const modal = button.closest('.modal');
                    if (modal) {
                        modal.classList.remove('open');
                    }
                });
            });

            if (proofViewerModal) {
                proofViewerModal.addEventListener('click', event => {
                    if (event.target === proofViewerModal) {
                        proofViewerModal.classList.remove('open');
                    }
                });
            }

            const openProofViewer = imageUrl => {
                if (!proofViewerModal || !proofViewerImage || imageUrl === '') {
                    return;
                }
                proofViewerImage.src = imageUrl;
                proofViewerModal.classList.add('open');
            };

            const updateTransferModes = preferredValue => {
                if (!transferModeSelect || !transferModeField || !transactionField || !transactionInput) {
                    return;
                }
                const selectedBank = String(paymentBankSelect ? (paymentBankSelect.value || '') : '');
                const methods = selectedBank !== '' ? [selectedBank] : [];
                const availableModes = [];
                methods.forEach(method => {
                    (transferModeMap[method] || []).forEach(mode => {
                        if (!availableModes.includes(mode)) {
                            availableModes.push(mode);
                        }
                    });
                });
                transferModeSelect.innerHTML = '';
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = availableModes.length ? 'Select transfer mode' : 'Not required';
                placeholder.selected = true;
                placeholder.disabled = availableModes.length > 0;
                transferModeSelect.appendChild(placeholder);

                availableModes.forEach(mode => {
                    const option = document.createElement('option');
                    option.value = mode;
                    option.textContent = mode;
                    option.selected = String(preferredValue || '') === mode;
                    transferModeSelect.appendChild(option);
                });

                if (transferModeLabel) {
                    transferModeLabel.textContent = selectedBank === 'IOB' ? 'UPI Option' : 'Transfer Mode';
                }
                transferModeField.classList.toggle('hidden', selectedBank === 'CASH' || availableModes.length === 0);
                transferModeSelect.required = availableModes.length > 0;
                transferModeSelect.disabled = selectedBank === 'CASH' || availableModes.length === 0;
                const requiresTransaction = methods.some(method => method !== 'CASH');
                transactionField.classList.toggle('hidden', !requiresTransaction);
                transactionInput.required = requiresTransaction;
                transactionInput.disabled = !requiresTransaction;
                if (!requiresTransaction) {
                    transactionInput.value = '';
                }
            };

            if (paymentBankSelect) {
                paymentBankSelect.addEventListener('change', () => updateTransferModes(''));
            }
            updateTransferModes('');

            window.openAccountsApproval = (event, trigger) => {
                if (event) {
                    if (typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }
                    if (typeof event.stopPropagation === 'function') {
                        event.stopPropagation();
                    }
                }
                if (!trigger || !approvalForm) {
                    return false;
                }
                const requestedAmount = Number(trigger.dataset.amountRequested || 0);
                const proofUrl = String(trigger.dataset.proofUrl || '');
                const proofMime = String(trigger.dataset.proofMime || '');
                const approvalMode = String(trigger.dataset.approvalMode || 'reimbursement');
                const approvalCategory = String(trigger.dataset.particular || '').toUpperCase();
                approvalActionInput.value = approvalMode === 'request' ? 'admin_approve_payment_request' : 'admin_approve_reimbursement';
                approvalRequestKeyInput.value = approvalMode === 'request' ? String(trigger.dataset.requestKey || '') : '';
                approvalId.value = approvalMode === 'request' ? '0' : String(trigger.dataset.reimbursementId || 0);
                approvalTypeInput.value = String('<?= h($approvalType) ?>');
                approvalAmountInput.name = approvalMode === 'request'
                    ? 'approved_amount[REIMBURSEMENT]'
                    : `approved_amount[${approvalCategory || 'REIMBURSEMENT'}]`;
                approvalTotal.textContent = requestedAmount.toFixed(2);
                approvalParticular.textContent = trigger.dataset.particular || '-';
                approvalDetails.textContent = trigger.dataset.details || '-';
                approvalForm.dataset.employeeName = String(trigger.dataset.employeeName || '');
                approvalAmountInput.value = requestedAmount.toFixed(2);
                approvalAmountInput.max = requestedAmount.toFixed(2);
                approvalAmountInput.required = true;
                approvalAmountInput.readOnly = false;
                approvalFinalAmount.textContent = requestedAmount.toFixed(2);

                if (proofUrl !== '') {
                    approvalProofWrap.innerHTML = proofMime.indexOf('application/pdf') === 0
                        ? `<embed src="${proofUrl}" type="application/pdf">`
                        : `<img src="${proofUrl}" alt="Reimbursement proof" title="Open full proof" data-proof-full>`;
                    const proofImage = approvalProofWrap.querySelector('[data-proof-full]');
                    if (proofImage) {
                        proofImage.addEventListener('click', () => openProofViewer(proofUrl));
                    }
                } else {
                    approvalProofWrap.innerHTML = '<p class="hint">No proof uploaded.</p>';
                }
                openModal('accounts-approval-modal');
                return false;
            };

            if (approvalAmountInput && approvalFinalAmount) {
                approvalAmountInput.addEventListener('input', () => {
                    const value = Number(approvalAmountInput.value || 0);
                    approvalFinalAmount.textContent = value.toFixed(2);
                });
            }

            window.goToAccountsApprovalConfirm = () => {
                if (!approvalForm) {
                    return;
                }
                const value = Number(approvalAmountInput.value || 0);
                const max = Number(approvalAmountInput.max || 0);
                if (value < 0 || value > max) {
                    approvalAmountInput.focus();
                    return;
                }
                approvalConfirmEmployee.textContent = approvalForm.dataset.employeeName || '-';
                approvalConfirmAmount.textContent = value.toFixed(2);
                approvalConfirmCopy.textContent = approvalActionInput.value === 'admin_approve_payment_request'
                    ? 'Click Approve to complete this approval request.'
                    : 'Click Approve to complete this reimbursement approval.';
                closeModal('accounts-approval-modal');
                openModal('accounts-approval-confirm-modal');
            };

            if (approvalConfirmSubmit && approvalForm) {
                approvalConfirmSubmit.addEventListener('click', () => {
                    if (typeof approvalForm.requestSubmit === 'function') {
                        approvalForm.requestSubmit();
                    } else {
                        approvalForm.submit();
                    }
                });
            }

            const refreshAllocationTotals = () => {
                const inputs = Array.from(allocationRows.querySelectorAll('[data-payable-input]'));
                totalActual.textContent = inputs.reduce((sum, input) => sum + Number(input.dataset.actual || 0), 0).toFixed(2);
                totalPayable.textContent = inputs.reduce((sum, input) => sum + Number(input.value || 0), 0).toFixed(2);
            };

            document.querySelectorAll('[data-pay-open]').forEach(button => {
                button.addEventListener('click', () => {
                    const payload = JSON.parse(button.dataset.payOpen || '{}');
                    const card = button.closest('[data-pay-card]');
                    const checkedIndexes = Array.from(card.querySelectorAll('.accounts-pay-select:checked')).map(input => Number(input.dataset.itemIndex || -1));
                    const items = (payload.items || []).filter((_, index) => checkedIndexes.includes(index));
                    if (items.length === 0) {
                        return;
                    }

                    document.getElementById('accounts-allocation-emp-id').value = payload.employee_emp_id || '';
                    document.getElementById('accounts-allocation-emp-name').value = payload.employee_name || '';
                    allocationRows.innerHTML = '';
                    allocationForm.dataset.employeeId = String(payload.employee_id || 0);
                    allocationForm.dataset.employeeCode = String(payload.employee_emp_id || '');
                    allocationForm.dataset.employeeName = String(payload.employee_name || '');

                    items.forEach(item => {
                        const actual = Number(item.actual_amount || 0);
                        const wrapper = document.createElement('div');
                        wrapper.className = 'accounts-breakdown-card';
                        wrapper.innerHTML = `
                            <input type="hidden" data-item-type value="${item.payment_type}">
                            <input type="hidden" data-item-reference value="${Number(item.reference_id || 0)}">
                            <strong>${item.label || item.payment_type}</strong>
                            <p class="hint">Actual: Rs ${actual.toFixed(2)}</p>
                            <label>Payable Amount
                                <input type="number" data-payable-input data-actual="${actual.toFixed(2)}" min="0.01" max="${actual.toFixed(2)}" step="0.01" value="${actual.toFixed(2)}" required>
                            </label>
                        `;
                        allocationRows.appendChild(wrapper);
                    });

                    allocationRows.querySelectorAll('[data-payable-input]').forEach(input => input.addEventListener('input', refreshAllocationTotals));
                    refreshAllocationTotals();
                    openModal('accounts-allocation-modal');
                });
            });

            const allocationNext = document.getElementById('accounts-allocation-next');
            if (allocationNext) {
                allocationNext.addEventListener('click', () => {
                    const rows = [];
                    for (const card of allocationRows.querySelectorAll('.accounts-breakdown-card')) {
                        const input = card.querySelector('[data-payable-input]');
                        const actual = Number(input.dataset.actual || 0);
                        const payable = Number(input.value || 0);
                        if (payable <= 0 || payable > actual) {
                            input.focus();
                            return;
                        }
                        rows.push({
                            type: card.querySelector('[data-item-type]').value,
                            referenceId: Number(card.querySelector('[data-item-reference]').value || 0),
                            actual,
                            payable,
                        });
                    }

                    if (!paymentEmployeeId || !paymentEmployeeCode || !paymentEmployeeName || !paymentBreakdownHidden) {
                        return;
                    }
                    paymentEmployeeId.value = allocationForm.dataset.employeeId || '0';
                    paymentEmployeeCode.value = allocationForm.dataset.employeeCode || '';
                    paymentEmployeeName.value = allocationForm.dataset.employeeName || '';
                    if (paymentBankSelect) {
                        paymentBankSelect.value = '';
                    }
                    if (paymentAmountInput) {
                        paymentAmountInput.value = rows.reduce((sum, row) => sum + row.payable, 0).toFixed(2);
                    }
                    if (transactionInput) {
                        transactionInput.value = '';
                    }
                    if (transferModeSelect) {
                        transferModeSelect.value = '';
                    }
                    const uniqueTypes = [...new Set(rows.map(row => String(row.type || '')))].filter(Boolean);
                    const paymentTypeValue = uniqueTypes.length === 1 ? uniqueTypes[0] : 'OTHER';
                    if (paymentTypeDisplay) {
                        paymentTypeDisplay.value = paymentTypeValue;
                    }
                    if (payrollReimbursementField && payrollReimbursementSelect) {
                        const reimbursementRows = rows.filter(row => row.type === 'REIMBURSEMENT' && row.referenceId > 0);
                        payrollReimbursementField.classList.toggle('hidden', paymentTypeValue !== 'REIMBURSEMENT');
                        payrollReimbursementSelect.innerHTML = '<option value="">Select approved reimbursement request</option>';
                        reimbursementRows.forEach((row, index) => {
                            const option = document.createElement('option');
                            option.value = String(row.referenceId);
                            option.textContent = `Reimbursement #${row.referenceId} - Rs ${row.payable.toFixed(2)}`;
                            option.selected = index === 0;
                            payrollReimbursementSelect.appendChild(option);
                        });
                    }
                    if (payrollIncentiveField && payrollIncentiveAmount) {
                        const incentiveTotal = rows
                            .filter(row => row.type === 'INCENTIVE')
                            .reduce((sum, row) => sum + row.payable, 0);
                        payrollIncentiveField.classList.toggle('hidden', paymentTypeValue !== 'INCENTIVE');
                        payrollIncentiveAmount.value = paymentTypeValue === 'INCENTIVE' ? incentiveTotal.toFixed(2) : '';
                    }
                    paymentBreakdownHidden.innerHTML = '';

                    rows.forEach(row => {
                        paymentBreakdownHidden.insertAdjacentHTML('beforeend', `
                            <input type="hidden" name="breakdown_type[]" value="${row.type}">
                            <input type="hidden" name="breakdown_actual_amount[]" value="${row.actual.toFixed(2)}">
                            <input type="hidden" name="breakdown_paid_amount[]" value="${row.payable.toFixed(2)}">
                            <input type="hidden" name="breakdown_remaining_amount[]" value="${Math.max(row.actual - row.payable, 0).toFixed(2)}">
                            <input type="hidden" name="breakdown_reference_id[]" value="${row.referenceId}">
                        `);
                    });

                    closeModal('accounts-allocation-modal');
                    updateTransferModes('');
                    openModal('accounts-payroll-payment-modal');
                });
            }

            document.querySelectorAll('[data-pay-scope-link]').forEach(link => {
                link.addEventListener('click', event => {
                    if (!payFilterForm) {
                        return;
                    }
                    event.preventDefault();
                    const url = new URL(String(link.href), window.location.href);
                    const formData = new FormData(payFilterForm);
                    url.search = '';
                    formData.forEach((value, key) => {
                        if (String(value) !== '') {
                            url.searchParams.append(key, String(value));
                        }
                    });
                    const payScope = String(link.dataset.payScopeLink || 'employee');
                    url.searchParams.delete('pay_types[]');
                    url.searchParams.delete('pay_types');
                    url.searchParams.set('pay_group', payScope);
                    url.searchParams.append('pay_types[]', payScope === 'freelancer' ? 'SALARY' : 'REIMBURSEMENT');
                    window.location.href = url.toString();
                });
            });

            document.querySelectorAll('[data-history-scope-link]').forEach(link => {
                link.addEventListener('click', event => {
                    if (!historyFilterForm) {
                        return;
                    }
                    event.preventDefault();
                    const url = new URL(String(link.href), window.location.href);
                    const formData = new FormData(historyFilterForm);
                    url.search = '';
                    formData.forEach((value, key) => {
                        if (String(value) !== '') {
                            url.searchParams.append(key, String(value));
                        }
                    });
                    url.searchParams.set('pay_group', String(link.dataset.historyScopeLink || 'employee'));
                    window.location.href = url.toString();
                });
            });
        });
    </script>
    <?php
    render_footer();
}

function render_admin_vendors(): void
{
    require_role('admin');
    $vendors = db()->query("SELECT * FROM users WHERE role = 'external_vendor' ORDER BY name")->fetchAll();
    
    render_header('Vendor Registrations', 'admin-vendors-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Vendors</span>
            <h1>Vendor Registrations</h1>
            <p>Create vendor accounts here and manage the list of external vendors added by admins.</p>
        </div>
    </section>
    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Create Vendor</span>
                <h2>Add Vendor Account</h2>
            </div>
        </div>
        <form method="post" class="stack-form" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_create_vendor">
            <div class="field">
                <label>Name of the Company</label>
                <div class="field-row"><input type="text" name="name" placeholder="Company name" required></div>
                <small class="field-error"><span>!</span>Company name is required.</small>
            </div>
            <div class="field">
                <label>Company Mail ID</label>
                <div class="field-row"><input type="email" name="email" placeholder="vendor@company.com" required></div>
                <small class="field-error"><span>!</span>Enter a valid company mail ID.</small>
            </div>
            <div class="field">
                <label>Company Phone Number</label>
                <div class="field-row"><input type="text" name="phone" placeholder="Phone number" required></div>
                <small class="field-error"><span>!</span>Company phone number is required.</small>
            </div>
            <p class="hint">The vendor password will be created the same way as employee passwords and sent to the vendor email automatically.</p>
            <button class="button solid" type="submit">Create Vendor Account</button>
        </form>
    </section>
    <div class="spacer"></div>
    <section class="table-wrap">
        <?php render_vendor_accounts_table($vendors, 'admin-vendors-table', 'admin-vendors-empty', true, 'admin_vendors'); ?>
    </section>
    <?php
    render_footer();
}
