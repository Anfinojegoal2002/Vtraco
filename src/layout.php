<?php

declare(strict_types=1);

function render_page(string $page): void
{
    switch ($page) {
        case 'login':
            render_login();
            break;
        case 'register':
            render_register();
            break;
        case 'admin_dashboard':
            render_admin_dashboard();
            break;
        case 'admin_employees':
            render_admin_employees();
            break;
        case 'admin_projects':
            render_admin_projects();
            break;
        case 'admin_rules':
            render_admin_rules();
            break;
        case 'admin_shift':
            render_admin_shift();
            break;
        case 'admin_attendance':
            render_admin_attendance();
            break;
        case 'admin_profile_settings':
            render_admin_profile_settings();
            break;
        case 'admin_reports':
            render_admin_reports();
            break;
        case 'employee_attendance':
            render_employee_attendance();
            break;
        case 'member_dashboard':
            render_member_dashboard();
            break;
        default:
            render_landing();
            break;
    }
}

function render_header(string $title, string $pageClass = ''): void
{
    $user = current_user();
    $page = $_GET['page'] ?? 'landing';
    $isAdminShell = $user && in_array($user['role'], ['admin', 'freelancer'], true);
    $isEmployeeShell = $user && $user['role'] === 'employee';
    $isSidebarShell = $isAdminShell || $isEmployeeShell;
    $isLandingPage = !$user && $page === 'landing';
    $showLoginChooser = !$user && in_array($page, ['landing', 'register'], true);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?> | <?= h(APP_NAME) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Merriweather:wght@300;400;700;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= h(asset_url('assets/css/app.css')) ?>">
    </head>
    <body class="<?= $isLandingPage ? 'landing-shell' : 'app-fixed' ?>">
    <div class="app-shell <?= $isAdminShell ? 'admin-shell' : ($isEmployeeShell ? 'employee-shell' : '') ?>">
        <?php if ($isSidebarShell): ?>
            <aside class="<?= $isAdminShell ? 'admin-sidebar' : 'employee-sidebar' ?>"<?= ($isAdminShell || $isEmployeeShell) ? ' data-profile-card data-profile-role="' . h((string) $user['role']) . '" data-profile-id="' . (int) $user['id'] . '"' : '' ?>>
                <a class="brand" href="<?= h(BASE_URL) ?>?page=<?= $isAdminShell ? 'admin_dashboard' : 'employee_attendance' ?>">
                    <span class="brand-mark">VT</span>
                    <span class="brand-copy"><strong>V Traco</strong><small><?= h($isAdminShell ? 'Attendance & Payroll' : 'Employee Workspace') ?></small></span>
                </a>
                <div class="sidebar-profile">
                    <div class="sidebar-avatar-wrap">
                        <img src="" alt="<?= h($user['name']) ?> profile photo" class="avatar sidebar-avatar-image hidden" data-profile-photo>
                        <div class="avatar" data-profile-fallback><?= h(user_initials((string) $user['name'])) ?></div>
                    </div>
                    <div>
                        <strong><?= h($user['name']) ?></strong><br>
                        <span class="hint"><?= h($isAdminShell ? 'Administrator' : (string) ($user['emp_id'] ?: 'Employee')) ?></span>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <?php if ($isAdminShell): ?>
                        <?php if ($user['role'] === 'freelancer'): ?>
                        <span class="sidebar-section-title">Employee</span>
                        <a class="sidebar-link <?= $page === 'admin_employees' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employees"><span class="nav-icon">E</span><span>Employees</span></a>
                        <span class="sidebar-section-title">Attendance</span>
                        <a class="sidebar-link <?= $page === 'admin_attendance' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_attendance"><span class="nav-icon">A</span><span>Attendance</span></a>
                        <?php else: ?>
                        <a class="sidebar-link <?= $page === 'admin_dashboard' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_dashboard"><span class="nav-icon">D</span><span>Dashboard</span></a>
                        <a class="sidebar-link <?= $page === 'admin_employees' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employees"><span class="nav-icon">E</span><span>Employees</span></a>
                        <a class="sidebar-link <?= $page === 'admin_projects' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_projects"><span class="nav-icon">P</span><span>Projects</span></a>
                        <a class="sidebar-link <?= $page === 'admin_attendance' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_attendance"><span class="nav-icon">A</span><span>Attendance</span></a>
                        <a class="sidebar-link <?= $page === 'admin_reports' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_reports"><span class="nav-icon">R</span><span>Reports</span></a>
                        <a class="sidebar-link <?= $page === 'admin_rules' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_rules"><span class="nav-icon">S</span><span>Settings</span></a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="sidebar-section-title">Employee</span>
                        <button class="sidebar-link sidebar-link-button" type="button" data-modal-target="employee-profile-settings-modal">
                            <span class="nav-icon">E</span>
                            <span>Profile Settings</span>
                        </button>
                        <span class="sidebar-section-title">Attendance</span>
                        <a class="sidebar-link <?= $page === 'employee_attendance' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=employee_attendance"><span class="nav-icon">M</span><span>My Attendance</span></a>
                    <?php endif; ?>
                </nav>
                <?php if ($isAdminShell && $user['role'] === 'admin'): ?>
                <div class="sidebar-settings-wrap">
                        <button class="sidebar-link sidebar-link-button" type="button" data-modal-target="admin-profile-settings-modal">
                            <span class="nav-icon">P</span>
                            <span>Profile Settings</span>
                        </button>
                </div>
                <?php endif; ?>
                <div class="sidebar-actions">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="logout">
                        <button class="button ghost <?= $isAdminShell ? 'admin-logout-button' : 'employee-logout-button' ?>" type="submit" style="width:100%;">Logout</button>
                    </form>
                </div>
            </aside>
            <div class="<?= $isAdminShell ? 'admin-main' : 'employee-main' ?>">
                <?php $flashItems = flashes(); if ($flashItems): ?>
                    <div class="flash-stack">
                        <?php foreach ($flashItems as $flash): ?>
                            <div class="flash <?= h($flash['type']) ?>">
                                <?php if (($flash['type'] ?? '') === 'success'): ?>
                                    <div class="flash-sweet-icon" aria-hidden="true">&#10003;</div>
                                    <div class="flash-copy">
                                        <strong>Success</strong>
                                        <span><?= h($flash['message']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <?= h($flash['message']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <main class="content <?= h($pageClass) ?>">
        <?php else: ?>
            <header class="topbar <?= $isLandingPage ? 'landing-topbar' : '' ?>">
                <a class="brand" href="<?= h(BASE_URL) ?>">
                    <span class="brand-mark">VT</span>
                    <span class="brand-copy"><strong>V Traco</strong><small>Attendance & Payroll</small></span>
                </a>
                <nav class="topbar-nav">
                    <?php if ($user): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="logout">
                            <button class="button ghost" type="submit">Logout</button>
                        </form>
                    <?php else: ?>
                        <?php if (!$isLandingPage): ?>
                            <a class="button ghost" href="<?= h(BASE_URL) ?>">Home</a>
                        <?php endif; ?>
                        <?php if ($showLoginChooser): ?>
                            <button class="button outline" type="button" data-modal-target="landing-login-modal">Login</button>
                        <?php else: ?>
                            <a class="button outline" href="<?= h(BASE_URL) ?>?page=login">Login</a>
                        <?php endif; ?>
                        <a class="button solid" href="<?= h(BASE_URL) ?>?page=register">Register</a>
                    <?php endif; ?>
                </nav>
            </header>
            <?php $flashItems = flashes(); if ($flashItems): ?>
                <div class="flash-stack">
                    <?php foreach ($flashItems as $flash): ?>
                        <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <main class="content <?= h($pageClass) ?>">
            <?php $flashItems = flashes(); if ($flashItems): ?>
                <div class="flash-stack">
                    <?php foreach ($flashItems as $flash): ?>
                        <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <main class="content <?= h($pageClass) ?>"><?php endif; ?>
    <?php
}

function render_landing_auth_modal(string $role, bool $isOpen): void
{
    switch ($role) {
        case 'admin':
            $title = 'Admin Login'; $eyebrow = 'Admin Access'; $iconLabel = 'A';
            $desc = 'Sign in to continue to your management workspace.';
            break;
        case 'external_vendor':
            $title = 'External Vendor Login'; $eyebrow = 'Vendor Access'; $iconLabel = 'V';
            $desc = 'Sign in with your self-registered vendor account.';
            break;
        case 'freelancer':
            $title = 'Corporate Employee Login'; $eyebrow = 'Corporate Employee Access'; $iconLabel = 'F';
            $desc = 'Freelancers can sign in with their self-registered account.';
            break;
        case 'employee':
        default:
            $title = 'Employee Login'; $eyebrow = 'Employee Access'; $iconLabel = 'E';
            $desc = 'Employees cannot self-register. Use the email and password sent to you by your admin via email.';
            break;
    }

    $modalId = $role . '-login-modal';
    $passwordId = $role . '-modal-password';
    ?>
    <div class="modal <?= $isOpen ? 'open' : '' ?>" id="<?= h($modalId) ?>">
        <div class="modal-card landing-auth-modal">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="landing-auth-shell">
                <div class="landing-auth-copy">
                    <span class="landing-login-icon <?= in_array($role, ['employee', 'freelancer']) ? 'employee' : '' ?>"><?= h($iconLabel) ?></span>
                    <span class="eyebrow"><?= h($eyebrow) ?></span>
                    <h2><?= h($title) ?></h2>
                    <p><?= h($desc) ?></p>
                    <button class="button ghost small" type="button" data-switch-modal-target="landing-login-modal">Back</button>
                </div>
                <form method="post" class="stack-form landing-auth-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="role" value="<?= h($role) ?>">
                    <input type="hidden" name="return_page" value="landing">
                    <div class="field">
                        <label>Email</label>
                        <div class="field-row"><input type="email" name="email" placeholder="name@company.com" required></div>
                        <small class="field-error"><span>!</span>Enter a valid email address.</small>
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <div class="field-row">
                            <input id="<?= h($passwordId) ?>" type="password" name="password" placeholder="Enter your password" required>
                            <button class="password-toggle" type="button" data-password-toggle="<?= h($passwordId) ?>">Show</button>
                        </div>
                        <small class="field-error"><span>!</span>Password is required.</small>
                    </div>
                    <button class="button solid" type="submit"><?= h($title) ?></button>
                    <?php if ($role === 'employee'): ?>
                        <button class="button ghost" type="submit" name="forgot_password" value="1">Forgot your password?</button>
                        <p class="hint">Enter your employee email above and we will send or log a temporary password for that account.</p>
                    <?php endif; ?>
                    <?php if (in_array($role, ['admin', 'external_vendor', 'freelancer'])): ?>
                        <p class="hint">Need an account? <a href="<?= h(BASE_URL) ?>?page=register">Open registration</a></p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function render_employee_profile_settings_modal(array $employee, string $employerName): void
{
    $employerDisplay = $employerName !== '' ? $employerName : 'Not assigned yet';
    if ($employerName !== '' && strcasecmp($employerName, (string) $employee['name']) === 0) {
        $employerDisplay .= ' (Admin)';
    }
    ?>
    <div class="modal" id="employee-profile-settings-modal">
        <div class="modal-card profile-settings-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Profile Settings</span>
            <h2><?= h($employee['name']) ?></h2>
            <div class="profile-settings-grid">
                <div class="list-item">
                    <strong>Employee ID</strong>
                    <span><?= h((string) ($employee['emp_id'] ?: 'Employee')) ?></span>
                </div>
                <div class="list-item">
                    <strong>Employer Name</strong>
                    <span><?= h($employerDisplay) ?></span>
                </div>
                <div class="list-item">
                    <strong>Email</strong>
                    <span><?= h($employee['email']) ?></span>
                </div>
                <div class="list-item">
                    <strong>Shift</strong>
                    <span><?= h((string) (($employee['shift'] ?? '') ?: 'Not assigned')) ?></span>
                </div>
            </div>
            <label class="profile-settings-field">
                <span>Choose Profile Photo</span>
                <input type="file" accept="image/*" data-profile-photo-input>
            </label>
            <p class="hint" data-profile-photo-status>Stored only in this browser for this employee.</p>
            <div class="profile-settings-actions">
                <button class="button ghost" type="button" data-switch-modal-target="employee-password-modal">Change Password</button>
            </div>
        </div>
    </div>
    <?php
}
function render_employee_password_modal(): void
{
    ?>
    <div class="modal" id="employee-password-modal">
        <div class="modal-card" style="max-width:520px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Employee Account</span>
            <h2>Change Password</h2>
            <p>Update your sign-in password directly from the sidebar.</p>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="employee_change_password">
                <div class="field">
                    <label>Current Password</label>
                    <div class="field-row">
                        <input id="employee-current-password" type="password" name="current_password" placeholder="Enter current password" required>
                        <button class="password-toggle" type="button" data-password-toggle="employee-current-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Current password is required.</small>
                </div>
                <div class="field">
                    <label>New Password</label>
                    <div class="field-row">
                        <input id="employee-new-password" type="password" name="new_password" minlength="8" placeholder="Minimum 8 characters with letters and numbers" required>
                        <button class="password-toggle" type="button" data-password-toggle="employee-new-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Password must be at least 8 characters and include a letter and number.</small>
                </div>
                <div class="field">
                    <label>Confirm Password</label>
                    <div class="field-row">
                        <input id="employee-confirm-password" type="password" name="confirm_password" minlength="8" placeholder="Repeat new password" required>
                        <button class="password-toggle" type="button" data-password-toggle="employee-confirm-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Please confirm the new password.</small>
                </div>
                <button class="button solid" type="submit">Update Password</button>
            </form>
        </div>
    </div>
    <?php
}

function render_admin_profile_settings_modal(array $admin): void
{
    $returnPage = (string) ($_GET['page'] ?? 'admin_dashboard');
    if (!str_starts_with($returnPage, 'admin_') || $returnPage === 'admin_profile_settings') {
        $returnPage = 'admin_dashboard';
    }
    $memberSince = !empty($admin['created_at']) ? date('d M Y', strtotime((string) $admin['created_at'])) : 'Recently added';
    ?>
    <div class="modal" id="admin-profile-settings-modal">
        <div class="modal-card profile-settings-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Profile Settings</span>
            <h2><?= h((string) $admin['name']) ?></h2>
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
            <label class="profile-settings-field">
                <span>Choose Profile Photo</span>
                <input type="file" accept="image/*" data-profile-photo-input>
            </label>
            <p class="hint" data-profile-photo-status>Stored only in this browser for this admin.</p>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_profile_update">
                <input type="hidden" name="return_page" value="<?= h($returnPage) ?>">
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
                <div class="profile-settings-actions">
                    <button class="button ghost" type="button" data-switch-modal-target="admin-password-modal">Change Password</button>
                    <button class="button solid" type="submit">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function render_admin_password_modal(): void
{
    $returnPage = (string) ($_GET['page'] ?? 'admin_dashboard');
    if (!str_starts_with($returnPage, 'admin_') || $returnPage === 'admin_profile_settings') {
        $returnPage = 'admin_dashboard';
    }
    ?>
    <div class="modal" id="admin-password-modal">
        <div class="modal-card" style="max-width:520px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Admin Account</span>
            <h2>Change Password</h2>
            <p>Update your sign-in password directly from the sidebar.</p>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_change_password">
                <input type="hidden" name="return_page" value="<?= h($returnPage) ?>">
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
                <div class="profile-settings-actions">
                    <button class="button ghost" type="button" data-switch-modal-target="admin-profile-settings-modal">Back to Profile</button>
                    <button class="button solid" type="submit">Update Password</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function render_footer(): void
{
    $user = current_user();
    $isAdminShell = $user && $user['role'] === 'admin';
    $isEmployeeShell = $user && $user['role'] === 'employee';
    $isSidebarShell = $isAdminShell || $isEmployeeShell;
    $page = $_GET['page'] ?? 'landing';
    $showLandingLoginModal = !$user && in_array($page, ['landing', 'register'], true);
    $landingAuthRole = in_array($_GET['auth'] ?? '', ['admin', 'employee', 'external_vendor', 'freelancer'], true) ? $_GET['auth'] : '';
    $employerName = '';
    if ($isEmployeeShell && !empty($user['admin_id'])) {
        $stmt = db()->prepare('SELECT name FROM users WHERE id = :id AND role = "admin"');
        $stmt->execute(['id' => (int) $user['admin_id']]);
        $employerName = (string) ($stmt->fetchColumn() ?: '');
    }
    ?>
        </main>
        <?php if ($isSidebarShell): ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($isAdminShell): ?>
        <?php render_admin_profile_settings_modal($user); ?>
        <?php render_admin_password_modal(); ?>
    <?php endif; ?>
    <?php if ($isEmployeeShell): ?>
        <?php render_employee_profile_settings_modal($user, $employerName); ?>
        <?php render_employee_password_modal(); ?>
    <?php endif; ?>
    <?php if ($showLandingLoginModal): ?>
        <div class="modal" id="landing-login-modal">
            <div class="modal-card landing-login-modal">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <div class="landing-login-head">
                    <span class="eyebrow">Choose Login</span>
                    <h2>Continue to your workspace</h2>
                    <p>Select the login flow that matches your role in V Traco.</p>
                </div>
                <div class="landing-login-grid">
                    <button class="landing-login-option" type="button" data-switch-modal-target="admin-login-modal">
                        <span class="landing-login-icon">A</span>
                        <strong>Admin Login</strong>
                        <span>Manage employees, attendance rules, payroll, and reports.</span>
                    </button>
                    <button class="landing-login-option" type="button" data-switch-modal-target="employee-login-modal">
                        <span class="landing-login-icon employee">E</span>
                        <strong>Employee Login</strong>
                        <span>Use the credentials sent to your email by your admin to sign in.</span>
                    </button>
                    <button class="landing-login-option" type="button" data-switch-modal-target="external_vendor-login-modal">
                        <span class="landing-login-icon">V</span>
                        <strong>External Vendor</strong>
                        <span>Sign in with your self-registered vendor account.</span>
                    </button>
                    <button class="landing-login-option" type="button" data-switch-modal-target="freelancer-login-modal">
                        <span class="landing-login-icon employee">F</span>
                        <strong>Corporate Employee</strong>
                        <span>Freelancers can sign in with their self-registered account.</span>
                    </button>
                </div>
            </div>
        </div>
        <?php render_landing_auth_modal('admin', $landingAuthRole === 'admin'); ?>
        <?php render_landing_auth_modal('employee', $landingAuthRole === 'employee'); ?>
        <?php render_landing_auth_modal('external_vendor', $landingAuthRole === 'external_vendor'); ?>
        <?php render_landing_auth_modal('freelancer', $landingAuthRole === 'freelancer'); ?>
    <?php endif; ?>
    <div class="modal" id="attendance-modal">
        <div class="modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="modal-grid" id="modal-content"></div>
        </div>
    </div>
    <script>window.VTRACO_CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;</script>
    <script src="<?= h(asset_url('assets/js/app.js')) ?>"></script>
    </body>
    </html>
    <?php
}




