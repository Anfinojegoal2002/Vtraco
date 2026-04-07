<?php

declare(strict_types=1);

function render_login(): void
{
    $role = in_array($_GET['role'] ?? 'admin', ['admin', 'employee'], true) ? $_GET['role'] : 'admin';
    render_header(ucfirst($role) . ' Login');
    ?>
    <section class="auth-page-wrap">
    <div class="auth-panel">
        <div class="panel">
            <span class="eyebrow"><?= h(ucfirst($role)) ?> Access</span>
            <h1><?= h(ucfirst($role)) ?> Login</h1>
            <form method="post" class="stack-form" data-validate>
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
                <button class="button solid" type="submit">Login</button>
            </form>
            <?php if ($role === 'admin'): ?>
                <p><a href="<?= h(BASE_URL) ?>?page=register">Register admin</a></p>
            <?php else: ?>
                <p class="hint">Employees cannot self-register. Please use the email and password sent to you by your admin via email.</p>
            <?php endif; ?>
        </div>
    </div>
    </section>
    <?php
    render_footer();
}

function render_register(): void
{
    render_header('Register Admin');
    ?>
    <section class="auth-page-wrap">
    <div class="auth-panel">
        <div class="panel">
            <span class="eyebrow">Admin Setup</span>
            <h1>Create an Admin Account</h1>
            <form method="post" class="stack-form" data-validate>
                <input type="hidden" name="action" value="register_admin">
                <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" placeholder="Admin name" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" placeholder="admin@company.com" required></div><small class="field-error"><span>!</span>Enter a valid email address.</small></div>
                <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" placeholder="Phone number" required></div><small class="field-error"><span>!</span>Phone number is required.</small></div>
                <div class="field">
                    <label>Password</label>
                    <div class="field-row">
                        <input id="register-password" type="password" name="password" minlength="6" placeholder="Minimum 6 characters" required>
                        <button class="password-toggle" type="button" data-password-toggle="register-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Password must be at least 6 characters.</small>
                </div>
                <div class="field">
                    <label>Confirm Password</label>
                    <div class="field-row">
                        <input id="register-confirm-password" type="password" name="confirm_password" minlength="6" placeholder="Repeat password" required>
                        <button class="password-toggle" type="button" data-password-toggle="register-confirm-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Please confirm the password.</small>
                </div>
                <button class="button solid" type="submit">Register Admin</button>
            </form>
        </div>
    </div>
    </section>
    <?php
    render_footer();
}