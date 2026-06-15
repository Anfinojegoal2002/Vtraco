<?php

declare(strict_types=1);

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


