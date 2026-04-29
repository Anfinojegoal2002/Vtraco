<?php

declare(strict_types=1);

function auth_login_roles(): array
{
    return [
        'admin' => [
            'title' => 'Admin Login',
            'eyebrow' => 'Admin Access',
            'description' => 'Sign in to manage attendance, employees, rules, and reporting.',
        ],
        'employee' => [
            'label' => 'Employee',
            'title' => 'Employee Login',
            'eyebrow' => 'Employee Access',
            'description' => 'Use the credentials shared by your admin to access your attendance workspace.',
        ],
        'corporate_employee' => [
            'label' => 'Contractual Employee',
            'title' => 'Contractual Employee Login',
            'eyebrow' => 'Contractual Employee Access',
            'description' => 'Use your contractual employee registration details to access the employee workspace.',
        ],
        'external_vendor' => [
            'label' => 'External Vendor',
            'title' => 'External Vendor Login',
            'eyebrow' => 'Vendor Access',
            'description' => 'Use the credentials shared by your admin to access your vendor account.',
        ],
        'freelancer' => [
            'label' => 'Contractual Admin',
            'title' => 'Contractual Admin Login',
            'eyebrow' => 'Admin Access',
            'description' => 'Sign in to manage your contractual employees and attendance.',
        ],
        'super_admin' => [
            'label' => 'Super Admin',
            'title' => 'Super Admin Login',
            'eyebrow' => 'System Control',
            'description' => 'Sign in to manage companies, approvals, and system-wide settings.',
        ],
    ];
}

function auth_registration_roles(): array
{
    return [
        'admin' => [
            'label' => 'Admin',
            'eyebrow' => 'Admin Setup',
            'title' => 'Register Your Company',
            'description' => 'Register a management account for payroll, rules, attendance review, and employee operations.',
            'button' => 'Register Admin',
        ],
        'corporate_employee' => [
            'label' => 'Contractual Employee',
            'eyebrow' => 'Employee Setup',
            'title' => 'Register as Contractual',
            'description' => 'Self-register as a contractual employee to track your own attendance and view your payroll.',
            'button' => 'Register Employee',
        ],
    ];
}

