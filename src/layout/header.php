<?php

declare(strict_types=1);

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
    $sidebarProfilePhoto = $user && !empty($user['profile_photo_path'])
        ? public_file_path((string) $user['profile_photo_path'])
        : '';
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
        <link rel="stylesheet" href="<?= h(asset_url('assets/css/app.css') . '?v=' . (string) filemtime(__DIR__ . '/../../assets/css/app.css')) ?>">
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
                        <img src="<?= h($sidebarProfilePhoto) ?>" alt="<?= h($user['name']) ?> profile photo" class="avatar sidebar-avatar-image<?= $sidebarProfilePhoto === '' ? ' hidden' : '' ?>" data-profile-photo>
                        <div class="avatar<?= $sidebarProfilePhoto !== '' ? ' hidden' : '' ?>" data-profile-fallback><?= h(user_initials((string) $user['name'])) ?></div>
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


