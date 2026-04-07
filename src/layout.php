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
        case 'admin_rules':
            render_admin_rules();
            break;
        case 'admin_attendance':
            render_admin_attendance();
            break;
        case 'employee_attendance':
            render_employee_attendance();
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
    $isAdminShell = $user && $user['role'] === 'admin';
    $isEmployeeShell = $user && $user['role'] === 'employee';
    $isLandingPage = !$user && $page === 'landing';
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
        <link rel="stylesheet" href="/vtraco/assets/css/app.css">
    </head>
    <body class="<?= $isLandingPage ? 'landing-shell' : 'app-fixed' ?>">
    <div class="app-shell <?= $isAdminShell ? 'admin-shell' : '' ?>">
        <?php if ($isAdminShell): ?>
            <aside class="admin-sidebar">
                <a class="brand" href="<?= h(BASE_URL) ?>?page=admin_dashboard">
                    <span class="brand-mark">VT</span>
                    <span class="brand-copy"><strong>V Traco</strong><small>Attendance & Payroll</small></span>
                </a>
                <div class="sidebar-profile">
                    <div class="avatar"><?= h(strtoupper(substr((string) $user['name'], 0, 1))) ?></div>
                    <div>
                        <strong><?= h($user['name']) ?></strong><br>
                        <span class="hint">Administrator</span>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <a class="sidebar-link <?= $page === 'admin_dashboard' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_dashboard"><span class="nav-icon">D</span><span>Dashboard</span></a>
                    <a class="sidebar-link <?= $page === 'admin_employees' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_employees"><span class="nav-icon">E</span><span>Employees</span></a>
                    <a class="sidebar-link <?= $page === 'admin_attendance' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_attendance"><span class="nav-icon">A</span><span>Attendance</span></a>
                    <a class="sidebar-link <?= $page === 'admin_rules' ? 'active' : '' ?>" href="<?= h(BASE_URL) ?>?page=admin_rules"><span class="nav-icon">R</span><span>Rules</span></a>
                </nav>
                <div class="sidebar-actions">
                    <form method="post">
                        <input type="hidden" name="action" value="logout">
                        <button class="button ghost admin-logout-button" type="submit" style="width:100%;">Logout</button>
                    </form>
                </div>
            </aside>
            <div class="admin-main">
                <?php $flashItems = flashes(); if ($flashItems): ?>
                    <div class="flash-stack">
                        <?php foreach ($flashItems as $flash): ?>
                            <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
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
                        <?php if ($isEmployeeShell): ?>
                            <div class="sidebar-profile" style="padding:10px 14px;background:rgba(67,56,202,0.06);">
                                <div class="avatar"><?= h(strtoupper(substr((string) $user['name'], 0, 1))) ?></div>
                                <div>
                                    <strong><?= h($user['name']) ?></strong><br>
                                    <span class="hint"><?= h((string) ($user['emp_id'] ?: 'Employee')) ?></span>
                                </div>
                            </div>
                            <a class="button outline" href="<?= h(BASE_URL) ?>?page=employee_attendance">My Attendance</a>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="action" value="logout">
                            <button class="button ghost" type="submit">Logout</button>
                        </form>
                    <?php else: ?>
                        <?php if ($isLandingPage): ?>
                            <button class="button outline" type="button" data-modal-target="landing-login-modal">Login</button>
                        <?php else: ?>
                            <a class="button outline" href="<?= h(BASE_URL) ?>?page=login&role=admin">Login</a>
                        <?php endif; ?>
                        <a class="button solid" href="<?= h(BASE_URL) ?>?page=register">Admin Register</a>
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
        <?php endif; ?>
    <?php
}

function render_landing_auth_modal(string $role, bool $isOpen): void
{
    $title = $role === 'admin' ? 'Admin Login' : 'Employee Login';
    $eyebrow = $role === 'admin' ? 'Admin Access' : 'Employee Access';
    $iconLabel = $role === 'admin' ? 'A' : 'E';
    $modalId = $role . '-login-modal';
    $passwordId = $role . '-modal-password';
    ?>
    <div class="modal <?= $isOpen ? 'open' : '' ?>" id="<?= h($modalId) ?>">
        <div class="modal-card landing-auth-modal">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="landing-auth-shell">
                <div class="landing-auth-copy">
                    <span class="landing-login-icon <?= $role === 'employee' ? 'employee' : '' ?>"><?= h($iconLabel) ?></span>
                    <span class="eyebrow"><?= h($eyebrow) ?></span>
                    <h2><?= h($title) ?></h2>
                    <?php if ($role === 'admin'): ?>
                    <p>Sign in to continue to your management workspace.</p>
                    <?php else: ?>
                    <p>Employees cannot self-register. Use the email and password sent to you by your admin via email.</p>
                    <?php endif; ?>
                    <button class="button ghost small" type="button" data-switch-modal-target="landing-login-modal">Back</button>
                </div>
                <form method="post" class="stack-form landing-auth-form" data-validate>
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
                    <?php if ($role === 'admin'): ?>
                        <p class="hint">Need an account? <a href="<?= h(BASE_URL) ?>?page=register">Register admin</a></p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function render_footer(): void
{
    $user = current_user();
    $isAdminShell = $user && $user['role'] === 'admin';
    $page = $_GET['page'] ?? 'landing';
    $showLandingLoginModal = !$user && $page === 'landing';
    $landingAuthRole = in_array($_GET['auth'] ?? '', ['admin', 'employee'], true) ? $_GET['auth'] : '';
    ?>
        </main>
        <?php if ($isAdminShell): ?>
            </div>
        <?php endif; ?>
    </div>
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
                </div>
            </div>
        </div>
        <?php render_landing_auth_modal('admin', $landingAuthRole === 'admin'); ?>
        <?php render_landing_auth_modal('employee', $landingAuthRole === 'employee'); ?>
    <?php endif; ?>
    <div class="modal" id="attendance-modal">
        <div class="modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="modal-grid" id="modal-content"></div>
        </div>
    </div>
    <script src="/vtraco/assets/js/app.js"></script>
    </body>
    </html>
    <?php
}
