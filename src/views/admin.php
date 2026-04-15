<?php

declare(strict_types=1);

function render_admin_dashboard(): void
{
    require_roles(['admin', 'freelancer']);
    $leaveRequests = recent_leave_requests();
    $snapshot = attendance_snapshot_for_date();
    $counts = $snapshot['counts'];
    $details = $snapshot['details'];
    $displayDate = date('d M Y', strtotime($snapshot['date']));

    $user = current_user();
    $isFreelancer = ($user['role'] ?? '') === 'freelancer';
    $label = $isFreelancer ? 'Staff' : 'Employees';
    $recentReimbursements = [];
    if (!$isFreelancer) {
        $recentReimbursements = admin_recent_reimbursements(5);
    }

    render_header($isFreelancer ? 'Corporate Dashboard' : 'Admin Dashboard');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow"><?= h($isFreelancer ? 'Corporate Dashboard' : 'Admin Dashboard') ?></span>
            <h1><?= h($isFreelancer ? 'Corporate Overview' : 'Admin Overview') ?></h1>
            <p>Review today&apos;s attendance totals, <?= h(strtolower($label)) ?> coverage, and half day or leave details for <?= h($displayDate) ?>.</p>
        </div>
    </section>
    <section class="dashboard-grid">
        <div class="metric-card">
            <span class="eyebrow">Team</span>
            <strong><?= employee_count() ?></strong>
            <span>Total <?= h($label) ?></span>
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
        <div class="metric-card">
            <span class="eyebrow">Today</span>
            <strong><?= (int) ($counts['Half Day'] ?? 0) ?></strong>
            <span>Half Day</span>
        </div>
        <div class="metric-card">
            <span class="eyebrow">Today</span>
            <strong><?= (int) ($counts['Leave'] ?? 0) ?></strong>
            <span>Leave</span>
        </div>
    </section>
    <div class="spacer"></div>
    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Request Details</span>
                <h2>Half Day &amp; Leave Details</h2>
            </div>
            <span class="badge"><?= h($displayDate) ?></span>
        </div>
        <div class="spacer"></div>
        <?php if ($details): ?>
            <div class="list">
                <?php foreach ($details as $detail): $statusClass = str_replace(' ', '-', (string) $detail['status']); ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h($detail['employee']['name']) ?></strong><br>
                                <span class="hint"><?= h((string) $detail['employee']['emp_id']) ?> | <?= h((string) $detail['employee']['email']) ?></span>
                            </div>
                            <span class="status-pill status-<?= h($statusClass) ?>"><?= h((string) $detail['status']) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <div class="hint"><?= h((string) $detail['detail']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No half day or leave details recorded for <?= h($displayDate) ?>.</div>
        <?php endif; ?>
    </section>

    <div class="spacer"></div>
    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Admin Overview</span>
                <h2>Leave Requests</h2>
            </div>
            <span class="badge"><?= count($leaveRequests) ?> recent</span>
        </div>
        <div class="spacer"></div>
        <?php if ($leaveRequests): ?>
            <div class="list">
                <?php foreach ($leaveRequests as $request): ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h((string) $request['name']) ?></strong><br>
                                <span class="hint"><?= h((string) $request['emp_id']) ?> | <?= h((string) $request['email']) ?></span>
                            </div>
                            <span class="badge"><?= h(date('d M Y', strtotime((string) $request['attend_date']))) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <div class="hint"><?= h(trim((string) $request['leave_reason']) ?: 'No leave reason provided.') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No leave requests have been submitted yet.</div>
        <?php endif; ?>
    </section>

    <?php if (!$isFreelancer): ?>
        <div class="spacer"></div>
        <section class="section-block">
            <div class="split">
                <div>
                    <span class="eyebrow">Accounts</span>
                    <h2>Reimbursement Requests</h2>
                </div>
                <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_reimbursements">View All</a>
            </div>
            <div class="spacer"></div>
            <?php if ($recentReimbursements): ?>
                <div class="list">
                    <?php foreach ($recentReimbursements as $item): ?>
                        <div class="list-item">
                            <div class="split">
                                <div>
                                    <strong><?= h((string) ($item['employee_name'] ?? '')) ?></strong><br>
                                    <span class="hint"><?= h((string) ($item['employee_emp_id'] ?? '')) ?> | <?= h(date('d M Y', strtotime((string) ($item['expense_date'] ?? 'now')))) ?></span>
                                </div>
                                <span class="status-pill reimbursement-status <?= h(reimbursement_status_badge_class((string) ($item['status'] ?? 'PENDING'))) ?>"><?= h((string) ($item['status'] ?? 'PENDING')) ?></span>
                            </div>
                            <div class="spacer"></div>
                            <div class="hint"><?= h((string) ($item['category'] ?? '')) ?> | Requested Rs <?= h(number_format((float) ($item['amount_requested'] ?? 0), 2)) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="list-item muted">No reimbursement requests found yet.</div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php
    render_footer();
}

function render_corporate_dashboard(): void
{
    $freelancer = require_role('freelancer');

    $leaveRequests = recent_leave_requests();
    $snapshot = attendance_snapshot_for_date();
    $counts = $snapshot['counts'];
    $details = $snapshot['details'];
    $displayDate = date('d M Y', strtotime($snapshot['date']));

    render_header('Corporate Dashboard', 'corporate-dashboard-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Corporate Dashboard</span>
            <h1>Corporate Dashboard</h1>
            <p>Track attendance for <?= h($displayDate) ?>.</p>
        </div>
    </section>

    <section class="dashboard-grid">
        <div class="metric-card">
            <span class="eyebrow">Team</span>
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

    <div class="spacer"></div>

    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Request Details</span>
                <h2>Half Day &amp; Leave Details</h2>
            </div>
            <span class="badge"><?= h($displayDate) ?></span>
        </div>
        <div class="spacer"></div>
        <?php if ($details): ?>
            <div class="list">
                <?php foreach ($details as $detail): $statusClass = str_replace(' ', '-', (string) $detail['status']); ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h($detail['employee']['name']) ?></strong><br>
                                <span class="hint"><?= h((string) $detail['employee']['emp_id']) ?> | <?= h((string) $detail['employee']['email']) ?></span>
                            </div>
                            <span class="status-pill status-<?= h($statusClass) ?>"><?= h((string) $detail['status']) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <div class="hint"><?= h((string) $detail['detail']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No half day or leave details recorded for <?= h($displayDate) ?>.</div>
        <?php endif; ?>
    </section>

    <div class="spacer"></div>

    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Attendance</span>
                <h2>Leave Requests</h2>
            </div>
            <span class="badge"><?= count($leaveRequests) ?> recent</span>
        </div>
        <div class="spacer"></div>
        <?php if ($leaveRequests): ?>
            <div class="list">
                <?php foreach ($leaveRequests as $request): ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h((string) $request['name']) ?></strong><br>
                                <span class="hint"><?= h((string) $request['emp_id']) ?> | <?= h((string) $request['email']) ?></span>
                            </div>
                            <span class="badge"><?= h(date('d M Y', strtotime((string) $request['attend_date']))) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <div class="hint"><?= h(trim((string) $request['leave_reason']) ?: 'No leave reason provided.') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No leave requests have been submitted yet.</div>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}

function render_vendor_dashboard(): void
{
    $vendor = require_role('external_vendor');

    $leaveRequests = recent_leave_requests();
    $snapshot = attendance_snapshot_for_date();
    $counts = $snapshot['counts'];
    $details = $snapshot['details'];
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
            <span class="eyebrow">Team</span>
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

    <div class="spacer"></div>

    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Request Details</span>
                <h2>Half Day &amp; Leave Details</h2>
            </div>
            <span class="badge"><?= h($displayDate) ?></span>
        </div>
        <div class="spacer"></div>
        <?php if ($details): ?>
            <div class="list">
                <?php foreach ($details as $detail): $statusClass = str_replace(' ', '-', (string) $detail['status']); ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h($detail['employee']['name']) ?></strong><br>
                                <span class="hint"><?= h((string) $detail['employee']['emp_id']) ?> | <?= h((string) $detail['employee']['email']) ?></span>
                            </div>
                            <span class="status-pill status-<?= h($statusClass) ?>"><?= h((string) $detail['status']) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <div class="hint"><?= h((string) $detail['detail']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No half day or leave details recorded for <?= h($displayDate) ?>.</div>
        <?php endif; ?>
    </section>

    <div class="spacer"></div>

    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Attendance</span>
                <h2>Leave Requests</h2>
            </div>
            <span class="badge"><?= count($leaveRequests) ?> recent</span>
        </div>
        <div class="spacer"></div>
        <?php if ($leaveRequests): ?>
            <div class="list">
                <?php foreach ($leaveRequests as $request): ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h((string) $request['name']) ?></strong><br>
                                <span class="hint"><?= h((string) $request['emp_id']) ?> | <?= h((string) $request['email']) ?></span>
                            </div>
                            <span class="badge"><?= h(date('d M Y', strtotime((string) $request['attend_date']))) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <div class="hint"><?= h(trim((string) $request['leave_reason']) ?: 'No leave reason provided.') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No leave requests have been submitted yet.</div>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}

function render_rules_editor(array $existing = [], ?string $submitLabel = null): void
{
    $defaults = array_merge([
        'manual_punch_in' => false,
        'manual_punch_out' => false,
        'manual_out_count' => 1,
        'biometric_punch_in' => false,
        'biometric_punch_out' => false,
    ], $existing);
    ?>
    <div class="rules-box">
        <div class="rules-grid">
            <label class="rule-card <?= ($defaults['manual_punch_in'] || $defaults['manual_punch_out']) ? 'active' : '' ?>">
                <input type="checkbox" name="manual_punch" value="1" <?= ($defaults['manual_punch_in'] || $defaults['manual_punch_out']) ? 'checked' : '' ?>>
                <span class="rule-icon">MP</span>
                <strong>Manual Punch</strong>
                <span class="hint">Allow employees to submit punch-in photos and complete punch-out details.</span>
            </label>
            <label class="rule-card <?= ($defaults['biometric_punch_in'] || $defaults['biometric_punch_out']) ? 'active' : '' ?>">
                <input type="checkbox" name="biometric_punch" value="1" <?= ($defaults['biometric_punch_in'] || $defaults['biometric_punch_out']) ? 'checked' : '' ?>>
                <span class="rule-icon">BP</span>
                <strong>Biometric Punch</strong>
                <span class="hint">Enable biometric punch-in and punch-out time capture.</span>
            </label>
        </div>
        <div class="split align-end admin-rules-footer">
            <label>Manual punch slots<input id="manual-out-count" type="number" min="1" name="manual_out_count" value="<?= h((string) $defaults['manual_out_count']) ?>"></label>
            <div class="inline-actions">
                <button class="button outline small" type="button" data-add-manual-slot data-target="#manual-out-count">+ Add Manual Punch</button>
                <?php if ($submitLabel !== null): ?>
                    <button class="button solid" type="submit" data-rule-submit><?= h($submitLabel) ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

function render_admin_employees(): void
{
    require_roles(['admin', 'freelancer', 'external_vendor']);
    $editEmployee = null;
    $stage = $_GET['stage'] ?? null;
    $pendingEmployee = $_SESSION['pending_employee'] ?? null;
    $pendingCsv = $_SESSION['pending_csv_import'] ?? null;

    if (isset($_GET['edit'])) {
        $editId = (int) $_GET['edit'];
        $editEmployee = employee_by_id($editId);
    }

    $allEmployees = employees();
    $leaveRequests = recent_leave_requests();

    $user = current_user();
    $isFreelancer = ($user['role'] ?? '') === 'freelancer';
    $isVendor = ($user['role'] ?? '') === 'external_vendor';
    $label = $isFreelancer ? 'Corporate Employee' : ($isVendor ? 'Vendor Employees' : 'Employees');
    $singularLabel = 'Employee';

    render_header($label);
    
    $employeeType = $_GET['type'] ?? 'regular';
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow"><?= ($isFreelancer || $isVendor) ? h($label) : ('Admin - ' . h($label)) ?></span>
            <h1><?= h($label) ?></h1>
            <p>Add <?= h(strtolower($label)) ?> manually, import a CSV batch, update records, and manage only the <?= h(strtolower($label)) ?> assigned to this administrator.</p>
        </div>
        <div class="action-bar">
            <button class="button outline" type="button" data-modal-target="employee-csv-modal">Bulk Import</button>
            <button class="button solid" type="button" data-modal-target="add-employee-modal">Add <?= h($singularLabel) ?></button>
        </div>
    </section>
    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2>Your <?= h($label) ?></h2>
                <span class="badge" id="admin-employees-count"><?php
                    $filteredEmployees = array_filter($allEmployees, function($emp) use ($employeeType) {
                        $type = (string) ($emp['employee_type'] ?? 'regular');
                        if ($employeeType === 'regular') {
                            return $type === 'regular' || $type === '';
                        }
                        return $type === $employeeType;
                    });
                    echo count($filteredEmployees);
                ?> total</span>
            </div>
            <div class="data-toolbar-right">
                <?php if (!$isFreelancer && !$isVendor): ?>
                    <nav class="employee-tabs inline" aria-label="Employee type filters">
                        <a href="<?= h(BASE_URL) ?>?page=admin_employees&type=regular" class="tab-link <?= $employeeType === 'regular' ? 'active' : '' ?>">Employee</a>
                        <a href="<?= h(BASE_URL) ?>?page=admin_employees&type=vendor" class="tab-link <?= $employeeType === 'vendor' ? 'active' : '' ?>">Vendor</a>
                        <a href="<?= h(BASE_URL) ?>?page=admin_employees&type=corporate" class="tab-link <?= $employeeType === 'corporate' ? 'active' : '' ?>">Corporate Employee</a>
                    </nav>
                <?php endif; ?>
                <div class="data-toolbar-search">
                    <input type="text" placeholder="Search by ID, name, email, phone, shift, or rule..." data-table-filter="admin-employees-table" data-empty-target="admin-employees-empty" data-count-target="admin-employees-count">
                </div>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Shift</th>
                    <th>Salary</th>
                    <th>Rules</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="admin-employees-table">
                <?php 
                $filteredEmployees = array_filter($allEmployees, function($emp) use ($employeeType) {
                    $type = (string) ($emp['employee_type'] ?? 'regular');
                    if ($employeeType === 'regular') {
                        return $type === 'regular' || $type === '';
                    }
                    return $type === $employeeType;
                });
                foreach ($filteredEmployees as $employee):
                    $rules = employee_rules((int) $employee['id']);
                    $rulesMarkup = rules_summary($rules);
                    $rulesText = strtolower(trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<br>', ' ', $rulesMarkup)))));
                    $searchText = strtolower(implode(' ', [
                        (string) $employee['emp_id'],
                        (string) $employee['name'],
                        (string) $employee['email'],
                        (string) $employee['phone'],
                        (string) ($employee['shift'] ?? ''),
                        $rulesText,
                    ]));
                ?>
                    <tr data-filter-row data-filter-text="<?= h($searchText) ?>">
                        <td><?= h($employee['emp_id']) ?></td>
                        <td><?= h($employee['name']) ?></td>
                        <td><?= h($employee['email']) ?></td>
                        <td><?= h($employee['phone']) ?></td>
                        <td><?= h((string) ($employee['shift'] ?: '-')) ?></td>
                        <td><?= h(number_format((float) $employee['salary'], 2)) ?></td>
                        <td><?= $rulesMarkup ?></td>
                        <td>
                            <div class="inline-actions">
                                <a class="button ghost small" href="<?= h(BASE_URL) ?>?page=admin_employees&edit=<?= (int) $employee['id'] ?>">Edit</a>
                                <button class="button outline small" type="button" data-confirm-delete data-user-id="<?= (int) $employee['id'] ?>" data-user-name="<?= h($employee['name']) ?>">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="list-item muted hidden table-empty-state" id="admin-employees-empty">No employees match your search.</div>
    </section>
    <?php if ($editEmployee): ?>
        <div class="modal open" id="edit-employee-modal" data-open-on-load>
            <div class="modal-card" style="max-width:720px;">
                <button class="modal-close" type="button" data-close-modal onclick="window.location='<?= h(BASE_URL) ?>?page=admin_employees&type=<?= h($employeeType) ?>'">&times;</button>
                <span class="eyebrow">Edit <?= h($singularLabel) ?></span>
                <h2><?= h($editEmployee['name']) ?></h2>
                <form method="post" class="stack-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="employee_update">
                    <input type="hidden" name="user_id" value="<?= (int) $editEmployee['id'] ?>">
                    <div class="reports-filter-grid">
                        <label>Emp ID<input type="text" name="emp_id" value="<?= h($editEmployee['emp_id']) ?>" required></label>
                        <label>Name<input type="text" name="name" value="<?= h($editEmployee['name']) ?>" required></label>
                        <label>Email<input type="email" name="email" value="<?= h($editEmployee['email']) ?>" required></label>
                        <label>Phone Number<input type="text" name="phone" value="<?= h($editEmployee['phone']) ?>" required></label>
                        <label>Shift<input type="text" name="shift" value="<?= h((string) ($editEmployee['shift'] ?? '')) ?>" placeholder="Enter shift"></label>
                        <label>Salary<input type="number" step="0.01" name="salary" value="<?= h((string) $editEmployee['salary']) ?>" required></label>
                        <label>Employee Type<select name="employee_type"><option value="regular" <?= ((string) ($editEmployee['employee_type'] ?? 'regular')) === 'regular' ? 'selected' : '' ?>>Regular Employee</option><option value="vendor" <?= ((string) ($editEmployee['employee_type'] ?? '')) === 'vendor' ? 'selected' : '' ?>>Vendor</option><option value="corporate" <?= ((string) ($editEmployee['employee_type'] ?? '')) === 'corporate' ? 'selected' : '' ?>>Corporate Employee</option></select></label>
                    </div>
                    <div class="inline-actions">
                        <button class="button solid" type="submit">Save Changes</button>
                        <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_employees&type=<?= h($employeeType) ?>">Cancel</a>
                    </div>
                </form>
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
                    <?php render_rules_editor(); ?>
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
                    <div class="reports-filter-grid">
                        <div class="field"><label>Emp ID</label><div class="field-row"><input type="text" name="emp_id" required></div><small class="field-error"><span>!</span>Emp ID is required.</small></div>
                        <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                        <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" required></div><small class="field-error"><span>!</span>Valid email required.</small></div>
                        <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" required></div><small class="field-error"><span>!</span>Phone number required.</small></div>
                        <div class="field"><label>Salary</label><div class="field-row"><input type="number" step="0.01" min="0" name="salary" required></div><small class="field-error"><span>!</span>Salary is required.</small></div>
                        <div class="field"><label>Employee Type</label><div class="field-row"><select name="employee_type" required><option value="regular">Regular Employee</option><option value="vendor">Vendor</option><option value="corporate">Corporate Employee</option></select></div><small class="field-error"><span>!</span>Employee type is required.</small></div>
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
                                <th>Salary</th>
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
                    <h3>Rules Assignment</h3>
                    <?php render_rules_editor(); ?>
                    <button class="button solid" type="submit" data-rule-submit>Import <?= h($label) ?></button>
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
                    <label class="upload-drop">
                        <strong>Select <?= h(strtolower($singularLabel)) ?> CSV</strong>
                        <p>Upload a CSV with <?= h(strtolower($singularLabel)) ?> details. Supported columns include ID, Name, Email, Phone, and Salary. Missing ID or Name can be generated automatically.</p>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </label>
                    <button class="button solid" type="submit">Upload CSV</button>
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

    <div class="spacer"></div>
    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Admin Overview</span>
                <h2>Leave Requests</h2>
            </div>
            <span class="badge"><?= count($leaveRequests) ?> recent</span>
        </div>
        <div class="spacer"></div>
        <?php if ($leaveRequests): ?>
            <div class="list">
                <?php foreach ($leaveRequests as $request): ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h((string) $request['name']) ?></strong><br>
                                <span class="hint"><?= h((string) $request['emp_id']) ?> | <?= h((string) $request['email']) ?></span>
                            </div>
                            <span class="badge"><?= h(date('d M Y', strtotime((string) $request['attend_date']))) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <div class="hint"><?= h(trim((string) $request['leave_reason']) ?: 'No leave reason provided.') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No leave requests have been submitted yet.</div>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}

function render_admin_projects(): void
{
    require_role('admin');

    $allProjects = active_projects();
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
    $modalTitle = $isEditing ? 'Edit Project' : 'Add Project';
    $submitLabel = $isEditing ? 'Save Project' : 'Add Project';

    render_header('Projects');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Projects</span>
            <h1>Projects</h1>
            <p>Create and manage project colleges, locations, durations, and session types from one place.</p>
        </div>
        <div class="action-bar">
            <button class="button solid" type="button" data-modal-target="project-modal">Add Project</button>
        </div>
    </section>

    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2>All Projects</h2>
                <span class="badge"><?= count($allProjects) ?> total</span>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>College Name</th>
                    <th>Location</th>
                    <th>Total Days</th>
                    <th>Session Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allProjects as $project): ?>
                    <?php $statusLabel = !empty($project['is_active']) ? 'Active' : 'Inactive'; ?>
                    <tr>
                        <td><?= h((string) $project['project_name']) ?></td>
                        <td><?= h((string) $project['college_name']) ?></td>
                        <td><?= h((string) $project['location']) ?></td>
                        <td><?= (int) $project['total_days'] ?></td>
                        <td><?= h(project_session_label((string) $project['session_type'])) ?></td>
                        <td><span class="status-pill status-<?= h($statusLabel) ?>"><?= h($statusLabel) ?></span></td>
                        <td>
                            <div class="inline-actions">
                                <a class="button ghost small" href="<?= h(BASE_URL) ?>?page=admin_projects&edit=<?= (int) $project['id'] ?>">Edit</a>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="project_toggle_active">
                                    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                    <button class="button outline small" type="submit"><?= !empty($project['is_active']) ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="project_delete">
                                    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                    <button class="button secondary small" type="submit" onclick="return confirm('Delete this project?');">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$allProjects): ?>
            <div class="list-item muted table-empty-state">No projects found. Add your first project to get started.</div>
        <?php endif; ?>
    </section>

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
                    <label>College Name
                        <input type="text" name="college_name" value="<?= h((string) ($formValues['college_name'] ?? '')) ?>" placeholder="ABC Engineering College" required>
                    </label>
                    <label>Location
                        <input type="text" name="location" value="<?= h((string) ($formValues['location'] ?? '')) ?>" placeholder="Ahmedabad, Gujarat" required>
                    </label>
                    <label>Total Days
                        <input type="number" min="1" name="total_days" value="<?= h((string) ($formValues['total_days'] ?? 1)) ?>" required>
                    </label>
                    <label>Session Type
                        <select name="session_type" required>
                            <?php foreach (project_session_types() as $sessionType): ?>
                                <option value="<?= h($sessionType) ?>" <?= (string) ($formValues['session_type'] ?? '') === $sessionType ? 'selected' : '' ?>><?= h(project_session_label($sessionType)) ?></option>
                            <?php endforeach; ?>
                        </select>
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
    <?php
    render_footer();
}

function render_admin_rules(): void 
{
    require_role('admin');
    $leaveRequests = recent_leave_requests();
    render_header('Rules', 'admin-rules-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Rules</span>
            <h1>Assign Rules</h1>
            <p>Select one or more of your employees, apply the allowed punch methods, and email the rule update instantly.</p>
        </div>
    </section>
    <section class="section-block scroll-panel">
        <form method="post" class="stack-form" data-rule-form data-employee-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="apply_rules">
            <div class="employee-picker">
                <div class="split">
                    <strong>Select Employees</strong>
                    <span class="hint">Search and pick one or more employees</span>
                </div>
                <input type="text" placeholder="Search employees..." data-employee-filter="employee-options">
                <div class="tag-list" id="selected-employee-tags"></div>
                <div class="employee-options" id="employee-options" data-tag-source="selected-employee-tags">
                    <?php foreach (employees() as $employee): ?>
                        <label class="employee-option">
                            <input type="checkbox" name="employee_ids[]" value="<?= (int) $employee['id'] ?>" data-label="<?= h($employee['name']) ?>">
                            <span><?= h($employee['name']) ?> (<?= h($employee['emp_id']) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php render_rules_editor([], 'Save'); ?>
        </form>
    </section>

    <div class="spacer"></div>
    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Admin Overview</span>
                <h2>Leave Requests</h2>
            </div>
            <span class="badge"><?= count($leaveRequests) ?> recent</span>
        </div>
        <div class="spacer"></div>
        <?php if ($leaveRequests): ?>
            <div class="list">
                <?php foreach ($leaveRequests as $request): ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h((string) $request['name']) ?></strong><br>
                                <span class="hint"><?= h((string) $request['emp_id']) ?> | <?= h((string) $request['email']) ?></span>
                            </div>
                            <span class="badge"><?= h(date('d M Y', strtotime((string) $request['attend_date']))) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <div class="hint"><?= h(trim((string) $request['leave_reason']) ?: 'No leave reason provided.') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No leave requests have been submitted yet.</div>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}

function calendar_payload(string $context, array $employee, string $date, array $recordBlock): string
{
    $rules = employee_rules((int) $employee['id']);
    return h(json_encode([
        'context' => $context,
        'employee_id' => (int) $employee['id'],
        'date' => $date,
        'display_date' => date('d M Y', strtotime($date)),
        'status' => $recordBlock['record']['status'],
        'sessions' => array_values($recordBlock['sessions']),
        'punch_in_time' => $recordBlock['record']['punch_in_time'],
        'punch_in_path' => $recordBlock['record']['punch_in_path'],
        'punch_in_lat' => $recordBlock['record']['punch_in_lat'],
        'punch_in_lng' => $recordBlock['record']['punch_in_lng'],
        'leave_reason' => $recordBlock['record']['leave_reason'],
        'biometric_in_time' => $recordBlock['record']['biometric_in_time'],
        'biometric_out_time' => $recordBlock['record']['biometric_out_time'],
        'rule_manual_in' => $rules['manual_punch_in'],
        'rule_manual_out' => $rules['manual_punch_out'],
        'manual_out_count' => $rules['manual_out_count'],
        'manual_out_slots' => $rules['manual_out_slots'],
        'rule_bio_in' => $rules['biometric_punch_in'],
        'rule_bio_out' => $rules['biometric_punch_out'],
    ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
}

function render_calendar(string $context, array $employee, string $month, array $monthAttendance): void
{
    [$start] = month_bounds($month);
    $offset = (int) $start->format('w');
    ?>
    <div class="calendar-shell">
        <div class="calendar-legend" aria-label="Attendance legend">
            <span class="legend-chip"><span class="legend-swatch legend-present"></span>Present</span>
            <span class="legend-chip"><span class="legend-swatch legend-absent"></span>Absent</span>
            <span class="legend-chip"><span class="legend-swatch legend-pending"></span>Pending</span>
        </div>
        <div class="calendar-grid scroll-panel">
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                <div class="weekday"><?= h($weekday) ?></div>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $offset; $i++): ?>
                <div class="day-card blank"></div>
            <?php endfor; ?>
            <?php foreach ($monthAttendance as $date => $entry): ?>
                <?php $status = (string) ($entry['record']['status'] ?? ''); ?>
                <?php $statusClass = str_replace(' ', '-', $status); ?>
                <?php $dayCardClass = 'day-card' . ($statusClass !== '' ? ' day-card-' . $statusClass : ''); ?>
                <?php $isEmployeeWeekOff = $context === 'employee' && ($status === 'Week Off'); ?>
                <?php if ($isEmployeeWeekOff): ?>
                    <div class="<?= h($dayCardClass) ?> static">
                        <?php if ($statusClass !== ''): ?>
                            <span class="day-dot dot-<?= h($statusClass) ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="day-number<?= $statusClass !== '' ? ' day-number-' . h($statusClass) : '' ?>"><?= date('d', strtotime($date)) ?></span>
                        <?php $dayCopy = in_array($status, ['Week Off', 'Pending'], true) ? $status : ''; ?>
                        <span class="day-copy"><?= h($dayCopy) ?></span>
                    </div>
                <?php else: ?>
                    <button class="<?= h($dayCardClass) ?>" type="button" data-attendance="<?= calendar_payload($context, $employee, $date, $entry) ?>">
                        <?php if ($statusClass !== ''): ?>
                            <span class="day-dot dot-<?= h($statusClass) ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="day-number<?= $statusClass !== '' ? ' day-number-' . h($statusClass) : '' ?>"><?= date('d', strtotime($date)) ?></span>
                        <?php $dayCopy = in_array($status, ['Week Off', 'Pending'], true) ? $status : ''; ?>
                        <span class="day-copy"><?= h($dayCopy) ?></span>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="calendar-summary">
            <div class="summary-card"><strong><?= count(array_filter($monthAttendance, fn($entry) => (($entry['record']['status'] ?? '') === 'Present'))) ?></strong><span>Total Present Days</span></div>
            <div class="summary-card summary-highlight"><strong>Rs <?= number_format(salary_for_month((float) $employee['salary'], $monthAttendance), 2) ?></strong><span>Calculated Salary</span></div>
        </div>
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
                <input type="hidden" name="start_time" value="09:00">
                <input type="hidden" name="end_time" value="18:00">
                <button class="button outline" type="submit">Add 9:00 AM - 6:00 PM</button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_add_shift_timing">
                <input type="hidden" name="start_time" value="10:30">
                <input type="hidden" name="end_time" value="20:30">
                <button class="button outline" type="submit">Add 10:30 AM - 8:30 PM</button>
            </form>
        </div>
        <div class="spacer"></div>
        <form method="post" class="stack-form" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_add_shift_timing">
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
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Posted On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timings as $timing): ?>
                    <tr>
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
    require_roles(['admin', 'freelancer', 'external_vendor']);
    $allEmployees = employees();
    $employeeType = $_GET['type'] ?? 'regular';
    
    $user = current_user();
    $isFreelancer = ($user['role'] ?? '') === 'freelancer';
    $isVendor = ($user['role'] ?? '') === 'external_vendor';
    
    // Filter employees by type
    $filteredEmployees = array_filter($allEmployees, function($emp) use ($employeeType) {
        $type = (string) ($emp['employee_type'] ?? 'regular');
        if ($employeeType === 'regular') {
            return $type === 'regular' || $type === '';
        }
        return $type === $employeeType;
    });
    $filteredEmployees = array_values($filteredEmployees); // Re-index array
    
    $fallbackEmployee = $filteredEmployees[0] ?? null;
    $selectedId = (int) ($_GET['employee_id'] ?? ($fallbackEmployee['id'] ?? 0));
    $employee = $selectedId ? (employee_by_id($selectedId) ?: $fallbackEmployee) : $fallbackEmployee;
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $leaveRequests = recent_leave_requests();
    $snapshot = attendance_snapshot_for_date();
    $counts = $snapshot['counts'];
    $details = $snapshot['details'];
    $displayDate = date('d M Y', strtotime($snapshot['date']));

    render_header('Attendance', 'admin-attendance-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow"><?= ($isFreelancer || $isVendor) ? 'Attendance' : 'Admin - Attendance' ?></span>
            <h1>Attendance Calendar</h1>
            <p>All Sundays are marked as Week Off automatically. Click any date to inspect sessions and edit the daily status.</p>
        </div>
        <div class="action-bar">
            <button class="button outline" type="button" data-modal-target="attendance-import-modal">Bulk Import</button>
        </div>
    </section>

    <?php if (!$isFreelancer && !$isVendor): ?>
    <section class="employee-tabs-section">
        <nav class="employee-tabs">
            <a href="<?= h(BASE_URL) ?>?page=admin_attendance&type=regular" class="tab-link <?= $employeeType === 'regular' ? 'active' : '' ?>">Employee</a>
            <a href="<?= h(BASE_URL) ?>?page=admin_attendance&type=vendor" class="tab-link <?= $employeeType === 'vendor' ? 'active' : '' ?>">Vendor</a>
            <a href="<?= h(BASE_URL) ?>?page=admin_attendance&type=corporate" class="tab-link <?= $employeeType === 'corporate' ? 'active' : '' ?>">Corporate Employee</a>
        </nav>
    </section>
    <?php endif; ?>

    <section class="section-block scroll-panel">
        <form method="get" class="form-grid">
            <input type="hidden" name="page" value="admin_attendance">
            <input type="hidden" name="type" value="<?= h($employeeType) ?>">
            <label>Employee
                <select name="employee_id">
                    <?php foreach ($filteredEmployees as $emp): ?>
                        <option value="<?= (int) $emp['id'] ?>" <?= $employee && (int) $employee['id'] === (int) $emp['id'] ? 'selected' : '' ?>><?= h($emp['name']) ?> (<?= h($emp['emp_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Month<input type="month" name="month" value="<?= h($month) ?>"></label>
            <div class="split align-end"><button class="button solid" type="submit">View Attendance</button></div>
        </form>
    </section>
    <div class="modal" id="attendance-import-modal">
        <div class="modal-card" style="max-width:720px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Attendance Import</span>
            <h2>Bulk Import Attendance</h2>
            <p>Upload the daily performance report from Excel or CSV. The importer reads employee rows using Empcode or Name, along with INTime, OUTTime, Status, and Remark, to mark attendance.</p>
            <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_attendance_csv_upload">
                <label class="upload-drop">
                    <strong>Select attendance file</strong>
                    <p>You can upload `.xlsx`, `.csv`, `.txt`, or legacy `.xls` HTML-style exports. If your file is an old binary `.xls` workbook, save it as `.xlsx` or `.csv` first. Employee rows are matched by Empcode first, then by Name if needed. If the report date is not detected, the attendance date below will be used.</p>
                    <input type="file" name="attendance_csv" accept=".xlsx,.xls,.csv,.txt" required>
                </label>
                <label>Attendance Date (optional)<input type="date" name="attendance_date"></label>
                <button class="button solid" type="submit">Import Attendance</button>
            </form>
        </div>
    </div>

    <div class="spacer"></div>
    <?php if ($employee): ?>
        <div class="attendance-panel">
            <?php render_calendar('admin', $employee, $month, month_attendance_for_user((int) $employee['id'], $month)); ?>
        </div>
    <?php else: ?>
        <section class="section-block"><p>No employees found. Add employees first.</p></section>
    <?php endif; ?>

    <div class="spacer"></div>
    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Admin Overview</span>
                <h2>Half Day &amp; Leave Details</h2>
            </div>
            <span class="badge"><?= h($displayDate) ?></span>
        </div>
        <div class="spacer"></div>
        <section class="dashboard-grid">
            <div class="metric-card">
                <span class="eyebrow">Today</span>
                <strong><?= (int) ($counts['Half Day'] ?? 0) ?></strong>
                <span>Half Day</span>
            </div>
            <div class="metric-card">
                <span class="eyebrow">Today</span>
                <strong><?= (int) ($counts['Leave'] ?? 0) ?></strong>
                <span>Leave</span>
            </div>
        </section>
        <div class="spacer"></div>
        <?php if ($details): ?>
            <div class="list">
                <?php foreach ($details as $detail): $statusClass = str_replace(' ', '-', (string) $detail['status']); ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h($detail['employee']['name']) ?></strong><br>
                                <span class="hint"><?= h((string) $detail['employee']['emp_id']) ?> | <?= h((string) $detail['employee']['email']) ?></span>
                            </div>
                            <span class="status-pill status-<?= h($statusClass) ?>"><?= h((string) $detail['status']) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <div class="hint"><?= h((string) $detail['detail']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No half day or leave details recorded for <?= h($displayDate) ?>.</div>
        <?php endif; ?>
    </section>

    <div class="spacer"></div>
    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Admin Overview</span>
                <h2>Leave Requests</h2>
            </div>
            <span class="badge"><?= count($leaveRequests) ?> recent</span>
        </div>
        <div class="spacer"></div>
        <?php if ($leaveRequests): ?>
            <div class="list">
                <?php foreach ($leaveRequests as $request): ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h((string) $request['name']) ?></strong><br>
                                <span class="hint"><?= h((string) $request['emp_id']) ?> | <?= h((string) $request['email']) ?></span>
                            </div>
                            <span class="badge"><?= h(date('d M Y', strtotime((string) $request['attend_date']))) ?></span>
                        </div>
                        <div class="spacer"></div>
                        <div class="hint"><?= h(trim((string) $request['leave_reason']) ?: 'No leave reason provided.') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No leave requests have been submitted yet.</div>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}

function render_admin_profile_settings(): void
{
    $admin = require_role('admin');
    $memberSince = !empty($admin['created_at']) ? date('d M Y', strtotime((string) $admin['created_at'])) : 'Recently added';

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
            <div class="list-item">
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

    render_header('Attendance Reports', 'admin-reports-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Reports</span>
            <h1>Attendance & Payroll Reports</h1>
            <p>Filter by employees, projects, and date range to view detailed attendance and incentive data.</p>
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
                    <label>Project Multi-Select</label>
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
                    <th>Project Name</th>
                    <th>Session Type</th>
                    <th>Attendance Status</th>
                    <th>Incentive Earned</th>
                    <th>Reimbursement Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reportData): ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><?= h(date('d M Y', strtotime((string)$row['date']))) ?></td>
                            <td><?= h((string)$row['employee_name']) ?></td>
                            <td><?= h((string)($row['project_name'] ?: '-')) ?></td>
                            <td><?= h(project_session_label((string)$row['session_type'])) ?></td>
                            <td>
                                <?php $statusClass = str_replace(' ', '-', (string)$row['attendance_status']); ?>
                                <span class="status-pill status-<?= h($statusClass) ?>"><?= h((string)$row['attendance_status']) ?></span>
                            </td>
                            <td>Rs <?= number_format((float)($row['incentive_earned'] ?? 0), 2) ?></td>
                            <td>Rs <?= number_format((float)($row['reimbursement_amount'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="muted center">No records found for the selected filters.</td>
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
            <span class="eyebrow">Admin - Reimbursement</span>
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
                    'attachmentUrl' => asset_url((string) ($item['attachment_path'] ?? '')),
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

function render_admin_accounts(): void
{
    require_role('admin');

    $filters = payment_filter_params($_GET);

    if (!empty($_GET['download_payslip_id'])) {
        $payment = admin_payment_by_id((int) $_GET['download_payslip_id']);
        if (!$payment) {
            flash('error', 'Payment record not found for payslip download.');
            redirect_to('admin_accounts', payment_redirect_query($filters));
        }

        stream_payment_payslip_pdf($payment);
    }

    $allEmployees = employees();
    $items = admin_payments($filters);
    $bankNames = payment_bank_names();
    $paymentTypes = payment_types();
    $transferModesMap = payment_transfer_modes_map();
    $incentiveTotals = incentive_totals_by_employee();
    $approvedReimbursements = approved_reimbursement_requests();
    $knownReimbursementIds = [];

    foreach ($approvedReimbursements as $reimbursement) {
        $knownReimbursementIds[(int) $reimbursement['id']] = true;
    }

    foreach ($items as $paymentRow) {
        $reimbursementId = (int) ($paymentRow['reimbursement_id'] ?? 0);
        if ($reimbursementId <= 0 || isset($knownReimbursementIds[$reimbursementId])) {
            continue;
        }

        $linkedReimbursement = payment_related_reimbursement_by_id($reimbursementId, (int) current_admin_id());
        if (!$linkedReimbursement) {
            continue;
        }

        $employee = employee_by_id((int) $linkedReimbursement['user_id']);
        if (!$employee) {
            continue;
        }

        $linkedReimbursement['employee_name'] = (string) ($employee['name'] ?? '');
        $linkedReimbursement['employee_emp_id'] = (string) ($employee['emp_id'] ?? '');
        $approvedReimbursements[] = $linkedReimbursement;
        $knownReimbursementIds[$reimbursementId] = true;
    }

    $reimbursementPayload = [];
    foreach ($approvedReimbursements as $reimbursement) {
        $reimbursementPayload[] = [
            'id' => (int) $reimbursement['id'],
            'userId' => (int) $reimbursement['user_id'],
            'employee' => trim((string) ($reimbursement['employee_name'] ?? '')),
            'employeeId' => trim((string) ($reimbursement['employee_emp_id'] ?? '')),
            'category' => (string) ($reimbursement['category'] ?? ''),
            'expenseDate' => (string) ($reimbursement['expense_date'] ?? ''),
            'remainingBalance' => number_format((float) ($reimbursement['remaining_balance'] ?? 0), 2, '.', ''),
            'amountRequested' => number_format((float) ($reimbursement['amount_requested'] ?? 0), 2, '.', ''),
        ];
    }

    $filterQuery = payment_redirect_query($filters);
    $modalDefaults = [
        'payment_id' => 0,
        'employee_id' => (int) ($filters['employee_id'] ?? 0),
        'payment_type' => '',
        'amount' => '',
        'bank_name' => '',
        'transfer_mode' => '',
        'transaction_id' => '',
        'payment_date' => date('Y-m-d'),
        'remarks' => '',
        'reimbursement_id' => 0,
    ];

    render_header('Accounts', 'admin-accounts-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Accounts</span>
            <h1>Accounts Payments</h1>
            <p>Track salary, incentive, reimbursement, and other employee payments from one place.</p>
        </div>
    </section>

    <section class="section-block accounts-filters-panel">
        <form method="get" class="stack-form">
            <input type="hidden" name="page" value="admin_accounts">
            <div class="accounts-filter-grid">
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
                    <label>Bank Name</label>
                    <select name="bank_name">
                        <option value="">All Banks</option>
                        <?php foreach ($bankNames as $bankName): ?>
                            <option value="<?= h($bankName) ?>" <?= $filters['bank_name'] === $bankName ? 'selected' : '' ?>><?= h($bankName) ?></option>
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
                    <div class="accounts-filter-button-row">
                        <button class="button solid" type="submit">Apply Filters</button>
                        <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_accounts">Reset</a>
                    </div>
                    <button class="button solid" type="button" id="open-payment-modal-button" data-payment-open-create data-modal-target="accounts-payment-modal">Add Payment</button>
                </div>
            </div>
        </form>
    </section>

    <div class="spacer"></div>
<?php
    ?>
    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2>Payment Records</h2>
                <span class="badge"><?= count($items) ?> found</span>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee Name</th>
                    <th>Payment Type</th>
                    <th>Amount</th>
                    <th>Bank</th>
                    <th>Transfer Mode</th>
                    <th>Transaction ID</th>
                    <th>Proof</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items): ?>
                    <?php foreach ($items as $paymentRow):
                        $proofUrl = !empty($paymentRow['proof_path']) ? asset_url((string) $paymentRow['proof_path']) : '';
                        $payslipUrl = BASE_URL . '?' . http_build_query(array_merge([
                            'page' => 'admin_accounts',
                            'download_payslip_id' => (int) $paymentRow['id'],
                        ], $filterQuery));
                        $editPayload = [
                            'payment_id' => (int) $paymentRow['id'],
                            'employee_id' => (int) $paymentRow['user_id'],
                            'payment_type' => (string) $paymentRow['payment_type'],
                            'amount' => number_format((float) ($paymentRow['amount'] ?? 0), 2, '.', ''),
                            'bank_name' => (string) $paymentRow['bank_name'],
                            'transfer_mode' => (string) ($paymentRow['transfer_mode'] ?? ''),
                            'transaction_id' => (string) ($paymentRow['transaction_id'] ?? ''),
                            'payment_date' => (string) $paymentRow['payment_date'],
                            'remarks' => (string) ($paymentRow['remarks'] ?? ''),
                            'reimbursement_id' => (int) ($paymentRow['reimbursement_id'] ?? 0),
                            'proof_name' => (string) ($paymentRow['proof_name'] ?? ''),
                        ];
                        ?>
                        <tr>
                            <td><?= h(date('d M Y', strtotime((string) $paymentRow['payment_date']))) ?></td>
                            <td><?= h((string) $paymentRow['employee_name']) ?></td>
                            <td><?= h((string) $paymentRow['payment_type']) ?></td>
                            <td>Rs <?= h(number_format((float) ($paymentRow['amount'] ?? 0), 2)) ?></td>
                            <td><?= h((string) $paymentRow['bank_name']) ?></td>
                            <td><?= h((string) (($paymentRow['transfer_mode'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($paymentRow['transaction_id'] ?? '') ?: '-')) ?></td>
                            <td>
                                <?php if ($proofUrl !== ''): ?>
                                    <a class="button outline small" href="<?= h($proofUrl) ?>" target="_blank" rel="noopener" download>Download</a>
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="payment-action-row">
                                    <button
                                        class="button outline small"
                                        type="button"
                                        data-payment-edit="<?= h(json_encode($editPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>"
                                    >
                                        Edit
                                    </button>
                                    <form method="post" onsubmit="return confirm('Delete this payment record?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="admin_delete_payment">
                                        <input type="hidden" name="payment_id" value="<?= (int) $paymentRow['id'] ?>">
                                        <input type="hidden" name="filter_employee_id" value="<?= (int) $filters['employee_id'] ?>">
                                        <input type="hidden" name="filter_bank_name" value="<?= h((string) $filters['bank_name']) ?>">
                                        <input type="hidden" name="filter_payment_type" value="<?= h((string) $filters['payment_type']) ?>">
                                        <input type="hidden" name="filter_from_date" value="<?= h((string) $filters['from_date']) ?>">
                                        <input type="hidden" name="filter_to_date" value="<?= h((string) $filters['to_date']) ?>">
                                        <button class="button ghost small" type="submit">Delete</button>
                                    </form>
                                    <a class="button outline small" href="<?= h($payslipUrl) ?>">Download Payslip</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="muted center">No payments found for the selected filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
<?php
    ?>
    <div class="modal fade" id="accounts-payment-modal">
        <div class="modal-dialog modal-dialog-centered accounts-payment-dialog">
            <div class="modal-content modal-card accounts-payment-card">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Accounts Payment</span>
                <h2 id="accounts-payment-modal-title">Add Payment</h2>
                <p class="hint" id="accounts-payment-modal-copy">Record a payment and notify the employee once it is processed.</p>
                <form method="post" enctype="multipart/form-data" class="stack-form" id="accounts-payment-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="admin_save_payment">
                    <input type="hidden" name="payment_id" id="accounts-payment-id" value="0">
                    <input type="hidden" name="filter_employee_id" value="<?= (int) $filters['employee_id'] ?>">
                    <input type="hidden" name="filter_bank_name" value="<?= h((string) $filters['bank_name']) ?>">
                    <input type="hidden" name="filter_payment_type" value="<?= h((string) $filters['payment_type']) ?>">
                    <input type="hidden" name="filter_from_date" value="<?= h((string) $filters['from_date']) ?>">
                    <input type="hidden" name="filter_to_date" value="<?= h((string) $filters['to_date']) ?>">

                    <div class="accounts-payment-grid">
                        <div class="field">
                            <label>Employee</label>
                            <div class="field-row">
                                <select name="employee_id" id="accounts-payment-employee" required>
                                    <option value="" selected disabled>Select employee</option>
                                    <?php foreach ($allEmployees as $employee): ?>
                                        <option value="<?= (int) $employee['id'] ?>">
                                            <?= h((string) $employee['name']) ?> (<?= h((string) $employee['emp_id']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="field-error"><span>!</span>Employee is required.</small>
                        </div>

                        <div class="field">
                            <label>Payment Type</label>
                            <div class="field-row">
                                <select name="payment_type" id="accounts-payment-type" required>
                                    <option value="" selected disabled>Select payment type</option>
                                    <?php foreach ($paymentTypes as $paymentType): ?>
                                        <option value="<?= h($paymentType) ?>"><?= h($paymentType) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="field-error"><span>!</span>Payment type is required.</small>
                        </div>

                        <div class="field accounts-conditional-field hidden" id="accounts-reimbursement-field">
                            <label>Select Approved Reimbursement Request</label>
                            <div class="field-row">
                                <select name="reimbursement_id" id="accounts-reimbursement-select">
                                    <option value="">Select approved reimbursement request</option>
                                </select>
                            </div>
                            <small class="hint" id="accounts-reimbursement-note">Choose a linked reimbursement request if this payment is settling a claim.</small>
                        </div>

                        <div class="field accounts-conditional-field hidden" id="accounts-incentive-field">
                            <label>Calculated Incentive</label>
                            <div class="field-row">
                                <input type="text" id="accounts-incentive-total" value="Rs 0.00" readonly>
                            </div>
                            <small class="hint">This value is read-only and helps while entering the actual payment amount.</small>
                        </div>

                        <div class="field">
                            <label>Amount</label>
                            <div class="field-row">
                                <input type="number" name="amount" id="accounts-payment-amount" min="0.01" step="0.01" required>
                            </div>
                            <small class="field-error"><span>!</span>Amount is required.</small>
                        </div>

                        <div class="field">
                            <label>Bank Name</label>
                            <div class="field-row">
                                <select name="bank_name" id="accounts-bank-name" required>
                                    <option value="" selected disabled>Select bank</option>
                                    <?php foreach ($bankNames as $bankName): ?>
                                        <option value="<?= h($bankName) ?>"><?= h($bankName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="field-error"><span>!</span>Bank name is required.</small>
                        </div>

                        <div class="field" id="accounts-transfer-mode-field">
                            <label>Transfer Mode</label>
                            <div class="field-row">
                                <select name="transfer_mode" id="accounts-transfer-mode">
                                    <option value="" selected disabled>Select transfer mode</option>
                                </select>
                            </div>
                            <small class="field-error"><span>!</span>Transfer mode is required for the selected bank.</small>
                        </div>

                        <div class="field" id="accounts-transaction-id-field">
                            <label>Transaction ID</label>
                            <div class="field-row">
                                <input type="text" name="transaction_id" id="accounts-transaction-id">
                            </div>
                            <small class="field-error"><span>!</span>Transaction ID is required unless the payment is cash.</small>
                        </div>

                        <div class="field">
                            <label>Payment Date</label>
                            <div class="field-row">
                                <input type="date" name="payment_date" id="accounts-payment-date" required>
                            </div>
                            <small class="field-error"><span>!</span>Payment date is required.</small>
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

                    <button class="button solid" type="submit" id="accounts-payment-submit-button">Save Payment</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .accounts-filter-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)) 1.25fr; gap: 18px; align-items: end; }
        .accounts-filter-actions { display: grid; gap: 10px; justify-items: end; }
        .accounts-filter-button-row { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .payment-action-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .payment-action-row form { margin: 0; }
        .accounts-payment-dialog { max-width: 920px; margin: 0 auto; }
        .accounts-payment-card { width: 100%; }
        .accounts-payment-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .accounts-conditional-field.hidden { display: none; }
        @media (max-width: 1180px) {
            .accounts-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .accounts-filter-actions { grid-column: 1 / -1; justify-items: stretch; }
            .accounts-filter-button-row { justify-content: flex-start; }
        }
        @media (max-width: 900px) {
            .accounts-filter-grid,
            .accounts-payment-grid { grid-template-columns: 1fr; }
            .payment-action-row { flex-direction: column; align-items: stretch; }
        }
    </style>
<?php
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const transferModeMap = <?= json_encode($transferModesMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const reimbursementOptions = <?= json_encode($reimbursementPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const incentiveTotals = <?= json_encode($incentiveTotals, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const createDefaults = <?= json_encode($modalDefaults, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const form = document.getElementById('accounts-payment-form');
            const modalTitle = document.getElementById('accounts-payment-modal-title');
            const modalCopy = document.getElementById('accounts-payment-modal-copy');
            const submitButton = document.getElementById('accounts-payment-submit-button');
            const paymentIdInput = document.getElementById('accounts-payment-id');
            const employeeSelect = document.getElementById('accounts-payment-employee');
            const paymentTypeSelect = document.getElementById('accounts-payment-type');
            const reimbursementField = document.getElementById('accounts-reimbursement-field');
            const reimbursementSelect = document.getElementById('accounts-reimbursement-select');
            const reimbursementNote = document.getElementById('accounts-reimbursement-note');
            const incentiveField = document.getElementById('accounts-incentive-field');
            const incentiveInput = document.getElementById('accounts-incentive-total');
            const amountInput = document.getElementById('accounts-payment-amount');
            const bankSelect = document.getElementById('accounts-bank-name');
            const transferModeField = document.getElementById('accounts-transfer-mode-field');
            const transferModeSelect = document.getElementById('accounts-transfer-mode');
            const transactionField = document.getElementById('accounts-transaction-id-field');
            const transactionInput = document.getElementById('accounts-transaction-id');
            const paymentDateInput = document.getElementById('accounts-payment-date');
            const remarksInput = document.getElementById('accounts-payment-remarks');
            const proofHelp = document.getElementById('accounts-proof-help');
            const openCreateButton = document.querySelector('[data-payment-open-create]');

            const openPageModal = id => {
                const target = document.getElementById(id);
                if (target) {
                    target.classList.add('open');
                }
            };

            const setSelectValue = (select, value) => {
                const normalized = String(value ?? '');
                Array.from(select.options).forEach(option => {
                    option.selected = option.value === normalized;
                });
            };

            const updateTransferModes = selectedValue => {
                const bankName = String(bankSelect.value || '');
                const modes = transferModeMap[bankName] || [];
                const previousValue = String(selectedValue ?? transferModeSelect.value ?? '');

                transferModeSelect.innerHTML = '';
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = modes.length ? 'Select transfer mode' : 'Not required for cash payments';
                placeholder.disabled = modes.length > 0;
                placeholder.selected = previousValue === '';
                transferModeSelect.appendChild(placeholder);

                modes.forEach(mode => {
                    const option = document.createElement('option');
                    option.value = mode;
                    option.textContent = mode;
                    option.selected = previousValue === mode;
                    transferModeSelect.appendChild(option);
                });

                const requiresTransferMode = modes.length > 0;
                transferModeField.classList.toggle('hidden', !requiresTransferMode);
                transferModeSelect.required = requiresTransferMode;
                transferModeSelect.disabled = !requiresTransferMode;

                const isCash = bankName === 'CASH';
                transactionField.classList.toggle('hidden', isCash);
                transactionInput.required = bankName !== '' && !isCash;
                transactionInput.disabled = isCash;
                if (isCash) {
                    transactionInput.value = '';
                }
            };

            const populateReimbursementOptions = selectedId => {
                const employeeId = Number(employeeSelect.value || 0);
                const activeOptions = reimbursementOptions.filter(option => employeeId === 0 || Number(option.userId) === employeeId);

                reimbursementSelect.innerHTML = '';
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select approved reimbursement request';
                placeholder.selected = !selectedId;
                reimbursementSelect.appendChild(placeholder);

                activeOptions.forEach(option => {
                    const item = document.createElement('option');
                    item.value = String(option.id);
                    const employeeCopy = option.employeeId ? `${option.employee} (${option.employeeId})` : option.employee;
                    const dateCopy = option.expenseDate ? option.expenseDate.split('-').reverse().join('-') : 'No date';
                    item.textContent = `${employeeCopy} - ${option.category} - ${dateCopy} - Balance Rs ${option.remainingBalance}`;
                    item.selected = String(selectedId || '') === String(option.id);
                    reimbursementSelect.appendChild(item);
                });

                const selectedOption = activeOptions.find(option => String(option.id) === String(selectedId || ''));
                reimbursementNote.textContent = selectedOption
                    ? `Remaining balance: Rs ${selectedOption.remainingBalance} | Requested: Rs ${selectedOption.amountRequested}`
                    : 'Choose a linked reimbursement request if this payment is settling a claim.';
            };

            const updateIncentiveDisplay = () => {
                const employeeId = String(employeeSelect.value || '0');
                const total = Number(incentiveTotals[employeeId] || 0);
                incentiveInput.value = `Rs ${total.toFixed(2)}`;
            };

            const updateConditionalFields = selectedReimbursementId => {
                const paymentType = String(paymentTypeSelect.value || '');
                const showReimbursement = paymentType === 'REIMBURSEMENT';
                const showIncentive = paymentType === 'INCENTIVE';

                reimbursementField.classList.toggle('hidden', !showReimbursement);
                incentiveField.classList.toggle('hidden', !showIncentive);

                if (showReimbursement) {
                    populateReimbursementOptions(selectedReimbursementId);
                } else {
                    reimbursementSelect.innerHTML = '<option value="">Select approved reimbursement request</option>';
                    reimbursementNote.textContent = 'Choose a linked reimbursement request if this payment is settling a claim.';
                }

                if (!showReimbursement) {
                    reimbursementSelect.value = '';
                }

                updateIncentiveDisplay();
            };

            const fillForm = payload => {
                paymentIdInput.value = String(payload.payment_id || 0);
                setSelectValue(employeeSelect, payload.employee_id || '');
                setSelectValue(paymentTypeSelect, payload.payment_type || '');
                amountInput.value = payload.amount || '';
                setSelectValue(bankSelect, payload.bank_name || '');
                updateConditionalFields(payload.reimbursement_id || '');
                updateTransferModes(payload.transfer_mode || '');
                if (payload.reimbursement_id) {
                    reimbursementSelect.value = String(payload.reimbursement_id);
                    populateReimbursementOptions(payload.reimbursement_id);
                }
                transactionInput.value = payload.transaction_id || '';
                paymentDateInput.value = payload.payment_date || '';
                remarksInput.value = payload.remarks || '';
                proofHelp.textContent = payload.proof_name
                    ? `Current proof: ${payload.proof_name}. Upload a new file only if you want to replace it.`
                    : 'Accepted formats: JPG, PNG, PDF.';
            };

            const resetCreateForm = () => {
                form.reset();
                fillForm(createDefaults);
                modalTitle.textContent = 'Add Payment';
                modalCopy.textContent = 'Record a payment and notify the employee once it is processed.';
                submitButton.textContent = 'Save Payment';
            };

            if (openCreateButton) {
                openCreateButton.addEventListener('click', resetCreateForm);
            }

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

            employeeSelect.addEventListener('change', () => updateConditionalFields(reimbursementSelect.value || ''));
            paymentTypeSelect.addEventListener('change', () => updateConditionalFields(''));
            bankSelect.addEventListener('change', () => updateTransferModes(''));
            reimbursementSelect.addEventListener('change', () => populateReimbursementOptions(reimbursementSelect.value || ''));

            resetCreateForm();
        });
    </script>
    <?php
    render_footer();
}
