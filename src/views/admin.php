<?php

declare(strict_types=1);

function render_admin_dashboard(): void
{
    require_role('admin');
    $leaveRequests = recent_leave_requests();
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
    require_role('admin');
    $stage = $_GET['stage'] ?? '';
    $pendingEmployee = $_SESSION['pending_employee'] ?? null;
    $pendingCsv = $_SESSION['pending_csv_import'] ?? [];
    $editId = (int) ($_GET['edit'] ?? 0);
    $editEmployee = $editId ? employee_by_id($editId) : null;
    $allEmployees = employees();
    $leaveRequests = recent_leave_requests();

    render_header('Employees');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Employees</span>
            <h1>Employees</h1>
            <p>Add employees manually, import a CSV batch, update records, and manage only the employees assigned to this administrator.</p>
        </div>
        <div class="action-bar">
            <button class="button outline" type="button" data-modal-target="employee-csv-modal">Bulk Import</button>
            <button class="button solid" type="button" data-modal-target="add-employee-modal">Add Employee</button>
        </div>
    </section>
    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2>Your Employees</h2>
                <span class="badge" id="admin-employees-count"><?= count($allEmployees) ?> total</span>
            </div>
            <div class="data-toolbar-search">
                <input type="text" placeholder="Search by Emp ID, name, email, phone, shift, or rule..." data-table-filter="admin-employees-table" data-empty-target="admin-employees-empty" data-count-target="admin-employees-count">
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
                <?php foreach ($allEmployees as $employee):
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
                <button class="modal-close" type="button" data-close-modal onclick="window.location='<?= h(BASE_URL) ?>?page=admin_employees'">&times;</button>
                <span class="eyebrow">Edit Employee</span>
                <h2><?= h($editEmployee['name']) ?></h2>
                <form method="post" class="stack-form">
                    <input type="hidden" name="action" value="employee_update">
                    <input type="hidden" name="user_id" value="<?= (int) $editEmployee['id'] ?>">
                    <div class="form-grid">
                        <label>Emp ID<input type="text" name="emp_id" value="<?= h($editEmployee['emp_id']) ?>" required></label>
                        <label>Name<input type="text" name="name" value="<?= h($editEmployee['name']) ?>" required></label>
                        <label>Email<input type="email" name="email" value="<?= h($editEmployee['email']) ?>" required></label>
                        <label>Phone Number<input type="text" name="phone" value="<?= h($editEmployee['phone']) ?>" required></label>
                        <label>Shift<input type="text" name="shift" value="<?= h((string) ($editEmployee['shift'] ?? '')) ?>" placeholder="Enter shift"></label>
                        <label>Salary<input type="number" step="0.01" name="salary" value="<?= h((string) $editEmployee['salary']) ?>" required></label>
                    </div>
                    <div class="inline-actions">
                        <button class="button solid" type="submit">Save Changes</button>
                        <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_employees">Cancel</a>
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
    <div class="modal <?= $stage === 'csv_rules' ? 'open' : '' ?>" id="employee-csv-modal" <?= $stage === 'csv_rules' ? 'data-open-on-load' : '' ?>>
        <div class="modal-card" style="max-width:720px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <?php if ($stage === 'csv_rules' && $pendingCsv): ?>
                <div class="steps">
                    <span class="step-pill">Step 1 of 2</span>
                    <span class="step-pill active">Step 2 of 2</span>
                </div>
                <span class="eyebrow">Bulk Import</span>
                <h2>Assign Rules to Imported Employees</h2>
                <p><?= count($pendingCsv) ?> employee row(s) are ready. Review the sample below, then choose the rules to apply to every imported employee.</p>
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
                    <input type="hidden" name="action" value="employee_csv_submit">
                    <h3>Rules Assignment</h3>
                    <?php render_rules_editor(); ?>
                    <button class="button solid" type="submit" data-rule-submit>Import Employees</button>
                </form>
            <?php else: ?>
                <div class="steps">
                    <span class="step-pill active">Step 1 of 2</span>
                    <span class="step-pill">Step 2 of 2</span>
                </div>
                <span class="eyebrow">Bulk Import</span>
                <h2>Import Employees from CSV</h2>
                <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                    <input type="hidden" name="action" value="employee_csv_upload">
                    <label class="upload-drop">
                        <strong>Select employee CSV</strong>
                        <p>Upload a CSV with employee details. Supported columns include Emp ID, Name, Email, Phone, and Salary. Missing Emp ID or Name can be generated automatically.</p>
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
                <input type="hidden" name="action" value="admin_add_shift_timing">
                <input type="hidden" name="start_time" value="09:00">
                <input type="hidden" name="end_time" value="18:00">
                <button class="button outline" type="submit">Add 9:00 AM - 6:00 PM</button>
            </form>
            <form method="post">
                <input type="hidden" name="action" value="admin_add_shift_timing">
                <input type="hidden" name="start_time" value="10:30">
                <input type="hidden" name="end_time" value="20:30">
                <button class="button outline" type="submit">Add 10:30 AM - 8:30 PM</button>
            </form>
        </div>
        <div class="spacer"></div>
        <form method="post" class="stack-form" data-validate>
            <input type="hidden" name="action" value="admin_add_shift_timing">
            <div class="form-grid">
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
    require_role('admin');
    $allEmployees = employees();
    $fallbackEmployee = $allEmployees[0] ?? null;
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
            <span class="eyebrow">Admin - Attendance</span>
            <h1>Attendance Calendar</h1>
            <p>All Sundays are marked as Week Off automatically. Click any date to inspect sessions and edit the daily status.</p>
        </div>
        <div class="action-bar">
            <button class="button outline" type="button" data-modal-target="attendance-import-modal">Bulk Import</button>
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
    <div class="modal" id="attendance-import-modal">
        <div class="modal-card" style="max-width:720px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Attendance Import</span>
            <h2>Bulk Import Attendance</h2>
            <p>Upload the daily performance report from Excel or CSV. The importer reads employee rows using Empcode or Name, along with INTime, OUTTime, Status, and Remark, to mark attendance.</p>
            <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
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
                    <input id="admin-new-password" type="password" name="new_password" minlength="6" placeholder="Minimum 6 characters" required>
                    <button class="password-toggle" type="button" data-password-toggle="admin-new-password">Show</button>
                </div>
                <small class="field-error"><span>!</span>New password must be at least 6 characters.</small>
            </div>
            <div class="field">
                <label>Confirm Password</label>
                <div class="field-row">
                    <input id="admin-confirm-password" type="password" name="confirm_password" minlength="6" placeholder="Repeat new password" required>
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