function render_login(): void
{
    $roles = auth_login_roles();
    $role = (string) ($_GET['role'] ?? 'admin');
    if (!isset($roles[$role]) || !can_login_role($role)) {
        $role = 'admin';
    }
    $selected = $roles[$role];
    $resetEmail = filter_var((string) ($_GET['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string) $_GET['email'] : '';
    $resetMode = (string) ($_GET['reset'] ?? '');
    render_header($selected['title']);
    ?>
    <section class="auth-page-wrap">
    <div class="auth-panel">
        <div class="panel">
            <div class="split">
                <div>
                    <span class="eyebrow"><?= h($selected['eyebrow']) ?></span>
                    <h1><?= h($selected['title']) ?></h1>
                    <p><?= h($selected['description']) ?></p>
                </div>
            </div>
            <?php if ($role !== 'super_admin'): ?>
                <div class="cards-2 auth-role-grid">
                    <?php foreach ($roles as $key => $meta): ?>
                        <a class="action-card<?= $key === $role ? ' active-auth-role' : '' ?>" href="<?= h(BASE_URL) ?>?page=login&role=<?= h($key) ?>">
                            <strong><?= h((string) ($meta['label'] ?? user_role_label($key))) ?></strong>
                            <span class="hint"><?= h($meta['eyebrow']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="spacer"></div>
            <?php if ($resetMode === 'email' && in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor'], true)): ?>
                <div class="stack-form">
                    <h3>Forgot Password</h3>
                    <form method="post" class="stack-form" style="margin-top:14px;" data-validate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="forgot_password_send_otp">
                        <input type="hidden" name="role" value="<?= h($role) ?>">
                        <div class="field">
                            <label>Email</label>
                            <div class="field-row"><input type="email" name="forgot_email" placeholder="name@company.com" required></div>
                            <small class="field-error"><span>!</span>Enter the email linked to this account.</small>
                        </div>
                        <button class="button solid" type="submit">Send OTP</button>
                        <p class="hint">We will send a 6-digit OTP to this email.</p>
                    </form>
                    <a class="button ghost" href="<?= h(BASE_URL) ?>?page=login&role=<?= h($role) ?>">Back to login</a>
                </div>
            <?php elseif ($resetMode === 'otp' && in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor'], true)): ?>
                <div class="stack-form">
                    <h3>Enter OTP</h3>
                    <p class="hint">Enter the OTP sent to <?= h($resetEmail ?: 'your email') ?>.</p>
                    <form method="post" class="stack-form" style="margin-top:14px;" data-validate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="forgot_password_verify_otp">
                        <input type="hidden" name="role" value="<?= h($role) ?>">
                        <input type="hidden" name="forgot_email" value="<?= h($resetEmail) ?>">
                        <div class="field">
                            <label>OTP</label>
                            <div class="field-row"><input type="text" name="otp" inputmode="numeric" minlength="6" maxlength="6" pattern="\d{6}" placeholder="6-digit OTP" required></div>
                            <small class="field-error"><span>!</span>Enter the 6-digit OTP.</small>
                        </div>
                        <button class="button solid" type="submit">Verify OTP</button>
                    </form>
                    <a class="button ghost" href="<?= h(BASE_URL) ?>?page=login&role=<?= h($role) ?>&reset=email">Change email</a>
                </div>
            <?php elseif ($resetMode === 'password' && in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor'], true)): ?>
                <div class="stack-form">
                    <h3>Set New Password</h3>
                    <p class="hint">Create a new password for <?= h($resetEmail ?: 'your account') ?>.</p>
                    <form method="post" class="stack-form" style="margin-top:14px;" data-validate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="forgot_password_set_password">
                        <input type="hidden" name="role" value="<?= h($role) ?>">
                        <input type="hidden" name="forgot_email" value="<?= h($resetEmail) ?>">
                        <div class="field">
                            <label>New Password</label>
                            <div class="field-row"><input type="password" name="password" maxlength="6" placeholder="Max 6 characters" required></div>
                            <small class="field-error"><span>!</span>Password can be letters, numbers, or symbols. Max 6 characters.</small>
                        </div>
                        <div class="field">
                            <label>Confirm Password</label>
                            <div class="field-row"><input type="password" name="confirm_password" maxlength="6" placeholder="Repeat password" required></div>
                            <small class="field-error"><span>!</span>Please confirm the password.</small>
                        </div>
                        <button class="button solid" type="submit">Reset Password</button>
                    </form>
                </div>
            <?php else: ?>
                <form method="post" class="stack-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="role" value="<?= h($role) ?>">
                    <div class="field">
                        <label>Email</label>
                        <div class="field-row"><input type="email" name="email" placeholder="name@company.com" required></div>
                        <small class="field-error"><span>!</span>Enter a valid email address.</small>
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <div class="field-row">
                            <input id="login-password" type="password" name="password" placeholder="Enter your password" required>
                            <button class="password-toggle" type="button" data-password-toggle="login-password">Show</button>
                        </div>
                        <small class="field-error"><span>!</span>Password is required.</small>
                    </div>
                    <button class="button solid" type="submit"><?= h($selected['title']) ?></button>
                    <?php if (in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor'], true)): ?>
                        <a class="button ghost" href="<?= h(BASE_URL) ?>?page=login&role=<?= h($role) ?>&reset=email">Forgot your password?</a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
            <div class="spacer"></div>
            <?php if (in_array($role, ['employee', 'corporate_employee'], true)): ?>
                <p class="hint">Employees use credentials sent by their admin.</p>
            <?php elseif ($role === 'external_vendor'): ?>
                <p class="hint">External vendors use credentials shared by the admin.</p>
            <?php elseif ($role !== 'super_admin'): ?>
                <p><a href="<?= h(BASE_URL) ?>?page=register">Need an account? Open registration.</a></p>
            <?php endif; ?>
        </div>
    </div>
    </section>
    <?php
    render_footer();
}

function render_register(): void
{
    $roles = auth_registration_roles();
    $requestedRole = (string) ($_GET['role'] ?? 'admin');
    $selectedRole = array_key_exists($requestedRole, $roles) ? $requestedRole : 'admin';
    $selected = $roles[$selectedRole];
    render_header('Register Account', 'auth-showcase-page');
    ?>
    <section class="auth-page-wrap auth-showcase-wrap">
    <div class="auth-register-card">
        <div class="auth-register-brand">
            <img class="brand-mark auth-register-brand-mark" src="<?= h(asset_url('assets/images/vtraco-logo.svg')) ?>" alt="" aria-hidden="true">
            <div class="auth-register-brand-copy">
                <strong>V Traco</strong>
                <span>Attendance &amp; Payroll</span>
            </div>
        </div>

        <p class="auth-register-tagline">Fill in the form below to create an admin account.</p>

        <div class="auth-register-copy">
            <span class="eyebrow"><?= h($selected['eyebrow']) ?></span>
            <h1><?= h($selected['title']) ?></h1>
            <p><?= h($selected['description']) ?></p>
        </div>

        <form method="post" class="stack-form auth-register-form auth-register-form-sheet" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="register_user">
                <input type="hidden" name="role" value="<?= h($selectedRole) ?>">
            <div class="field">
                <label>Name</label>
                <div class="field-row"><input type="text" name="name" placeholder="<?= h((string) ($selected['label'] ?? user_role_label($selectedRole))) ?> name" required></div>
                <small class="field-error"><span>!</span>Name is required.</small>
            </div>
            <div class="field">
                <label>Email Address</label>
                <div class="field-row"><input type="email" name="email" placeholder="name@company.com" required></div>
                <small class="field-error"><span>!</span>Enter a valid email address.</small>
            </div>
            <div class="field">
                <label>Phone Number</label>
                <div class="field-row"><input type="text" name="phone" placeholder="Phone number" required></div>
                <small class="field-error"><span>!</span>Phone number is required.</small>
            </div>
            <div class="field">
                <label>Password</label>
                <div class="field-row">
                    <input id="register-password-<?= h($selectedRole) ?>" type="password" name="password" maxlength="6" placeholder="Max 6 characters" required>
                    <button class="password-toggle" type="button" data-password-toggle="register-password-<?= h($selectedRole) ?>">Show</button>
                </div>
                <small class="field-error"><span>!</span>Password can be letters, numbers, or symbols. Max 6 characters.</small>
            </div>
            <div class="field">
                <label>Confirm Password</label>
                <div class="field-row">
                    <input id="register-confirm-password-<?= h($selectedRole) ?>" type="password" name="confirm_password" maxlength="6" placeholder="Repeat password" required>
                    <button class="password-toggle" type="button" data-password-toggle="register-confirm-password-<?= h($selectedRole) ?>">Show</button>
                </div>
                <small class="field-error"><span>!</span>Please confirm the password.</small>
            </div>
            <button class="button solid auth-register-submit" type="submit"><?= h($selected['button']) ?></button>
            <div class="auth-register-footer">
                <span>Already have an account?</span>
                <a class="auth-register-link" href="<?= h(BASE_URL) ?>?page=login&role=<?= h($selectedRole) ?>">Login</a>
            </div>
        </form>
    </div>
    </section>
    <?php
    render_footer();
}

function render_member_dashboard(): void
{
    $user = require_roles(['external_vendor', 'freelancer']);
    $page = $_GET['tab'] ?? 'dashboard';
    
    // Get employees assigned to this vendor
    $vendorId = (int) $user['id'];
    $stmt = db()->prepare("SELECT * FROM users WHERE admin_id = :vendor_id AND role IN ('employee') ORDER BY name");
    $stmt->execute(['vendor_id' => $vendorId]);
    $allEmployees = $stmt->fetchAll();
    $employeeCount = count($allEmployees);
    
    render_header('Dashboard');
    ?>

    <?php if ($page === 'dashboard'): ?>
        <section class="section-block">
            <div class="split">
                <div>
                    <span class="eyebrow">Employee Summary</span>
                    <h2>Team Overview</h2>
                </div>
            </div>
            <div class="spacer"></div>
            <?php
            $totalSalary = 0;
            foreach ($allEmployees as $emp) {
                $totalSalary += (float) ($emp['salary'] ?? 0);
            }
            $avgSalary = $employeeCount > 0 ? $totalSalary / $employeeCount : 0;
            ?>
            <div class="dashboard-grid">
                <div class="metric-card">
                    <span class="eyebrow">Total Employees</span>
                    <strong><?= $employeeCount ?></strong>
                    <span>Active Team Members</span>
                </div>
            </div>
        </section>

        <div class="spacer"></div>
        <section class="section-block">
            <span class="eyebrow">Quick Actions</span>
            <h2>Manage Your Team</h2>
            <p>Use the Employees tab to add new team members or import employees in bulk. Use the Attendance tab to track your team's daily attendance and performance.</p>
        </section>

    <?php elseif ($page === 'employees'): ?>
        <section class="page-title">
            <div>
                <span class="eyebrow">Team Management</span>
                <h2>Your Employees</h2>
                <p>Add employees manually, import a CSV batch, and manage your team members.</p>
            </div>
            <div class="action-bar">
                <button class="button outline" type="button" data-modal-target="vendor-employee-csv-modal">Bulk Import</button>
                <button class="button solid" type="button" data-modal-target="vendor-add-employee-modal">Add Employee</button>
            </div>
        </section>

        <section class="section-block">
            <div class="split">
                <div>
                    <span class="eyebrow">Your Team</span>
                    <h2>Employees List</h2>
                </div>
                <span class="badge"><?= $employeeCount ?> total</span>
            </div>
            <div class="spacer"></div>
            <div class="list-item muted">Employee list management is available through the Add and Import options above.</div>
        </section>

        <div class="modal" id="vendor-add-employee-modal">
            <div class="modal-card">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Add Employee</span>
                <h2>Add New Employee</h2>
                <form method="post" class="stack-form" data-validate data-watch-required>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="vendor_add_employee">
                    <div class="reports-filter-grid">
                        <div class="field"><label>Emp ID</label><div class="field-row"><input type="text" name="emp_id" required></div><small class="field-error"><span>!</span>Emp ID is required.</small></div>
                        <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                        <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" required></div><small class="field-error"><span>!</span>Valid email required.</small></div>
                        <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" required></div><small class="field-error"><span>!</span>Phone number required.</small></div>
                        <div class="field"><label>Shift</label><div class="field-row"><input type="text" name="shift" placeholder="Enter shift (optional)"></div></div>
                        <div class="field"><label>Salary</label><div class="field-row"><input type="number" step="0.01" min="0" name="salary" required></div><small class="field-error"><span>!</span>Salary is required.</small></div>
                    </div>
                    <div class="inline-actions">
                        <button class="button outline" type="button" data-close-modal>Cancel</button>
                        <button class="button solid" type="submit">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal" id="vendor-employee-csv-modal">
            <div class="modal-card" style="max-width:720px;">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Bulk Import</span>
                <h2>Import Employees from CSV</h2>
                <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="vendor_upload_csv">
                    <label class="upload-drop">
                        <strong>Select employee CSV</strong>
                        <p>Upload a CSV with employee details. Supported columns include ID, Name, Email, Phone, Shift, and Salary. The CSV should have a header row with column names.</p>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </label>
                    <button class="button solid" type="submit">Upload CSV</button>
                </form>
            </div>
        </div>

    <?php elseif ($page === 'attendance'): ?>
        <?php
        $selectedEmployeeId = (int) ($_GET['employee_id'] ?? 0);
        $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
        $selectedEmployee = null;
        
        if ($selectedEmployeeId && $allEmployees) {
            foreach ($allEmployees as $emp) {
                if ((int) $emp['id'] === $selectedEmployeeId) {
                    $selectedEmployee = $emp;
                    break;
                }
            }
        }
        
        $monthAttendance = $selectedEmployee ? month_attendance_for_user((int) $selectedEmployee['id'], $month) : [];
        ?>
        <section class="section-block scroll-panel">
            <form method="get" class="form-grid">
                <input type="hidden" name="page" value="member_dashboard">
                <input type="hidden" name="tab" value="attendance">
                <label>Employee
                    <select name="employee_id" onchange="this.form.submit()">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= (int) $emp['id'] ?>" <?= $selectedEmployeeId === (int) $emp['id'] ? 'selected' : '' ?>><?= h($emp['name']) ?> (<?= h($emp['emp_id']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Month<input type="month" name="month" value="<?= h($month) ?>" onchange="this.form.submit()"></label>
            </form>
        </section>

        <div class="spacer"></div>
        
        <?php if ($selectedEmployee && $monthAttendance): ?>
            <section class="attendance-panel">
                <div class="attendance-header">
                    <h2><?= h($selectedEmployee['name']) ?> - <?= h(date('F Y', strtotime($month . '-01'))) ?></h2>
                </div>
                <div class="spacer"></div>
                <?php render_calendar('vendor', $selectedEmployee, $month, $monthAttendance); ?>
            </section>
        <?php elseif (!$selectedEmployee): ?>
            <section class="section-block">
                <div class="list-item muted">Select an employee from the dropdown above to view their attendance calendar.</div>
            </section>
        <?php else: ?>
            <section class="section-block">
                <div class="list-item muted">No attendance data available for the selected employee and month.</div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
    <?php
    render_footer();
}
