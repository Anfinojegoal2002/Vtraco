<?php

declare(strict_types=1);

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
                        <?php $loginHref = $key === 'corporate_employee' ? (BASE_URL . '?page=landing&auth=corporate_employee') : (BASE_URL . '?page=login&role=' . $key); ?>
                        <a class="action-card<?= $key === $role ? ' active-auth-role' : '' ?>" href="<?= h($loginHref) ?>">
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


