<?php

declare(strict_types=1);

function reset_login_password_by_email(string $role, string $email): array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE role = :role AND email = :email ORDER BY id DESC LIMIT 1');
    $stmt->execute([
        'role' => $role,
        'email' => $email,
    ]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['handled' => false, 'rate_limited' => false, 'user' => null];
    }

    $lastRequestedAt = (string) ($user['password_reset_requested_at'] ?? '');
    if ($lastRequestedAt !== '' && strtotime($lastRequestedAt) > (time() - forgot_password_cooldown_seconds())) {
        return ['handled' => true, 'rate_limited' => true, 'user' => $user];
    }

    $password = (string) random_int(100000, 999999);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = date('Y-m-d H:i:s');

    db()->prepare('UPDATE users SET password_hash = :password_hash, force_password_change = 1, password_reset_requested_at = :requested_at, password_changed_at = NULL WHERE id = :id')
        ->execute([
            'password_hash' => $hash,
            'requested_at' => $now,
            'id' => (int) $user['id'],
        ]);

    $updatedUser = array_merge($user, [
        'password_hash' => $hash,
        'force_password_change' => 1,
        'password_reset_requested_at' => $now,
        'password_changed_at' => null,
    ]);

    $roleLabel = user_role_label($role);
    $html = '<p>Hello ' . h((string) $updatedUser['name']) . ',</p>'
        . '<p>A password was created for your ' . h($roleLabel) . ' account.</p>'
        . '<p><strong>Login Email:</strong> ' . h((string) $updatedUser['email']) . '<br>'
        . '<strong>Password:</strong> ' . h($password) . '</p>'
        . '<p>Please sign in and change your password from Profile Settings.</p>';
    $mailResult = send_html_mail((string) $updatedUser['email'], 'Your V Traco Password', $html);

    return [
        'handled' => true,
        'rate_limited' => false,
        'user' => $updatedUser,
        'password' => $password,
        'mail_result' => $mailResult,
    ];
}

