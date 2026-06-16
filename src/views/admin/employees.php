<?php

declare(strict_types=1);

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
        <div class="modal-card employee-rules-modal-card employee-detail-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="employee-detail-hero">
                <div class="employee-detail-avatar"><?= h(user_initials((string) ($employee['name'] ?? 'Employee'))) ?></div>
                <div class="employee-detail-title">
                    <span class="eyebrow">Employee Details</span>
                    <h2><?= h((string) $employee['name']) ?></h2>
                    <div class="employee-detail-submeta">
                        <span><?= h($displayValue($employee, 'emp_id')) ?></span>
                        <span><?= h(user_role_label((string) ($employee['role'] ?? 'employee'))) ?></span>
                        <span><?= h($displayValue($employee, 'designation')) ?></span>
                    </div>
                </div>
                <div class="employee-detail-badges">
                    <span class="status-pill status-<?= h((string) ($employee['status'] ?? 'ACTIVE')) ?>"><?= h((string) ($employee['status'] ?? 'ACTIVE')) ?></span>
                    <span class="status-pill status-<?= h(ucfirst((string) ($employee['profile_status'] ?? 'incomplete'))) ?>">Profile <?= h(ucfirst((string) ($employee['profile_status'] ?? 'incomplete'))) ?></span>
                </div>
            </div>
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
                            $url = $path !== '' ? public_file_path($path) : '';
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
                        <option value="<?= h($shiftOption) ?>" <?= normalize_shift_selection((string) ($defaults['shift'] ?? '')) === $shiftOption ? 'selected' : '' ?>><?= h(str_replace('-', 'â€“', $shiftOption)) ?></option>
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


function render_project_assignment_picker(array $selectedProjectIds = [], string $filterId = 'project-assignment-options', array $assignmentRanges = [], ?array $targetEmployee = null): void
{
    $allProjects = active_projects();
    $selectedLookup = array_fill_keys(array_map('intval', $selectedProjectIds), true);
    $targetEmployeeType = strtolower(trim((string) ($targetEmployee['employee_type'] ?? '')));
    $targetEmployeeRole = strtolower(trim((string) ($targetEmployee['role'] ?? '')));
    $targetEmployeeDesignation = strtolower(trim((string) ($targetEmployee['designation'] ?? '')));
    $usesContractualDailySalary = $targetEmployee !== null
        && ($targetEmployeeRole === 'corporate_employee' || $targetEmployeeType === 'corporate' || $targetEmployeeDesignation === 'contractual');
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
                            <?php if ($usesContractualDailySalary): ?>
                                <input type="hidden" name="project_incentive[<?= $projectId ?>]" value="<?= h($projectIncentive) ?>">
                                <label>Contractual Employee Daily Salary<input type="number" min="0" step="0.01" name="project_daily_salary[<?= $projectId ?>]" value="<?= h($projectDailySalary) ?>" placeholder="0.00"></label>
                            <?php else: ?>
                                <label>Incentive Per Session<input type="number" min="0" step="0.01" name="project_incentive[<?= $projectId ?>]" value="<?= h($projectIncentive) ?>" placeholder="0.00"></label>
                                <input type="hidden" name="project_daily_salary[<?= $projectId ?>]" value="<?= h($projectDailySalary) ?>">
                            <?php endif; ?>
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
    $vendorProjects = db()->query("SELECT vendor_id, vendor_name, project_name FROM projects WHERE vendor_id IS NOT NULL OR TRIM(COALESCE(vendor_name, '')) <> '' ORDER BY project_name ASC")->fetchAll();
    $vendorProjectLookup = [];
    foreach ($vendorProjects as $project) {
        $vendorId = (int) ($project['vendor_id'] ?? 0);
        $vendorKey = strtolower(trim((string) ($project['vendor_name'] ?? '')));
        $projectName = trim((string) ($project['project_name'] ?? ''));
        if ($vendorId > 0 && $projectName !== '') {
            $vendorProjectLookup['id:' . $vendorId][$projectName] = true;
        }
        if ($vendorKey !== '' && $projectName !== '') {
            $vendorProjectLookup[$vendorKey][$projectName] = true;
        }
    }

    return $vendorProjectLookup;
}


function vendor_project_lookup_keys(array $vendor): array
{
    $keys = [];
    $vendorId = (int) ($vendor['id'] ?? 0);
    if ($vendorId > 0) {
        $keys[] = 'id:' . $vendorId;
    }

    foreach (['name', 'company_name', 'representative_name', 'email'] as $field) {
        $value = strtolower(trim((string) ($vendor[$field] ?? '')));
        if ($value !== '') {
            $keys[] = $value;
        }
    }

    return array_values(array_unique($keys));
}


