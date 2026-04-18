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
    $label = $isFreelancer ? 'Staff' : 'Employees';

    render_header($isFreelancer ? 'Corporate Dashboard' : 'Admin Dashboard');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow"><?= h($isFreelancer ? 'Corporate Dashboard' : 'Admin Dashboard') ?></span>
            <h1><?= h($isFreelancer ? 'Corporate Dashboard' : 'Admin Dashboard') ?></h1>
            <p>Review today&apos;s employee log totals, <?= h(strtolower($label)) ?> coverage, and half day details for <?= h($displayDate) ?>.</p>
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
    </section>
    <div class="spacer"></div>

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

    render_header('Contractual Employee (Freelancer) Dashboard', 'corporate-dashboard-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Contractual Employee (Freelancer) Dashboard</span>
            <h1>Contractual Employee (Freelancer) Dashboard</h1>
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
    <?php
    render_footer();
}

function render_rules_editor(array $existing = [], ?string $submitLabel = null, bool $allowBlankShift = false): void
{
    $defaults = array_merge([
        'manual_punch_in' => false,
        'manual_punch_out' => false,
        'manual_out_count' => 1,
        'biometric_punch_in' => false,
        'biometric_punch_out' => false,
        'shift' => standard_shift_options()[0],
    ], $existing);
    ?>
    <div class="rules-box">
        <div class="field">
            <label>Shift Timing</label>
            <div class="field-row">
                <select name="shift">
                    <?php if ($allowBlankShift): ?>
                        <option value="" <?= (string) ($defaults['shift'] ?? '') === '' ? 'selected' : '' ?>>Keep current shift</option>
                    <?php endif; ?>
                    <?php foreach (standard_shift_options() as $shiftOption): ?>
                        <option value="<?= h($shiftOption) ?>" <?= normalize_shift_selection((string) ($defaults['shift'] ?? '')) === $shiftOption ? 'selected' : '' ?>><?= h(str_replace('-', '–', $shiftOption)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
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

function render_project_assignment_picker(array $selectedProjectIds = [], string $filterId = 'project-assignment-options'): void
{
    $allProjects = projects();
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
                    ?>
                    <label class="employee-option">
                        <input type="checkbox" name="project_ids[]" value="<?= $projectId ?>" <?= isset($selectedLookup[$projectId]) ? 'checked' : '' ?>>
                        <span>
                            <?= h((string) ($project['project_name'] ?? '')) ?><br>
                            <small class="hint"><?= h(implode(' | ', $detailParts)) ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-item muted">No projects are available yet. Add projects first in the Projects page.</div>
        <?php endif; ?>
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
    $defaultAssignedProjectIds = array_values(array_filter(array_map(
        static fn (array $project): int => (int) ($project['id'] ?? 0),
        active_projects()
    )));
    $editEmployeeProjectIds = $editEmployee ? employee_available_project_ids($editEmployee) : [];

    $user = current_user();
    $isFreelancer = ($user['role'] ?? '') === 'freelancer';
    $isVendor = ($user['role'] ?? '') === 'external_vendor';
    $label = $isFreelancer ? 'Contractual Employee (Freelancer)' : ($isVendor ? 'Vendor Employees' : 'Employees');
    $singularLabel = 'Employee';

    render_header($label);
    
    $employeeType = $_GET['type'] ?? 'regular';
    $employeeType = in_array($employeeType, ['regular', 'vendor', 'corporate'], true) ? $employeeType : 'regular';
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
                    $vendorRegistrations = [];
                    $freelancerRegistrations = [];
                    $filteredEmployees = [];
                    if ($employeeType === 'vendor') {
                        $vendorRegistrations = db()->query("SELECT * FROM users WHERE role = 'external_vendor' ORDER BY name")->fetchAll();
                        if (!empty($_GET['vendor_id'])) {
                            // Vendor-added employees have role='employee' and admin_id=vendor's user id
                            $vEmpStmt = db()->prepare("SELECT * FROM users WHERE role IN ('employee', 'corporate_employee') AND admin_id = ? ORDER BY name");
                            $vEmpStmt->execute([(int)$_GET['vendor_id']]);
                            $filteredEmployees = $vEmpStmt->fetchAll();
                        }
                    } elseif ($employeeType === 'corporate') {
                        $freelancerRegistrations = db()->query("SELECT * FROM users WHERE role = 'freelancer' ORDER BY name")->fetchAll();
                        if (!empty($_GET['freelancer_id'])) {
                            // Freelancer-added employees have role='corporate_employee' and admin_id=freelancer's user id
                            $fEmpStmt = db()->prepare("SELECT * FROM users WHERE role = 'corporate_employee' AND admin_id = ? ORDER BY name");
                            $fEmpStmt->execute([(int)$_GET['freelancer_id']]);
                            $filteredEmployees = $fEmpStmt->fetchAll();
                        }
                    } else {
                        if ($isVendor || $isFreelancer) {
                            $filteredEmployees = $allEmployees;
                        } else {
                            $filteredEmployees = array_filter($allEmployees, function($emp) use ($employeeType) {
                                $type = (string) ($emp['employee_type'] ?? 'regular');
                                if ($employeeType === 'regular') {
                                    return $type === 'regular' || $type === '';
                                }
                                return $type === $employeeType;
                            });
                        }
                    }
                    echo count($filteredEmployees);
                ?> total</span>
            </div>
            <div class="data-toolbar-right">
                <?php if (!$isFreelancer && !$isVendor): ?>
                    <nav class="employee-tabs inline" aria-label="Employee type filters">
                        <a href="<?= h(BASE_URL) ?>?page=admin_employees&type=regular" class="tab-link <?= $employeeType === 'regular' ? 'active' : '' ?>">Employee</a>
                        <a href="<?= h(BASE_URL) ?>?page=admin_employees&type=vendor" class="tab-link <?= $employeeType === 'vendor' ? 'active' : '' ?>">Vendor</a>
                        <a href="<?= h(BASE_URL) ?>?page=admin_employees&type=corporate" class="tab-link <?= $employeeType === 'corporate' ? 'active' : '' ?>">Contractual Employee (Freelancer)</a>
                    </nav>
                <?php endif; ?>
                <div class="data-toolbar-search">
                    <input type="text" placeholder="Search by ID, name, email, phone, shift, or rule..." data-table-filter="admin-employees-table" data-empty-target="admin-employees-empty" data-count-target="admin-employees-count">
                </div>
            </div>
        </div>
        <?php if ($employeeType === 'vendor'): ?>
        <section class="section-block scroll-panel" style="margin-bottom: 20px; padding: 15px; border-radius: 12px;">
            <form method="get" class="form-grid">
                <input type="hidden" name="page" value="admin_employees">
                <input type="hidden" name="type" value="vendor">
                <label>Vendor
                    <select name="vendor_id" onchange="this.form.submit()">
                        <option value="">-- Select Vendor --</option>
                        <?php foreach ($vendorRegistrations as $vendor): ?>
                            <option value="<?= (int) $vendor['id'] ?>" <?= ((int)($_GET['vendor_id'] ?? 0) === (int)$vendor['id']) ? 'selected' : '' ?>><?= h($vendor['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        </section>
        <?php elseif ($employeeType === 'corporate'): ?>
        <section class="section-block scroll-panel" style="margin-bottom: 20px; padding: 15px; border-radius: 12px;">
            <form method="get" class="form-grid">
                <input type="hidden" name="page" value="admin_employees">
                <input type="hidden" name="type" value="corporate">
                <label>Contractual Employee (Freelancer)
                    <select name="freelancer_id" onchange="this.form.submit()">
                        <option value="">-- Select Freelancer --</option>
                        <?php foreach ($freelancerRegistrations as $freelancer): ?>
                            <option value="<?= (int) $freelancer['id'] ?>" <?= ((int)($_GET['freelancer_id'] ?? 0) === (int)$freelancer['id']) ? 'selected' : '' ?>><?= h($freelancer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        </section>
        <?php endif; ?>
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
        <?php if (($employeeType === 'vendor' && empty($_GET['vendor_id']))): ?>
            <div class="list-item muted" style="display:block; padding: 16px;">Select a vendor from the dropdown above to view their employees.</div>
        <?php elseif (($employeeType === 'corporate' && empty($_GET['freelancer_id']))): ?>
            <div class="list-item muted" style="display:block; padding: 16px;">Select a contractual employee (freelancer) from the dropdown above to view their employees.</div>
        <?php elseif (!$filteredEmployees): ?>
            <div class="list-item muted" style="display:block; padding: 16px;">No employees found.</div>
        <?php endif; ?>
        <div class="list-item muted hidden table-empty-state" id="admin-employees-empty">No records match your search.</div>
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
                        <label>Shift
                            <select name="shift">
                                <option value="">Not assigned</option>
                                <?php foreach (standard_shift_options() as $shiftOption): ?>
                                    <option value="<?= h($shiftOption) ?>" <?= normalize_shift_selection((string) ($editEmployee['shift'] ?? '')) === $shiftOption ? 'selected' : '' ?>><?= h(str_replace('-', '–', $shiftOption)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Salary<input type="number" step="0.01" name="salary" value="<?= h((string) $editEmployee['salary']) ?>" required></label>
                        <label>Employee Type<select name="employee_type"><option value="regular" <?= ((string) ($editEmployee['employee_type'] ?? 'regular')) === 'regular' ? 'selected' : '' ?>>Regular Employee</option><option value="vendor" <?= ((string) ($editEmployee['employee_type'] ?? '')) === 'vendor' ? 'selected' : '' ?>>Vendor</option><option value="corporate" <?= ((string) ($editEmployee['employee_type'] ?? '')) === 'corporate' ? 'selected' : '' ?>>Contractual Employee (Freelancer)</option></select></label>
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
                    <?php render_rules_editor(['shift' => (string) ($pendingEmployee['shift'] ?? standard_shift_options()[0])]); ?>
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
                    <?php elseif ($employeeType === 'corporate' && !empty($_GET['freelancer_id'])): ?>
                        <input type="hidden" name="freelancer_id" value="<?= (int) $_GET['freelancer_id'] ?>">
                    <?php endif; ?>
                    <div class="reports-filter-grid">
                        <div class="field"><label>Emp ID</label><div class="field-row"><input type="text" name="emp_id" required></div><small class="field-error"><span>!</span>Emp ID is required.</small></div>
                        <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                        <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" required></div><small class="field-error"><span>!</span>Valid email required.</small></div>
                        <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" required></div><small class="field-error"><span>!</span>Phone number required.</small></div>
                        <div class="field"><label>Salary</label><div class="field-row"><input type="number" step="0.01" min="0" name="salary" required></div><small class="field-error"><span>!</span>Salary is required.</small></div>
                        <div class="field"><label>Employee Type</label><div class="field-row"><select name="employee_type" required><option value="regular" <?= $employeeType === 'regular' ? 'selected' : '' ?>>Regular Employee</option><option value="vendor" <?= $employeeType === 'vendor' ? 'selected' : '' ?>>Vendor</option><option value="corporate" <?= $employeeType === 'corporate' ? 'selected' : '' ?>>Contractual Employee (Freelancer)</option></select></div><small class="field-error"><span>!</span>Employee type is required.</small></div>
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
                    <?php render_rules_editor(['shift' => standard_shift_options()[0]]); ?>
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
                    <?php if ($employeeType === 'vendor' && !empty($_GET['vendor_id'])): ?>
                        <input type="hidden" name="vendor_id" value="<?= (int) $_GET['vendor_id'] ?>">
                    <?php elseif ($employeeType === 'corporate' && !empty($_GET['freelancer_id'])): ?>
                        <input type="hidden" name="freelancer_id" value="<?= (int) $_GET['freelancer_id'] ?>">
                    <?php endif; ?>
                    <div class="reports-filter-grid">
                        <div class="field"><label>Employee Type</label><div class="field-row"><select name="employee_type" required><option value="regular" <?= $employeeType === 'regular' ? 'selected' : '' ?>>Regular Employee</option><option value="vendor" <?= $employeeType === 'vendor' ? 'selected' : '' ?>>Vendor</option><option value="corporate" <?= $employeeType === 'corporate' ? 'selected' : '' ?>>Contractual Employee (Freelancer)</option></select></div><small class="field-error"><span>!</span>Employee type is required.</small></div>
                    </div>
                    <label class="upload-drop">
                        <strong>Select <?= h(strtolower($singularLabel ?? 'Employee')) ?> CSV</strong>
                        <p>Upload a CSV with <?= h(strtolower($singularLabel ?? 'Employee')) ?> details. Supported columns include ID, Name, Email, Phone, and Salary. Missing ID or Name can be generated automatically.</p>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </label>
                    <button class="button solid" type="submit">Import CSV</button>
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
    require_role('admin');

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
    $modalTitle = $isEditing ? 'Edit Project' : 'Add Project';
    $submitLabel = $isEditing ? 'Save Project' : 'Add Project';

    render_header('Projects');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Projects</span>
            <h1>Projects</h1>
            <p>Create and manage project colleges, locations, durations, and session types from one place. Deactivated projects stay saved, become currently unavailable, and can be activated again later.</p>
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
    $defaultAssignedProjectIds = array_values(array_filter(array_map(
        static fn (array $project): int => (int) ($project['id'] ?? 0),
        active_projects()
    )));
    render_header('Rules', 'admin-rules-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Rules</span>
            <h1>Assign Rules</h1>
            <p>Select one or more of your employees, apply the allowed punch methods, assign their project list, and email the rule update instantly.</p>
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
            <?php render_rules_editor([], null, true); ?>
            <div class="inline-actions">
                <button class="button solid" type="submit" data-rule-submit>Save</button>
            </div>
        </form>
    </section>
    <?php
    render_footer();
}

function calendar_payload(string $context, array $employee, string $date, array $recordBlock, array $reimbursementMeta = [], ?string $month = null, string $viewMode = 'attendance'): string
{
    $rules = employee_rules((int) $employee['id']);
    $month = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : substr($date, 0, 7);
    $sessions = array_map(static function (array $session): array {
        $session['punch_in_path'] = public_file_path((string) ($session['punch_in_path'] ?? ''));
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

    return h(json_encode([
        'context' => $context,
        'employee_id' => (int) $employee['id'],
        'date' => $date,
        'display_date' => date('d M Y', strtotime($date)),
        'status' => $recordBlock['record']['status'],
        'sessions' => array_values($sessions),
        'punch_in_time' => $recordBlock['record']['punch_in_time'],
        'punch_in_path' => public_file_path((string) ($recordBlock['record']['punch_in_path'] ?? '')),
        'punch_in_lat' => $recordBlock['record']['punch_in_lat'],
        'punch_in_lng' => $recordBlock['record']['punch_in_lng'],
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
            'count' => (int) ($reimbursementMeta['count'] ?? 0),
            'total' => number_format((float) ($reimbursementMeta['total'] ?? 0), 2, '.', ''),
            'current_month' => $month === date('Y-m'),
            'future' => $date > date('Y-m-d'),
            'locked' => !empty($reimbursementMeta['count']),
            'items' => $reimbursementItems,
        ],
    ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
}

function render_calendar(string $context, array $employee, string $month, array $monthAttendance, array $calendarMeta = []): void
{
    [$start] = month_bounds($month);
    $offset = (int) $start->format('w');
    $weekRows = (int) max(4, min(6, (int) ceil(($offset + count($monthAttendance)) / 7)));
    $showSummary = !array_key_exists('show_summary', $calendarMeta) || !empty($calendarMeta['show_summary']);
    $compact = !empty($calendarMeta['compact']);
    $reimbursementsByDate = $calendarMeta['reimbursements_by_date'] ?? [];
    $viewMode = $calendarMeta['view_mode'] ?? 'attendance';
    $attendanceCounts = attendance_counts($monthAttendance);
    $salaryBreakdown = salary_breakdown_for_month((float) ($employee['salary'] ?? 0), $monthAttendance);
    ?>
    <div class="calendar-shell<?= $compact ? ' calendar-shell-compact' : '' ?>">
        <div class="calendar-legend" aria-label="Attendance legend">
            <?php if ($viewMode === 'reimbursement'): ?>
                <span class="legend-chip"><span class="legend-swatch legend-reimbursement" style="background-color: #6366f1;"></span>Reimbursement Claim</span>
            <?php else: ?>
                <span class="legend-chip"><span class="legend-swatch legend-present"></span>Present</span>
                <span class="legend-chip"><span class="legend-swatch legend-absent"></span>Absent</span>
                <span class="legend-chip"><span class="legend-swatch legend-pending"></span>Pending</span>
                <span class="legend-chip"><span class="legend-swatch legend-reimbursement" style="background-color: #6366f1;"></span>Reimbursement</span>
            <?php endif; ?>
        </div>
        <div class="calendar-grid<?= $compact ? ' calendar-grid-compact' : '' ?>"<?= $compact ? ' style="--calendar-week-rows: ' . $weekRows . ';"' : '' ?>>
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                <div class="weekday"><?= h($weekday) ?></div>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $offset; $i++): ?>
                <div class="day-card blank"></div>
            <?php endfor; ?>
            <?php foreach ($monthAttendance as $date => $entry): ?>
                <?php $status = (string) ($entry['record']['status'] ?? ''); ?>
                <?php $statusClass = str_replace(' ', '-', $status); ?>
                <?php 
                    $dayCardClass = 'day-card';
                    if ($viewMode !== 'reimbursement' && $statusClass !== '') {
                        $dayCardClass .= ' day-card-' . $statusClass;
                    }
                ?>
                <?php $isEmployeeWeekOff = $context === 'employee' && ($status === 'Week Off'); ?>
                <?php $reimbursementMeta = $reimbursementsByDate[$date] ?? ['count' => 0, 'total' => 0.0, 'items' => []]; ?>
                <?php if ($isEmployeeWeekOff): ?>
                    <div class="<?= h($dayCardClass) ?> static<?= $compact ? ' compact' : '' ?>">
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
                            <span class="day-number<?= ($viewMode !== 'reimbursement' && $statusClass !== '') ? ' day-number-' . h($statusClass) : '' ?>"><?= date('d', strtotime($date)) ?></span>
                            <?php $dayCopy = in_array($status, ['Week Off', 'Pending'], true) ? $status : ''; ?>
                            <span class="day-copy"><?= h($dayCopy) ?></span>
                            <?php if (!empty($reimbursementMeta['count'])): ?>
                                <span class="day-badge reimbursement">R <?= (int) $reimbursementMeta['count'] ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <button class="<?= h($dayCardClass) ?><?= $compact ? ' compact' : '' ?>" type="button" data-attendance="<?= calendar_payload($context, $employee, $date, $entry, $reimbursementMeta, $month, $viewMode) ?>">
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
                            <span class="day-number<?= ($viewMode !== 'reimbursement' && $statusClass !== '') ? ' day-number-' . h($statusClass) : '' ?>"><?= date('d', strtotime($date)) ?></span>
                            <?php $dayCopy = in_array($status, ['Week Off', 'Pending'], true) ? $status : ''; ?>
                            <span class="day-copy"><?= h($dayCopy) ?></span>
                            <?php if (!empty($reimbursementMeta['count'])): ?>
                                <span class="day-badge reimbursement">R <?= (int) $reimbursementMeta['count'] ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php if ($showSummary): ?>
            <div class="calendar-summary">
                <?php if ($viewMode === 'reimbursement'): ?>
                    <?php 
                        $totalClaims = 0;
                        $totalAmount = 0.0;
                        foreach ($reimbursementsByDate as $meta) {
                            $totalClaims += (int) ($meta['count'] ?? 0);
                            $totalAmount += (float) ($meta['total'] ?? 0);
                        }
                    ?>
                    <div class="summary-card"><strong><?= $totalClaims ?></strong><span>Total Claims</span></div>
                    <div class="summary-card summary-highlight"><strong>Rs <?= number_format($totalAmount, 2) ?></strong><span>Total Requested</span></div>
                <?php else: ?>
                    <div class="summary-card"><strong><?= (int) ($attendanceCounts['present'] ?? 0) ?></strong><span>Total Present Days</span></div>
                    <div class="summary-card"><strong><?= (int) ($attendanceCounts['half_day'] ?? 0) ?></strong><span>Half Days</span></div>
                    <div class="summary-card summary-highlight"><strong>Rs <?= number_format((float) ($salaryBreakdown['calculated_salary'] ?? 0), 2) ?></strong><span>Calculated Salary</span></div>
                <?php endif; ?>
            </div>
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
    $view = $_GET['view'] ?? 'attendance';

    $user = current_user();
    $isFreelancer = ($user['role'] ?? '') === 'freelancer';
    $isVendor = ($user['role'] ?? '') === 'external_vendor';

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
    } elseif ($employeeType === 'corporate' && !$isFreelancer && !$isVendor) {
        $freelancerRegistrations = db()->query("SELECT * FROM users WHERE role = 'freelancer' ORDER BY name")->fetchAll();
        if (!empty($_GET['freelancer_id'])) {
            $fEmpStmt = db()->prepare("SELECT * FROM users WHERE role = 'corporate_employee' AND admin_id = ? ORDER BY name");
            $fEmpStmt->execute([(int)$_GET['freelancer_id']]);
            $filteredEmployees = $fEmpStmt->fetchAll();
        }
    } else {
        $filteredEmployees = array_values(array_filter($allEmployees, function($emp) use ($employeeType) {
            $type = (string) ($emp['employee_type'] ?? 'regular');
            if ($employeeType === 'regular') {
                return $type === 'regular' || $type === '';
            }
            return $type === $employeeType;
        }));
    }

    $fallbackEmployee = $filteredEmployees[0] ?? null;
    $selectedId = (int) ($_GET['employee_id'] ?? ($fallbackEmployee['id'] ?? 0));
    // For vendor/corporate with no selection yet, don't auto-select
    if (($employeeType === 'vendor' && empty($_GET['vendor_id'])) ||
        ($employeeType === 'corporate' && empty($_GET['freelancer_id']))) {
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

    render_header('Employee Log', 'admin-employee-log-page');
    ?>
    <section class="page-title">
        <div>
            <h1><?= $view === 'reimbursement' ? 'Reimbursement Calendar' : 'Employee Log Calendar' ?></h1>
        </div>
        <div class="action-bar">
            <a href="<?= h(BASE_URL) ?>?page=admin_employee_log&type=<?= h($employeeType) ?>&view=attendance" class="button <?= $view === 'attendance' ? 'solid' : 'outline' ?>">Employee Log Calendar</a>
            <a href="<?= h(BASE_URL) ?>?page=admin_employee_log&type=<?= h($employeeType) ?>&view=reimbursement" class="button <?= $view === 'reimbursement' ? 'solid' : 'outline' ?>">Reimbursement</a>
        </div>
    </section>

    <?php if (!$isFreelancer && !$isVendor): ?>
    <section class="employee-tabs-section">
        <nav class="employee-tabs">
            <a href="<?= h(BASE_URL) ?>?page=admin_employee_log&type=regular&view=<?= h($view) ?>" class="tab-link <?= $employeeType === 'regular' ? 'active' : '' ?>">Employee</a>
            <a href="<?= h(BASE_URL) ?>?page=admin_employee_log&type=vendor&view=<?= h($view) ?>" class="tab-link <?= $employeeType === 'vendor' ? 'active' : '' ?>">Vendor</a>
            <a href="<?= h(BASE_URL) ?>?page=admin_employee_log&type=corporate&view=<?= h($view) ?>" class="tab-link <?= $employeeType === 'corporate' ? 'active' : '' ?>">Contractual Employee (Freelancer)</a>
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
        <?php elseif ($employeeType === 'corporate'): ?>
        <form method="get" class="form-grid" style="margin-bottom: 12px;">
            <input type="hidden" name="page" value="admin_employee_log">
            <input type="hidden" name="type" value="corporate">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <label>Contractual Employee (Freelancer)
                <select name="freelancer_id" onchange="this.form.submit()">
                    <option value="">-- Select Freelancer --</option>
                    <?php foreach ($freelancerRegistrations as $fr): ?>
                        <option value="<?= (int) $fr['id'] ?>" <?= ((int)($_GET['freelancer_id'] ?? 0) === (int)$fr['id']) ? 'selected' : '' ?>><?= h($fr['name']) ?></option>
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
            <?php elseif ($employeeType === 'corporate' && !empty($_GET['freelancer_id'])): ?>
                <input type="hidden" name="freelancer_id" value="<?= (int)$_GET['freelancer_id'] ?>">
            <?php endif; ?>
            <label>Employee
                <select name="employee_id">
                    <option value="">-- Select Employee --</option>
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
            <span class="eyebrow">Employee Log Import</span>
            <h2>Bulk Import Employee Log</h2>
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
    <?php if (($employeeType === 'vendor' && empty($_GET['vendor_id'])) || ($employeeType === 'corporate' && empty($_GET['freelancer_id']))): ?>
        <section class="section-block"><p class="hint"><?= $employeeType === 'vendor' ? 'Select a vendor above to view their employee attendance.' : 'Select a contractual employee above to view their employee attendance.' ?></p></section>
    <?php elseif ($employee): ?>
        <div class="attendance-panel" style="display: block; height: auto; overflow: visible;">
            <?php 
                $reimbursements = employee_reimbursements_by_date_map((int) $employee['id'], $month);
                render_calendar('admin', $employee, $month, month_attendance_for_user((int) $employee['id'], $month), [
                    'compact' => false,
                    'reimbursements_by_date' => $reimbursements,
                    'view_mode' => $view
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

    render_header('Reports', 'admin-reports-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Reports</span>
            <h1>Employee Log Reports</h1>
            <p>Filter by employees, projects, and date range to review attendance logs and manual punch entries.</p>
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
                    <th>Slot</th>
                    <th>Session Type</th>
                    <th>Attendance Status</th>
                    <th>Manual Punch In</th>
                    <th>Manual Punch Out</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reportData): ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><?= h(date('d M Y', strtotime((string)$row['date']))) ?></td>
                            <td><?= h((string)$row['employee_name']) ?></td>
                            <td><?= h((string)($row['project_name'] ?: '-')) ?></td>
                            <td><?= h((string)($row['slot_name'] ?: '-')) ?></td>
                            <td><?= h(project_session_label((string)$row['session_type'])) ?></td>
                            <td>
                                <?php $statusClass = str_replace(' ', '-', (string)$row['attendance_status']); ?>
                                <span class="status-pill status-<?= h($statusClass) ?>"><?= h((string)$row['attendance_status']) ?></span>
                            </td>
                            <td><?= h((string) (($row['manual_punch_in'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['manual_punch_out'] ?? '') ?: '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="muted center">No records found for the selected filters.</td>
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
                                    <span class="hint"><?= h((string) ($row['employee_emp_id'] ?: 'Employee')) ?></span>
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
                        <th>Date</th>
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
                            <select name="employee_id" id="accounts-payment-employee" required>
                                <option value="" selected disabled>Select employee</option>
                                <?php foreach ($allEmployees as $employee): ?>
                                    <option value="<?= (int) $employee['id'] ?>"><?= h((string) $employee['name']) ?> (<?= h((string) $employee['emp_id']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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

                    <div class="field">
                        <label>Amount</label>
                        <div class="field-row">
                            <input type="number" name="amount" id="accounts-payment-amount" min="0.01" step="0.01" required>
                        </div>
                    </div>

                    <div class="field accounts-method-field">
                        <label>Payment Method(s)</label>
                        <div class="payment-method-grid" id="accounts-payment-method-grid">
                            <?php foreach ($paymentMethods as $method): ?>
                                <label class="payment-method-option">
                                    <input type="checkbox" name="payment_methods[]" value="<?= h($method) ?>" data-payment-method>
                                    <span><?= h($method) ?></span>
                                </label>
                            <?php endforeach; ?>
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
            const amountInput = document.getElementById('accounts-payment-amount');
            const transferModeField = document.getElementById('accounts-transfer-mode-field');
            const transferModeSelect = document.getElementById('accounts-transfer-mode');
            const transactionField = document.getElementById('accounts-transaction-id-field');
            const transactionInput = document.getElementById('accounts-transaction-id');
            const paymentDateInput = document.getElementById('accounts-payment-date');
            const remarksInput = document.getElementById('accounts-payment-remarks');
            const calculationNote = document.getElementById('accounts-calculation-note');
            const proofHelp = document.getElementById('accounts-proof-help');
            const methodInputs = Array.from(document.querySelectorAll('[data-payment-method]'));

            const openPageModal = id => {
                const target = document.getElementById(id);
                if (target) {
                    target.classList.add('open');
                }
            };

            const selectedMethods = () => methodInputs.filter(input => input.checked).map(input => input.value);

            const setMethods = methods => {
                const values = Array.isArray(methods) ? methods.map(String) : [];
                methodInputs.forEach(input => {
                    input.checked = values.includes(String(input.value));
                });
            };

            const setSelectValue = (select, value) => {
                const normalized = String(value ?? '');
                Array.from(select.options).forEach(option => {
                    option.selected = option.value === normalized;
                });
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
                const requiresTransaction = methods.some(method => method !== 'CASH');
                transactionField.classList.toggle('hidden', !requiresTransaction);
                transactionInput.required = requiresTransaction;
                if (!requiresTransaction) {
                    transactionInput.value = '';
                }
            };

            const populateReimbursementOptions = selectedId => {
                const employeeId = Number(employeeSelect.value || 0);
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
                const paymentType = String(paymentTypeSelect.value || '');
                const methods = selectedMethods();
                const showReimbursement = paymentType === 'REIMBURSEMENT';
                reimbursementField.classList.toggle('hidden', !showReimbursement);
                if (showReimbursement) {
                    populateReimbursementOptions(reimbursementSelect.value || '');
                } else {
                    reimbursementSelect.innerHTML = '<option value="">Select approved reimbursement request</option>';
                }

                updateTransferModes(transferModeSelect.value || '');

                const isValid = String(employeeSelect.value || '') !== ''
                    && paymentType !== ''
                    && Number(amountInput.value || 0) > 0
                    && methods.length > 0
                    && (!transferModeSelect.required || String(transferModeSelect.value || '') !== '')
                    && (!transactionInput.required || String(transactionInput.value || '').trim() !== '')
                    && (!showReimbursement || String(reimbursementSelect.value || '') !== '');

                submitButton.disabled = !isValid;
                calculationNote.textContent = isValid
                    ? 'Payment calculation is valid. You can complete this payout now.'
                    : 'Complete the required payment details before the Pay button is enabled.';
            };

            const fillForm = payload => {
                paymentIdInput.value = String(payload.payment_id || 0);
                setSelectValue(employeeSelect, payload.employee_id || '');
                setSelectValue(paymentTypeSelect, payload.payment_type || '');
                amountInput.value = payload.amount || '';
                setMethods(payload.payment_methods || []);
                setSelectValue(transferModeSelect, payload.transfer_mode || '');
                transactionInput.value = payload.transaction_id || '';
                paymentDateInput.value = payload.payment_date || '';
                remarksInput.value = payload.remarks || '';
                proofHelp.textContent = payload.proof_name
                    ? `Current proof: ${payload.proof_name}. Upload a new file only if you want to replace it.`
                    : 'Accepted formats: JPG, PNG, PDF.';
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

            methodInputs.forEach(input => input.addEventListener('change', syncFormState));
            employeeSelect.addEventListener('change', syncFormState);
            paymentTypeSelect.addEventListener('change', syncFormState);
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
            <p>List of external vendors who have registered on the portal.</p>
        </div>
    </section>
    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2>Registered Vendors</h2>
                <span class="badge"><?= count($vendors) ?> total</span>
            </div>
            <div class="data-toolbar-right">
                <div class="data-toolbar-search">
                    <input type="text" placeholder="Search by name, email, or phone..." data-table-filter="admin-vendors-table" data-empty-target="admin-vendors-empty">
                </div>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Registered At</th>
                </tr>
            </thead>
            <tbody id="admin-vendors-table">
                <?php foreach ($vendors as $vendor): ?>
                    <?php
                        $searchText = strtolower(implode(' ', [
                            (string) $vendor['name'],
                            (string) $vendor['email'],
                            (string) $vendor['phone'],
                        ]));
                    ?>
                    <tr data-filter-row data-filter-text="<?= h($searchText) ?>">
                        <td><strong><?= h($vendor['name']) ?></strong></td>
                        <td><?= h($vendor['email']) ?></td>
                        <td><?= h($vendor['phone']) ?></td>
                        <td><?= !empty($vendor['created_at']) ? h(date('d M Y, h:i A', strtotime($vendor['created_at']))) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$vendors): ?>
            <div class="list-item muted table-empty-state" style="display: block;">No vendor registrations found.</div>
        <?php endif; ?>
        <div class="list-item muted hidden table-empty-state" id="admin-vendors-empty">No vendors match your search.</div>
    </section>
    <?php
    render_footer();
}
