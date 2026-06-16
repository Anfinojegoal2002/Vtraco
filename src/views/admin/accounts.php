<?php

declare(strict_types=1);

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
                                <p class="hint"><?= h((string) ($group['employee_emp_id'] ?? '')) ?><?= !empty($group['vendor_name']) ? ' â€¢ ' . h((string) $group['vendor_name']) : '' ?></p>
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
        .accounts-approval-card { max-height: calc(100dvh - 40px); overflow: hidden; display: flex; flex-direction: column; }
        .accounts-approval-card > .modal-close { z-index: 5; }
        .accounts-approval-card > .stack-form { min-height: 0; overflow: auto; padding-right: 4px; }
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