function vendor_project_names_for_vendor(array $vendor, array $vendorProjectLookup): array
{
    $projectNames = [];
    foreach (vendor_project_lookup_keys($vendor) as $key) {
        foreach (($vendorProjectLookup[$key] ?? []) as $projectName => $_) {
            $projectNames[$projectName] = true;
        }
    }

    return array_keys($projectNames);
}


function render_vendor_accounts_table(array $vendors, string $tableId, string $emptyId, bool $showSearch = false, string $returnPage = 'admin_employees'): void
{
    $returnPage = in_array($returnPage, ['admin_employees', 'admin_vendors'], true) ? $returnPage : 'admin_employees';
    $vendorProjectLookup = vendor_project_lookup();
    $allProjects = active_projects();
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
                    $vendorKeys = vendor_project_lookup_keys($vendor);
                    $projectNames = vendor_project_names_for_vendor($vendor, $vendorProjectLookup);
                    $projectLabel = $projectNames ? implode(', ', $projectNames) : '-';
                    $searchText = strtolower(implode(' ', [
                        $projectLabel,
                        (string) $vendor['name'],
                        (string) $vendor['email'],
                        (string) $vendor['phone'],
                    ]));
                ?>
                <tr data-filter-row data-filter-text="<?= h($searchText) ?>">
                    <td data-label="Project">
                        <?php if ($projectNames): ?>
                            <?= h($projectLabel) ?>
                        <?php else: ?>
                            <button class="button outline small" type="button" data-modal-target="<?= h($tableId) ?>-assign-modal-<?= $vendorId ?>">Assign Project</button>
                        <?php endif; ?>
                    </td>
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
            $vendorKeys = vendor_project_lookup_keys($vendor);
            $projectNames = vendor_project_names_for_vendor($vendor, $vendorProjectLookup);
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
        <div class="modal" id="<?= h($tableId) ?>-assign-modal-<?= $vendorId ?>">
            <div class="modal-card employee-rules-modal-card">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Vendor Projects</span>
                <h2>Assign Project</h2>
                <p class="hint"><?= h((string) $vendor['name']) ?> will be linked to the selected projects.</p>
                <form method="post" class="stack-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="admin_vendor_project_assign">
                    <input type="hidden" name="vendor_id" value="<?= $vendorId ?>">
                    <input type="hidden" name="redirect_page" value="<?= h($returnPage) ?>">
                    <div class="employee-picker">
                        <div class="split">
                            <strong>Assigned Projects</strong>
                            <span class="hint">Select projects managed by this vendor.</span>
                        </div>
                        <?php if ($allProjects): ?>
                            <input type="text" placeholder="Search projects..." data-employee-filter="<?= h($tableId) ?>-vendor-project-options-<?= $vendorId ?>">
                            <div class="employee-options" id="<?= h($tableId) ?>-vendor-project-options-<?= $vendorId ?>">
                                <?php foreach ($allProjects as $project): ?>
                                    <?php
                                        $projectId = (int) ($project['id'] ?? 0);
                                        $projectVendorId = (int) ($project['vendor_id'] ?? 0);
                                        $projectVendorName = strtolower(trim((string) ($project['vendor_name'] ?? '')));
                                        $isChecked = $projectVendorId === $vendorId || ($projectVendorId <= 0 && $projectVendorName !== '' && in_array($projectVendorName, $vendorKeys, true));
                                        $detailParts = array_values(array_filter([
                                            trim((string) ($project['college_name'] ?? '')),
                                            trim((string) ($project['location'] ?? '')),
                                        ]));
                                    ?>
                                    <label class="employee-option">
                                        <input type="checkbox" name="project_ids[]" value="<?= $projectId ?>" <?= $isChecked ? 'checked' : '' ?>>
                                        <span>
                                            <?= h((string) ($project['project_name'] ?? 'Project')) ?><br>
                                            <small class="hint"><?= h($detailParts ? implode(' | ', $detailParts) : 'Active') ?></small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="list-item muted">No verified active projects are available yet. Add or verify projects first in the Projects page.</div>
                        <?php endif; ?>
                    </div>
                    <div class="inline-actions">
                        <button class="button outline" type="button" data-close-modal>Cancel</button>
                        <button class="button solid" type="submit">Save Project Assignment</button>
                    </div>
                </form>
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
                                    <button class="button ghost small" type="button" data-lazy-employee-details="<?= $employeeId ?>" data-modal-id="<?= h($rulesModalId) ?>">View</button>
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
                            <?php render_project_assignment_picker($selectedProjectIds, 'team-project-assignment-options-' . $employeeId, $assignmentRanges, $employee); ?>
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
                            <input type="text" name="designation" value="<?= h((string) ($editEmployee['designation'] ?? '')) ?>" <?= $editUsesManagedFields ? 'disabled' : 'required' ?>>
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


