<?php

declare(strict_types=1);

function render_admin_dashboard(): void
{
    require_role('admin');
    $snapshot = attendance_snapshot_for_date();
    $counts = $snapshot['counts'];
    $details = $snapshot['details'];
    $displayDate = date('d M Y', strtotime($snapshot['date']));

    render_header('Admin Dashboard');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin Dashboard</span>
            <h1>Admin Overview</h1>
            <p>Review today&apos;s attendance totals, employee coverage, and half day or leave details for <?= h($displayDate) ?>.</p>
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
    <?php
    render_footer();
}

function render_rules_editor(array $existing = []): void
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
            <label class="rule-card <?= $defaults['manual_punch_in'] ? 'active' : '' ?>">
                <input type="checkbox" name="manual_punch_in" value="1" <?= $defaults['manual_punch_in'] ? 'checked' : '' ?>>
                <span class="rule-icon">MI</span>
                <strong>Manual Punch In</strong>
                <span class="hint">Allow employees to submit a geo-tagged punch-in photo.</span>
            </label>
            <label class="rule-card <?= $defaults['manual_punch_out'] ? 'active' : '' ?>">
                <input type="checkbox" name="manual_punch_out" value="1" <?= $defaults['manual_punch_out'] ? 'checked' : '' ?>>
                <span class="rule-icon">MO</span>
                <strong>Manual Punch Out</strong>
                <span class="hint">Allow employees to submit session details at the end of work.</span>
            </label>
            <label class="rule-card <?= $defaults['biometric_punch_in'] ? 'active' : '' ?>">
                <input type="checkbox" name="biometric_punch_in" value="1" <?= $defaults['biometric_punch_in'] ? 'checked' : '' ?>>
                <span class="rule-icon">BI</span>
                <strong>Biometric Punch In</strong>
                <span class="hint">Enable biometric punch-in time capture.</span>
            </label>
            <label class="rule-card <?= $defaults['biometric_punch_out'] ? 'active' : '' ?>">
                <input type="checkbox" name="biometric_punch_out" value="1" <?= $defaults['biometric_punch_out'] ? 'checked' : '' ?>>
                <span class="rule-icon">BO</span>
                <strong>Biometric Punch Out</strong>
                <span class="hint">Enable biometric punch-out time capture.</span>
            </label>
        </div>
        <div class="split align-end">
            <label>Manual punch slots<input id="manual-out-count" type="number" min="1" name="manual_out_count" value="<?= h((string) $defaults['manual_out_count']) ?>"></label>
            <button class="button outline small" type="button" data-add-manual-slot data-target="#manual-out-count">+ Add Manual Punch</button>
        </div>
    </div>
    <?php
}

