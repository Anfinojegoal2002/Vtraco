<?php

declare(strict_types=1);

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
                <div class="field-row"><input type="text" name="phone" placeholder="+91" required></div>
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
                <?php $loginHref = $selectedRole === 'corporate_employee' ? (BASE_URL . '?page=landing&auth=corporate_employee') : (BASE_URL . '?page=login&role=' . $selectedRole); ?>
                <a class="auth-register-link" href="<?= h($loginHref) ?>">Login</a>
            </div>
        </form>
    </div>
    </section>
    <?php
    render_footer();
}


