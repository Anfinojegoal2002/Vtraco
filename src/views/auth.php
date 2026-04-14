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
            'title' => 'Employee Login',
            'eyebrow' => 'Employee Access',
            'description' => 'Use the credentials shared by your admin to access your attendance workspace.',
        ],
        'external_vendor' => [
            'title' => 'External Vendor Login',
            'eyebrow' => 'Vendor Access',
            'description' => 'Use your vendor registration details to access your account.',
        ],
        'freelancer' => [
            'title' => 'Corporate Employee (Freelancer) Login',
            'eyebrow' => 'Freelancer Access',
            'description' => 'Use your freelancer registration details to access your account.',
        ],
    ];
}

function auth_registration_roles(): array
{
    return [
        'admin' => [
            'eyebrow' => 'Admin Setup',
            'title' => 'Create Admin Account',
            'description' => 'Register a management account for payroll, rules, attendance review, and employee operations.',
            'button' => 'Register',
        ],
        'external_vendor' => [
            'eyebrow' => 'Vendor Registration',
            'title' => 'Create External Vendor Account',
            'description' => 'Register an external vendor account for partner and service-provider access.',
            'button' => 'Register Vendor',
        ],
        'freelancer' => [
            'eyebrow' => 'Freelancer Registration',
            'title' => 'Create Corporate Employee (Freelancer) Account',
            'description' => 'Register a freelancer account for self-managed access to the portal.',
            'button' => 'Register Freelancer',
        ],
    ];
}

function render_login(): void
{
    $roles = auth_login_roles();
    $role = can_login_role((string) ($_GET['role'] ?? 'admin')) ? (string) $_GET['role'] : 'admin';
    $selected = $roles[$role];
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
            <div class="cards-2 auth-role-grid">
                <?php foreach ($roles as $key => $meta): ?>
                    <a class="action-card<?= $key === $role ? ' active-auth-role' : '' ?>" href="<?= h(BASE_URL) ?>?page=login&role=<?= h($key) ?>">
                        <strong><?= h(user_role_label($key)) ?></strong>
                        <span class="hint"><?= h($meta['eyebrow']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="spacer"></div>
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
                <?php if ($role === 'employee'): ?>
                    <button class="button ghost" type="submit" name="forgot_password" value="1">Forgot your password?</button>
                    <p class="hint">Enter your employee email above and we will send or log a temporary password for that account.</p>
                <?php endif; ?>
            </form>
            <div class="spacer"></div>
            <?php if ($role === 'employee'): ?>
                <p class="hint">Employees cannot self-register. Please use the credentials sent to you by your admin.</p>
            <?php else: ?>
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
            <span class="brand-mark auth-register-brand-mark">VT</span>
            <div class="auth-register-brand-copy">
                <strong>V Traco</strong>
                <span>Attendance &amp; Payroll</span>
            </div>
        </div>

        <p class="auth-register-tagline">Choose your registration type, then fill in the form below.</p>

        <div class="auth-register-segmented" role="tablist" aria-label="Registration types">
            <?php foreach ($roles as $role => $meta): ?>
                <a class="auth-register-segment<?= $role === $selectedRole ? ' active-auth-role' : '' ?>" href="<?= h(BASE_URL) ?>?page=register&role=<?= h($role) ?>">
                    <strong><?= h(user_role_label($role)) ?></strong>
                </a>
            <?php endforeach; ?>
        </div>

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
                <div class="field-row"><input type="text" name="name" placeholder="<?= h(user_role_label($selectedRole)) ?> name" required></div>
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
                    <input id="register-password-<?= h($selectedRole) ?>" type="password" name="password" minlength="8" placeholder="Minimum 8 characters with letters and numbers" required>
                    <button class="password-toggle" type="button" data-password-toggle="register-password-<?= h($selectedRole) ?>">Show</button>
                </div>
                <small class="field-error"><span>!</span>Password must be at least 8 characters and include a letter and number.</small>
            </div>
            <div class="field">
                <label>Confirm Password</label>
                <div class="field-row">
                    <input id="register-confirm-password-<?= h($selectedRole) ?>" type="password" name="confirm_password" minlength="8" placeholder="Repeat password" required>
                    <button class="password-toggle" type="button" data-password-toggle="register-confirm-password-<?= h($selectedRole) ?>">Show</button>
                </div>
                <small class="field-error"><span>!</span>Please confirm the password.</small>
            </div>
            <button class="button solid auth-register-submit" type="submit"><?= h($selected['button']) ?></button>
            <div class="auth-register-footer">
                <span>Already have an account?</span>
                <button class="auth-register-link" type="button" data-modal-target="landing-login-modal">Login</button>
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
    render_header('My Account');
    ?>
    <section class="auth-page-wrap">
    <div class="auth-panel auth-panel-wide">
        <div class="panel">
            <span class="eyebrow"><?= h(user_role_label((string) $user['role'])) ?></span>
            <h1><?= h((string) $user['name']) ?></h1>
            <p>Your account has been registered successfully. This portal can be extended with role-specific workflows next.</p>
        </div>
        <div class="dashboard-grid">
            <div class="section-block">
                <h2>Account Details</h2>
                <div class="list">
                    <div class="list-item"><strong>Role</strong><br><span class="hint"><?= h(user_role_label((string) $user['role'])) ?></span></div>
                    <div class="list-item"><strong>Email</strong><br><span class="hint"><?= h((string) $user['email']) ?></span></div>
                    <div class="list-item"><strong>Phone</strong><br><span class="hint"><?= h((string) (($user['phone'] ?? '') ?: 'Not added')) ?></span></div>
                    <div class="list-item"><strong>Joined</strong><br><span class="hint"><?= h(!empty($user['created_at']) ? date('d M Y', strtotime((string) $user['created_at'])) : 'Recently added') ?></span></div>
                </div>
            </div>
            <div class="section-block">
                <h2>Next Step</h2>
                <p>This new role is now registered and can sign in successfully. If you want, I can build role-specific pages and workflows for vendors and freelancers next.</p>
                <div class="inline-actions">
                    <a class="button outline" href="<?= h(BASE_URL) ?>">Go to Landing Page</a>
                </div>
            </div>
        </div>
    </div>
    </section>
    <?php
    render_footer();
}