function password_reset_user_by_email(string $role, string $email): ?array
{
    $stmt = db()->prepare('
        SELECT *
        FROM users
        WHERE LOWER(TRIM(email)) = LOWER(:email)
          AND role IN ("admin", "employee", "corporate_employee", "external_vendor")
        ORDER BY CASE WHEN role = :role THEN 0 ELSE 1 END, id DESC
        LIMIT 1
    ');
    $stmt->execute([
        'email' => trim($email),
        'role' => $role,
    ]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function send_login_password_reset_otp(string $role, string $email): array
{
    $user = password_reset_user_by_email($role, $email);

    if (!$user) {
        return ['handled' => false, 'rate_limited' => false, 'user' => null];
    }

    $role = (string) $user['role'];
    $email = (string) $user['email'];
    $otp = (string) random_int(100000, 999999);
    $now = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', time() + 10 * 60);

    db()->prepare('UPDATE password_reset_otps SET used_at = :used_at WHERE user_id = :user_id AND role = :role AND used_at IS NULL')
        ->execute([
            'used_at' => $now,
            'user_id' => (int) $user['id'],
            'role' => $role,
        ]);

    db()->prepare('INSERT INTO password_reset_otps (user_id, role, email, otp_hash, expires_at, created_at) VALUES (:user_id, :role, :email, :otp_hash, :expires_at, :created_at)')
        ->execute([
            'user_id' => (int) $user['id'],
            'role' => $role,
            'email' => $email,
            'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

    db()->prepare('UPDATE users SET password_reset_requested_at = :requested_at WHERE id = :id')
        ->execute([
            'requested_at' => $now,
            'id' => (int) $user['id'],
        ]);

    $roleLabel = user_role_label($role);
    $html = '<p>Hello ' . h((string) $user['name']) . ',</p>'
        . '<p>Use this OTP to continue resetting your ' . h($roleLabel) . ' account password.</p>'
        . '<p style="font-size:24px;font-weight:800;letter-spacing:6px;">' . h($otp) . '</p>'
        . '<p>This OTP expires in 10 minutes. If you did not request it, you can ignore this email.</p>';
    $mailResult = send_html_mail((string) $user['email'], 'Your V Traco Password Reset OTP', $html);

    return [
        'handled' => true,
        'rate_limited' => false,
        'user' => $user,
        'mail_result' => $mailResult,
    ];
}

function verify_login_password_reset_otp(string $role, string $email, string $otp): array
{
    $stmt = db()->prepare('
        SELECT pro.*, u.name, u.email AS user_email
        FROM password_reset_otps pro
        JOIN users u ON u.id = pro.user_id
        WHERE pro.role = :role
          AND pro.email = :email
          AND pro.used_at IS NULL
          AND pro.expires_at > :now
        ORDER BY pro.id DESC
        LIMIT 1
    ');
    $stmt->execute([
        'role' => $role,
        'email' => $email,
        'now' => date('Y-m-d H:i:s'),
    ]);
    $reset = $stmt->fetch();

    if (!$reset || !password_verify($otp, (string) $reset['otp_hash'])) {
        return ['verified' => false, 'user_id' => null];
    }

    $now = date('Y-m-d H:i:s');
    db()->prepare('UPDATE password_reset_otps SET used_at = :used_at WHERE id = :id')
        ->execute([
            'used_at' => $now,
            'id' => (int) $reset['id'],
        ]);

    return ['verified' => true, 'user_id' => (int) $reset['user_id']];
}

function complete_verified_password_reset(int $userId, string $role, string $email, string $password): void
{
    db()->prepare('UPDATE users SET password_hash = :password_hash, force_password_change = 0, password_changed_at = :changed_at, password_reset_requested_at = NULL WHERE id = :id AND role = :role AND email = :email')
        ->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'changed_at' => date('Y-m-d H:i:s'),
            'id' => $userId,
            'role' => $role,
            'email' => $email,
        ]);
}

function handle_post_action(string $action): void
{
    verify_csrf_request();

    switch ($action) {
        case 'forgot_password_send_otp':
            $role = can_login_role((string) ($_POST['role'] ?? 'admin')) ? (string) $_POST['role'] : 'admin';
            $email = trim((string) ($_POST['forgot_email'] ?? $_POST['email'] ?? ''));
            $returnPage = (string) ($_POST['return_page'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid email address to receive the OTP.');
                if ($returnPage === 'landing') {
                    redirect_to('landing', ['auth' => $role, 'reset' => 'email']);
                }
                redirect_to('login', ['role' => $role, 'reset' => 'email']);
            }

            $nextResetMode = 'otp';
            try {
                $reset = send_login_password_reset_otp($role, $email);
                if (!empty($reset['handled']) && empty($reset['rate_limited']) && !empty($reset['user'])) {
                    $role = (string) $reset['user']['role'];
                    $email = (string) $reset['user']['email'];
                    audit_log('password_reset_otp_requested', [
                        'email' => $email,
                        'delivery' => !empty($reset['mail_result']['sent']) ? 'email' : 'mail_log',
                        'role' => $role,
                        'log_file' => $reset['mail_result']['log_file'] ?? '',
                        'mail_error' => $reset['mail_result']['error'] ?? '',
                    ], (int) $reset['user']['id'], ['role' => 'guest']);
                    app_log(!empty($reset['mail_result']['sent']) ? 'info' : 'warning', 'Password reset OTP mail result.', [
                        'email' => $email,
                        'role' => $role,
                        'sent' => !empty($reset['mail_result']['sent']),
                        'log_file' => $reset['mail_result']['log_file'] ?? '',
                        'error' => $reset['mail_result']['error'] ?? '',
                    ]);
                    if (!empty($reset['mail_result']['sent'])) {
                        flash('success', 'OTP sent to ' . $email . '. Please check Inbox and Spam.');
                    } else {
                        flash('error', 'OTP could not be sent by SMTP. A copy was saved in storage/emails/' . ($reset['mail_result']['log_file'] ?? '') . (($reset['mail_result']['error'] ?? '') !== '' ? ' | Error: ' . $reset['mail_result']['error'] : '') . '.');
                    }
                } elseif (!empty($reset['rate_limited']) && !empty($reset['user'])) {
                    $role = (string) $reset['user']['role'];
                    $email = (string) $reset['user']['email'];
                    audit_log('password_reset_otp_rate_limited', [
                        'email' => $email,
                        'role' => $role,
                    ], (int) $reset['user']['id'], ['role' => 'guest']);
                    flash('error', 'An OTP was already requested recently for this account. Please wait 15 minutes and try again.');
                } else {
                    audit_log('password_reset_otp_unknown_email', [
                        'email' => $email,
                        'role' => $role,
                    ], null, ['role' => 'guest']);
                    flash('error', 'No ' . user_role_label($role) . ' account was found for ' . $email . '.');
                    $nextResetMode = 'email';
                }
            } catch (Throwable $exception) {
                report_exception($exception, 'Password reset OTP request failed.', ['email' => $email, 'role' => $role]);
                flash('error', 'Unable to send the OTP right now.');
                $nextResetMode = 'email';
            }

            if ($returnPage === 'landing') {
                redirect_to('landing', ['auth' => $role, 'reset' => $nextResetMode, 'email' => $email]);
            }
            redirect_to('login', ['role' => $role, 'reset' => $nextResetMode, 'email' => $email]);
            break;

        case 'forgot_password_verify_otp':
            $role = can_login_role((string) ($_POST['role'] ?? 'admin')) ? (string) $_POST['role'] : 'admin';
            $email = trim((string) ($_POST['forgot_email'] ?? ''));
            $otp = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? '')) ?? '';
            $returnPage = (string) ($_POST['return_page'] ?? '');

            $redirectParams = ['role' => $role, 'reset' => 'otp', 'email' => $email];
            if ($returnPage === 'landing') {
                $redirectParams = ['auth' => $role, 'reset' => 'otp', 'email' => $email];
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($otp) !== 6) {
                flash('error', 'Enter the email and 6-digit OTP sent to your mail.');
                redirect_to($returnPage === 'landing' ? 'landing' : 'login', $redirectParams);
            }

            try {
                $result = verify_login_password_reset_otp($role, $email, $otp);
                if (empty($result['verified'])) {
                    flash('error', 'Invalid or expired OTP. Please check the code or request a new OTP.');
                    redirect_to($returnPage === 'landing' ? 'landing' : 'login', $redirectParams);
                }

                $_SESSION['password_reset_verified'] = [
                    'role' => $role,
                    'email' => $email,
                    'user_id' => (int) $result['user_id'],
                    'expires_at' => time() + 10 * 60,
                ];
                audit_log('password_reset_otp_verified', [
                    'email' => $email,
                    'role' => $role,
                ], (int) $result['user_id'], ['role' => 'guest']);
                flash('success', 'OTP verified. Set your new password.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Password reset OTP verification failed.', ['email' => $email, 'role' => $role]);
                flash('error', 'Unable to verify the OTP right now.');
                redirect_to($returnPage === 'landing' ? 'landing' : 'login', $redirectParams);
            }

            if ($returnPage === 'landing') {
                redirect_to('landing', ['auth' => $role, 'reset' => 'password', 'email' => $email]);
            }
            redirect_to('login', ['role' => $role, 'reset' => 'password', 'email' => $email]);
            break;

        case 'forgot_password_set_password':
            $role = can_login_role((string) ($_POST['role'] ?? 'admin')) ? (string) $_POST['role'] : 'admin';
            $email = trim((string) ($_POST['forgot_email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            $returnPage = (string) ($_POST['return_page'] ?? '');
            $verified = $_SESSION['password_reset_verified'] ?? null;
            $redirectParams = $returnPage === 'landing'
                ? ['auth' => $role, 'reset' => 'password', 'email' => $email]
                : ['role' => $role, 'reset' => 'password', 'email' => $email];

            if (
                !is_array($verified)
                || ($verified['role'] ?? '') !== $role
                || ($verified['email'] ?? '') !== $email
                || (int) ($verified['expires_at'] ?? 0) < time()
            ) {
                unset($_SESSION['password_reset_verified']);
                flash('error', 'OTP verification expired. Please request OTP again.');
                redirect_to($returnPage === 'landing' ? 'landing' : 'login', $returnPage === 'landing' ? ['auth' => $role, 'reset' => 'email'] : ['role' => $role, 'reset' => 'email']);
            }
            if ($password !== $confirmPassword) {
                flash('error', 'Passwords do not match.');
                redirect_to($returnPage === 'landing' ? 'landing' : 'login', $redirectParams);
            }
            if (!password_meets_policy($password)) {
                flash('error', password_policy_message());
                redirect_to($returnPage === 'landing' ? 'landing' : 'login', $redirectParams);
            }

            try {
                complete_verified_password_reset((int) $verified['user_id'], $role, $email, $password);
                unset($_SESSION['password_reset_verified']);
                audit_log('password_reset_completed', [
                    'email' => $email,
                    'role' => $role,
                ], (int) $verified['user_id'], ['role' => 'guest']);
                flash('success', 'Password reset successful. Please login with your new password.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Password reset completion failed.', ['email' => $email, 'role' => $role]);
                flash('error', 'Unable to reset the password right now.');
                redirect_to($returnPage === 'landing' ? 'landing' : 'login', $redirectParams);
            }

            if ($returnPage === 'landing') {
                redirect_to('landing', ['auth' => $role]);
            }
            redirect_to('login', ['role' => $role]);
            break;

        case 'login':
            $role = can_login_role((string) ($_POST['role'] ?? 'admin')) ? (string) $_POST['role'] : 'admin';
            $email = trim((string) ($_POST['email'] ?? ''));
            $returnPage = (string) ($_POST['return_page'] ?? '');
            if (!empty($_POST['forgot_password'])) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    flash('error', 'Enter your account email first, then click Forgot your password.');
                    if ($returnPage === 'landing') {
                        redirect_to('landing', ['auth' => $role]);
                    }
                    redirect_to('login', ['role' => $role]);
                }

                try {
                    $reset = reset_login_password_by_email($role, $email);
                    if (!empty($reset['handled']) && empty($reset['rate_limited']) && !empty($reset['user'])) {
                        audit_log('password_reset_requested', [
                            'email' => $email,
                            'delivery' => !empty($reset['mail_result']['sent']) ? 'email' : 'mail_log',
                            'role' => $role,
                        ], (int) $reset['user']['id'], ['role' => 'guest']);
                    } elseif (!empty($reset['rate_limited']) && !empty($reset['user'])) {
                        audit_log('password_reset_rate_limited', [
                            'email' => $email,
                            'role' => $role,
                        ], (int) $reset['user']['id'], ['role' => 'guest']);
                    } else {
                        audit_log('password_reset_unknown_email', [
                            'email' => $email,
                            'role' => $role,
                        ], null, ['role' => 'guest']);
                    }
                    flash('success', 'If that account email exists, a password has been sent or logged locally. For security, reset requests are rate-limited.');
                } catch (Throwable $exception) {
                    report_exception($exception, 'Self-service password reset failed.', ['email' => $email, 'role' => $role]);
                    flash('error', 'Unable to process the password reset request right now.');
                }

                if ($returnPage === 'landing') {
                    redirect_to('landing', ['auth' => $role]);
                }
                redirect_to('login', ['role' => $role]);
            }

            $stmt = db()->prepare('SELECT * FROM users WHERE role = :role AND email = :email ORDER BY id DESC');
            $stmt->execute([
                'role' => $role,
                'email' => $email,
            ]);
            $user = null;
            $password = (string) ($_POST['password'] ?? '');
            foreach ($stmt->fetchAll() as $candidate) {
                if (!password_verify($password, (string) ($candidate['password_hash'] ?? ''))) {
                    continue;
                }

                if (in_array(($candidate['role'] ?? ''), ['admin', 'freelancer', 'external_vendor'], true)) {
                    $status = (string) ($candidate['status'] ?? 'ACTIVE');
                    if ($status === 'PENDING') {
                        flash('error', 'Your account is pending approval from the Super Admin.');
                        redirect_to($returnPage === 'landing' ? 'landing' : 'login', ['role' => $role]);
                    }
                    if ($status === 'BLOCKED') {
                        flash('error', 'Your account has been blocked. Please contact support.');
                        redirect_to($returnPage === 'landing' ? 'landing' : 'login', ['role' => $role]);
                    }
                }

                $user = $candidate;
                break;
            }

            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                audit_log('login_success', [
                    'email' => $email,
                ], (int) $user['id'], $user);
                if (in_array(($user['role'] ?? ''), ['employee', 'corporate_employee'], true) && password_change_required($user)) {
                    flash('success', 'A password is active on this account. Please change it from Profile Settings after signing in.');
                }
                redirect_to(home_page_for_user($user));
            }
            audit_log('login_failed', [
                'role' => $role,
                'email' => $email,
            ], null, ['role' => 'guest']);
            flash('error', ucfirst((string) $role) . ' login failed.');
            if ($returnPage === 'landing') {
                redirect_to('landing', ['auth' => $role]);
            }
            redirect_to('login', ['role' => $role]);
            break;

        case 'register_user':
        case 'register_admin':
            $role = $action === 'register_admin'
                ? 'admin'
                : trim((string) ($_POST['role'] ?? 'admin'));
            if (!can_self_register_role($role)) {
                flash('error', 'Choose a valid registration type.');
                redirect_to('register');
            }
            if (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
                flash('error', 'Passwords do not match.');
                redirect_to('register');
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $companyName = trim((string) ($_POST['company_name'] ?? ''));
            $returnPage = (string) ($_POST['return_page'] ?? 'register');
            $password = (string) ($_POST['password'] ?? '');
            if ($name === '') {
                flash('error', 'Name is required.');
                redirect_to($returnPage);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid email address.');
                redirect_to($returnPage);
            }
            if (!password_meets_policy($password)) {
                flash('error', password_policy_message());
                redirect_to($returnPage);
            }
            try {
                $status = ($role === 'admin') ? 'PENDING' : 'ACTIVE';
                db()->prepare('INSERT INTO users (role, status, emp_id, name, company_name, email, phone, salary, password_hash, password_changed_at, created_at) VALUES (:role, :status, NULL, :name, :company_name, :email, :phone, 0, :password_hash, :password_changed_at, :created_at)')
                    ->execute([
                        'role' => $role,
                        'status' => $status,
                        'name' => $name,
                        'company_name' => $companyName,
                        'email' => $email,
                        'phone' => $phone,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'password_changed_at' => now(),
                        'created_at' => now(),
                    ]);
                audit_log('user_registered', [
                    'email' => $email,
                    'role' => $role,
                ], (int) db()->lastInsertId(), ['role' => 'guest']);
                if ($role === 'admin') {
                    flash('success', 'Registration request submitted. Please wait for Super Admin approval.');
                } else {
                    flash('success', 'Registration successful. You can now login.');
                }
                redirect_to($returnPage);
            } catch (Throwable $exception) {
                if (str_contains($exception->getMessage(), 'Duplicate entry')) {
                    flash('error', 'This email is already registered.');
                } else {
                    flash('error', 'Unable to complete registration right now.');
                }
                redirect_to($returnPage);
            }
            break;

        case 'admin_create_vendor':
            $admin = require_role('admin');
            $redirectPage = trim((string) ($_POST['redirect_page'] ?? 'admin_vendors'));
            $redirectPage = in_array($redirectPage, ['admin_vendors', 'admin_employees'], true) ? $redirectPage : 'admin_vendors';
            $redirectParams = [];
            if ($redirectPage === 'admin_employees') {
                $redirectParams['type'] = 'vendor';
            }

            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));

            if ($name === '') {
                flash('error', 'Vendor name is required.');
                redirect_to($redirectPage, $redirectParams);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid vendor email address.');
                redirect_to($redirectPage, $redirectParams);
            }
            if ($phone === '') {
                flash('error', 'Vendor phone number is required.');
                redirect_to($redirectPage, $redirectParams);
            }
            if (role_email_exists('external_vendor', $email)) {
                flash('error', 'A vendor account already exists for this email.');
                redirect_to($redirectPage, $redirectParams);
            }

            try {
                $password = (string) random_int(100000, 999999);
                db()->prepare('INSERT INTO users (role, emp_id, name, email, phone, salary, password_hash, force_password_change, password_changed_at, created_at) VALUES ("external_vendor", NULL, :name, :email, :phone, 0, :password_hash, 1, NULL, :created_at)')
                    ->execute([
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'created_at' => now(),
                    ]);

                $vendorId = (int) db()->lastInsertId();
                $vendor = [
                    'id' => $vendorId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                ];
                $html = '<p>Hello ' . h($name) . ',</p>'
                    . '<p>An admin created your V Traco vendor account.</p>'
                    . '<p><strong>Login Email:</strong> ' . h($email) . '<br>'
                    . '<strong>Password:</strong> ' . h($password) . '</p>'
                    . '<p>Please sign in and change your password from Profile Settings.</p>';
                $mailResult = send_html_mail($email, 'Your V Traco Vendor Account', $html);

                audit_log('vendor_created', [
                    'email' => $email,
                    'delivery' => !empty($mailResult['sent']) ? 'email' : 'mail_log',
                    'created_by' => (int) ($admin['id'] ?? 0),
                ], $vendorId);

                $_SESSION['vendor_created_popup'] = [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'mail_sent' => !empty($mailResult['sent']),
                    'mail_log' => (string) ($mailResult['log_file'] ?? ''),
                    'mail_error' => (string) ($mailResult['error'] ?? ''),
                ];

                $message = 'Vendor account created for ' . $name . '. Login email: ' . $email . '. The password is ready for admin review.';
                if (!empty($mailResult['sent'])) {
                    $message .= ' A notification email was sent successfully.';
                } else {
                    $message .= ' Email delivery is not configured yet, so a copy containing the password was saved in storage/emails/' . ($mailResult['log_file'] ?? '') . (($mailResult['error'] ?? '') !== '' ? ' | Error: ' . $mailResult['error'] : '') . '.';
                }

                flash('success', $message);
                redirect_to($redirectPage, $redirectParams);
            } catch (Throwable $exception) {
                report_exception($exception, 'Vendor creation failed.', ['email' => $email]);
                flash('error', 'Vendor account creation failed. Please try again.');
                redirect_to($redirectPage, $redirectParams);
            }
            break;

        case 'employee_manual_next':
            require_roles(['admin', 'freelancer', 'external_vendor']);
            if (!filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid employee email address.');
                redirect_to('admin_employees');
            }
            if (trim((string) ($_POST['name'] ?? '')) === '') {
                flash('error', 'Employee name is required.');
                redirect_to('admin_employees');
            }
            if (trim((string) ($_POST['phone'] ?? '')) === '') {
                flash('error', 'Employee phone number is required.');
                redirect_to('admin_employees');
            }
            if (!is_numeric((string) ($_POST['salary'] ?? '')) || (float) ($_POST['salary'] ?? 0) < 0) {
                flash('error', 'Employee salary must be zero or greater.');
                redirect_to('admin_employees');
            }
            $_SESSION['pending_employee'] = [
                'emp_id' => trim((string) ($_POST['emp_id'] ?? '')),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'phone' => trim((string) ($_POST['phone'] ?? '')),
                'shift' => trim((string) ($_POST['shift'] ?? '')),
                'salary' => (float) ($_POST['salary'] ?? 0),
                'employee_type' => trim((string) ($_POST['employee_type'] ?? 'regular')),
            ];
            redirect_to('admin_employees', ['stage' => 'manual_rules']);
            break;

        case 'employee_manual_submit':
            require_roles(['admin', 'freelancer', 'external_vendor']);

            $pending = $_SESSION['pending_employee'] ?? null;
            if (!$pending) {
                flash('error', 'No pending employee found.');
                redirect_to('admin_employees');
            }
            try {
                $rules = normalize_rules_from_input($_POST);
                $projectIds = array_map('intval', $_POST['project_ids'] ?? []);
                $createdEmployee = insert_employee($pending, $rules, $projectIds);
                unset($_SESSION['pending_employee']);
                audit_log('employee_created', [
                    'email' => (string) $createdEmployee['employee']['email'],
                    'delivery' => !empty($createdEmployee['mail_result']['sent']) ? 'email' : 'mail_log',
                ], (int) $createdEmployee['employee']['id']);
                flash('success', employee_credentials_delivery_message($createdEmployee['employee'], $createdEmployee['mail_result'], $createdEmployee['password']));
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee creation failed.', ['email' => $pending['email'] ?? '']);
                flash('error', $exception->getMessage() ?: 'Unable to add employee. Email or Emp ID may already exist.');
            }
            redirect_to('admin_employees');
            break;

        case 'employee_csv_upload':
            require_roles(['admin', 'freelancer', 'external_vendor']);
            try {
                validate_employee_csv_upload($_FILES['csv_file'] ?? []);
                $employeeType = trim((string) ($_POST['employee_type'] ?? 'regular'));
                $_SESSION['pending_csv_import'] = array_map(
                    static fn (array $row): array => array_merge($row, ['employee_type' => $employeeType]),
                    parse_employee_csv((string) $_FILES['csv_file']['tmp_name'])
                );
                flash('success', 'CSV uploaded. Assign rules to continue.');
                redirect_to('admin_employees', ['stage' => 'csv_rules']);
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee CSV upload failed.', [
                    'filename' => (string) (($_FILES['csv_file']['name'] ?? '') ?: ''),
                ]);
                flash('error', $exception->getMessage());
                redirect_to('admin_employees');
            }
            break;

        case 'employee_csv_submit':
            require_roles(['admin', 'freelancer', 'external_vendor']);

            $rows = $_SESSION['pending_csv_import'] ?? [];
            if (!$rows) {
                flash('error', 'No CSV import is pending.');
                redirect_to('admin_employees');
            }
            $rules = normalize_rules_from_input($_POST);
            $projectIds = array_map('intval', $_POST['project_ids'] ?? []);
            if (!$rules['manual_punch_in'] && !$rules['manual_punch_out'] && !$rules['biometric_punch_in'] && !$rules['biometric_punch_out']) {
                flash('error', 'Select at least one rule before submitting the CSV import.');
                redirect_to('admin_employees', ['stage' => 'csv_rules']);
            }
            $created = 0;
            $skipped = 0;
            $emailsSent = 0;
            $emailsLogged = 0;
            foreach ($rows as $row) {
                try {
                    $createdEmployee = insert_employee($row, $rules, $projectIds);
                    $created++;
                    if (!empty($createdEmployee['mail_result']['sent'])) {
                        $emailsSent++;
                    } else {
                        $emailsLogged++;
                    }
                } catch (Throwable $exception) {
                    $skipped++;
                }
            }
            unset($_SESSION['pending_csv_import']);
            $message = 'CSV import completed. Created: ' . $created;
            if ($emailsSent) {
                $message .= ' | Emails sent: ' . $emailsSent;
            }
            if ($emailsLogged) {
                $message .= ' | Logged locally: ' . $emailsLogged;
            }
            if ($skipped) {
                $message .= ' | Skipped: ' . $skipped;
            }
            audit_log('employee_csv_import_completed', [
                'created' => $created,
                'skipped' => $skipped,
                'emails_sent' => $emailsSent,
                'emails_logged' => $emailsLogged,
            ]);
            flash('success', $message);
            redirect_to('admin_employees');
            break;

        case 'employee_update':
            $admin = require_roles(['admin', 'freelancer', 'external_vendor']);

            $employeeId = (int) ($_POST['user_id'] ?? 0);
            if (!employee_by_id($employeeId)) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            try {
                $email = trim((string) ($_POST['email'] ?? ''));
                $name = trim((string) ($_POST['name'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $salary = (float) ($_POST['salary'] ?? 0);
                if ($name === '') {
                    throw new RuntimeException('Employee name is required.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid employee email address.');
                }
                if ($phone === '') {
                    throw new RuntimeException('Employee phone number is required.');
                }
                if (!is_numeric((string) ($_POST['salary'] ?? '')) || $salary < 0) {
                    throw new RuntimeException('Employee salary must be zero or greater.');
                }
                $role = current_manager_target_role();
                if (role_requires_unique_email($role) && role_email_exists($role, $email, $employeeId)) {
                    throw new RuntimeException('This employee email is already assigned.');
                }
                
                $employeeType = trim((string) ($_POST['employee_type'] ?? 'regular'));
                if (($admin['role'] ?? '') === 'freelancer') {
                    $employeeType = 'corporate';
                } elseif (($admin['role'] ?? '') === 'external_vendor') {
                    $employeeType = 'vendor';
                }
                if (!in_array($employeeType, ['regular', 'vendor', 'corporate'], true)) {
                    $employeeType = 'regular';
                }
                if ($employeeType === 'vendor' && ($admin['role'] ?? '') !== 'external_vendor') {
                    throw new RuntimeException('Vendor employees can only be managed by the vendor.');
                }
                
                db()->prepare('UPDATE users SET emp_id = :emp_id, name = :name, email = :email, phone = :phone, shift = :shift, salary = :salary, employee_type = :employee_type WHERE id = :id AND role = :role AND admin_id = :admin_id')
                    ->execute([
                        'id' => $employeeId,
                        'role' => $role,
                        'admin_id' => (int) $admin['id'],
                        'emp_id' => trim((string) ($_POST['emp_id'] ?? '')),
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'shift' => trim((string) ($_POST['shift'] ?? '')),
                        'salary' => $salary,
                        'employee_type' => $employeeType,
                    ]);
                audit_log('employee_updated', [
                    'email' => $email,
                ], $employeeId);
                flash('success', 'Employee updated.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee update failed.', ['employee_id' => $employeeId]);
                flash('error', $exception->getMessage() ?: 'Unable to update employee.');
            }
            redirect_to('admin_employees');
            break;

        case 'employee_reset_password':
            require_roles(['admin', 'freelancer', 'external_vendor']);

            $employeeId = (int) ($_POST['user_id'] ?? 0);
            if (!employee_by_id($employeeId)) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            try {
                $reset = reset_employee_password($employeeId);
                audit_log('employee_password_reset_admin', [
                    'delivery' => !empty($reset['mail_result']['sent']) ? 'email' : 'mail_log',
                ], $employeeId);
                flash('success', employee_credentials_delivery_message($reset['employee'], $reset['mail_result'], $reset['password'], 'reset'));
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin employee password reset failed.', ['employee_id' => $employeeId]);
                flash('error', 'Unable to reset the employee password.');
            }
            redirect_to('admin_employees');
            break;
        case 'employee_delete':
            $admin = require_roles(['admin', 'freelancer', 'external_vendor']);

            $employeeId = (int) ($_POST['user_id'] ?? 0);
            if (!employee_by_id($employeeId)) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            $role = current_manager_target_role();
            db()->prepare('DELETE FROM users WHERE id = :id AND role = :role AND admin_id = :admin_id')
                ->execute([
                    'id' => $employeeId,
                    'role' => $role,
                    'admin_id' => (int) $admin['id'],
                ]);
            audit_log('employee_deleted', [], $employeeId);
            flash('success', 'Employee deleted successfully.');
            redirect_to('admin_employees');
            break;

        case 'project_save':
            require_role('admin');

            $projectId = (int) ($_POST['project_id'] ?? 0);
            $_SESSION['project_form'] = array_merge(project_form_defaults(), [
                'id' => $projectId,
                'project_name' => trim((string) ($_POST['project_name'] ?? '')),
                'college_name' => trim((string) ($_POST['college_name'] ?? '')),
                'location' => trim((string) ($_POST['location'] ?? '')),
                'total_days' => trim((string) ($_POST['total_days'] ?? '')),
                'session_type' => trim((string) ($_POST['session_type'] ?? 'FULL_DAY')),
                'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            ]);

            try {
                $savedProjectId = save_project($_POST, $projectId > 0 ? $projectId : null);
                $savedProject = project_by_id($savedProjectId);
                unset($_SESSION['project_form']);
                audit_log($projectId > 0 ? 'project_updated' : 'project_created', [
                    'project_name' => (string) ($savedProject['project_name'] ?? ''),
                    'college_name' => (string) ($savedProject['college_name'] ?? ''),
                    'is_active' => (int) ($savedProject['is_active'] ?? 0),
                ], null);
                flash('success', $projectId > 0 ? 'Project updated successfully.' : 'Project added successfully.');
                redirect_to('admin_projects');
            } catch (Throwable $exception) {
                report_exception($exception, 'Project save failed.', ['project_id' => $projectId]);
                flash('error', $exception->getMessage() ?: 'Unable to save the project.');
                if ($projectId > 0) {
                    redirect_to('admin_projects', ['edit' => $projectId]);
                }
                redirect_to('admin_projects', ['stage' => 'create']);
            }
            break;

        case 'project_delete':
            require_role('admin');

            $projectId = (int) ($_POST['project_id'] ?? 0);
            try {
                $project = project_by_id($projectId);
                delete_project($projectId);
                audit_log('project_deleted', [
                    'project_name' => (string) ($project['project_name'] ?? ''),
                ], null);
                flash('success', 'Project deleted successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Project delete failed.', ['project_id' => $projectId]);
                flash('error', $exception->getMessage() ?: 'Unable to delete the project.');
            }
            redirect_to('admin_projects');
            break;

        case 'project_toggle_active':
            require_role('admin');

            $projectId = (int) ($_POST['project_id'] ?? 0);
            try {
                $project = toggle_project_active($projectId);
                audit_log('project_toggled', [
                    'project_name' => (string) ($project['project_name'] ?? ''),
                    'is_active' => (int) ($project['is_active'] ?? 0),
                ], null);
                flash('success', !empty($project['is_active']) ? 'Project activated successfully.' : 'Project deactivated successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Project status toggle failed.', ['project_id' => $projectId]);
                flash('error', $exception->getMessage() ?: 'Unable to update the project status.');
            }
            redirect_to('admin_projects');
            break;

        case 'admin_add_shift_timing':
            require_role('admin');
            $redirectPage = trim((string) ($_POST['redirect_page'] ?? 'admin_shift'));
            $redirectPage = in_array($redirectPage, ['admin_shift', 'admin_rules'], true) ? $redirectPage : 'admin_shift';
            try {
                $startTime = trim((string) ($_POST['start_time'] ?? ''));
                $endTime = trim((string) ($_POST['end_time'] ?? ''));

                if ($startTime === '' || $endTime === '') {
                    throw new RuntimeException('Start time and end time are required.');
                }

                if ($startTime === $endTime) {
                    throw new RuntimeException('Start time and end time must be different.');
                }

                add_shift_timing([
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]);
                flash('success', 'Shift timing posted successfully.');
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
            }
            redirect_to($redirectPage);
            break;

        case 'admin_delete_shift_timing':
            require_role('admin');
            try {
                delete_shift_timing((int) ($_POST['shift_id'] ?? 0));
                flash('success', 'Shift timing deleted.');
            } catch (Throwable $exception) {
                flash('error', 'Unable to delete shift timing.');
            }
            redirect_to('admin_shift');
            break;

        case 'apply_rules':
            require_role('admin');
            $ids = array_map('intval', $_POST['employee_ids'] ?? []);
            $rules = normalize_rules_from_input($_POST);
            if (!$ids) {
                flash('error', 'Select at least one employee.');
                redirect_to('admin_rules');
            }
            $updated = 0;
            foreach ($ids as $id) {
                $employee = employee_by_id($id);
                if (!$employee) {
                    continue;
                }
                save_employee_rules((int) $employee['id'], $rules);
                $projectIds = array_map('intval', $_POST['project_ids'] ?? []);
                save_employee_project_assignments((int) $employee['id'], $projectIds);
                send_rules_updated_email($employee, $rules);
                $updated++;
            }
            audit_log('employee_rules_updated_bulk', [
                'employee_count' => $updated,
                'rules' => $rules,
            ]);
            flash($updated > 0 ? 'success' : 'error', $updated > 0 ? 'Rules applied successfully.' : 'No employees were available for this administrator.');
            redirect_to('admin_rules');
            break;

        case 'admin_attendance_csv_upload':
            require_role('admin');
            try {
                validate_attendance_report_upload($_FILES['attendance_csv'] ?? []);
                $result = import_attendance_report_csv((string) ($_FILES['attendance_csv']['tmp_name'] ?? ''), trim((string) ($_POST['attendance_date'] ?? '')), (string) ($_FILES['attendance_csv']['name'] ?? ''));
                $message = 'Attendance import completed. Imported: ' . (int) $result['imported'];
                if (!empty($result['date'])) {
                    $message .= ' | Date: ' . date('d M Y', strtotime((string) $result['date']));
                }
                if (!empty($result['skipped'])) {
                    $message .= ' | Skipped: ' . (int) $result['skipped'];
                }
                if (!empty($result['unmatched'])) {
                    $message .= ' | Unmatched Employees: ' . implode(', ', $result['unmatched']);
                }
                audit_log('attendance_import_completed', [
                    'filename' => (string) ($_FILES['attendance_csv']['name'] ?? ''),
                    'date' => $result['date'] ?? null,
                    'imported' => (int) $result['imported'],
                    'skipped' => (int) ($result['skipped'] ?? 0),
                    'unmatched' => $result['unmatched'] ?? [],
                ]);
                flash('success', $message);
            } catch (Throwable $exception) {
                report_exception($exception, 'Attendance import failed.', [
                    'filename' => (string) ($_FILES['attendance_csv']['name'] ?? ''),
                ]);
                flash('error', $exception->getMessage());
            }
            redirect_to('admin_attendance');
            break;

        case 'admin_set_status':
            require_role('admin');
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            $employee = employee_by_id($employeeId);
            if (!$employee) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_attendance');
            }
            $status = (string) ($_POST['status'] ?? 'Absent');
            update_attendance_record((int) $employee['id'], (string) ($_POST['attend_date'] ?? ''), [
                'status' => $status,
                'admin_override_status' => $status,
            ]);
            audit_log('attendance_status_overridden', [
                'attend_date' => (string) ($_POST['attend_date'] ?? ''),
                'status' => $status,
            ], (int) $employee['id']);
            flash('success', 'Attendance status updated.');
            redirect_to('admin_attendance', [
                'employee_id' => (int) $employee['id'],
                'month' => substr((string) ($_POST['attend_date'] ?? date('Y-m-d')), 0, 7),
            ]);
            break;

        case 'admin_profile_update':
            $admin = require_roles(['admin', 'freelancer', 'external_vendor']);

            $defaultReturn = home_page_for_user($admin);
            $returnPage = (string) ($_POST['return_page'] ?? $defaultReturn);
            $allowedPrefixes = ['admin_', 'vendor_', 'corporate_', 'member_'];
            $isValid = false;
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($returnPage, $prefix)) {
                    $isValid = true;
                    break;
                }
            }
            if (!$isValid || $returnPage === 'admin_profile_settings') {
                $returnPage = $defaultReturn;
            }
            $isVendorProfile = ($admin['role'] ?? '') === 'external_vendor';
            $name = $isVendorProfile
                ? trim((string) ($admin['name'] ?? ''))
                : trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $companyName = trim((string) ($_POST['company_name'] ?? ''));
            $representativeName = trim((string) ($_POST['representative_name'] ?? ''));
            $gstNo = strtoupper(trim((string) ($_POST['gst_no'] ?? '')));
            $panNo = strtoupper(trim((string) ($_POST['pan_no'] ?? '')));
            $bankAccountNo = trim((string) ($_POST['bank_account_no'] ?? ''));
            $bankIfscCode = strtoupper(trim((string) ($_POST['bank_ifsc_code'] ?? '')));
            $bankBranch = trim((string) ($_POST['bank_branch'] ?? ''));
            $bankName = trim((string) ($_POST['bank_name'] ?? ''));

            if ($name === '') {
                flash('error', 'Name is required.');
                redirect_to($returnPage);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid email address.');
                redirect_to($returnPage);
            }
            if ($isVendorProfile) {
                if ($companyName === '') {
                    $companyName = $name;
                }
                if ($representativeName === '') {
                    flash('error', 'Representative name is required.');
                    redirect_to($returnPage);
                }
            }

            try {
                if ($isVendorProfile) {
                    db()->prepare('UPDATE users SET name = :name, email = :email, phone = :phone, company_name = :company_name, representative_name = :representative_name, gst_no = :gst_no, pan_no = :pan_no, bank_account_no = :bank_account_no, bank_ifsc_code = :bank_ifsc_code, bank_branch = :bank_branch, bank_name = :bank_name WHERE id = :id AND role = :role')
                        ->execute([
                            'id' => (int) $admin['id'],
                            'role' => $admin['role'],
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone,
                            'company_name' => $companyName,
                            'representative_name' => $representativeName,
                            'gst_no' => $gstNo,
                            'pan_no' => $panNo,
                            'bank_account_no' => $bankAccountNo,
                            'bank_ifsc_code' => $bankIfscCode,
                            'bank_branch' => $bankBranch,
                            'bank_name' => $bankName,
                        ]);
                } else {
                    db()->prepare('UPDATE users SET name = :name, email = :email, phone = :phone WHERE id = :id AND role = :role')
                        ->execute([
                            'id' => (int) $admin['id'],
                            'role' => $admin['role'],
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone,
                        ]);
                }
                audit_log('admin_profile_updated', [
                    'email' => $email,
                ], (int) $admin['id']);
                flash('success', 'Profile updated successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin profile update failed.', ['admin_id' => (int) $admin['id']]);
                flash('error', 'Unable to update profile.');
            }
            redirect_to($returnPage);
            break;

        case 'super_admin_get_data':
            require_role('super_admin');
            $stmt = db()->prepare("SELECT id, name, email, company_name, representative_name, status, created_at FROM users WHERE role IN ('admin', 'freelancer', 'external_vendor') ORDER BY created_at DESC");
            $stmt->execute();
            $all = $stmt->fetchAll();
            $approved = [];
            $pending = [];
            foreach ($all as $u) {
                if ($u['status'] === 'PENDING') {
                    $pending[] = $u;
                } else {
                    $approved[] = $u;
                }
            }
            render_json(['success' => true, 'approved' => $approved, 'pending' => $pending]);
            break;

        case 'super_admin_toggle_status':
            require_role('super_admin');
            verify_csrf_request();
            $id = (int) ($_POST['id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'ACTIVE');
            if (!in_array($status, ['ACTIVE', 'BLOCKED'], true)) {
                render_json(['success' => false, 'message' => 'Invalid status'], 400);
            }
            db()->prepare("UPDATE users SET status = :status WHERE id = :id AND role IN ('admin', 'freelancer', 'external_vendor')")
                ->execute(['status' => $status, 'id' => $id]);
            audit_log('super_admin_company_status_toggled', ['id' => $id, 'status' => $status]);
            render_json(['success' => true]);
            break;

        case 'super_admin_approve':
            require_role('super_admin');
            verify_csrf_request();
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = :id AND status = 'PENDING'")
                ->execute(['id' => $id]);
            audit_log('super_admin_company_approved', ['id' => $id]);
            render_json(['success' => true]);
            break;

        case 'super_admin_deny':
            require_role('super_admin');
            verify_csrf_request();
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare("DELETE FROM users WHERE id = :id AND status = 'PENDING'")
                ->execute(['id' => $id]);
            audit_log('super_admin_company_denied', ['id' => $id]);
            render_json(['success' => true]);
            break;

        case 'admin_change_password':
            $admin = require_roles(['admin', 'freelancer', 'external_vendor']);

            $defaultReturn = home_page_for_user($admin);
            $returnPage = (string) ($_POST['return_page'] ?? $defaultReturn);
            $allowedPrefixes = ['admin_', 'vendor_', 'corporate_', 'member_'];
            $isValid = false;
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($returnPage, $prefix)) {
                    $isValid = true;
                    break;
                }
            }
            if (!$isValid || $returnPage === 'admin_profile_settings') {
                $returnPage = $defaultReturn;
            }
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (!password_verify($currentPassword, (string) $admin['password_hash'])) {
                flash('error', 'Current password is incorrect.');
                redirect_to($returnPage);
            }
            if (!password_meets_policy($newPassword)) {
                flash('error', password_policy_message());
                redirect_to($returnPage);
            }
            if ($newPassword !== $confirmPassword) {
                flash('error', 'New password and confirm password do not match.');
                redirect_to($returnPage);
            }

            db()->prepare('UPDATE users SET password_hash = :password_hash, password_changed_at = :password_changed_at WHERE id = :id AND role = :role')
                ->execute([
                    'id' => (int) $admin['id'],
                    'role' => $admin['role'],
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'password_changed_at' => now(),
                ]);
            audit_log('admin_password_changed', [], (int) $admin['id']);
            flash('success', 'Password updated successfully. Please use the new password the next time you sign in.');
            redirect_to($returnPage);
            break;

        case 'employee_change_password':
            $employee = require_roles(['employee', 'corporate_employee']);

            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (!password_verify($currentPassword, (string) $employee['password_hash'])) {
                flash('error', 'Current password is incorrect.');
                redirect_to('employee_attendance');
            }
            if (!password_meets_policy($newPassword)) {
                flash('error', password_policy_message());
                redirect_to('employee_attendance');
            }
            if ($newPassword !== $confirmPassword) {
                flash('error', 'New password and confirm password do not match.');
                redirect_to('employee_attendance');
            }

            db()->prepare('UPDATE users SET password_hash = :password_hash, force_password_change = 0, password_changed_at = :password_changed_at WHERE id = :id AND role = :role')
                ->execute([
                    'id' => (int) $employee['id'],
                    'role' => $employee['role'],
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'password_changed_at' => now(),
                ]);
            audit_log('employee_password_changed', [], (int) $employee['id']);
            flash('success', 'Password updated successfully. Please use the new password the next time you sign in.');
            redirect_to('employee_attendance');
            break;
        case 'employee_manual_in':
        case 'employee_punch_in':
            $employee = require_roles(['employee', 'corporate_employee']);

            try {
                $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
                if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                    throw new RuntimeException('Week Off dates do not require attendance.');
                }
                $rules = employee_rules((int) $employee['id']);
                $slotIndex = max(1, (int) ($_POST['slot_index'] ?? 1));
                $slotLimit = max(1, manual_slot_limit($rules));
                if (empty($rules['manual_punch_in'])) {
                    throw new RuntimeException('Manual Punch In is not enabled for this employee.');
                }
                if ($slotIndex > $slotLimit) {
                    throw new RuntimeException('Manual Punch In ' . $slotIndex . ' is not available for this date.');
                }
                if ((int) (($_FILES['punch_photo']['error'] ?? UPLOAD_ERR_NO_FILE)) === UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Manual Punch In ' . $slotIndex . ' requires a photo upload.');
                }

                $slotName = trim((string) ($_POST['slot_name'] ?? '')) ?: manual_slot_name($rules, $slotIndex);
                $record = ensure_attendance_record((int) $employee['id'], $date);
                $existingSession = attendance_session_by_slot((int) $record['id'], $slotName);
                if (!$existingSession && $slotIndex === 1 && !empty($record['punch_in_path'])) {
                    throw new RuntimeException('Manual Punch In 1 is already submitted for this date.');
                }
                if ($existingSession && !empty($existingSession['punch_in_path'])) {
                    throw new RuntimeException('Manual Punch In ' . $slotIndex . ' is already submitted for this date.');
                }

                $path = handle_upload($_FILES['punch_photo'] ?? []);
                $sessionPayload = [
                    'session_mode' => 'manual_pair',
                    'slot_name' => $slotName,
                    'punch_in_path' => $path,
                    'punch_in_lat' => trim((string) ($_POST['latitude'] ?? '')),
                    'punch_in_lng' => trim((string) ($_POST['longitude'] ?? '')),
                    'punch_in_time' => now(),
                ];

                if ($existingSession) {
                    update_attendance_session((int) $existingSession['id'], $sessionPayload);
                } else {
                    add_attendance_session((int) $record['id'], $sessionPayload);
                }
                $recordFields = ['status' => 'Pending'];
                if ($slotIndex === 1 || empty($record['punch_in_path'])) {
                    $recordFields['punch_in_path'] = $sessionPayload['punch_in_path'];
                    $recordFields['punch_in_lat'] = $sessionPayload['punch_in_lat'];
                    $recordFields['punch_in_lng'] = $sessionPayload['punch_in_lng'];
                    $recordFields['punch_in_time'] = $sessionPayload['punch_in_time'];
                }
                update_attendance_record((int) $employee['id'], $date, $recordFields);
                audit_log('employee_manual_punch_in_submitted', [
                    'attend_date' => $date,
                    'slot_index' => $slotIndex,
                ], (int) $employee['id']);
                flash('success', 'Manual punch in ' . $slotIndex . ' submitted.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee manual punch in failed.', [
                    'employee_id' => (int) $employee['id'],
                    'attend_date' => (string) ($_POST['attend_date'] ?? ''),
                ]);
                flash('error', $exception->getMessage());
            }
            redirect_to('employee_attendance', ['month' => substr((string) ($_POST['attend_date'] ?? date('Y-m-d')), 0, 7)]);
            break;
        case 'employee_manual_out':
            $employee = require_roles(['employee', 'corporate_employee']);

            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
            if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                flash('error', 'Week Off dates do not require attendance.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            $rules = employee_rules((int) $employee['id']);
            $slotIndex = max(1, (int) ($_POST['slot_index'] ?? 1));
            $slotLimit = max(1, manual_slot_limit($rules));
            $slotName = trim((string) ($_POST['slot_name'] ?? '')) ?: manual_slot_name($rules, $slotIndex);
            $record = ensure_attendance_record((int) $employee['id'], $date);
            $session = attendance_session_by_slot((int) $record['id'], $slotName);
            $collegeName = trim((string) ($_POST['college_name'] ?? ''));
            $sessionName = trim((string) ($_POST['session_name'] ?? ''));
            $dayPortion = trim((string) ($_POST['day_portion'] ?? 'Full Day'));
            $sessionDuration = (float) ($_POST['session_duration'] ?? 0);
            $location = trim((string) ($_POST['location'] ?? ''));

            if (empty($rules['manual_punch_out'])) {
                flash('error', 'Manual Punch Out is not enabled for this employee.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if ($slotIndex > $slotLimit) {
                flash('error', 'Manual Punch Out ' . $slotIndex . ' is not available for this date.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if (!$session && $slotIndex === 1 && !empty($record['punch_in_path'])) {
                add_attendance_session((int) $record['id'], [
                    'session_mode' => 'manual_pair',
                    'slot_name' => $slotName,
                    'punch_in_path' => $record['punch_in_path'],
                    'punch_in_lat' => $record['punch_in_lat'],
                    'punch_in_lng' => $record['punch_in_lng'],
                    'punch_in_time' => $record['punch_in_time'],
                ]);
                $session = attendance_session_by_slot((int) $record['id'], $slotName);
            }
            if (!$session || empty($session['punch_in_path'])) {
                flash('error', 'Submit Manual Punch In ' . $slotIndex . ' first.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if (session_has_manual_out($session)) {
                flash('error', 'Manual Punch Out ' . $slotIndex . ' is already submitted for this date.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if ($collegeName === '' || $sessionName === '' || $location === '' || $sessionDuration <= 0) {
                flash('error', 'Manual Punch Out ' . $slotIndex . ' requires College Name, Session Name, Session Duration, and Location.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }

            update_attendance_session((int) $session['id'], [
                'session_mode' => 'manual_pair',
                'college_name' => $collegeName,
                'session_name' => $sessionName,
                'day_portion' => $dayPortion,
                'session_duration' => $sessionDuration,
                'location' => $location,
                'punch_out_time' => now(),
            ]);
            $updatedRecord = $record;
            $updatedRecord['status'] = ($dayPortion === 'Half Day') ? 'Half Day' : 'Present';
            $updatedSessions = attendance_sessions((int) $record['id']);
            update_attendance_record((int) $employee['id'], $date, [
                'status' => resolved_attendance_status($updatedRecord, $updatedSessions),
            ]);
            audit_log('employee_manual_punch_out_submitted', [
                'attend_date' => $date,
                'slot_index' => $slotIndex,
                'day_portion' => $dayPortion,
            ], (int) $employee['id']);
            flash('success', 'Manual punch out ' . $slotIndex . ' of ' . $slotLimit . ' submitted.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;
        case 'employee_biometric':
            $employee = require_roles(['employee', 'corporate_employee']);

            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
            if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                flash('error', 'Week Off dates do not require attendance.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            $type = (string) ($_POST['stamp_type'] ?? 'in');
            update_attendance_record((int) $employee['id'], $date, [
                $type === 'out' ? 'biometric_out_time' : 'biometric_in_time' => now(),
                'status' => 'Present',
            ]);
            audit_log('employee_biometric_marked', [
                'attend_date' => $date,
                'stamp_type' => $type,
            ], (int) $employee['id']);
            flash('success', 'Biometric ' . ($type === 'out' ? 'out' : 'in') . ' captured.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;

        case 'employee_leave':
            $employee = require_roles(['employee', 'corporate_employee']);

            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
            if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                flash('error', 'Week Off dates do not require attendance.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            update_attendance_record((int) $employee['id'], $date, [
                'status' => 'Leave',
                'leave_reason' => trim((string) ($_POST['leave_reason'] ?? '')),
            ]);
            audit_log('employee_leave_requested', [
                'attend_date' => $date,
            ], (int) $employee['id']);
            flash('success', 'Leave request recorded.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;

        case 'employee_submit_reimbursement':
            $employee = require_role('employee');
            $date = (string) ($_POST['expense_date'] ?? date('Y-m-d'));

            try {
                $created = create_employee_reimbursement(
                    $employee,
                    $date,
                    $_POST,
                    $_FILES['attachment'] ?? []
                );
                audit_log('employee_reimbursement_submitted', [
                    'reimbursement_id' => (int) $created['id'],
                    'expense_date' => (string) $created['expense_date'],
                    'category' => (string) $created['category'],
                    'amount_requested' => (float) $created['amount_requested'],
                ], (int) $employee['id']);
                flash('success', 'Reimbursement request submitted successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee reimbursement submission failed.', [
                    'employee_id' => (int) $employee['id'],
                    'expense_date' => $date,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to submit the reimbursement request right now.');
            }

            redirect_to('employee_reimbursements');
            break;

        case 'admin_update_reimbursement_status':
            $admin = require_role('admin');
            $reimbursementId = (int) ($_POST['reimbursement_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'PENDING');
            $redirectParams = reimbursement_admin_filter_params($_POST);

            try {
                $updated = update_admin_reimbursement_status($reimbursementId, $status);
                audit_log('admin_reimbursement_status_updated', [
                    'reimbursement_id' => (int) $updated['id'],
                    'status' => (string) $updated['status'],
                ], (int) $updated['user_id'], $admin);
                flash('success', 'Reimbursement status updated to ' . (string) $updated['status'] . '.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin reimbursement status update failed.', [
                    'admin_id' => (int) $admin['id'],
                    'reimbursement_id' => $reimbursementId,
                    'status' => $status,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to update the reimbursement status.');
            }

            redirect_to('admin_reimbursements', $redirectParams);
            break;

        case 'admin_mark_reimbursement_partial':
            $admin = require_role('admin');
            $reimbursementId = (int) ($_POST['reimbursement_id'] ?? 0);
            $partialAmount = (float) ($_POST['partial_amount'] ?? 0);
            $redirectParams = reimbursement_admin_filter_params($_POST);

            try {
                $updated = mark_reimbursement_partially_paid($reimbursementId, $partialAmount);
                audit_log('admin_reimbursement_partially_paid', [
                    'reimbursement_id' => (int) $updated['id'],
                    'amount_paid' => (float) $updated['amount_paid'],
                    'remaining_balance' => (float) $updated['remaining_balance'],
                ], (int) $updated['user_id'], $admin);
                flash('success', 'Partial reimbursement payment recorded successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin partial reimbursement payment failed.', [
                    'admin_id' => (int) $admin['id'],
                    'reimbursement_id' => $reimbursementId,
                    'partial_amount' => $partialAmount,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to record the partial payment.');
            }

            redirect_to('admin_reimbursements', $redirectParams);
            break;

        case 'admin_mark_reimbursement_paid':
            $admin = require_role('admin');
            $reimbursementId = (int) ($_POST['reimbursement_id'] ?? 0);
            $redirectParams = reimbursement_admin_filter_params($_POST);

            try {
                $updated = mark_reimbursement_paid(
                    $reimbursementId,
                    $_POST,
                    $_FILES['payment_proof'] ?? []
                );
                audit_log('admin_reimbursement_paid', [
                    'reimbursement_id' => (int) $updated['id'],
                    'payment_id' => (int) ($updated['payment_id'] ?? 0),
                    'amount_paid' => (float) $updated['amount_paid'],
                ], (int) $updated['user_id'], $admin);
                flash('success', 'Reimbursement marked as paid and payment entry created.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin reimbursement paid flow failed.', [
                    'admin_id' => (int) $admin['id'],
                    'reimbursement_id' => $reimbursementId,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to complete the payment flow.');
            }

            redirect_to('admin_reimbursements', $redirectParams);
            break;

        case 'admin_record_reimbursement_payment':
            $admin = require_role('admin');
            $reimbursementId = (int) ($_POST['reimbursement_id'] ?? 0);
            $redirectParams = reimbursement_admin_filter_params($_POST);

            try {
                $reimbursement = admin_reimbursement_by_id($reimbursementId);
                if (!$reimbursement) {
                    throw new RuntimeException('Reimbursement request not found.');
                }

                // Reuse the Accounts Payments module for reimbursement settlements.
                $payment = create_payment([
                    'employee_id' => (int) ($reimbursement['user_id'] ?? 0),
                    'payment_type' => 'REIMBURSEMENT',
                    'reimbursement_id' => $reimbursementId,
                    'amount' => $_POST['amount'] ?? 0,
                    'bank_name' => $_POST['bank_name'] ?? '',
                    'transfer_mode' => $_POST['transfer_mode'] ?? '',
                    'transaction_id' => $_POST['transaction_id'] ?? '',
                    'payment_date' => $_POST['payment_date'] ?? date('Y-m-d'),
                    'remarks' => $_POST['remarks'] ?? '',
                ], $_FILES['proof_upload'] ?? []);

                $updated = admin_reimbursement_by_id($reimbursementId);
                if (!$updated) {
                    throw new RuntimeException('Unable to reload the reimbursement request.');
                }

                audit_log('admin_reimbursement_payment_recorded', [
                    'reimbursement_id' => $reimbursementId,
                    'payment_id' => (int) ($payment['id'] ?? 0),
                    'amount' => (float) ($payment['amount'] ?? 0),
                    'bank_name' => (string) ($payment['bank_name'] ?? ''),
                ], (int) ($updated['user_id'] ?? 0), $admin);

                flash('success', 'Reimbursement payment recorded successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin reimbursement payment recording failed.', [
                    'admin_id' => (int) $admin['id'],
                    'reimbursement_id' => $reimbursementId,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to record the reimbursement payment.');
            }

            redirect_to('admin_reimbursements', $redirectParams);
            break;

        case 'admin_save_payment':
            $admin = require_role('admin');
            $paymentId = max(0, (int) ($_POST['payment_id'] ?? 0));
            $filters = payment_filter_params($_POST);

            try {
                if ($paymentId > 0) {
                    $payment = update_payment($paymentId, $_POST, $_FILES['proof_upload'] ?? []);
                    audit_log('admin_payment_updated', [
                        'payment_id' => (int) $payment['id'],
                        'payment_type' => (string) $payment['payment_type'],
                        'amount' => (float) $payment['amount'],
                    ], (int) $payment['user_id'], $admin);
                    flash('success', 'Payment updated successfully.');
                } else {
                    $payment = create_payment($_POST, $_FILES['proof_upload'] ?? []);
                    audit_log('admin_payment_created', [
                        'payment_id' => (int) $payment['id'],
                        'payment_type' => (string) $payment['payment_type'],
                        'amount' => (float) $payment['amount'],
                    ], (int) $payment['user_id'], $admin);
                    flash('success', 'Payment added successfully.');
                }
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin payment save failed.', [
                    'admin_id' => (int) $admin['id'],
                    'payment_id' => $paymentId,
                    'payment_type' => (string) ($_POST['payment_type'] ?? ''),
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to save the payment right now.');
            }

            redirect_to('admin_accounts', payment_redirect_query($filters));
            break;

        case 'admin_delete_payment':
            $admin = require_role('admin');
            $paymentId = max(0, (int) ($_POST['payment_id'] ?? 0));
            $filters = payment_filter_params($_POST);

            try {
                $payment = admin_payment_by_id($paymentId);
                delete_payment($paymentId);
                audit_log('admin_payment_deleted', [
                    'payment_id' => $paymentId,
                ], (int) ($payment['user_id'] ?? 0), $admin);
                flash('success', 'Payment deleted successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin payment delete failed.', [
                    'admin_id' => (int) $admin['id'],
                    'payment_id' => $paymentId,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to delete the payment right now.');
            }

            redirect_to('admin_accounts', payment_redirect_query($filters));
            break;

        case 'mark_notifications_read':
            $user = require_roles(['admin', 'employee', 'corporate_employee', 'external_vendor', 'freelancer']);
            mark_notifications_read((int) $user['id']);
            flash('success', 'Notifications marked as read.');
            redirect_to((string) ($_POST['return_page'] ?? 'notifications'));
            break;

        case 'dismiss_notification':
            $user = require_roles(['admin', 'employee', 'corporate_employee', 'external_vendor', 'freelancer']);
            $notificationId = max(0, (int) ($_POST['notification_id'] ?? 0));
            if ($notificationId > 0) {
                mark_notification_read((int) $user['id'], $notificationId);
            }
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                exit;
            }
            redirect_to((string) ($_POST['return_page'] ?? 'notifications'));
            break;

        case 'filter_reports':
            require_role('admin');
            // This case doesn't redirect, it just lets the page render with POST data
            break;

        case 'export_reports_csv':
            require_role('admin');
            $filters = [
                'employee_ids' => $_POST['employee_ids'] ?? [],
                'project_ids' => $_POST['project_ids'] ?? [],
                'from_date' => $_POST['from_date'] ?? '',
                'to_date' => $_POST['to_date'] ?? '',
            ];
            $data = get_attendance_report_data($filters);
            export_report_csv($data);
            break;

        case 'export_reports_pdf':
            require_role('admin');
            $filters = [
                'employee_ids' => $_POST['employee_ids'] ?? [],
                'project_ids' => $_POST['project_ids'] ?? [],
                'from_date' => $_POST['from_date'] ?? '',
                'to_date' => $_POST['to_date'] ?? '',
            ];
            $data = get_attendance_report_data($filters);
            export_report_pdf($data);
            break;

        case 'super_admin_get_data':
            require_role('super_admin');
            $approved = db()->query("SELECT id, name, email, role, representative_name, company_name, status FROM users WHERE role = 'admin' AND status IN ('ACTIVE', 'BLOCKED') ORDER BY company_name ASC, name ASC")->fetchAll();
            $pending = db()->query("SELECT id, name, email, role, representative_name, company_name, created_at FROM users WHERE role = 'admin' AND status = 'PENDING' ORDER BY created_at DESC")->fetchAll();
            error_log('Super Admin Data Request: ' . count($approved) . ' approved, ' . count($pending) . ' pending');
            header('Content-Type: application/json');
            echo json_encode(['approved' => $approved, 'pending' => $pending]);
            exit;

        case 'super_admin_toggle_status':
            require_role('super_admin');
            $id = (int) ($_POST['id'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['ACTIVE', 'BLOCKED'], true) ? $_POST['status'] : 'ACTIVE';
            db()->prepare("UPDATE users SET status = :status WHERE id = :id AND role = 'admin'")->execute(['status' => $status, 'id' => $id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;

        case 'super_admin_approve':
            require_role('super_admin');
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = :id AND role = 'admin'")->execute(['id' => $id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;

        case 'super_admin_deny':
            require_role('super_admin');
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare("DELETE FROM users WHERE id = :id AND role = 'admin' AND status = 'PENDING'")->execute(['id' => $id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;

        case 'logout':
            $user = current_user();
            if ($user) {
                audit_log('logout', [], (int) $user['id'], $user);
            }
            unset($_SESSION['user_id']);
            session_regenerate_id(true);
            redirect_to('landing');
            break;
    }
}