function render_admin_employees(): void
{
    require_role('admin');
    $stage = $_GET['stage'] ?? '';
    $pendingEmployee = $_SESSION['pending_employee'] ?? null;
    $pendingCsv = $_SESSION['pending_csv_import'] ?? [];
    $editId = (int) ($_GET['edit'] ?? 0);
    $editEmployee = $editId ? employee_by_id($editId) : null;
    $allEmployees = employees();

    render_header('Employees');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Employees</span>
            <h1>Employees</h1>
            <p>Add employees manually, import a CSV batch, update records, and manage stored employee data.</p>
        </div>
        <div class="action-bar">
            <button class="button outline" type="button" data-modal-target="bulk-import-modal">Bulk Import</button>
            <button class="button solid" type="button" data-modal-target="add-employee-modal">Add Employee</button>
        </div>
    </section>
    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2>All Employees</h2>
                <span class="badge" id="admin-employees-count"><?= count($allEmployees) ?> total</span>
            </div>
            <div class="data-toolbar-search">
                <input type="text" placeholder="Search by Emp ID, name, email, phone, or rule..." data-table-filter="admin-employees-table" data-empty-target="admin-employees-empty" data-count-target="admin-employees-count">
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Salary</th>
                    <th>Rules</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="admin-employees-table">
                <?php foreach ($allEmployees as $employee):
                    $rules = employee_rules((int) $employee['id']);
                    $rulesMarkup = rules_summary($rules);
                    $rulesText = strtolower(trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<br>', ' ', $rulesMarkup)))));
                    $searchText = strtolower(implode(' ', [
                        (string) $employee['emp_id'],
                        (string) $employee['name'],
                        (string) $employee['email'],
                        (string) $employee['phone'],
                        $rulesText,
                    ]));
                ?>
                    <tr data-filter-row data-filter-text="<?= h($searchText) ?>">
                        <td><?= h($employee['emp_id']) ?></td>
                        <td><?= h($employee['name']) ?></td>
                        <td><?= h($employee['email']) ?></td>
                        <td><?= h($employee['phone']) ?></td>
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
        <div class="spacer"></div>
        <section class="section-block">
            <h2>Edit Employee</h2>
            <form method="post" class="stack-form">
                <input type="hidden" name="action" value="employee_update">
                <input type="hidden" name="user_id" value="<?= (int) $editEmployee['id'] ?>">
                <div class="form-grid">
                    <label>Emp ID<input type="text" name="emp_id" value="<?= h($editEmployee['emp_id']) ?>" required></label>
                    <label>Name<input type="text" name="name" value="<?= h($editEmployee['name']) ?>" required></label>
                    <label>Email<input type="email" name="email" value="<?= h($editEmployee['email']) ?>" required></label>
                    <label>Phone Number<input type="text" name="phone" value="<?= h($editEmployee['phone']) ?>" required></label>
                    <label>Salary<input type="number" step="0.01" name="salary" value="<?= h((string) $editEmployee['salary']) ?>" required></label>
                </div>
                <button class="button solid" type="submit">Save Changes</button>
            </form>
        </section>
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
                <h2>Add Employee</h2>
                <form method="post" class="stack-form" data-validate data-watch-required>
                    <input type="hidden" name="action" value="employee_manual_next">
                    <div class="form-grid">
                        <div class="field"><label>Emp ID</label><div class="field-row"><input type="text" name="emp_id" required></div><small class="field-error"><span>!</span>Emp ID is required.</small></div>
                        <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                        <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" required></div><small class="field-error"><span>!</span>Valid email required.</small></div>
                        <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" required></div><small class="field-error"><span>!</span>Phone number required.</small></div>
                        <div class="field"><label>Salary</label><div class="field-row"><input type="number" step="0.01" min="0" name="salary" required></div><small class="field-error"><span>!</span>Salary is required.</small></div>
                    </div>
                    <button class="button solid" type="submit" data-required-submit>Next</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="modal <?= $stage === 'csv_rules' ? 'open' : '' ?>" id="bulk-import-modal" <?= $stage === 'csv_rules' ? 'data-open-on-load' : '' ?>>
        <div class="modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <h2>Bulk Import Employees</h2>
            <?php if ($stage === 'csv_rules' && $pendingCsv): ?>
                <div class="steps">
                    <span class="step-pill">Upload Complete</span>
                    <span class="step-pill active">Assign Rules</span>
                </div>
                <div class="preview-table">
                    <table>
                        <thead><tr><th>Emp ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Salary</th></tr></thead>
                        <tbody>
                        <?php foreach ($pendingCsv as $row): ?>
                            <tr>
                                <td><?= h($row['emp_id']) ?></td>
                                <td><?= h($row['name']) ?></td>
                                <td><?= h($row['email']) ?></td>
                                <td><?= h($row['phone']) ?></td>
                                <td><?= h(number_format((float) $row['salary'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="spacer"></div>
                <form method="post" class="stack-form" data-rule-form>
                    <input type="hidden" name="action" value="employee_csv_submit">
                    <?php render_rules_editor(); ?>
                    <button class="button solid" type="submit" data-rule-submit>Submit CSV Import</button>
                </form>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                    <input type="hidden" name="action" value="employee_csv_upload">
                    <label class="upload-drop">
                        <strong>Drop your CSV file here or click to browse</strong>
                        <p>Expected headers: Emp ID, Name, Email, Phone Number, Salary</p>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </label>
                    <button class="button secondary" type="submit">Upload CSV</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="modal" id="delete-employee-modal">
        <div class="modal-card" style="max-width:560px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Confirm Delete</span>
            <h2>Delete Employee</h2>
            <p>This will permanently remove <strong data-delete-name>employee</strong> and related attendance records.</p>
            <form method="post" class="inline-actions">
                <input type="hidden" name="action" value="employee_delete">
                <input type="hidden" name="user_id" value="">
                <button class="button outline" type="button" data-close-modal>Cancel</button>
                <button class="button secondary" type="submit">Delete Employee</button>
            </form>
        </div>
    </div>
    <?php
    render_footer();
}

function render_admin_rules(): void
{
    require_role('admin');
    render_header('Rules');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Rules</span>
            <h1>Assign Rules</h1>
            <p>Select one or more employees, apply the allowed punch methods, and email the rule update instantly.</p>
        </div>
    </section>
    <section class="section-block scroll-panel">
        <form method="post" class="stack-form" data-rule-form data-employee-form>
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
            <?php render_rules_editor(); ?>
            <button class="button solid" type="submit" data-rule-submit>Apply</button>
        </form>
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
        <div class="calendar-grid scroll-panel">
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                <div class="weekday"><?= h($weekday) ?></div>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $offset; $i++): ?>
                <div class="day-card blank"></div>
            <?php endfor; ?>
            <?php foreach ($monthAttendance as $date => $entry): $statusClass = str_replace(' ', '-', $entry['record']['status']); ?>
                <button class="day-card" type="button" data-attendance="<?= calendar_payload($context, $employee, $date, $entry) ?>">
                    <span class="day-number"><?= date('d', strtotime($date)) ?></span>
                    <span class="day-dot dot-<?= h($statusClass) ?>"></span>
                    <span class="status-pill status-<?= h($statusClass) ?>"><?= h($entry['record']['status']) ?></span>
                    <span class="hint"><?= count($entry['sessions']) ?> session(s)</span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="calendar-summary">
            <div class="summary-card"><strong><?= count($monthAttendance) ?></strong><span>Total Days in Month</span></div>
            <div class="summary-card"><strong><?= rtrim(rtrim(number_format(working_days_total($monthAttendance), 1), '0'), '.') ?></strong><span>Total Working Days</span></div>
            <div class="summary-card summary-highlight"><strong>Rs <?= number_format(salary_for_month((float) $employee['salary'], $monthAttendance), 2) ?></strong><span>Calculated Salary</span></div>
        </div>
    </div>
    <?php
}

function render_admin_attendance(): void
{
    require_role('admin');
    $allEmployees = employees();
    $fallbackEmployee = $allEmployees[0] ?? null;
    $selectedId = (int) ($_GET['employee_id'] ?? ($fallbackEmployee['id'] ?? 0));
    $employee = $selectedId ? employee_by_id($selectedId) : $fallbackEmployee;
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');

    render_header('Attendance');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Attendance</span>
            <h1>Attendance Calendar</h1>
            <p>All Sundays are marked as Week Off automatically. Click any date to inspect sessions and edit the daily status.</p>
        </div>
    </section>
    <section class="section-block scroll-panel">
        <form method="get" class="form-grid">
            <input type="hidden" name="page" value="admin_attendance">
            <label>Employee
                <select name="employee_id">
                    <?php foreach ($allEmployees as $emp): ?>
                        <option value="<?= (int) $emp['id'] ?>" <?= $employee && (int) $employee['id'] === (int) $emp['id'] ? 'selected' : '' ?>><?= h($emp['name']) ?> (<?= h($emp['emp_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Month<input type="month" name="month" value="<?= h($month) ?>"></label>
            <div class="split align-end"><button class="button solid" type="submit">View Attendance</button></div>
        </form>
    </section>
    <div class="spacer"></div>
    <?php if ($employee): ?>
        <div class="attendance-panel">
            <?php render_calendar('admin', $employee, $month, month_attendance_for_user((int) $employee['id'], $month)); ?>
        </div>
    <?php else: ?>
        <section class="section-block"><p>No employees found. Add employees first.</p></section>
    <?php endif; ?>
    <?php
    render_footer();
}

