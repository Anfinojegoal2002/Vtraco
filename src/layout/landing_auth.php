<?php

declare(strict_types=1);

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


