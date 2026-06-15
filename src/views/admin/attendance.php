<?php

declare(strict_types=1);

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
        <form method="post" class="stack-form" data-validate autocomplete="off">
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
    $biometricBaseUrl = '';
    $biometricCorporateId = '';
    $biometricUsername = '';
    $biometricEnabled = $biometricIntegration ? !empty($biometricIntegration['is_enabled']) : false;
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
                <input type="checkbox" name="is_enabled" value="1">
                <span>Enable automatic eTime Office attendance sync</span>
            </label>
            <div class="reports-filter-grid">
                <div class="field">
                    <label>API Base URL</label>
                    <div class="field-row"><input type="url" name="base_url" value="<?= h($biometricBaseUrl) ?>" autocomplete="off" required></div>
                    <small class="field-error"><span>!</span>Base URL is required.</small>
                </div>
                <div class="field">
                    <label>Corporate ID</label>
                    <div class="field-row"><input type="text" name="corporate_id" value="<?= h($biometricCorporateId) ?>" autocomplete="off" required></div>
                    <small class="field-error"><span>!</span>Corporate ID is required.</small>
                </div>
                <div class="field">
                    <label>Username</label>
                    <div class="field-row"><input type="text" name="username" value="<?= h($biometricUsername) ?>" autocomplete="off" required></div>
                    <small class="field-error"><span>!</span>Username is required.</small>
                </div>
                <div class="field">
                    <label>Password</label>
                    <div class="field-row"><input type="password" name="password" autocomplete="new-password" placeholder="Enter eTime password" required></div>
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


