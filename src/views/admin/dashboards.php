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


