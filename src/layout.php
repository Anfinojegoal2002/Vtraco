<?php

declare(strict_types=1);

function render_page(string $page): void
{
    $user = current_user();
    switch ($page) {
        case 'login':
            if (($_GET['role'] ?? '') === 'corporate_employee') {
                $popupParams = ['auth' => 'corporate_employee'];
                foreach (['reset', 'email'] as $key) {
                    if (isset($_GET[$key]) && (string) $_GET[$key] !== '') {
                        $popupParams[$key] = (string) $_GET[$key];
                    }
                }
                redirect_to('landing', $popupParams);
            }
            render_login();
            break;
        case 'register':
            render_register();
            break;
        case 'admin_dashboard':
            render_admin_dashboard();
            break;
        case 'corporate_dashboard':
            render_corporate_dashboard();
            break;
        case 'vendor_dashboard':
            render_vendor_dashboard();
            break;
        case 'vendor_payments':
            render_vendor_payments();
            break;
        case 'super_admin_dashboard':
            render_super_admin_dashboard();
            break;
        case 'admin_vendors':
            render_admin_vendors();
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
        case 'admin_employee_log':
            render_admin_attendance();
            break;
        case 'admin_profile_settings':
            render_admin_profile_settings();
            break;
        case 'admin_reports':
            render_admin_reports();
            break;
        case 'admin_accounts':
            render_admin_accounts();
            break;

        case 'notifications':
            render_notifications();
            break;

        case 'employee_attendance':
        case 'employee_log':
            render_employee_attendance();
            break;
        case 'employee_reimbursements':
            render_employee_reimbursements();
            break;
        case 'employee_projects':
            render_employee_projects();
            break;
        case 'employee_payments':
            render_employee_payments();
            break;
        case 'employee_profile':
            render_employee_profile();
            break;
        case 'employee_onboarding_reviews':
            render_employee_onboarding_reviews();
            break;
        case 'employee_profile_completion':
            render_employee_profile_completion();
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
    global $page;
    $user = current_user();
    $page = $page ?? ($_GET['page'] ?? 'landing');
    $isAdminShell = $user && in_array($user['role'], ['admin', 'freelancer', 'external_vendor'], true);
    $isEmployeeShell = $user && in_array($user['role'], ['employee', 'corporate_employee'], true);
    $hasPowerAdminAccess = $isEmployeeShell && employee_has_power_access($user);
    $hasPowerAttendanceAccess = $isEmployeeShell && employee_has_power_attendance_access($user);
    $hasPowerTeamAccess = $isEmployeeShell && employee_has_power_team_access($user);
    $hasPowerProjectsAccess = $isEmployeeShell && employee_has_power_projects_access($user);
    $hasPowerAccountsAccess = $isEmployeeShell && employee_has_power_accounts_access($user);
    $isSidebarShell = $isAdminShell || $isEmployeeShell;
    $profileSettingsModalId = $isAdminShell ? 'admin-profile-settings-modal' : ($isEmployeeShell ? 'employee-profile-settings-modal' : '');
    $profileSettingsActivePage = $isAdminShell ? 'admin_profile_settings' : 'employee_profile';
    $isLandingPage = !$user && $page === 'landing';
    $showLoginChooser = !$user && in_array($page, ['landing', 'register'], true);
    $notifications = $user ? notifications_for_user((int) $user['id'], 3) : [];
    $unreadNotifications = $user ? unread_notification_count((int) $user['id']) : 0;
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
        <link rel="icon" type="image/svg+xml" href="<?= h(asset_url('assets/images/vtraco-logo.svg')) ?>">
        <link rel="stylesheet" href="<?= h(asset_url('assets/css/app.css') . '?v=' . (string) filemtime(__DIR__ . '/../assets/css/app.css')) ?>">
        <!-- Alpine.js -->
        <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    </head>
    <body class="<?= $isLandingPage ? 'landing-shell' : 'app-fixed' ?>">
    <div class="app-shell <?= $isAdminShell ? 'admin-shell' : ($isEmployeeShell ? 'employee-shell' : '') ?>">
        <?php if ($isSidebarShell): ?>
            <button
                class="mobile-nav-toggle"
                type="button"
                aria-label="Open navigation menu"
                aria-expanded="false"
                data-sidebar-toggle
            >
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="mobile-sidebar-backdrop" data-sidebar-backdrop hidden></div>
            <aside class="<?= $isAdminShell ? 'admin-sidebar' : 'employee-sidebar' ?>" data-sidebar>
                <a class="brand" href="<?= h(BASE_URL) ?>?page=<?= $isAdminShell ? (
                    (($user['role'] ?? '') === 'freelancer') ? 'corporate_dashboard'
                    : ((($user['role'] ?? '') === 'external_vendor') ? 'vendor_dashboard' : 'admin_dashboard')
                ) : 'employee_attendance' ?>">
                    <img class="brand-mark" src="<?= h(asset_url('assets/images/vtraco-logo.svg')) ?>" alt="" aria-hidden="true">
                    <span class="brand-copy"><strong>V Traco</strong><small><?= h($isAdminShell ? 'Attendance & Payroll' : 'Employee Workspace') ?></small></span>
                </a>
                <div
                    class="sidebar-profile"
                    <?= ($isAdminShell || $isEmployeeShell)
                        ? ' data-profile-card data-profile-role="' . h((string) $user['role']) . '" data-profile-id="' . (int) $user['id'] . '" data-modal-target="' . h($profileSettingsModalId) . '" role="button" tabindex="0"'
                        : '' ?>
                >
                    <div class="sidebar-avatar-wrap">
                        <img src="" alt="<?= h($user['name']) ?> profile photo" class="avatar sidebar-avatar-image hidden" data-profile-photo>
                        <div class="avatar" data-profile-fallback><?= h(user_initials((string) $user['name'])) ?></div>
                    </div>
                    <div>
                        <strong><?= h($user['name']) ?></strong><br>
                        <span class="hint"><?php
                            if ($isAdminShell && (($user['role'] ?? '') === 'freelancer')) {
                                echo h('Freelancer');
                            } elseif ($isAdminShell && (($user['role'] ?? '') === 'external_vendor')) {
                                echo h('External Vendor');
                            } else {
                                echo h($isAdminShell ? 'Administrator' : (string) ($user['emp_id'] ?: 'Employee'));
                            }
                        ?></span>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <?php if ($isAdminShell): ?>
                        <?php if ($user['role'] === 'freelancer'): ?>
                        <a class="sidebar-link <?= $page === 'corporate_dashboard' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=corporate_dashboard"><span class="nav-icon">D</span><span>Dashboard</span></a>
                        <span class="sidebar-section-title">Team</span>
                        <a class="sidebar-link <?= $page === 'admin_employees' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employees"><span class="nav-icon">T</span><span>Team</span></a>
                        <span class="sidebar-section-title">Track Attendance</span>
                        <a class="sidebar-link <?= in_array($page, ['admin_attendance', 'admin_employee_log'], true) ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employee_log"><span class="nav-icon">L</span><span>Track Attendance</span></a>
                        <a class="sidebar-link <?= $page === 'notifications' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=notifications"><span class="nav-icon">N</span><span>Notifications</span><?php if ($unreadNotifications > 0): ?><span class="sidebar-link-badge"><?= (int) $unreadNotifications ?></span><?php endif; ?></a>
                        <?php elseif ($user['role'] === 'external_vendor'): ?>
                            <a class="sidebar-link <?= $page === 'vendor_dashboard' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=vendor_dashboard"><span class="nav-icon">D</span><span>Dashboard</span></a>
                            <span class="sidebar-section-title">Team</span>
                            <a class="sidebar-link <?= $page === 'admin_employees' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employees"><span class="nav-icon">T</span><span>Team</span></a>
                            <a class="sidebar-link <?= $page === 'admin_projects' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_projects"><span class="nav-icon">P</span><span>Projects</span></a>
                            <a class="sidebar-link <?= $page === 'vendor_payments' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=vendor_payments"><span class="nav-icon">$</span><span>Payment</span></a>
                            <span class="sidebar-section-title">Track Attendance</span>
                            <a class="sidebar-link <?= in_array($page, ['admin_attendance', 'admin_employee_log'], true) ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employee_log"><span class="nav-icon">L</span><span>Track Attendance</span></a>
                            <a class="sidebar-link <?= $page === 'notifications' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=notifications"><span class="nav-icon">N</span><span>Notifications</span><?php if ($unreadNotifications > 0): ?><span class="sidebar-link-badge"><?= (int) $unreadNotifications ?></span><?php endif; ?></a>
                        <?php else: ?>
                        <a class="sidebar-link <?= $page === 'admin_dashboard' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_dashboard"><span class="nav-icon">D</span><span>Dashboard</span></a>
                        <a class="sidebar-link <?= $page === 'admin_employees' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employees"><span class="nav-icon">T</span><span>Team</span></a>
                        <a class="sidebar-link <?= $page === 'admin_projects' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_projects"><span class="nav-icon">P</span><span>Projects</span></a>
                        <a class="sidebar-link <?= in_array($page, ['admin_attendance', 'admin_employee_log'], true) ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employee_log"><span class="nav-icon">L</span><span>Track Attendance</span></a>
                        <a class="sidebar-link <?= $page === 'admin_accounts' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_accounts"><span class="nav-icon">A</span><span>Accounts</span></a>
                        <a class="sidebar-link <?= $page === 'admin_reports' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_reports"><span class="nav-icon">R</span><span>Reports</span></a>

                        <a class="sidebar-link <?= $page === 'admin_rules' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_rules"><span class="nav-icon">S</span><span>Rules</span></a>
                        <a class="sidebar-link <?= $page === 'notifications' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=notifications"><span class="nav-icon">N</span><span>Notifications</span><?php if ($unreadNotifications > 0): ?><span class="sidebar-link-badge"><?= (int) $unreadNotifications ?></span><?php endif; ?></a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php $isContractualEmployeeShell = ($user['role'] ?? '') === 'corporate_employee'; ?>
                        <?php $isVendorTrainerShell = employee_is_vendor_trainer($user); ?>
                        <?php $isProjectCoordinatorShell = employee_is_project_coordinator($user); ?>
                        <?php if ($isContractualEmployeeShell || $isVendorTrainerShell || $isProjectCoordinatorShell): ?>
                            <a class="sidebar-link <?= in_array($page, ['employee_attendance', 'employee_log'], true) ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=employee_attendance"><span class="nav-icon">D</span><span>Dashboard</span></a>
                            <a class="sidebar-link <?= $page === 'employee_projects' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=employee_projects"><span class="nav-icon">P</span><span>Projects</span></a>
                            <?php if ($isContractualEmployeeShell): ?>
                                <a class="sidebar-link <?= $page === 'employee_payments' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=employee_payments"><span class="nav-icon">$</span><span>Payment</span></a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <span class="sidebar-section-title">Track Attendance</span>
                        <a class="sidebar-link <?= in_array($page, ['employee_attendance', 'employee_log'], true) ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=employee_log"><span class="nav-icon">L</span><span>Track Attendance</span></a>
                        <?php if (employee_is_in_house_trainer($user) && !$isContractualEmployeeShell && !$isVendorTrainerShell && !$isProjectCoordinatorShell): ?>
                            <a class="sidebar-link <?= $page === 'employee_projects' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=employee_projects"><span class="nav-icon">P</span><span>Projects</span></a>
                        <?php endif; ?>
                        <?php if ($hasPowerAttendanceAccess || $hasPowerTeamAccess || $hasPowerProjectsAccess || $hasPowerAccountsAccess): ?>
                            <span class="sidebar-section-title">Power</span>
                            <?php if ($hasPowerAttendanceAccess): ?>
                                <a class="sidebar-link <?= in_array($page, ['admin_attendance', 'admin_employee_log'], true) ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employee_log"><span class="nav-icon">L</span><span>Track Attendance</span></a>
                            <?php endif; ?>
                            <?php if ($hasPowerTeamAccess): ?>
                                <a class="sidebar-link <?= $page === 'admin_employees' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employees"><span class="nav-icon">T</span><span>Team</span></a>
                            <?php endif; ?>
                            <?php if ($hasPowerProjectsAccess): ?>
                                <a class="sidebar-link <?= $page === 'admin_projects' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_projects"><span class="nav-icon">P</span><span>Team Projects</span></a>
                            <?php endif; ?>
                            <?php if ($hasPowerAccountsAccess): ?>
                                <a class="sidebar-link <?= $page === 'admin_accounts' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_accounts"><span class="nav-icon">A</span><span>Accounts</span></a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a class="sidebar-link <?= $page === 'notifications' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=notifications"><span class="nav-icon">N</span><span>Notifications</span><?php if ($unreadNotifications > 0): ?><span class="sidebar-link-badge"><?= (int) $unreadNotifications ?></span><?php endif; ?></a>
                    <?php endif; ?>
                </nav>
                <?php if ($user): ?>
                <?php endif; ?>
                <div class="sidebar-actions">
                    <?php if ($profileSettingsModalId !== ''): ?>
                        <button class="sidebar-link sidebar-link-button <?= $page === $profileSettingsActivePage ? 'active' : '' ?>" type="button" data-modal-target="<?= h($profileSettingsModalId) ?>"><span class="nav-icon">U</span><span>Profile Settings</span></button>
                    <?php endif; ?>
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
                                <div class="flash-icon" aria-hidden="true"></div>
                                <strong><?= h(ucfirst((string) ($flash['type'] ?? 'Info'))) ?>!</strong>
                                <span><?= h($flash['message']) ?></span>
                                <button class="flash-ok" type="button">OK</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <main class="content <?= h($pageClass) ?>">
                    <?php if ($user && employee_profile_requires_completion($user) && function_exists('render_employee_profile_completion_modal')): ?>
                        <?php render_employee_profile_completion_modal($user); ?>
                    <?php endif; ?>
        <?php elseif ($page !== 'super_admin_dashboard'): ?>
            <header class="topbar <?= $isLandingPage ? 'landing-topbar' : '' ?>">
                <a class="brand" href="<?= h(BASE_URL) ?>">
                    <img class="brand-mark" src="<?= h(asset_url('assets/images/vtraco-logo.svg')) ?>" alt="" aria-hidden="true">
                    <span class="brand-copy"><strong>V Traco</strong><small>Attendance & Payroll</small></span>
                </a>
                <nav class="topbar-nav">
                    <?php if ($user): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="logout">
                            <button class="button ghost" type="submit">Logout</button>
                        </form>
                    <?php elseif (($_GET['role'] ?? '') !== 'super_admin'): ?>
                        <?php if (!$isLandingPage): ?>
                            <a class="button ghost" href="<?= h(BASE_URL) ?>">Home</a>
                        <?php endif; ?>
                        <?php if ($showLoginChooser): ?>
                            <button class="button outline" type="button" data-modal-target="landing-login-modal">Login</button>
                        <?php else: ?>
                            <a class="button outline" href="<?= h(BASE_URL) ?>?page=login">Login</a>
                        <?php endif; ?>
                        <a class="button solid" href="javascript:void(0)" data-modal-target="admin-register-modal">Register</a>
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

function render_landing_register_form_modal(string $role): void
{
    $roles = auth_registration_roles();
    if (!isset($roles[$role])) {
        return;
    }
    $selected = $roles[$role];
    ?>
    <div class="modal" id="<?= h($role) ?>-register-modal">
        <div class="modal-card auth-register-modal">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="auth-register-side">
                <div class="auth-register-brand">
                    <img class="brand-mark" src="<?= h(asset_url('assets/images/vtraco-logo.svg')) ?>" alt="">
                    <span>V Traco</span>
                </div>
                <div class="auth-register-context">
                    <span class="eyebrow"><?= h($selected['eyebrow']) ?></span>
                    <h2><?= h($selected['title']) ?></h2>
                    <p><?= h($selected['description']) ?></p>
                </div>
                <div class="auth-register-features">
                    <div class="feat">
                        <strong>Security First</strong>
                        <span>Your data is encrypted and secure.</span>
                    </div>
                    <div class="feat">
                        <strong>Instant Access</strong>
                        <span>Start managing your team in minutes.</span>
                    </div>
                </div>
            </div>
            <div class="auth-register-main">
                <form method="post" class="stack-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="register_user">
                    <input type="hidden" name="role" value="<?= h($role) ?>">
                    <input type="hidden" name="return_page" value="landing">
                    
                    <div class="form-grid-2">
                        <div class="field">
                            <label>Full Name</label>
                            <div class="field-row"><input type="text" name="name" placeholder="John Doe" required></div>
                        </div>
                        
                        <?php if ($role === 'admin'): ?>
                            <div class="field">
                                <label>Company Name</label>
                                <div class="field-row"><input type="text" name="company_name" placeholder="Acme Inc." required></div>
                            </div>
                        <?php endif; ?>

                        <div class="field">
                            <label>Email Address</label>
                            <div class="field-row"><input type="email" name="email" placeholder="name@example.com" required></div>
                        </div>
                        <div class="field">
                            <label>Phone Number</label>
                            <div class="field-row"><input type="text" name="phone" placeholder="+91" required></div>
                        </div>
                        <div class="field">
                            <label>Password</label>
                            <div class="field-row">
                                <input id="reg-pass-<?= h($role) ?>" type="password" name="password" maxlength="6" placeholder="Max 6 chars" required>
                                <button class="password-toggle" type="button" data-password-toggle="reg-pass-<?= h($role) ?>">Show</button>
                            </div>
                        </div>
                        <div class="field">
                            <label>Confirm Password</label>
                            <div class="field-row">
                                <input id="reg-conf-<?= h($role) ?>" type="password" name="confirm_password" maxlength="6" placeholder="Repeat" required>
                                <button class="password-toggle" type="button" data-password-toggle="reg-conf-<?= h($role) ?>">Show</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="auth-register-footer-actions">
                        <button class="button solid large full-width" type="submit"><?= h($selected['button']) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
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
            $desc = 'Use the credentials shared by your admin to access your vendor account.';
            break;
        case 'corporate_employee':
            $title = 'Contractual Employee Login'; $eyebrow = 'Contractual Employee Access'; $iconLabel = 'C';
            $desc = 'Use your contractual employee registration details to access the employee workspace.';
            break;
        case 'employee':
        default:
            $title = 'Employee Login'; $eyebrow = 'Employee Access'; $iconLabel = 'E';
            $desc = 'Employees cannot self-register. Use the email and password sent to you by your admin via email.';
            break;
    }

    $modalId = $role . '-login-modal';
    $passwordId = $role . '-modal-password';
    $resetEmail = filter_var((string) ($_GET['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string) $_GET['email'] : '';
    $resetMode = (string) ($_GET['auth'] ?? '') === $role ? (string) ($_GET['reset'] ?? '') : '';
    ?>
    <div class="modal <?= $isOpen ? 'open' : '' ?>" id="<?= h($modalId) ?>">
        <div class="modal-card landing-auth-modal">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="landing-auth-shell">
                <div class="landing-auth-copy">
                    <span class="landing-login-icon <?= in_array($role, ['employee', 'corporate_employee', 'freelancer']) ? 'employee' : '' ?>"><?= h($iconLabel) ?></span>
                    <span class="eyebrow"><?= h($eyebrow) ?></span>
                    <h2><?= h($title) ?></h2>
                    <p><?= h($desc) ?></p>
                    <button class="button ghost small" type="button" data-switch-modal-target="landing-login-modal">Back</button>
                </div>
                <?php if ($resetMode === 'email' && in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor'], true)): ?>
                    <div class="stack-form landing-auth-form">
                        <h3>Forgot Password</h3>
                        <form method="post" class="stack-form" style="margin-top:14px;" data-validate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="forgot_password_send_otp">
                            <input type="hidden" name="role" value="<?= h($role) ?>">
                            <input type="hidden" name="return_page" value="landing">
                            <div class="field">
                                <label>Email</label>
                                <div class="field-row"><input type="email" name="forgot_email" placeholder="name@company.com" required></div>
                                <small class="field-error"><span>!</span>Enter the email linked to this account.</small>
                            </div>
                            <button class="button solid" type="submit">Send OTP</button>
                            <p class="hint">We will send a 6-digit OTP to this email.</p>
                        </form>
                        <a class="button ghost" href="<?= h(BASE_URL) ?>?auth=<?= h($role) ?>">Back to login</a>
                    </div>
                <?php elseif ($resetMode === 'otp' && in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor'], true)): ?>
                    <div class="stack-form landing-auth-form">
                        <h3>Enter OTP</h3>
                        <p class="hint">Enter the OTP sent to <?= h($resetEmail ?: 'your email') ?>.</p>
                        <form method="post" class="stack-form" style="margin-top:14px;" data-validate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="forgot_password_verify_otp">
                            <input type="hidden" name="role" value="<?= h($role) ?>">
                            <input type="hidden" name="return_page" value="landing">
                            <input type="hidden" name="forgot_email" value="<?= h($resetEmail) ?>">
                            <div class="field">
                                <label>OTP</label>
                                <div class="field-row"><input type="text" name="otp" inputmode="numeric" minlength="6" maxlength="6" pattern="\d{6}" placeholder="6-digit OTP" required></div>
                                <small class="field-error"><span>!</span>Enter the 6-digit OTP.</small>
                            </div>
                            <button class="button solid" type="submit">Verify OTP</button>
                        </form>
                        <a class="button ghost" href="<?= h(BASE_URL) ?>?auth=<?= h($role) ?>&reset=email">Change email</a>
                    </div>
                <?php elseif ($resetMode === 'password' && in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor'], true)): ?>
                    <div class="stack-form landing-auth-form">
                        <h3>Set New Password</h3>
                        <p class="hint">Create a new password for <?= h($resetEmail ?: 'your account') ?>.</p>
                        <form method="post" class="stack-form" style="margin-top:14px;" data-validate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="forgot_password_set_password">
                            <input type="hidden" name="role" value="<?= h($role) ?>">
                            <input type="hidden" name="return_page" value="landing">
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
                        <?php if (in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor'], true)): ?>
                            <a class="button ghost" href="<?= h(BASE_URL) ?>?auth=<?= h($role) ?>&reset=email">Forgot your password?</a>
                        <?php endif; ?>
                    </form>
                    <?php if ($role === 'admin'): ?>
                        <p class="hint">Need an account? <a href="<?= h(BASE_URL) ?>?page=register">Open registration</a></p>
                    <?php endif; ?>
                <?php endif; ?>
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
    $status = (string) ($employee['profile_status'] ?? 'incomplete');
    $isContractualEmployee = (string) ($employee['role'] ?? '') === 'corporate_employee'
        || (string) ($employee['employee_type'] ?? '') === 'corporate';
    $showOfferLetterForm = false;
    $statusCopy = [
        'verified' => 'Verified',
        'pending' => 'Under Review',
        'rejected' => 'Needs Update',
        'incomplete' => 'Incomplete',
    ];
    $profilePhoto = !empty($employee['profile_photo_path'])
        ? public_file_path((string) $employee['profile_photo_path'])
        : '';
    $documents = [
        'aadhaar_card' => 'Aadhaar Card',
        'pan_card' => 'PAN Card',
        'profile_photo' => 'Profile Photo',
        'qualification_certificate' => 'Qualification Certificate',
        'bank_proof' => 'Bank Proof',
        'resume' => 'Resume',
    ];
    ?>
    <div class="modal" id="employee-profile-settings-modal">
        <div class="modal-card profile-settings-modal-card employee-profile-settings-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="employee-profile-card employee-profile-settings-summary">
                <div class="employee-profile-media">
                    <?php if ($profilePhoto !== ''): ?>
                        <img class="employee-profile-photo" src="<?= h($profilePhoto) ?>" alt="<?= h((string) $employee['name']) ?> profile photo" onerror="this.classList.add('hidden');this.nextElementSibling.classList.remove('hidden');">
                        <div class="employee-profile-fallback hidden"><?= h(user_initials((string) $employee['name'])) ?></div>
                    <?php else: ?>
                        <div class="employee-profile-fallback"><?= h(user_initials((string) $employee['name'])) ?></div>
                    <?php endif; ?>
                </div>
                <div class="employee-profile-copy">
                    <div>
                        <span class="eyebrow">Profile Settings</span>
                        <h2><?= h((string) $employee['name']) ?></h2>
                    </div>
                    <div class="employee-profile-meta">
                        <div class="list-item"><strong>Employee ID</strong><span><?= h((string) ($employee['emp_id'] ?: 'Employee')) ?></span></div>
                        <div class="list-item"><strong>Employer Name</strong><span><?= h($employerDisplay) ?></span></div>
                        <div class="list-item"><strong>Email</strong><span><?= h((string) $employee['email']) ?></span></div>
                        <div class="list-item"><strong>Phone</strong><span><?= h((string) (($employee['phone'] ?? '') ?: '-')) ?></span></div>
                        <div class="list-item"><strong>Designation</strong><span><?= h((string) (($employee['designation'] ?? '') ?: '-')) ?></span></div>
                        <div class="list-item"><strong>Shift</strong><span><?= h(employee_shift_display($employee)) ?></span></div>
                    </div>
                </div>
                <div class="employee-profile-meta">
                    <div class="list-item"><strong>Status</strong><span><?= h($statusCopy[$status] ?? ucfirst($status)) ?></span></div>
                    <div class="list-item"><strong>Date of Birth</strong><span><?= !empty($employee['date_of_birth']) ? h(date('d M Y', strtotime((string) $employee['date_of_birth']))) : '-' ?></span></div>
                    <div class="list-item"><strong>Gender</strong><span><?= h((string) (($employee['gender'] ?? '') ?: '-')) ?></span></div>
                    <div class="list-item"><strong>Qualification</strong><span><?= h((string) (($employee['highest_qualification'] ?? '') ?: '-')) ?></span></div>
                </div>
            </div>
            <?php if ($status === 'rejected'): ?>
                <div class="profile-verification-alert employee-profile-settings-alert">
                    <strong>Verification Rejected</strong>
                    <span><?= nl2br(h((string) ($employee['profile_rejection_reason'] ?? 'Please review your details and resubmit.'))) ?></span>
                </div>
            <?php elseif ($status === 'pending'): ?>
                <div class="profile-verification-pending employee-profile-settings-alert">
                    <strong>Verification Pending</strong>
                    <span>Your profile details have been submitted. You can still update details if needed.</span>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="employee-profile-form employee-profile-settings-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="employee_profile_update">

                <div class="profile-verification-section">
                    <div class="profile-verification-section-head">
                        <strong>Account Details</strong>
                    </div>
                    <div class="profile-verification-grid">
                        <div class="field"><label>Employee ID</label><div class="field-row"><input type="text" name="emp_id" value="<?= h((string) ($employee['emp_id'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Employee ID is required.</small></div>
                        <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" value="<?= h((string) ($employee['name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                        <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" value="<?= h((string) ($employee['email'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Valid email is required.</small></div>
                        <div class="field"><label>Date of Joining</label><div class="field-row"><input type="date" name="date_of_joining" value="<?= h((string) ($employee['date_of_joining'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of joining is required.</small></div>
                        <div class="field"><label>Designation</label><div class="field-row"><select name="designation" required><option value="">Select designation</option><?php foreach (employee_designation_options() as $value => $label): ?><option value="<?= h($value) ?>" <?= ((string) ($employee['designation'] ?? '')) === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Designation is required.</small></div>
                        <div class="field"><label>Shift</label><div class="field-row"><input type="text" name="shift" value="<?= h(normalize_shift_selection((string) ($employee['shift'] ?? ''))) ?>" required></div><small class="field-error"><span>!</span>Shift is required.</small></div>
                    </div>
                </div>

                <div class="profile-verification-section">
                    <div class="profile-verification-section-head">
                        <strong>Personal Details</strong>
                    </div>
                    <div class="profile-verification-grid">
                        <div class="field"><label>Date of Birth</label><div class="field-row"><input type="date" name="date_of_birth" value="<?= h((string) ($employee['date_of_birth'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of birth is required.</small></div>
                        <div class="field"><label>Gender</label><div class="field-row"><select name="gender" required><option value="">Select gender</option><?php foreach (['Female', 'Male', 'Non-binary', 'Prefer not to say'] as $gender): ?><option value="<?= h($gender) ?>" <?= ((string) ($employee['gender'] ?? '')) === $gender ? 'selected' : '' ?>><?= h($gender) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Gender is required.</small></div>
                        <div class="field"><label>Highest Qualification</label><div class="field-row"><input type="text" name="highest_qualification" value="<?= h((string) ($employee['highest_qualification'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Qualification is required.</small></div>
                        <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" value="<?= h((string) ($employee['phone'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Phone number is required.</small></div>
                    </div>
                    <label class="profile-verification-wide">Address<textarea name="address" required><?= h((string) ($employee['address'] ?? '')) ?></textarea></label>
                </div>

                <div class="profile-verification-section">
                    <div class="profile-verification-section-head">
                        <strong>Bank Details</strong>
                    </div>
                    <div class="profile-verification-grid">
                        <div class="field"><label>Bank Name</label><div class="field-row"><input type="text" name="bank_name" value="<?= h((string) ($employee['bank_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Bank name is required.</small></div>
                        <div class="field"><label>Account Number</label><div class="field-row"><input type="text" name="bank_account_no" value="<?= h((string) ($employee['bank_account_no'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account number is required.</small></div>
                        <div class="field"><label>IFSC Code</label><div class="field-row"><input type="text" name="bank_ifsc_code" value="<?= h((string) ($employee['bank_ifsc_code'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>IFSC code is required.</small></div>
                        <div class="field"><label>Account Holder Name</label><div class="field-row"><input type="text" name="account_holder_name" value="<?= h((string) ($employee['account_holder_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account holder name is required.</small></div>
                    </div>
                </div>

                <div class="profile-verification-section">
                    <div class="profile-verification-section-head">
                        <strong>Documents</strong>
                    </div>
                    <div class="profile-document-grid">
                        <?php foreach ($documents as $field => $label): ?>
                            <?php $hasFile = !empty($employee[$field . '_path']); ?>
                            <label class="profile-document-upload<?= $hasFile ? ' has-file' : '' ?>">
                                <span class="profile-document-icon"><?= $hasFile ? 'OK' : '+' ?></span>
                                <span class="profile-document-copy">
                                    <strong><?= h($label) ?></strong>
                                    <small><?= $hasFile ? h((string) $employee[$field . '_name']) : 'JPG, PNG, PDF, DOC, DOCX' ?></small>
                                </span>
                                <input type="file" name="<?= h($field) ?>" <?= !$hasFile ? 'required' : '' ?> accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <label class="profile-settings-field">
                    <span>Choose Sidebar Photo</span>
                    <input type="file" accept="image/*" data-profile-photo-input>
                </label>
                <p class="hint" data-profile-photo-status>Stored only in this browser for this employee.</p>

                <div class="profile-verification-actions employee-profile-settings-actions">
                    <button class="button ghost" type="button" data-switch-modal-target="employee-password-modal">Change Password</button>
                    <button class="button solid" type="submit">Save Profile</button>
                </div>
            </form>

            <?php if ($showOfferLetterForm): ?>
                <?php
                    $offerName = trim((string) ($employee['offer_letter_name'] ?? '')) ?: (string) ($employee['name'] ?? '');
                    $offerAddress = trim((string) ($employee['offer_letter_address'] ?? '')) ?: (string) ($employee['address'] ?? '');
                    $offerDesignation = trim((string) ($employee['offer_letter_designation'] ?? '')) ?: (string) ($employee['designation'] ?? '');
                    $offerSignature = !empty($employee['offer_letter_signature_path'])
                        ? public_file_path((string) $employee['offer_letter_signature_path'])
                        : '';
                ?>
                <section class="profile-verification-section offer-letter-section offer-letter-launch-section">
                    <div class="profile-verification-section-head">
                        <strong>Offer Letter</strong>
                        <button class="button solid small" type="button" data-modal-target="employee-offer-letter-modal">Open Offer Letter Form</button>
                    </div>
                    <p class="hint">Open the offer letter as a separate form to update details and upload your signature.</p>
                </section>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($showOfferLetterForm): ?>
        <div class="modal" id="employee-offer-letter-modal">
            <div class="modal-card offer-letter-modal-card">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Verified Employee</span>
                <h2>Offer Letter Form</h2>
                <p class="hint">Update the offer letter fields and upload your signature image.</p>
                <section class="profile-verification-section offer-letter-section">
                    <div class="offer-letter-preview">
                        <div class="offer-letter-head">
                            <strong>V Traco</strong>
                            <span>Offer Letter</span>
                        </div>
                        <p>Date: <?= h(date('d M Y')) ?></p>
                        <p>To,<br><strong><?= h($offerName) ?></strong><br><?= nl2br(h($offerAddress !== '' ? $offerAddress : '-')) ?></p>
                        <div class="offer-letter-details">
                            <div><strong>Employee ID</strong><span><?= h((string) (($employee['emp_id'] ?? '') ?: '-')) ?></span></div>
                            <div><strong>Name</strong><span><?= h($offerName !== '' ? $offerName : '-') ?></span></div>
                            <div><strong>Designation</strong><span><?= h($offerDesignation !== '' ? $offerDesignation : '-') ?></span></div>
                            <div><strong>Employer</strong><span><?= h($employerDisplay) ?></span></div>
                            <div><strong>Email</strong><span><?= h((string) (($employee['email'] ?? '') ?: '-')) ?></span></div>
                            <div><strong>Phone</strong><span><?= h((string) (($employee['phone'] ?? '') ?: '-')) ?></span></div>
                            <div><strong>Date of Joining</strong><span><?= !empty($employee['date_of_joining']) ? h(date('d M Y', strtotime((string) $employee['date_of_joining']))) : '-' ?></span></div>
                            <div><strong>Shift</strong><span><?= h(employee_shift_display($employee)) ?></span></div>
                            <div><strong>Salary</strong><span>Rs <?= h(number_format((float) ($employee['salary'] ?? 0), 2)) ?></span></div>
                            <div class="offer-letter-detail-wide"><strong>Address</strong><span><?= nl2br(h($offerAddress !== '' ? $offerAddress : '-')) ?></span></div>
                        </div>
                        <p>Dear <?= h($offerName !== '' ? $offerName : 'Employee') ?>,</p>
                        <p>We are pleased to offer you the position of <strong><?= h($offerDesignation !== '' ? $offerDesignation : 'Employee') ?></strong> with <?= h($employerDisplay) ?>. Your joining and work details will follow the rules assigned in V Traco.</p>
                        <p>Please confirm your acceptance by updating the details below and uploading your signature image.</p>
                        <div class="offer-letter-sign-row">
                            <div>
                                <span>Employee Signature</span>
                                <?php if ($offerSignature !== ''): ?>
                                    <img class="offer-letter-signature" src="<?= h($offerSignature) ?>" alt="Employee signature">
                                <?php else: ?>
                                    <strong class="offer-letter-sign-placeholder">Signature pending</strong>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span>For <?= h($employerDisplay) ?></span>
                                <strong>Authorized Signatory</strong>
                            </div>
                        </div>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="stack-form offer-letter-form" data-validate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="employee_offer_letter_update">
                        <div class="profile-verification-grid">
                            <div class="field"><label>Name</label><div class="field-row"><input type="text" name="offer_letter_name" value="<?= h($offerName) ?>" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                            <div class="field"><label>Designation</label><div class="field-row"><input type="text" name="offer_letter_designation" value="<?= h($offerDesignation) ?>" required></div><small class="field-error"><span>!</span>Designation is required.</small></div>
                        </div>
                        <label class="profile-verification-wide">Address<textarea name="offer_letter_address" required><?= h($offerAddress) ?></textarea></label>
                        <label class="profile-document-upload<?= $offerSignature !== '' ? ' has-file' : '' ?>">
                            <span class="profile-document-icon"><?= $offerSignature !== '' ? 'OK' : '+' ?></span>
                            <span class="profile-document-copy">
                                <strong>Signature Image</strong>
                                <small><?= $offerSignature !== '' ? h((string) ($employee['offer_letter_signature_name'] ?? 'Uploaded')) : 'JPG, PNG, or WEBP' ?></small>
                            </span>
                            <input type="file" name="offer_letter_signature" <?= $offerSignature === '' ? 'required' : '' ?> accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                        </label>
                        <div class="profile-verification-actions employee-profile-settings-actions">
                            <button class="button solid" type="submit">Save Offer Letter</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    <?php endif; ?>
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
    $returnPage = (string) ($_GET['page'] ?? home_page_for_user($admin));
    $allowedPrefixes = ['admin_', 'vendor_', 'corporate_', 'member_'];
    $isValid = false;
    foreach ($allowedPrefixes as $prefix) {
        if (str_starts_with($returnPage, $prefix)) {
            $isValid = true;
            break;
        }
    }
    if (!$isValid || $returnPage === 'admin_profile_settings') {
        $returnPage = home_page_for_user($admin);
    }
    $memberSince = !empty($admin['created_at']) ? date('d M Y', strtotime((string) $admin['created_at'])) : 'Recently added';
    $isVendorProfile = ($admin['role'] ?? '') === 'external_vendor';
    $roleLabel = user_role_label((string) ($admin['role'] ?? 'admin'));
    $companyName = trim((string) ($admin['company_name'] ?? ''));
    if ($companyName === '') {
        $companyName = (string) ($admin['name'] ?? '');
    }
    $representativeName = trim((string) ($admin['representative_name'] ?? ''));
    if ($representativeName === '') {
        $representativeName = (string) ($admin['name'] ?? '');
    }
    $companyAddress = trim((string) ($admin['company_address'] ?? ''));
    $companyEmail = trim((string) ($admin['company_email'] ?? ''));
    $companyPhone = trim((string) ($admin['company_phone'] ?? ''));
    $designation = trim((string) ($admin['designation'] ?? ''));
    $personalEmail = trim((string) ($admin['personal_email'] ?? ''));
    if ($personalEmail === '') {
        $personalEmail = (string) ($admin['email'] ?? '');
    }
    $personalPhone = trim((string) ($admin['personal_phone'] ?? ''));
    if ($personalPhone === '') {
        $personalPhone = (string) ($admin['phone'] ?? '');
    }
    $vendorDocumentNames = [
        'bank_proof' => trim((string) ($admin['bank_proof_name'] ?? '')),
        'company_logo' => trim((string) ($admin['company_logo_name'] ?? '')),
        'profile_photo' => trim((string) ($admin['profile_photo_name'] ?? '')),
    ];
    $showBiometricIntegration = ($admin['role'] ?? '') === 'admin';
    $biometricIntegration = $showBiometricIntegration ? biometric_integration_for_admin((int) $admin['id']) : null;
    $biometricBaseUrl = (string) ($biometricIntegration['base_url'] ?? 'https://api.etimeoffice.com/api/');
    $biometricCorporateId = (string) ($biometricIntegration['corporate_id'] ?? 'karyoun');
    $biometricUsername = (string) ($biometricIntegration['username'] ?? 'Arun');
    $biometricEnabled = $biometricIntegration ? !empty($biometricIntegration['is_enabled']) : true;
    $biometricLastSync = !empty($biometricIntegration['last_sync_at'])
        ? date('d M Y, h:i A', strtotime((string) $biometricIntegration['last_sync_at']))
        : 'Not synced yet';
    $biometricLastTest = !empty($biometricIntegration['last_test_at'])
        ? date('d M Y, h:i A', strtotime((string) $biometricIntegration['last_test_at']))
        : 'Not tested yet';
    ?>
    <div class="modal" id="admin-profile-settings-modal">
        <div class="modal-card profile-settings-modal-card<?= $isVendorProfile ? ' vendor-profile-settings-card' : '' ?>">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            
            <?php if ($isVendorProfile): ?>
                <aside class="vendor-profile-settings-sidebar">
                    <span class="eyebrow">Profile Settings</span>
                    <h2><?= h($companyName) ?></h2>
                    <div class="profile-settings-grid">
                        <div class="list-item">
                            <strong>Your Name</strong>
                            <span><?= h($representativeName) ?></span>
                        </div>
                        <div class="list-item">
                            <strong>Designation</strong>
                            <span><?= h($designation !== '' ? $designation : 'Not added') ?></span>
                        </div>
                        <div class="list-item">
                            <strong>Company Mail</strong>
                            <span><?= h($companyEmail !== '' ? $companyEmail : (string) $admin['email']) ?></span>
                        </div>
                        <div class="list-item">
                            <strong>Company Phone</strong>
                            <span><?= h($companyPhone !== '' ? $companyPhone : 'Not added') ?></span>
                        </div>
                        <div class="list-item">
                            <strong>Member Since</strong>
                            <span><?= h($memberSince) ?></span>
                        </div>
                        <div class="list-item">
                            <strong>Role</strong>
                            <span><?= h($roleLabel) ?></span>
                        </div>
                    </div>
                    <p class="hint">Upload company proof, logo, and your photo from the form.</p>
                </aside>

                <main class="vendor-profile-settings-main">
                    <form method="post" enctype="multipart/form-data" class="vendor-profile-settings-form" data-validate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="admin_profile_update">
                        <input type="hidden" name="return_page" value="<?= h($returnPage) ?>">
                        
                        <div class="form-section-card">
                            <span class="eyebrow">Company Detail</span>
                            <div class="field">
                                <label>Company Name</label>
                                <div class="field-row"><input type="text" name="company_name" value="<?= h($companyName) ?>" required></div>
                                <small class="field-error"><span>!</span>Company name is required.</small>
                            </div>
                            <div class="field">
                                <label>Company Mail</label>
                                <div class="field-row"><input type="email" name="company_email" value="<?= h($companyEmail) ?>" required></div>
                                <small class="field-error"><span>!</span>Valid company mail is required.</small>
                            </div>
                            <div class="field">
                                <label>Company Phone Number</label>
                                <div class="field-row"><input type="text" name="company_phone" value="<?= h($companyPhone) ?>" required></div>
                                <small class="field-error"><span>!</span>Company phone number is required.</small>
                            </div>
                            <div class="field profile-settings-wide">
                                <label>Company Address</label>
                                <div class="field-row"><input type="text" name="company_address" value="<?= h($companyAddress) ?>" required></div>
                                <small class="field-error"><span>!</span>Company address is required.</small>
                            </div>
                        </div>

                        <div class="form-section-card">
                            <span class="eyebrow">Your Detail</span>
                            <div class="field">
                                <label>Your Name</label>
                                <div class="field-row"><input type="text" name="representative_name" value="<?= h($representativeName) ?>" required></div>
                                <small class="field-error"><span>!</span>Your name is required.</small>
                            </div>
                            <div class="field">
                                <label>Your Designation</label>
                                <div class="field-row"><input type="text" name="designation" value="<?= h($designation) ?>" required></div>
                                <small class="field-error"><span>!</span>Your designation is required.</small>
                            </div>
                            <div class="field">
                                <label>Personal Number</label>
                                <div class="field-row"><input type="text" name="personal_phone" value="<?= h($personalPhone) ?>" required></div>
                                <small class="field-error"><span>!</span>Personal number is required.</small>
                            </div>
                            <div class="field">
                                <label>Personal Mail</label>
                                <div class="field-row"><input type="email" name="personal_email" value="<?= h($personalEmail) ?>" required></div>
                                <small class="field-error"><span>!</span>Valid personal mail is required.</small>
                            </div>
                        </div>

                        <div class="form-section-card">
                            <span class="eyebrow">Tax Detail</span>
                            <div class="field">
                                <label>GST (if have)</label>
                                <div class="field-row"><input type="text" name="gst_no" value="<?= h((string) ($admin['gst_no'] ?? '')) ?>"></div>
                            </div>
                        </div>

                        <div class="form-section-card">
                            <span class="eyebrow">Upload</span>
                            <div class="field">
                                <label>Company Bank Proof</label>
                                <div class="field-row"><input type="file" name="bank_proof" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" <?= $vendorDocumentNames['bank_proof'] === '' ? 'required' : '' ?>></div>
                                <small class="hint"><?= h($vendorDocumentNames['bank_proof'] !== '' ? $vendorDocumentNames['bank_proof'] : 'PDF, image, DOC, or DOCX up to 5 MB.') ?></small>
                            </div>
                            <div class="field">
                                <label>Logo</label>
                                <div class="field-row"><input type="file" name="company_logo" accept="image/*" <?= $vendorDocumentNames['company_logo'] === '' ? 'required' : '' ?>></div>
                                <small class="hint"><?= h($vendorDocumentNames['company_logo'] !== '' ? $vendorDocumentNames['company_logo'] : 'JPG, PNG, or WEBP up to 5 MB.') ?></small>
                            </div>
                            <div class="field">
                                <label>Your Photo</label>
                                <div class="field-row"><input type="file" name="profile_photo" accept="image/*" data-profile-photo-input <?= $vendorDocumentNames['profile_photo'] === '' ? 'required' : '' ?>></div>
                                <small class="hint" data-profile-photo-status><?= h($vendorDocumentNames['profile_photo'] !== '' ? $vendorDocumentNames['profile_photo'] : 'JPG, PNG, or WEBP up to 5 MB.') ?></small>
                            </div>
                        </div>

                        <div class="profile-settings-actions">
                            <button class="button ghost" type="button" data-switch-modal-target="admin-password-modal">Change Password</button>
                            <button class="button solid" type="submit">Save Changes</button>
                        </div>
                    </form>
                </main>

            <?php else: ?>
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
                        <span><?= h($roleLabel) ?></span>
                    </div>
                </div>
                <label class="profile-settings-field">
                    <span>Choose Profile Photo</span>
                    <input type="file" accept="image/*" data-profile-photo-input>
                </label>
                <p class="hint" data-profile-photo-status>Stored only in this browser for this <?= h(strtolower($roleLabel)) ?>.</p>
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
                <?php if ($showBiometricIntegration): ?>
                    <hr class="soft-divider">
                    <button class="button outline profile-settings-toggle" type="button" data-switch-modal-target="admin-biometric-integration-modal">Biometric Integration</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($showBiometricIntegration): ?>
    <div class="modal" id="admin-biometric-integration-modal">
        <div class="modal-card" style="max-width:720px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Biometric Integration</span>
            <h2>eTime Office</h2>
            <p>Connect this admin account to eTime Office so Track Attendance can mark biometric IN/OUT records automatically.</p>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_biometric_integration_save">
                <input type="hidden" name="return_page" value="<?= h($returnPage) ?>">
                <label class="checkbox-line">
                    <input type="checkbox" name="is_enabled" value="1" <?= $biometricEnabled ? 'checked' : '' ?>>
                    <span>Enable automatic eTime Office attendance sync</span>
                </label>
                <div class="reports-filter-grid">
                    <div class="field">
                        <label>API Base URL</label>
                        <div class="field-row"><input type="url" name="base_url" value="<?= h($biometricBaseUrl) ?>" required></div>
                        <small class="field-error"><span>!</span>Base URL is required.</small>
                    </div>
                    <div class="field">
                        <label>Corporate ID</label>
                        <div class="field-row"><input type="text" name="corporate_id" value="<?= h($biometricCorporateId) ?>" required></div>
                        <small class="field-error"><span>!</span>Corporate ID is required.</small>
                    </div>
                    <div class="field">
                        <label>Username</label>
                        <div class="field-row"><input type="text" name="username" value="<?= h($biometricUsername) ?>" required></div>
                        <small class="field-error"><span>!</span>Username is required.</small>
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <div class="field-row"><input type="password" name="password" autocomplete="new-password" placeholder="<?= $biometricIntegration ? 'Leave blank to keep saved password' : 'Enter eTime password' ?>" <?= $biometricIntegration ? '' : 'required' ?>></div>
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
                <div class="profile-settings-actions">
                    <button class="button ghost" type="button" data-switch-modal-target="admin-profile-settings-modal">Back to Profile</button>
                    <button class="button outline" type="submit" name="integration_mode" value="test">Test Connection</button>
                    <button class="button solid" type="submit" name="integration_mode" value="save">Save Integration</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
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
    global $page;
    $user = current_user();
    $isAdminShell = $user && in_array($user['role'], ['admin', 'freelancer', 'external_vendor'], true);
    $isEmployeeShell = $user && in_array($user['role'], ['employee', 'corporate_employee'], true);
    $isSidebarShell = $isAdminShell || $isEmployeeShell;
    $currentPage = $page ?? ($_GET['page'] ?? 'landing');
    $showLandingLoginModal = !$user && in_array($currentPage, ['landing', 'register'], true);
    $landingAuthRole = in_array($_GET['auth'] ?? '', ['admin', 'employee', 'corporate_employee', 'external_vendor'], true) ? $_GET['auth'] : '';
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
                        <span>Use the credentials shared by your admin to sign in.</span>
                    </button>
                    <button class="landing-login-option" type="button" data-switch-modal-target="corporate_employee-login-modal">
                        <span class="landing-login-icon employee">C</span>
                        <strong>Contractual Employee</strong>
                        <span>Use your self-registered contractual employee account.</span>
                    </button>
                </div>
            </div>
        </div>
        <?php render_landing_auth_modal('admin', $landingAuthRole === 'admin'); ?>
        <?php render_landing_auth_modal('employee', $landingAuthRole === 'employee'); ?>
        <?php render_landing_auth_modal('external_vendor', $landingAuthRole === 'external_vendor'); ?>
        <?php render_landing_auth_modal('corporate_employee', $landingAuthRole === 'corporate_employee'); ?>
        <?php render_landing_register_form_modal('admin'); ?>
        <?php render_landing_register_form_modal('corporate_employee'); ?>
    <?php endif; ?>
    <div class="modal" id="attendance-modal">
        <div class="modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="modal-grid" id="modal-content"></div>
        </div>
    </div>
    <script>window.VTRACO_CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;</script>
    <script src="<?= h(asset_url('assets/js/app.js') . '?v=' . (string) filemtime(__DIR__ . '/../assets/js/app.js')) ?>"></script>
    </body>
    </html>
    <?php
}
