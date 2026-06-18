<?php

declare(strict_types=1);

function employee_onboarding_storage_root(): string
{
    return dirname(UPLOAD_PATH) . '/onboarding';
}

function store_employee_onboarding_upload(array $file, int $employeeId, string $field): array
{
    validate_uploaded_file($file, ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'], 5 * 1024 * 1024, str_replace('_', ' ', $field));
    ensure_directory(employee_onboarding_storage_root() . '/' . $employeeId);
    $extension = uploaded_file_extension($file) ?: 'dat';
    $target = employee_onboarding_storage_root() . '/' . $employeeId . '/' . $field . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($extension);
    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target)) {
        throw new RuntimeException('Unable to save ' . str_replace('_', ' ', $field) . '.');
    }

    return [
        'path' => normalize_relative_path($target),
        'name' => (string) ($file['name'] ?? basename($target)),
    ];
}

function store_employee_offer_signature_upload(array $file, int $employeeId): array
{
    validate_uploaded_file($file, ['jpg', 'jpeg', 'png'], 3 * 1024 * 1024, 'signature image');
    $mime = uploaded_file_mime_type($file);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        throw new RuntimeException('Signature upload must be a JPG or PNG image.');
    }

    ensure_directory(employee_onboarding_storage_root() . '/' . $employeeId);
    $extension = uploaded_file_extension($file) ?: 'png';
    $target = employee_onboarding_storage_root() . '/' . $employeeId . '/offer_signature_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($extension);
    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target)) {
        throw new RuntimeException('Unable to save signature image.');
    }

    return [
        'path' => normalize_relative_path($target),
        'name' => (string) ($file['name'] ?? basename($target)),
    ];
}

function employee_offer_letter_signature_data_uri(array $employee): string
{
    $relativePath = normalize_relative_path((string) ($employee['offer_letter_signature_path'] ?? ''));
    if ($relativePath === '' || preg_match('#^https?://#i', $relativePath)) {
        return '';
    }

    $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
    if (!is_file($absolutePath)) {
        return '';
    }

    $mime = mime_content_type($absolutePath) ?: 'image/png';
    if (!str_starts_with((string) $mime, 'image/')) {
        return '';
    }

    $contents = file_get_contents($absolutePath);
    if (!is_string($contents) || $contents === '') {
        return '';
    }

    return 'data:' . $mime . ';base64,' . base64_encode($contents);
}

function employee_offer_letter_filename(array $employee): string
{
    $name = preg_replace('/[^a-z0-9]+/i', '_', strtolower((string) (($employee['offer_letter_name'] ?? '') ?: ($employee['name'] ?? 'employee'))));
    $name = trim((string) $name, '_') ?: 'employee';

    return 'offer_letter_' . $name . '_' . date('Ymd_His') . '.pdf';
}

function employee_is_contractual_assignment_target(array $employee): bool
{
    $role = strtolower(trim((string) ($employee['role'] ?? '')));
    $employeeType = strtolower(trim((string) ($employee['employee_type'] ?? '')));
    $designation = strtolower(trim((string) ($employee['designation'] ?? '')));

    return $role === 'corporate_employee'
        || $employeeType === 'corporate'
        || $designation === 'contractual';
}

function contractual_assignment_confirmation_admin(array $admin): array
{
    $adminId = (int) ($admin['id'] ?? 0);
    if ($adminId <= 0) {
        return $admin;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id AND role IN ("admin", "freelancer", "external_vendor") LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $row = $stmt->fetch();

    return $row ?: $admin;
}

function publish_contractual_assignment_confirmations(array $employee, array $admin, array $projectIds, array $projectRanges): array
{
    $summary = [
        'processed' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    if (!employee_is_contractual_assignment_target($employee)) {
        return $summary;
    }

    $assignmentProjectIds = normalize_project_assignment_ids($projectIds);
    if ($assignmentProjectIds === []) {
        return $summary;
    }

    $letterAdmin = contractual_assignment_confirmation_admin($admin);
    $adminId = current_admin_id() ?? (int) ($letterAdmin['id'] ?? 0);
    foreach ($assignmentProjectIds as $projectId) {
        $project = project_by_id($projectId, $adminId > 0 ? $adminId : null);
        if (!$project) {
            $summary['skipped']++;
            $summary['errors'][] = 'Project not found for confirmation letter.';
            continue;
        }

        $summary['processed']++;
    }

    return $summary;
}

function employee_offer_letter_html(array $employee, string $employerName): string
{
    $offerName = trim((string) ($employee['offer_letter_name'] ?? '')) ?: (string) ($employee['name'] ?? '');
    $offerAddress = trim((string) ($employee['offer_letter_address'] ?? '')) ?: (string) ($employee['address'] ?? '');
    $offerDesignation = trim((string) ($employee['offer_letter_designation'] ?? '')) ?: (string) ($employee['designation'] ?? '');
    $signatureDataUri = employee_offer_letter_signature_data_uri($employee);
    $employerDisplay = trim($employerName) !== '' ? $employerName : 'V Traco';

    ob_start();
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: DejaVu Sans, sans-serif; color: #172554; font-size: 12px; line-height: 1.6; }
            .sheet { border: 1px solid #cbd5e1; padding: 28px; }
            .head { border-bottom: 1px solid #cbd5e1; padding-bottom: 14px; margin-bottom: 18px; }
            .brand { font-size: 24px; font-weight: 700; color: #1d4ed8; }
            .title { float: right; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-top: 8px; }
            .grid { width: 100%; border-collapse: collapse; margin: 16px 0; }
            .grid td { width: 33.33%; vertical-align: top; border: 1px solid #e2e8f0; padding: 8px 10px; }
            .grid strong { display: block; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
            .grid span { display: block; color: #0f172a; font-weight: 700; }
            .address-cell { width: 100% !important; }
            .sign-row { width: 100%; margin-top: 36px; border-collapse: collapse; }
            .sign-row td { width: 50%; vertical-align: bottom; padding-top: 18px; border-top: 1px solid #cbd5e1; }
            .sign-row td + td { padding-left: 28px; }
            .signature { max-width: 180px; max-height: 72px; margin: 8px 0; }
            .muted { color: #64748b; }
        </style>
    </head>
    <body>
        <div class="sheet">
            <div class="head">
                <span class="brand">V Traco</span>
                <span class="title">Offer Letter</span>
            </div>
            <p><strong>Date:</strong> <?= h(date('d M Y')) ?></p>
            <p>To,<br><strong><?= h($offerName) ?></strong><br><?= nl2br(h($offerAddress !== '' ? $offerAddress : '-')) ?></p>
            <table class="grid">
                <tr>
                    <td><strong>Employee ID</strong><span><?= h((string) (($employee['emp_id'] ?? '') ?: '-')) ?></span></td>
                    <td><strong>Name</strong><span><?= h($offerName !== '' ? $offerName : '-') ?></span></td>
                    <td><strong>Designation</strong><span><?= h($offerDesignation !== '' ? $offerDesignation : '-') ?></span></td>
                </tr>
                <tr>
                    <td><strong>Employer</strong><span><?= h($employerDisplay) ?></span></td>
                    <td><strong>Email</strong><span><?= h((string) (($employee['email'] ?? '') ?: '-')) ?></span></td>
                    <td><strong>Phone</strong><span><?= h((string) (($employee['phone'] ?? '') ?: '-')) ?></span></td>
                </tr>
                <tr>
                    <td><strong>Date of Joining</strong><span><?= !empty($employee['date_of_joining']) ? h(date('d M Y', strtotime((string) $employee['date_of_joining']))) : '-' ?></span></td>
                    <td><strong>Shift</strong><span><?= h(employee_shift_display($employee)) ?></span></td>
                    <td><strong>Salary</strong><span>Rs <?= h(number_format((float) ($employee['salary'] ?? 0), 2)) ?></span></td>
                </tr>
                <tr>
                    <td colspan="3" class="address-cell"><strong>Address</strong><span><?= nl2br(h($offerAddress !== '' ? $offerAddress : '-')) ?></span></td>
                </tr>
            </table>
            <p>Dear <?= h($offerName !== '' ? $offerName : 'Employee') ?>,</p>
            <p>We are pleased to offer you the position of <strong><?= h($offerDesignation !== '' ? $offerDesignation : 'Employee') ?></strong> with <?= h($employerDisplay) ?>. Your joining and work details will follow the rules assigned in V Traco.</p>
            <p>Please treat this letter as confirmation of your offer and acceptance details recorded in V Traco.</p>
            <table class="sign-row">
                <tr>
                    <td>
                        <span class="muted">Employee Signature</span><br>
                        <?php if ($signatureDataUri !== ''): ?>
                            <img class="signature" src="<?= h($signatureDataUri) ?>" alt="Employee signature"><br>
                        <?php endif; ?>
                        <strong><?= h($offerName !== '' ? $offerName : 'Employee') ?></strong>
                    </td>
                    <td>
                        <span class="muted">For <?= h($employerDisplay) ?></span><br><br>
                        <strong>Authorized Signatory</strong>
                    </td>
                </tr>
            </table>
        </div>
    </body>
    </html>
    <?php

    return (string) ob_get_clean();
}

function employee_offer_letter_pdf_contents(array $employee, string $employerName): string
{
    require_once __DIR__ . '/../../vendor/autoload.php';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml(employee_offer_letter_html($employee, $employerName));
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

function stream_employee_offer_letter_pdf(array $employee, string $employerName): void
{
    $contents = employee_offer_letter_pdf_contents($employee, $employerName);
    $filename = employee_offer_letter_filename($employee);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($contents));
    echo $contents;
    exit;
}

function employee_offer_letter_employer_name(array $employee): string
{
    $adminId = (int) ($employee['admin_id'] ?? 0);
    if ($adminId <= 0) {
        return '';
    }

    $stmt = db()->prepare('SELECT COALESCE(NULLIF(company_name, ""), name) FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $adminId]);

    return (string) ($stmt->fetchColumn() ?: '');
}

function vendor_profile_storage_root(): string
{
    return dirname(UPLOAD_PATH) . '/vendors';
}

function store_vendor_profile_upload(array $file, int $vendorId, string $field): array
{
    $imageFields = ['company_logo', 'profile_photo'];
    $allowedExtensions = in_array($field, $imageFields, true)
        ? ['jpg', 'jpeg', 'png', 'webp']
        : ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

    validate_uploaded_file($file, $allowedExtensions, 5 * 1024 * 1024, str_replace('_', ' ', $field));
    ensure_directory(vendor_profile_storage_root() . '/' . $vendorId);
    $extension = uploaded_file_extension($file) ?: 'dat';
    $target = vendor_profile_storage_root() . '/' . $vendorId . '/' . $field . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($extension);
    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target)) {
        throw new RuntimeException('Unable to save ' . str_replace('_', ' ', $field) . '.');
    }

    return [
        'path' => normalize_relative_path($target),
        'name' => (string) ($file['name'] ?? basename($target)),
    ];
}

function notify_profile_reviewers(array $employee): void
{
    foreach (employee_profile_reviewer_ids($employee) as $reviewerId) {
        create_notification_entry(
            $reviewerId,
            'Profile verification submitted',
            (string) ($employee['name'] ?? 'Employee') . ' submitted onboarding details for review.',
            'info',
            'employee_profile',
            (int) ($employee['id'] ?? 0),
            (int) ($employee['id'] ?? 0)
        );
    }
}

function approve_employee_profile_submission(array $employee, array $reviewer): void
{
    $employeeId = (int) ($employee['id'] ?? 0);
    $reviewerId = (int) ($reviewer['id'] ?? 0);
    if ($employeeId <= 0 || $reviewerId <= 0) {
        throw new RuntimeException('Employee not found for approval.');
    }

    db()->prepare('UPDATE users
        SET admin_id = COALESCE(NULLIF(admin_id, 0), :admin_id),
            profile_status = "verified",
            profile_rejection_reason = NULL
        WHERE id = :id
          AND role IN ("employee", "corporate_employee")
          AND (admin_id = :admin_id OR (role = "corporate_employee" AND (admin_id IS NULL OR admin_id = 0)))')
        ->execute([
            'id' => $employeeId,
            'admin_id' => $reviewerId,
        ]);

    create_notification_entry(
        $employeeId,
        'Profile verified',
        'Your profile has been approved. Dashboard access is now enabled.',
        'success',
        'employee_profile',
        $employeeId,
        $reviewerId
    );
    $updatedEmployee = array_merge($employee, ['profile_status' => 'verified', 'profile_rejection_reason' => null]);
    send_employee_profile_status_email($updatedEmployee, 'verified');
}

function employee_profile_reviewer_ids(array $employee): array
{
    $adminId = (int) ($employee['admin_id'] ?? 0);
    $reviewerIds = [];
    if ($adminId > 0) {
        $reviewerIds[] = $adminId;
    } elseif ((string) ($employee['role'] ?? '') === 'corporate_employee') {
        foreach (db()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'ACTIVE'")->fetchAll() as $adminRow) {
            $reviewerIds[] = (int) ($adminRow['id'] ?? 0);
        }
    }

    return array_values(array_unique(array_filter($reviewerIds)));
}

function employee_profile_change_labels(array $employee, array $payload, array $documentValues): array
{
    $labels = [
        'emp_id' => 'Employee ID',
        'name' => 'Name',
        'email' => 'Email',
        'date_of_joining' => 'Date of Joining',
        'date_of_birth' => 'Date of Birth',
        'gender' => 'Gender',
        'designation' => 'Designation',
        'shift' => 'Shift',
        'highest_qualification' => 'Highest Qualification',
        'phone' => 'Phone Number',
        'address' => 'Address',
        'training_experience_years' => 'Training Experience',
        'languages_known' => 'Languages Known',
        'technical_skills' => 'Technical Skills',
        'bank_name' => 'Bank Name',
        'bank_account_no' => 'Account Number',
        'bank_ifsc_code' => 'IFSC Code',
        'account_holder_name' => 'Account Holder Name',
        'aadhaar_card' => 'Aadhaar Card',
        'pan_card' => 'PAN Card',
        'profile_photo' => 'Profile Photo',
        'qualification_certificate' => 'Qualification Certificate',
        'bank_proof' => 'Bank Proof',
        'resume' => 'Resume',
    ];
    $changed = [];

    foreach ($payload as $field => $value) {
        if (trim((string) ($employee[$field] ?? '')) !== trim((string) $value)) {
            $changed[] = $labels[$field] ?? ucwords(str_replace('_', ' ', (string) $field));
        }
    }

    foreach ($documentValues as $field => $value) {
        if (!str_ends_with((string) $field, '_path')) {
            continue;
        }
        $baseField = substr((string) $field, 0, -5);
        if (trim((string) ($employee[$field] ?? '')) !== trim((string) $value)) {
            $changed[] = $labels[$baseField] ?? ucwords(str_replace('_', ' ', $baseField));
        }
    }

    return array_values(array_unique($changed));
}

function notify_employee_profile_updated(array $employee, array $changedLabels): void
{
    if ($changedLabels === []) {
        return;
    }

    $employeeName = trim((string) ($employee['name'] ?? 'Employee')) ?: 'Employee';
    $employeeCode = trim((string) ($employee['emp_id'] ?? ''));
    $employeeLabel = $employeeName . ($employeeCode !== '' ? ' (' . $employeeCode . ')' : '');
    $changedSummary = implode(', ', array_slice($changedLabels, 0, 6));
    if (count($changedLabels) > 6) {
        $changedSummary .= ', and ' . (count($changedLabels) - 6) . ' more';
    }

    foreach (employee_profile_reviewer_ids($employee) as $reviewerId) {
        create_notification_entry(
            $reviewerId,
            'Employee profile edited',
            $employeeLabel . ' edited profile details: ' . $changedSummary . '.',
            'info',
            'employee_profile',
            (int) ($employee['id'] ?? 0),
            (int) ($employee['id'] ?? 0)
        );
    }
}

function employee_profile_save_payload(array $employee): array
{
    $isContractual = (string) ($employee['role'] ?? '') === 'corporate_employee';
    $requiredText = $isContractual
        ? [
            'emp_id',
            'name',
            'date_of_joining',
            'date_of_birth',
            'gender',
            'training_experience_years',
            'languages_known',
            'email',
            'phone',
            'technical_skills',
            'bank_name',
            'bank_account_no',
            'bank_ifsc_code',
            'account_holder_name',
        ]
        : [
            'emp_id',
            'name',
            'email',
            'date_of_joining',
            'date_of_birth',
            'gender',
            'highest_qualification',
            'phone',
            'address',
            'bank_name',
            'bank_account_no',
            'bank_ifsc_code',
            'account_holder_name',
        ];

    $payload = [];
    foreach ($requiredText as $field) {
        $payload[$field] = trim((string) ($_POST[$field] ?? ''));
        if ($payload[$field] === '') {
            throw new RuntimeException(ucwords(str_replace('_', ' ', $field)) . ' is required.');
        }
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['date_of_birth'])) {
        throw new RuntimeException('Choose a valid date of birth.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['date_of_joining'])) {
        throw new RuntimeException('Choose a valid date of joining.');
    }
    if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid mail ID.');
    }
    if (role_email_exists((string) $employee['role'], $payload['email'], (int) $employee['id'])) {
        throw new RuntimeException('This mail ID is already assigned.');
    }

    $payload['designation'] = trim((string) ($employee['designation'] ?? ''));
    if ($payload['designation'] === '') {
        throw new RuntimeException('Designation is assigned by admin. Please contact your admin to update it.');
    }
    $payload['shift'] = normalize_shift_selection((string) ($employee['shift'] ?? ''));
    if ($payload['shift'] === '') {
        throw new RuntimeException('Shift is assigned by admin. Please contact your admin to update it.');
    }
    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role IN ('employee', 'corporate_employee') AND emp_id = :emp_id AND id <> :id");
    $stmt->execute([
        'emp_id' => $payload['emp_id'],
        'id' => (int) $employee['id'],
    ]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('This employee ID is already assigned.');
    }

    $documentFields = $isContractual
        ? ['pan_card', 'bank_proof', 'profile_photo', 'resume']
        : ['aadhaar_card', 'pan_card', 'profile_photo', 'qualification_certificate', 'bank_proof', 'resume'];
    $documentValues = [];
    foreach ($documentFields as $field) {
        $file = $_FILES[$field] ?? [];
        $hasExisting = trim((string) ($employee[$field . '_path'] ?? '')) !== '';
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            if (!$hasExisting) {
                throw new RuntimeException(ucwords(str_replace('_', ' ', $field)) . ' is required.');
            }
            $documentValues[$field . '_path'] = (string) ($employee[$field . '_path'] ?? '');
            $documentValues[$field . '_name'] = (string) ($employee[$field . '_name'] ?? '');
            continue;
        }

        $stored = store_employee_onboarding_upload($file, (int) $employee['id'], $field);
        $documentValues[$field . '_path'] = $stored['path'];
        $documentValues[$field . '_name'] = $stored['name'];
    }

    return [$payload, $documentValues];
}

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
    $actionUser = current_user();
    if ($actionUser && employee_profile_requires_completion($actionUser) && !in_array($action, ['employee_profile_submit', 'employee_profile_update', 'logout'], true)) {
        flash('info', 'Complete profile verification before using the dashboard.');
        redirect_to('employee_profile_completion');
    }

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

                if (in_array(($candidate['role'] ?? ''), ['admin', 'freelancer', 'external_vendor', 'employee', 'corporate_employee'], true)) {
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

                if (in_array((string) ($candidate['role'] ?? ''), ['employee', 'corporate_employee'], true) && (string) ($candidate['profile_status'] ?? '') !== 'verified') {
                    flash('error', 'Your profile is waiting for admin approval. Please login again after approval.');
                    redirect_to($returnPage === 'landing' ? 'landing' : 'login', ['role' => $role]);
                }

                $user = $candidate;
                break;
            }

            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                if (in_array(($user['role'] ?? ''), ['employee', 'corporate_employee'], true)) {
                    $_SESSION['show_employee_profile_on_login'] = 1;
                }
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

        case 'landing_contact_submit':
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $company = trim((string) ($_POST['company'] ?? ''));
            $message = trim((string) ($_POST['message'] ?? ''));

            if ($name === '' || $email === '' || $message === '') {
                flash('error', 'Name, email, and message are required.');
                redirect_to('landing');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Please enter a valid email address.');
                redirect_to('landing');
            }

            try {
                $subject = 'V Traco - New Contact Form Submission from ' . $name;
                $htmlContent = <<<'HTML'
                <div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;">
                    <h2 style="color: #4338ca;">New Contact Form Submission</h2>
                    <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0;">
                        <p><strong>Name:</strong> %NAME%</p>
                        <p><strong>Email:</strong> %EMAIL%</p>
                        <p><strong>Company:</strong> %COMPANY%</p>
                        <p><strong>Message:</strong></p>
                        <p style="white-space: pre-wrap; background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #4338ca;">%MESSAGE%</p>
                    </div>
                    <p style="color: #666; font-size: 12px;">This is an automated message from V Traco contact form.</p>
                </div>
                HTML;

                $htmlContent = str_replace(
                    ['%NAME%', '%EMAIL%', '%COMPANY%', '%MESSAGE%'],
                    [
                        htmlspecialchars($name),
                        htmlspecialchars($email),
                        htmlspecialchars($company !== '' ? $company : 'Not provided'),
                        htmlspecialchars($message),
                    ],
                    $htmlContent
                );

                $contactEmails = ['admin@training.com', 'anfinojegoal@gmail.com'];
                $mailSent = false;
                
                foreach ($contactEmails as $recipientEmail) {
                    $mailResult = send_html_mail(
                        to: $recipientEmail,
                        subject: $subject,
                        html: $htmlContent
                    );
                    if (!empty($mailResult['sent'])) {
                        $mailSent = true;
                    }
                }

                app_log('info', 'Contact form submission received.', [
                    'name' => $name,
                    'email' => $email,
                    'company' => $company,
                    'mail_sent' => $mailSent,
                ]);

                flash('success', 'Thank you! Your message has been received. We will get back to you soon.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Contact form submission failed.', [
                    'name' => $name,
                    'email' => $email,
                ]);
                flash('error', 'Unable to submit your message right now. Error: ' . $exception->getMessage());
            }

            redirect_to('landing');
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
                flash('error', 'Company name is required.');
                redirect_to($redirectPage, $redirectParams);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid company mail ID.');
                redirect_to($redirectPage, $redirectParams);
            }
            if ($phone === '') {
                flash('error', 'Company phone number is required.');
                redirect_to($redirectPage, $redirectParams);
            }
            if (role_email_exists('external_vendor', $email)) {
                flash('error', 'A vendor account already exists for this email.');
                redirect_to($redirectPage, $redirectParams);
            }

            try {
                $password = random_password();
                db()->prepare('INSERT INTO users (role, emp_id, name, company_name, email, phone, salary, password_hash, force_password_change, password_changed_at, created_at) VALUES ("external_vendor", NULL, :name, :company_name, :email, :phone, 0, :password_hash, 1, NULL, :created_at)')
                    ->execute([
                        'name' => $name,
                        'company_name' => $name,
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
                $loginLink = mail_login_link_for_role('external_vendor');
                $html = '<p>Hello ' . h($name) . ',</p>'
                    . '<p>An admin created your V Traco vendor account.</p>'
                    . '<p><strong>Login Email:</strong> ' . h($email) . '<br>'
                    . '<strong>Password:</strong> ' . h($password) . '</p>'
                    . '<p><strong>Login Link:</strong> <a href="' . h($loginLink) . '">' . h($loginLink) . '</a></p>'
                    . '<p><a href="' . h($loginLink) . '" style="display:inline-block;background:#3085d6;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:700;">Open Login</a></p>'
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

        case 'admin_vendor_status_update':
            require_role('admin');
            $vendorId = (int) ($_POST['vendor_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'BLOCKED');
            if (!in_array($status, ['ACTIVE', 'BLOCKED'], true)) {
                $status = 'BLOCKED';
            }
            $redirectPage = trim((string) ($_POST['redirect_page'] ?? 'admin_employees'));
            $redirectPage = in_array($redirectPage, ['admin_vendors', 'admin_employees'], true) ? $redirectPage : 'admin_employees';
            $redirectParams = $redirectPage === 'admin_employees' ? ['type' => 'vendor'] : [];

            $stmt = db()->prepare("UPDATE users SET status = :status WHERE id = :id AND role = 'external_vendor'");
            $stmt->execute(['status' => $status, 'id' => $vendorId]);
            if ($stmt->rowCount() < 1) {
                flash('error', 'Vendor account status could not be updated.');
                redirect_to($redirectPage, $redirectParams);
            }
            audit_log('vendor_status_updated', ['status' => $status], $vendorId);
            flash('success', $status === 'ACTIVE' ? 'Vendor account activated successfully.' : 'Vendor account marked inactive successfully.');
            redirect_to($redirectPage, $redirectParams);
            break;

        case 'admin_vendor_project_assign':
            $admin = require_role('admin');
            $vendorId = (int) ($_POST['vendor_id'] ?? 0);
            $redirectPage = trim((string) ($_POST['redirect_page'] ?? 'admin_employees'));
            $redirectPage = in_array($redirectPage, ['admin_vendors', 'admin_employees'], true) ? $redirectPage : 'admin_employees';
            $redirectParams = $redirectPage === 'admin_employees' ? ['type' => 'vendor'] : [];
            $projectIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['project_ids'] ?? [])))));

            try {
                $vendorStmt = db()->prepare("SELECT id, name, company_name FROM users WHERE id = :id AND role = 'external_vendor' LIMIT 1");
                $vendorStmt->execute(['id' => $vendorId]);
                $vendor = $vendorStmt->fetch();
                if (!$vendor) {
                    throw new RuntimeException('Select a valid vendor account.');
                }

                $vendorName = trim((string) (($vendor['company_name'] ?? '') ?: ($vendor['name'] ?? '')));
                $adminId = (int) (current_admin_id() ?? ($admin['id'] ?? 0));
                $pdo = db();
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE projects SET vendor_id = NULL, vendor_name = NULL WHERE admin_id = :admin_id AND vendor_id = :vendor_id')
                    ->execute([
                        'admin_id' => $adminId,
                        'vendor_id' => $vendorId,
                    ]);

                if ($projectIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
                    $params = array_merge([$vendorId, $vendorName, $adminId], $projectIds);
                    $pdo->prepare("UPDATE projects SET vendor_id = ?, vendor_name = ? WHERE admin_id = ? AND id IN ($placeholders)")
                        ->execute($params);
                }

                $pdo->commit();
                audit_log('vendor_project_assignment_updated', [
                    'project_ids' => $projectIds,
                ], $vendorId);
                flash('success', 'Vendor project assignment saved successfully.');
            } catch (Throwable $exception) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                report_exception($exception, 'Vendor project assignment failed.', ['vendor_id' => $vendorId]);
                flash('error', $exception->getMessage() ?: 'Unable to save vendor project assignment.');
            }

            redirect_to($redirectPage, $redirectParams);
            break;

        case 'admin_vendor_delete':
            require_role('admin');
            $vendorId = (int) ($_POST['vendor_id'] ?? 0);
            $redirectPage = trim((string) ($_POST['redirect_page'] ?? 'admin_employees'));
            $redirectPage = in_array($redirectPage, ['admin_vendors', 'admin_employees'], true) ? $redirectPage : 'admin_employees';
            $redirectParams = $redirectPage === 'admin_employees' ? ['type' => 'vendor'] : [];

            $vendorStmt = db()->prepare("SELECT id, name FROM users WHERE id = :id AND role = 'external_vendor' LIMIT 1");
            $vendorStmt->execute(['id' => $vendorId]);
            $vendor = $vendorStmt->fetch();
            if (!$vendor) {
                flash('error', 'Vendor account not found.');
                redirect_to($redirectPage, $redirectParams);
            }

            try {
                $pdo = db();
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM users WHERE admin_id = :vendor_id AND role IN ('employee', 'corporate_employee')")
                    ->execute(['vendor_id' => $vendorId]);
                $deleteVendor = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'external_vendor'");
                $deleteVendor->execute(['id' => $vendorId]);
                if ($deleteVendor->rowCount() < 1) {
                    throw new RuntimeException('Vendor account could not be deleted.');
                }
                $pdo->commit();
                audit_log('vendor_deleted', ['name' => (string) ($vendor['name'] ?? '')], $vendorId);
                flash('success', 'Vendor account deleted successfully.');
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                report_exception($exception, 'Vendor deletion failed.', ['vendor_id' => $vendorId]);
                flash('error', 'Vendor account could not be deleted. Please try again.');
            }
            redirect_to($redirectPage, $redirectParams);
            break;

        case 'employee_manual_next':
            $manager = require_power_team_access(['admin', 'freelancer', 'external_vendor']);
            $employeeType = trim((string) ($_POST['employee_type'] ?? 'regular'));
            if (($manager['role'] ?? '') === 'freelancer') {
                $employeeType = 'corporate';
            } elseif (($manager['role'] ?? '') === 'external_vendor') {
                $employeeType = 'vendor';
            }
            if (!in_array($employeeType, ['regular', 'vendor', 'corporate'], true)) {
                $employeeType = 'regular';
            }
            $isContractualEmployee = $employeeType === 'corporate';
            $isVendorEmployee = $employeeType === 'vendor';
            $isFreelancerManager = ($manager['role'] ?? '') === 'freelancer';
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
            if (!$isVendorEmployee && trim((string) ($_POST['recruited_through'] ?? '')) === '') {
                flash('error', 'Sourced through is required.');
                redirect_to('admin_employees');
            }
            if ($isFreelancerManager) {
                if (!is_numeric((string) ($_POST['salary'] ?? '')) || (float) ($_POST['salary'] ?? 0) < 0) {
                    flash('error', 'Hourly rate must be zero or greater.');
                    redirect_to('admin_employees');
                }
            } elseif (!$isContractualEmployee && !$isVendorEmployee) {
                if (!is_numeric((string) ($_POST['salary'] ?? '')) || (float) ($_POST['salary'] ?? 0) < 0) {
                    flash('error', 'Employee salary must be zero or greater.');
                    redirect_to('admin_employees');
                }
                if (trim((string) ($_POST['recruiter_name'] ?? '')) === '') {
                    flash('error', 'Recruiter name is required.');
                    redirect_to('admin_employees');
                }
                if (trim((string) ($_POST['designation'] ?? '')) === '') {
                    flash('error', 'Designation is required.');
                    redirect_to('admin_employees');
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['date_of_joining'] ?? ''))) {
                    flash('error', 'Date of joining is required.');
                    redirect_to('admin_employees');
                }
            }
            $_SESSION['pending_employee'] = [
                'emp_id' => trim((string) ($_POST['emp_id'] ?? '')),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'phone' => trim((string) ($_POST['phone'] ?? '')),
                'shift' => normalize_shift_selection((string) ($_POST['shift'] ?? standard_shift_options()[0])),
                'salary' => $isFreelancerManager ? (float) ($_POST['salary'] ?? 0) : (($isContractualEmployee || $isVendorEmployee) ? 0 : (float) ($_POST['salary'] ?? 0)),
                'employee_type' => $employeeType,
                'recruiter_name' => ($isContractualEmployee || $isVendorEmployee) ? (string) ($manager['name'] ?? 'Admin') : trim((string) ($_POST['recruiter_name'] ?? '')),
                'recruited_through' => $isVendorEmployee ? '' : trim((string) ($_POST['recruited_through'] ?? '')),
                'designation' => $isContractualEmployee ? 'Contractual' : ($isVendorEmployee ? 'Vendor' : trim((string) ($_POST['designation'] ?? ''))),
                'date_of_joining' => ($isContractualEmployee || $isVendorEmployee) ? date('Y-m-d') : trim((string) ($_POST['date_of_joining'] ?? '')),
            ];
            redirect_to('admin_employees', ['stage' => 'manual_rules']);
            break;

        case 'employee_manual_submit':
            require_power_team_access(['admin', 'freelancer', 'external_vendor']);

            $pending = $_SESSION['pending_employee'] ?? null;
            if (!$pending) {
                flash('error', 'No pending employee found.');
                redirect_to('admin_employees');
            }
            try {
                $rules = normalize_rules_from_input($_POST);
                $pending['shift'] = resolve_shift_selection_from_input($_POST, (string) ($pending['shift'] ?? standard_shift_options()[0]));
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
            require_power_team_access(['admin', 'freelancer', 'external_vendor']);
            try {
                $uploadedEmployeeFile = $_FILES['csv_file'] ?? [];
                validate_employee_csv_upload($uploadedEmployeeFile);
                $employeeType = trim((string) ($_POST['employee_type'] ?? 'regular'));
                $manager = current_user() ?: [];
                if (($manager['role'] ?? '') === 'freelancer') {
                    $employeeType = 'corporate';
                } elseif (($manager['role'] ?? '') === 'external_vendor') {
                    $employeeType = 'vendor';
                }
                if (!in_array($employeeType, ['regular', 'vendor', 'corporate'], true)) {
                    $employeeType = 'regular';
                }
                $_SESSION['pending_csv_import'] = array_map(
                    static fn (array $row): array => array_merge([
                        'recruiter_name' => (string) ($manager['name'] ?? 'Admin'),
                        'recruited_through' => 'Bulk Import',
                        'designation' => $employeeType === 'corporate' ? 'Contractual' : ($employeeType === 'vendor' ? 'Vendor' : 'Regular Employee'),
                        'date_of_joining' => date('Y-m-d'),
                    ], $row, ['employee_type' => $employeeType]),
                    parse_employee_csv(
                        (string) ($uploadedEmployeeFile['tmp_name'] ?? ''),
                        (string) ($uploadedEmployeeFile['name'] ?? ''),
                        $employeeType !== 'vendor'
                    )
                );
                flash('success', 'CSV uploaded. Assign rules to continue.');
                $redirectArgs = ['stage' => 'csv_rules'];
                if (in_array($employeeType, ['regular', 'corporate'], true)) {
                    $redirectArgs['type'] = $employeeType;
                }
                redirect_to('admin_employees', $redirectArgs);
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee CSV upload failed.', [
                    'filename' => (string) (($_FILES['csv_file']['name'] ?? '') ?: ''),
                ]);
                flash('error', $exception->getMessage());
                redirect_to('admin_employees');
            }
            break;

        case 'employee_csv_cancel':
            require_power_team_access(['admin', 'freelancer', 'external_vendor']);
            unset($_SESSION['pending_csv_import']);
            flash('info', 'Bulk import cancelled.');
            redirect_to('admin_employees');
            break;

        case 'employee_csv_submit':
            require_power_team_access(['admin', 'freelancer', 'external_vendor']);

            $rows = $_SESSION['pending_csv_import'] ?? [];
            if (!$rows) {
                flash('error', 'No CSV import is pending.');
                redirect_to('admin_employees');
            }
            $rules = normalize_rules_from_input($_POST);
            $projectIds = array_map('intval', $_POST['project_ids'] ?? []);
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $emailsSent = 0;
            $emailsLogged = 0;
            foreach ($rows as $row) {
                try {
                    $createdEmployee = import_employee_row($row, $rules, $projectIds);
                    if (($createdEmployee['result'] ?? 'created') === 'updated') {
                        $updated++;
                    } else {
                        $created++;
                    }
                    if (!empty($createdEmployee['mail_result']['sent'])) {
                        $emailsSent++;
                    } else {
                        $emailsLogged++;
                    }
                } catch (Throwable $exception) {
                    $skipped++;
                    report_exception($exception, 'Employee CSV row import failed.', [
                        'emp_id' => (string) ($row['emp_id'] ?? ''),
                        'email' => (string) ($row['email'] ?? ''),
                    ]);
                }
            }
            unset($_SESSION['pending_csv_import']);
            $message = 'CSV import completed. Created: ' . $created;
            if ($updated) {
                $message .= ' | Updated: ' . $updated;
            }
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
                'updated' => $updated,
                'skipped' => $skipped,
                'emails_sent' => $emailsSent,
                'emails_logged' => $emailsLogged,
            ]);
            flash('success', $message);
            redirect_to('admin_employees');
            break;

        case 'employee_update':
            $admin = require_power_team_access(['admin', 'freelancer', 'external_vendor']);

            $employeeId = (int) ($_POST['user_id'] ?? 0);
            $existingEmployee = employee_by_id($employeeId);
            if (!$existingEmployee) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            try {
                $email = trim((string) ($_POST['email'] ?? ''));
                $name = trim((string) ($_POST['name'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $employeeType = trim((string) ($_POST['employee_type'] ?? 'regular'));
                if (($admin['role'] ?? '') === 'freelancer') {
                    $employeeType = 'corporate';
                } elseif (($admin['role'] ?? '') === 'external_vendor') {
                    $employeeType = 'vendor';
                }
                $isVendorEmployeeUpdate = $employeeType === 'vendor';
                $isContractualEmployeeUpdate = $employeeType === 'corporate';
                $isManagedEmployeeUpdate = $isContractualEmployeeUpdate || $isVendorEmployeeUpdate;
                $salary = $isManagedEmployeeUpdate ? 0.0 : (float) ($_POST['salary'] ?? 0);
                $recruiterName = $isManagedEmployeeUpdate
                    ? (trim((string) ($existingEmployee['recruiter_name'] ?? '')) ?: (string) ($admin['name'] ?? 'Admin'))
                    : trim((string) ($_POST['recruiter_name'] ?? ''));
                $recruitedThrough = $isManagedEmployeeUpdate
                    ? trim((string) ($existingEmployee['recruited_through'] ?? ''))
                    : trim((string) ($_POST['recruited_through'] ?? ''));
                $designation = $isContractualEmployeeUpdate
                    ? 'Contractual'
                    : ($isVendorEmployeeUpdate ? 'Vendor' : trim((string) ($_POST['designation'] ?? '')));
                $dateOfJoining = $isManagedEmployeeUpdate
                    ? (trim((string) ($existingEmployee['date_of_joining'] ?? '')) ?: date('Y-m-d'))
                    : trim((string) ($_POST['date_of_joining'] ?? ''));
                if ($name === '') {
                    throw new RuntimeException('Employee name is required.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid employee email address.');
                }
                if ($phone === '') {
                    throw new RuntimeException('Employee phone number is required.');
                }
                if (!$isManagedEmployeeUpdate && (!is_numeric((string) ($_POST['salary'] ?? '')) || $salary < 0)) {
                    throw new RuntimeException('Employee salary must be zero or greater.');
                }
                if (!$isManagedEmployeeUpdate && $recruiterName === '') {
                    throw new RuntimeException('Recruiter name is required.');
                }
                if (!$isManagedEmployeeUpdate && $recruitedThrough === '') {
                    throw new RuntimeException('Recruited through is required.');
                }
                if (!$isManagedEmployeeUpdate && $designation === '') {
                    throw new RuntimeException('Designation is required.');
                }
                if (!$isManagedEmployeeUpdate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfJoining)) {
                    throw new RuntimeException('Date of joining is required.');
                }
                if (!in_array($employeeType, ['regular', 'vendor', 'corporate'], true)) {
                    $employeeType = 'regular';
                }
                if ($employeeType === 'vendor' && ($admin['role'] ?? '') !== 'external_vendor') {
                    throw new RuntimeException('Vendor employees can only be managed by the vendor.');
                }
                $role = $employeeType === 'corporate' ? 'corporate_employee' : current_manager_target_role();
                $existingRole = (string) ($existingEmployee['role'] ?? $role);
                if (role_requires_unique_email($role) && role_email_exists($role, $email, $employeeId)) {
                    throw new RuntimeException('This employee email is already assigned.');
                }
                $shift = $isManagedEmployeeUpdate
                    ? (string) ($existingEmployee['shift'] ?? '')
                    : shift_selection_from_time_inputs($_POST, (string) ($existingEmployee['shift'] ?? ''));
                
                db()->prepare('UPDATE users SET role = :new_role, emp_id = :emp_id, name = :name, email = :email, phone = :phone, shift = :shift, salary = :salary, employee_type = :employee_type, recruiter_name = :recruiter_name, recruited_through = :recruited_through, designation = :designation, date_of_joining = :date_of_joining WHERE id = :id AND role = :existing_role AND admin_id = :admin_id')
                    ->execute([
                        'id' => $employeeId,
                        'existing_role' => $existingRole,
                        'new_role' => $role,
                        'admin_id' => (int) $admin['id'],
                        'emp_id' => trim((string) ($_POST['emp_id'] ?? '')),
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'shift' => $shift,
                        'salary' => $salary,
                        'employee_type' => $employeeType,
                        'recruiter_name' => $recruiterName,
                        'recruited_through' => $recruitedThrough,
                        'designation' => $designation,
                        'date_of_joining' => $dateOfJoining,
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

        case 'admin_review_employee_profile':
            $reviewer = require_power_team_access(['admin', 'freelancer']);
            $employeeId = (int) ($_POST['user_id'] ?? 0);
            $decision = (string) ($_POST['decision'] ?? '');
            $reason = trim((string) ($_POST['rejection_reason'] ?? ''));
            $reviewAdminId = (int) $reviewer['id'];
            $stmt = db()->prepare("SELECT * FROM users
                WHERE id = :id
                  AND role IN ('employee', 'corporate_employee')
                  AND (admin_id = :admin_id OR (role = 'corporate_employee' AND (admin_id IS NULL OR admin_id = 0)))
                LIMIT 1");
            $stmt->execute(['id' => $employeeId, 'admin_id' => $reviewAdminId]);
            $employee = $stmt->fetch() ?: null;
            if (!$employee) {
                flash('error', 'Employee not found for review.');
                redirect_to('admin_employees');
            }
            try {
                if ($decision === 'approve') {
                    approve_employee_profile_submission($employee, $reviewer);
                    audit_log('employee_profile_approved', [], $employeeId);
                    flash('success', 'Employee profile approved.');
                } elseif ($decision === 'reject') {
                    if ($reason === '') {
                        throw new RuntimeException('Rejection reason is required.');
                    }
                    db()->prepare('UPDATE users
                        SET admin_id = COALESCE(NULLIF(admin_id, 0), :admin_id),
                            profile_status = "rejected",
                            profile_rejection_reason = :reason
                        WHERE id = :id
                          AND role IN ("employee", "corporate_employee")
                          AND (admin_id = :admin_id OR (role = "corporate_employee" AND (admin_id IS NULL OR admin_id = 0)))')
                        ->execute(['reason' => $reason, 'id' => $employeeId, 'admin_id' => $reviewAdminId]);
                    create_notification_entry($employeeId, 'Profile rejected', 'Your profile was rejected: ' . $reason, 'warning', 'employee_profile', $employeeId, (int) $reviewer['id']);
                    $updatedEmployee = array_merge($employee, ['profile_status' => 'rejected', 'profile_rejection_reason' => $reason]);
                    send_employee_profile_status_email($updatedEmployee, 'rejected', $reason);
                    audit_log('employee_profile_rejected', ['reason' => $reason], $employeeId);
                    flash('success', 'Employee profile rejected and returned for resubmission.');
                } else {
                    throw new RuntimeException('Choose approve or reject.');
                }
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee profile review failed.', ['employee_id' => $employeeId]);
                flash('error', $exception->getMessage() ?: 'Unable to review employee profile.');
            }
            redirect_to('admin_employees');
            break;

        case 'employee_reset_password':
            require_power_team_access(['admin', 'freelancer', 'external_vendor']);

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

        case 'employee_status_update':
            $admin = require_power_team_access(['admin', 'freelancer', 'external_vendor']);

            $employeeId = (int) ($_POST['user_id'] ?? 0);
            $employee = employee_by_id($employeeId);
            if (!$employee) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            $status = (string) ($_POST['status'] ?? 'ACTIVE');
            if (!in_array($status, ['ACTIVE', 'BLOCKED'], true)) {
                $status = 'ACTIVE';
            }
            $redirectParams = [];
            if ((string) ($employee['role'] ?? '') === 'corporate_employee' || (string) ($employee['employee_type'] ?? '') === 'corporate') {
                $redirectParams['type'] = 'corporate';
            } elseif ((string) ($employee['employee_type'] ?? '') === 'vendor') {
                $redirectParams['type'] = 'vendor';
            }

            $setParts = ['status = :status'];
            $params = [
                'status' => $status,
                'id' => $employeeId,
                'admin_id' => (int) $admin['id'],
            ];
            if ($status === 'ACTIVE') {
                $setParts[] = 'admin_id = COALESCE(NULLIF(admin_id, 0), :admin_id)';
                $setParts[] = 'profile_status = "verified"';
                $setParts[] = 'profile_rejection_reason = NULL';
            }
            $updateStmt = db()->prepare('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id AND role IN ("employee", "corporate_employee") AND (admin_id = :admin_id OR (role = "corporate_employee" AND (admin_id IS NULL OR admin_id = 0)))');
            $updateStmt->execute($params);
            if ($updateStmt->rowCount() < 1) {
                flash('error', 'Employee status could not be updated. Please refresh and try again.');
                redirect_to('admin_employees', $redirectParams);
            }
            audit_log('employee_status_updated', ['status' => $status], $employeeId);
            flash('success', $status === 'ACTIVE' ? 'Employee approved successfully.' : 'Employee marked inactive successfully.');
            redirect_to('admin_employees', $redirectParams);
            break;

        case 'admin_bulk_employee_action':
            $admin = require_power_team_access(['admin', 'freelancer', 'external_vendor']);
            $bulkAction = (string) ($_POST['bulk_action'] ?? '');
            $employeeIds = $_POST['employee_ids'] ?? [];
            $employeeIds = is_array($employeeIds) ? array_values(array_unique(array_filter(array_map('intval', $employeeIds)))) : [];
            $redirectParams = [];
            $returnType = (string) ($_POST['return_type'] ?? 'regular');
            if (in_array($returnType, ['regular', 'vendor', 'corporate'], true)) {
                $redirectParams['type'] = $returnType;
            }
            if (!empty($_POST['vendor_id'])) {
                $redirectParams['vendor_id'] = (int) $_POST['vendor_id'];
            }

            try {
                if ($employeeIds === []) {
                    throw new RuntimeException('Please select at least one employee.');
                }
                if (!in_array($bulkAction, ['approve', 'inactive', 'delete'], true)) {
                    throw new RuntimeException('Choose a bulk action.');
                }

                $processed = 0;
                $changed = 0;
                foreach ($employeeIds as $employeeId) {
                    $employee = employee_by_id($employeeId);
                    if (!$employee) {
                        continue;
                    }
                    if ((int) ($employee['admin_id'] ?? 0) !== (int) $admin['id']) {
                        continue;
                    }
                    if ((string) ($employee['role'] ?? '') === 'corporate_employee' || (string) ($employee['employee_type'] ?? '') === 'corporate') {
                        $redirectParams['type'] = 'corporate';
                    } elseif ((string) ($employee['employee_type'] ?? '') === 'vendor') {
                        $redirectParams['type'] = 'vendor';
                    }
                    $processed++;

                    if ($bulkAction === 'delete') {
                        $deleteStmt = db()->prepare('DELETE FROM users WHERE id = :id AND role = :role AND admin_id = :admin_id');
                        $deleteStmt->execute([
                            'id' => $employeeId,
                            'role' => (string) $employee['role'],
                            'admin_id' => (int) $admin['id'],
                        ]);
                        if ($deleteStmt->rowCount() > 0) {
                            audit_log('employee_deleted', ['bulk' => true], $employeeId);
                            $changed++;
                        }
                        continue;
                    }

                    $status = $bulkAction === 'approve' ? 'ACTIVE' : 'BLOCKED';
                    $setParts = ['status = :status'];
                    $params = [
                        'status' => $status,
                        'id' => $employeeId,
                        'admin_id' => (int) $admin['id'],
                    ];
                    if ($status === 'ACTIVE') {
                        $setParts[] = 'admin_id = COALESCE(NULLIF(admin_id, 0), :admin_id)';
                        $setParts[] = 'profile_status = "verified"';
                        $setParts[] = 'profile_rejection_reason = NULL';
                    }
                    $updateStmt = db()->prepare('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id AND role IN ("employee", "corporate_employee") AND (admin_id = :admin_id OR (role = "corporate_employee" AND (admin_id IS NULL OR admin_id = 0)))');
                    $updateStmt->execute($params);
                    if ($updateStmt->rowCount() > 0) {
                        audit_log('employee_status_updated', ['status' => $status, 'bulk' => true], $employeeId);
                        $changed++;
                    }
                }

                if ($processed < 1) {
                    throw new RuntimeException('No selected employees were updated.');
                }

                $actionLabel = $bulkAction === 'approve' ? 'approved' : ($bulkAction === 'inactive' ? 'marked inactive' : 'deleted');
                flash(
                    'success',
                    $processed === 1
                        ? 'Employee ' . $actionLabel . ' successfully.'
                        : $processed . ' employees ' . $actionLabel . ' successfully.'
                );
            } catch (Throwable $exception) {
                report_exception($exception, 'Bulk employee action failed.', [
                    'admin_id' => (int) $admin['id'],
                    'bulk_action' => $bulkAction,
                    'selected_count' => count($employeeIds),
                    'processed_count' => $processed ?? 0,
                    'changed_count' => $changed ?? 0,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to complete the selected employee action.');
            }

            redirect_to('admin_employees', $redirectParams);
            break;

        case 'admin_employee_project_assign':
            $admin = require_power_team_access(['admin', 'freelancer', 'external_vendor']);

            $employeeId = (int) ($_POST['user_id'] ?? 0);
            $employee = employee_by_id($employeeId);
            $returnType = (string) ($_POST['return_type'] ?? 'regular');
            $redirectParams = in_array($returnType, ['regular', 'vendor', 'corporate'], true) ? ['type' => $returnType] : [];
            if (!$employee) {
                flash('error', 'Team member not found for this administrator.');
                redirect_to('admin_employees', $redirectParams);
            }

            try {
                $projectIds = array_map('intval', $_POST['project_ids'] ?? []);
                $projectRanges = normalize_project_assignment_ranges(
                    $_POST['project_from'] ?? [],
                    $_POST['project_to'] ?? [],
                    $projectIds,
                    $_POST['project_incentive'] ?? [],
                    $_POST['project_daily_salary'] ?? []
                );
                save_employee_project_assignments($employeeId, $projectIds, $projectRanges);
                $confirmationSummary = publish_contractual_assignment_confirmations($employee, $admin, $projectIds, $projectRanges);
                audit_log('employee_project_assignment_updated', [
                    'project_ids' => $projectIds,
                    'confirmation_processed' => $confirmationSummary['processed'] ?? 0,
                    'confirmation_skipped' => $confirmationSummary['skipped'] ?? 0,
                ], $employeeId);
                $message = 'Project assignment saved successfully.';
                if (($confirmationSummary['processed'] ?? 0) > 0) {
                    $published = (int) ($confirmationSummary['processed'] ?? 0);
                    $message .= ' Project confirmation letter available in the contractual employee dashboard for ' . $published . ' project' . ($published === 1 ? '' : 's') . '.';
                    if (($confirmationSummary['skipped'] ?? 0) > 0) {
                        $message .= ' ' . (int) $confirmationSummary['skipped'] . ' skipped.';
                    }
                }
                flash('success', $message);
            } catch (Throwable $exception) {
                report_exception($exception, 'Team project assignment failed.', ['employee_id' => $employeeId]);
                flash('error', $exception->getMessage() ?: 'Unable to save project assignment.');
            }
            redirect_to('admin_employees', $redirectParams);
            break;

        case 'employee_delete':
            $admin = require_power_team_access(['admin', 'freelancer', 'external_vendor']);

            $employeeId = (int) ($_POST['user_id'] ?? 0);
            $employee = employee_by_id($employeeId);
            if (!$employee) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            $redirectParams = [];
            if ((string) ($employee['role'] ?? '') === 'corporate_employee' || (string) ($employee['employee_type'] ?? '') === 'corporate') {
                $redirectParams['type'] = 'corporate';
            } elseif ((string) ($employee['employee_type'] ?? '') === 'vendor') {
                $redirectParams['type'] = 'vendor';
            }
            $deleteStmt = db()->prepare('DELETE FROM users WHERE id = :id AND role = :role AND admin_id = :admin_id');
            $deleteStmt->execute([
                'id' => $employeeId,
                'role' => (string) $employee['role'],
                'admin_id' => (int) $admin['id'],
            ]);
            if ($deleteStmt->rowCount() < 1) {
                flash('error', 'Employee could not be deleted. Please refresh and try again.');
                redirect_to('admin_employees', $redirectParams);
            }
            audit_log('employee_deleted', [], $employeeId);
            flash('success', 'Employee deleted successfully.');
            redirect_to('admin_employees', $redirectParams);
            break;

        case 'project_save':
            $admin = require_power_projects_access(['admin']);

            $projectId = (int) ($_POST['project_id'] ?? 0);
            $projectKind = (string) ($_POST['project_kind'] ?? 'standard');
            $projectKind = in_array($projectKind, ['standard', 'contractual', 'vendor'], true) ? $projectKind : 'standard';
            $isContractualProject = $projectKind === 'contractual';
            $isVendorProject = $projectKind === 'vendor';
            $_SESSION['project_form'] = array_merge(project_form_defaults(), [
                'id' => $projectId,
                'project_kind' => $projectKind,
                'project_name' => trim((string) ($_POST['project_name'] ?? '')),
                'vendor_id' => max(0, (int) ($_POST['vendor_id'] ?? 0)),
                'vendor_name' => trim((string) ($_POST['vendor_name'] ?? '')),
                'college_name' => trim((string) ($_POST['college_name'] ?? '')),
                'location' => trim((string) ($_POST['location'] ?? '')),
                'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                'contractual_employee_ids' => $_POST['contractual_employee_ids'] ?? [],
                'contractual_project_from' => trim((string) ($_POST['contractual_project_from'] ?? '')),
                'contractual_project_to' => trim((string) ($_POST['contractual_project_to'] ?? '')),
                'contractual_daily_salary' => trim((string) ($_POST['contractual_daily_salary'] ?? '')),
                'contractual_pay_basis' => normalize_project_pay_basis($_POST['contractual_pay_basis'] ?? 'daily'),
            ]);

            try {
                if ($isContractualProject) {
                    $contractualEmployeeIds = array_values(array_filter(
                        array_map('intval', $_POST['contractual_employee_ids'] ?? []),
                        static fn (int $id): bool => $id > 0
                    ));
                    if ($contractualEmployeeIds === []) {
                        throw new RuntimeException('Select at least one contractual employee.');
                    }
                    if ((float) ($_POST['contractual_daily_salary'] ?? 0) <= 0) {
                        throw new RuntimeException('Hours must be greater than zero.');
                    }
                }
                $savedProjectId = save_project($_POST, $projectId > 0 ? $projectId : null);
                if ($isContractualProject) {
                    save_contractual_project_setup($savedProjectId, $_POST, (int) $admin['id']);
                }
                $savedProject = project_by_id($savedProjectId);
                unset($_SESSION['project_form']);
                audit_log($projectId > 0 ? 'project_updated' : 'project_created', [
                    'project_name' => (string) ($savedProject['project_name'] ?? ''),
                    'college_name' => (string) ($savedProject['college_name'] ?? ''),
                    'is_active' => (int) ($savedProject['is_active'] ?? 0),
                    'project_kind' => $projectKind,
                ], null);
                $successMessage = $projectId > 0 ? 'Project updated successfully.' : 'Project added successfully.';
                if ($isContractualProject) {
                    flash('success', 'Contractual project added successfully. Review the confirmation letter before publishing it to the contractual employee dashboard.');
                    redirect_to('admin_projects', ['stage' => 'contractual_confirm', 'project_id' => $savedProjectId]);
                } elseif ($isVendorProject) {
                    $successMessage = 'Vendor project added successfully.';
                }
                flash('success', $successMessage);
                redirect_to('admin_projects');
            } catch (Throwable $exception) {
                report_exception($exception, 'Project save failed.', ['project_id' => $projectId]);
                flash('error', $exception->getMessage() ?: 'Unable to save the project.');
                if ($projectId > 0) {
                    redirect_to('admin_projects', ['edit' => $projectId]);
                }
                $retryStage = $isContractualProject ? 'contractual_create' : ($isVendorProject ? 'vendor_create' : 'create');
                redirect_to('admin_projects', ['stage' => $retryStage]);
            }
            break;

        case 'contractual_project_send_confirmations':
            $admin = require_power_projects_access(['admin']);
            $projectId = (int) ($_POST['project_id'] ?? 0);
            try {
                $project = project_by_id($projectId, (int) $admin['id']);
                if (!$project) {
                    throw new RuntimeException('Project not found.');
                }
                $assignmentStmt = db()->prepare("SELECT COUNT(*) FROM employee_project_assignments a
                    INNER JOIN users u ON u.id = a.user_id
                    WHERE a.project_id = :project_id
                      AND u.admin_id = :admin_id
                      AND (u.role = 'corporate_employee' OR u.employee_type = 'corporate')");
                $assignmentStmt->execute([
                    'project_id' => $projectId,
                    'admin_id' => (int) $admin['id'],
                ]);
                $processedLetters = (int) $assignmentStmt->fetchColumn();
                if ($processedLetters <= 0) {
                    throw new RuntimeException('No contractual employees are assigned to this project.');
                }

                $message = 'Project confirmation letter is available in the contractual employee dashboard for ' . $processedLetters . ' contractual employee' . ($processedLetters === 1 ? '' : 's') . '.';
                audit_log('contractual_project_confirmation_published', [
                    'project_id' => $projectId,
                    'processed' => $processedLetters,
                ], null);
                flash('success', $message);
            } catch (Throwable $exception) {
                report_exception($exception, 'Contractual project confirmation publish failed.', ['project_id' => $projectId]);
                flash('error', $exception->getMessage() ?: 'Unable to publish the confirmation letter.');
                if ($projectId > 0) {
                    redirect_to('admin_projects', ['stage' => 'contractual_confirm', 'project_id' => $projectId]);
                }
            }
            redirect_to('admin_projects');
            break;

        case 'project_delete':
            require_power_projects_access(['admin']);

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

        case 'employee_project_create':
            $employee = require_roles(['employee', 'corporate_employee']);
            $isProjectCoordinator = employee_is_project_coordinator($employee);
            if (!employee_is_in_house_trainer($employee) && !$isProjectCoordinator) {
                flash('error', 'Only in-house trainers and project coordinators can create projects.');
                redirect_to('employee_attendance');
            }

            $pdo = db();
            $manageTransaction = !$pdo->inTransaction();
            try {
                if ($manageTransaction) {
                    $pdo->beginTransaction();
                }

                $projectId = save_project(array_merge($_POST, [
                    'is_active' => 0,
                    'defer_project_code' => 1,
                ]), null);
                mark_project_pending_verification($projectId, (int) $employee['id']);
                $projectRange = [
                    'from' => (string) ($_POST['project_from'] ?? ''),
                    'to' => (string) ($_POST['project_to'] ?? ''),
                    'incentive' => (float) ($_POST['project_incentive'] ?? 0),
                    'daily_salary' => (float) ($_POST['project_daily_salary'] ?? 0),
                ];
                if ($isProjectCoordinator) {
                    $assignmentIds = normalize_project_coordinator_assignment_ids($_POST['project_employee_ids'] ?? [], $employee);
                    foreach ($assignmentIds as $assignmentId) {
                        save_employee_project_assignment($assignmentId, $projectId, $projectRange, true);
                    }
                } else {
                    save_employee_project_assignment((int) $employee['id'], $projectId, $projectRange, true);
                }
                $project = project_by_id($projectId);
                audit_log('employee_project_created', [
                    'project_id' => $projectId,
                    'project_name' => (string) ($project['project_name'] ?? ''),
                    'college_name' => (string) ($project['college_name'] ?? ''),
                    'assigned_employee_count' => isset($assignmentIds) ? count($assignmentIds) : 1,
                ], (int) $employee['id'], $employee);
                if (!empty($employee['admin_id'])) {
                    create_notification_entry(
                        (int) $employee['admin_id'],
                        'Project verification needed',
                        (string) ($employee['name'] ?? 'Trainer') . ' submitted "' . (string) ($project['project_name'] ?? 'Project') . '" for verification.',
                        'info',
                        'project',
                        $projectId,
                        (int) $employee['id']
                    );
                }

                if ($manageTransaction) {
                    $pdo->commit();
                }

                flash('success', 'Project submitted for admin verification.');
            } catch (Throwable $exception) {
                if ($manageTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                report_exception($exception, 'Trainer project create failed.', [
                    'employee_id' => (int) $employee['id'],
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to create project.');
            }
            redirect_to('employee_projects');
            break;

        case 'project_verify':
            require_power_projects_access(['admin']);

            $projectId = (int) ($_POST['project_id'] ?? 0);
            try {
                $project = verify_project($projectId);
                audit_log('project_verified', [
                    'project_name' => (string) ($project['project_name'] ?? ''),
                    'college_name' => (string) ($project['college_name'] ?? ''),
                ], null);
                if (!empty($project['created_by_user_id'])) {
                    create_notification_entry(
                        (int) $project['created_by_user_id'],
                        'Project verified',
                        '"' . (string) ($project['project_name'] ?? 'Project') . '" has been verified by admin.',
                        'success',
                        'project',
                        (int) ($project['id'] ?? $projectId),
                        (int) (current_user()['id'] ?? 0)
                    );
                }
                flash('success', 'Project verified successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Project verification failed.', ['project_id' => $projectId]);
                flash('error', $exception->getMessage() ?: 'Unable to verify the project.');
            }
            redirect_to('admin_projects');
            break;

        case 'project_toggle_active':
            require_power_projects_access(['admin']);

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

        case 'admin_project_trainers_save':
            $admin = require_power_projects_access(['admin']);
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $selectedTrainerIds = array_values(array_unique(array_filter(
                array_map('intval', $_POST['employee_ids'] ?? []),
                static fn(int $employeeId): bool => $employeeId > 0
            )));

            try {
                $project = project_by_id($projectId, (int) $admin['id']);
                if (!$project) {
                    throw new RuntimeException('Project not found.');
                }

                $validTrainerIds = [];
                foreach (project_assignable_employees() as $employee) {
                    $validTrainerIds[(int) ($employee['id'] ?? 0)] = true;
                }
                foreach ($selectedTrainerIds as $trainerId) {
                    if (!isset($validTrainerIds[$trainerId])) {
                        throw new RuntimeException('Select only valid trainers.');
                    }
                }

                $pdo = db();
                $pdo->beginTransaction();
                $pdo->prepare("DELETE a FROM employee_project_assignments a
                    INNER JOIN users u ON u.id = a.user_id
                    WHERE a.project_id = :project_id
                      AND u.admin_id = :admin_id
                      AND u.role IN ('employee', 'corporate_employee')")
                    ->execute([
                        'project_id' => $projectId,
                        'admin_id' => (int) $admin['id'],
                    ]);
                foreach ($selectedTrainerIds as $trainerId) {
                    save_employee_project_assignment($trainerId, $projectId, [], true);
                }
                $pdo->commit();

                audit_log('project_trainers_assigned', [
                    'project_id' => $projectId,
                    'trainer_count' => count($selectedTrainerIds),
                ], $projectId);
                flash('success', 'Assigned trainers updated successfully.');
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                report_exception($exception, 'Project trainer assignment failed.', ['project_id' => $projectId]);
                flash('error', $exception->getMessage() ?: 'Unable to update assigned trainers.');
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
                $shiftFrom = normalize_rule_date_value($_POST['shift_from'] ?? ($_POST['shift_date'] ?? date('Y-m-d')));
                $shiftTo = normalize_rule_date_value($_POST['shift_to'] ?? $shiftFrom);
                [$shiftFrom, $shiftTo] = ordered_rule_date_range($shiftFrom, $shiftTo);

                if ($startTime === '' || $endTime === '') {
                    throw new RuntimeException('Start time and end time are required.');
                }
                if ($shiftFrom === '') {
                    $shiftFrom = date('Y-m-d');
                }
                if ($shiftTo === '') {
                    $shiftTo = $shiftFrom;
                }

                if ($startTime === $endTime) {
                    throw new RuntimeException('Start time and end time must be different.');
                }

                add_shift_timing([
                    'shift_from' => $shiftFrom,
                    'shift_to' => $shiftTo,
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

        case 'vendor_project_unassign':
            require_role('external_vendor');
            $employeeId = (int) ($_POST['user_id'] ?? 0);
            $projectId = (int) ($_POST['project_id'] ?? 0);
            try {
                $employee = employee_by_id($employeeId);
                if (!$employee || $projectId <= 0) {
                    throw new RuntimeException('Project assignment not found.');
                }
                db()->prepare('DELETE FROM employee_project_assignments WHERE user_id = :user_id AND project_id = :project_id')
                    ->execute([
                        'user_id' => $employeeId,
                        'project_id' => $projectId,
                    ]);
                flash('success', 'Project removed from employee.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Vendor project unassign failed.', [
                    'employee_id' => $employeeId,
                    'project_id' => $projectId,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to remove project assignment.');
            }
            redirect_to('admin_projects');
            break;

        case 'apply_rules':
            $rulesManager = require_roles(['admin', 'external_vendor']);
            $ids = array_map('intval', $_POST['employee_ids'] ?? []);
            $allocationType = (string) ($_POST['allocation_type'] ?? 'all');
            $powerAccessAction = (string) ($_POST['power_access_action'] ?? 'save');
            $removePowerAccess = $allocationType === 'power' && $powerAccessAction === 'remove';
            if (($rulesManager['role'] ?? '') === 'external_vendor' && $allocationType !== 'project') {
                flash('error', 'Vendors can only assign projects.');
                redirect_to('admin_projects');
            }
            if (!in_array($allocationType, ['all', 'time', 'project', 'power'], true)) {
                $allocationType = 'all';
            }
            $rules = normalize_rules_from_input($_POST);
            if (!$ids) {
                flash('error', 'Select at least one employee.');
                redirect_to(($rulesManager['role'] ?? '') === 'external_vendor' ? 'admin_projects' : 'admin_rules');
            }
            $projectIds = array_map('intval', $_POST['project_ids'] ?? []);
            $projectRanges = normalize_project_assignment_ranges($_POST['project_from'] ?? [], $_POST['project_to'] ?? [], $projectIds, $_POST['project_incentive'] ?? [], $_POST['project_daily_salary'] ?? []);
            $updated = 0;
            foreach ($ids as $id) {
                $employee = employee_by_id($id);
                if (!$employee) {
                    continue;
                }
                $currentRules = $rules;
                if ($allocationType === 'power') {
                    $existingRules = employee_rules((int) $employee['id']);
                    $powerKeys = [
                        'power_access',
                        'power_attendance_employee',
                        'power_attendance_trainer',
                        'power_attendance_freelancer',
                        'power_attendance_vendor',
                        'power_attendance_vendor_trainer',
                        'power_team_employee',
                        'power_team_freelancer',
                        'power_team_vendor',
                        'power_projects',
                        'power_accounts',
                        'power_accounts_verify',
                        'power_accounts_pay',
                        'power_accounts_history',
                    ];
                    foreach ($powerKeys as $powerKey) {
                        $existingRules[$powerKey] = $removePowerAccess ? false : !empty($rules[$powerKey]);
                    }
                    save_employee_rules((int) $employee['id'], $existingRules);
                    $currentRules = $existingRules;
                }
                if ($allocationType === 'time' || $allocationType === 'all') {
                    $resolvedShift = resolve_shift_selection_from_input($_POST, (string) ($employee['shift'] ?? ''), true);
                    if ($resolvedShift !== '') {
                        db()->prepare('UPDATE users SET shift = :shift WHERE id = :id AND admin_id = :admin_id')
                            ->execute([
                                'shift' => $resolvedShift,
                                'id' => (int) $employee['id'],
                                'admin_id' => (int) $rulesManager['id'],
                            ]);
                        $employee['shift'] = $resolvedShift;
                    }
                    if ($allocationType === 'time') {
                        $existingRules = employee_rules((int) $employee['id']);
                        foreach (['project_session_from', 'project_session_to', 'shift_from', 'shift_to', 'employee_from', 'employee_to'] as $timeKey) {
                            $existingRules[$timeKey] = (string) ($rules[$timeKey] ?? '');
                        }
                        save_employee_rules((int) $employee['id'], $existingRules);
                        $currentRules = $existingRules;
                    } else {
                        save_employee_rules((int) $employee['id'], $rules);
                        $currentRules = $rules;
                    }
                }
                if ($allocationType === 'project' || $allocationType === 'all') {
                    save_employee_project_assignments((int) $employee['id'], $projectIds, $projectRanges);
                    if ($allocationType === 'project') {
                        $currentRules = employee_rules((int) $employee['id']);
                    }
                }
                send_rules_updated_email($employee, $currentRules, $allocationType !== 'time');
                $updated++;
            }
            audit_log('employee_rules_updated_bulk', [
                'employee_count' => $updated,
                'allocation_type' => $allocationType,
                'rules' => $rules,
            ]);
            $successMessage = match ($allocationType) {
                'project' => 'Project allocation saved successfully.',
                'power' => $removePowerAccess ? 'Power access removed successfully.' : 'Power access saved successfully.',
                default => 'Time allocation saved successfully.',
            };
            flash($updated > 0 ? 'success' : 'error', $updated > 0 ? $successMessage : 'No employees were available for this administrator.');
            redirect_to(($rulesManager['role'] ?? '') === 'external_vendor' ? 'admin_projects' : 'admin_rules');
            break;

        case 'admin_attendance_csv_upload':
            require_power_attendance_access(['admin']);
            $returnPage = in_array((string) ($_POST['return_page'] ?? ''), ['admin_attendance', 'admin_employee_log'], true)
                ? (string) $_POST['return_page']
                : 'admin_employee_log';
            $returnType = in_array((string) ($_POST['return_type'] ?? 'employee'), ['employee', 'trainer', 'freelancer', 'vendor', 'vendor_trainer', 'regular', 'corporate'], true)
                ? (string) $_POST['return_type']
                : 'employee';
            $returnType = ['regular' => 'employee', 'corporate' => 'freelancer'][$returnType] ?? $returnType;
            $returnView = in_array((string) ($_POST['return_view'] ?? 'attendance'), ['attendance', 'reimbursement'], true)
                ? (string) $_POST['return_view']
                : 'attendance';
            $returnArgs = [
                'type' => $returnType,
                'view' => $returnView,
            ];
            $returnEmployeeId = (int) ($_POST['return_employee_id'] ?? 0);
            if ($returnEmployeeId > 0) {
                $returnArgs['employee_id'] = $returnEmployeeId;
            }
            $returnMonth = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['return_month'] ?? '')) ? (string) $_POST['return_month'] : '';
            if ($returnMonth !== '') {
                $returnArgs['month'] = $returnMonth;
            }
            $returnVendorId = (int) ($_POST['return_vendor_id'] ?? 0);
            if ($returnType === 'vendor' && $returnVendorId > 0) {
                $returnArgs['vendor_id'] = $returnVendorId;
            }
            try {
                validate_attendance_report_upload($_FILES['attendance_csv'] ?? []);
                $result = import_attendance_report_csv((string) ($_FILES['attendance_csv']['tmp_name'] ?? ''), trim((string) ($_POST['attendance_date'] ?? '')), (string) ($_FILES['attendance_csv']['name'] ?? ''));
                $importDates = array_values(array_filter((array) ($result['dates'] ?? []), static fn ($date): bool => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1));
                if ($returnMonth === '' && $importDates !== []) {
                    $returnArgs['month'] = substr((string) $importDates[0], 0, 7);
                } elseif ($returnMonth === '' && !empty($result['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $result['date'])) {
                    $returnArgs['month'] = substr((string) $result['date'], 0, 7);
                }
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
                if (!empty($result['ambiguous'])) {
                    $message .= ' | Duplicate Empcode Matches: ' . implode(', ', $result['ambiguous']);
                }
                audit_log('attendance_import_completed', [
                    'filename' => (string) ($_FILES['attendance_csv']['name'] ?? ''),
                    'date' => $result['date'] ?? null,
                    'imported' => (int) $result['imported'],
                    'skipped' => (int) ($result['skipped'] ?? 0),
                    'unmatched' => $result['unmatched'] ?? [],
                    'ambiguous' => $result['ambiguous'] ?? [],
                ]);
                flash('success', $message);
            } catch (Throwable $exception) {
                report_exception($exception, 'Attendance import failed.', [
                    'filename' => (string) ($_FILES['attendance_csv']['name'] ?? ''),
                ]);
                flash('error', $exception->getMessage());
            }
            redirect_to($returnPage, $returnArgs);
            break;

        case 'admin_etime_attendance_sync':
            require_power_attendance_access(['admin']);
            $returnPage = in_array((string) ($_POST['return_page'] ?? ''), ['admin_attendance', 'admin_employee_log'], true)
                ? (string) $_POST['return_page']
                : 'admin_employee_log';
            $returnType = in_array((string) ($_POST['return_type'] ?? 'employee'), ['employee', 'trainer', 'freelancer', 'vendor', 'vendor_trainer', 'regular', 'corporate'], true)
                ? (string) $_POST['return_type']
                : 'employee';
            $returnType = ['regular' => 'employee', 'corporate' => 'freelancer'][$returnType] ?? $returnType;
            $returnView = in_array((string) ($_POST['return_view'] ?? 'attendance'), ['attendance', 'reimbursement'], true)
                ? (string) $_POST['return_view']
                : 'attendance';
            $returnArgs = [
                'type' => $returnType,
                'view' => $returnView,
            ];
            $returnEmployeeId = (int) ($_POST['return_employee_id'] ?? 0);
            if ($returnEmployeeId > 0) {
                $returnArgs['employee_id'] = $returnEmployeeId;
            }
            $returnMonth = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['return_month'] ?? '')) ? (string) $_POST['return_month'] : '';
            if ($returnMonth !== '') {
                $returnArgs['month'] = $returnMonth;
            }
            $returnVendorId = (int) ($_POST['return_vendor_id'] ?? 0);
            if ($returnType === 'vendor' && $returnVendorId > 0) {
                $returnArgs['vendor_id'] = $returnVendorId;
            }

            $fromDate = trim((string) ($_POST['etime_from_date'] ?? ''));
            $toDate = trim((string) ($_POST['etime_to_date'] ?? ''));
            $empCode = trim((string) ($_POST['etime_empcode'] ?? 'ALL'));
            $password = (string) ($_POST['etime_password'] ?? '');
            try {
                $result = sync_etime_inout_attendance($fromDate, $toDate, $empCode, $password);
                if ($returnMonth === '' && !empty($result['dates'][0])) {
                    $returnArgs['month'] = substr((string) $result['dates'][0], 0, 7);
                }

                $message = 'eTime Office sync completed. Imported: ' . (int) $result['imported'];
                if (!empty($result['skipped'])) {
                    $message .= ' | Skipped: ' . (int) $result['skipped'];
                }
                if (!empty($result['unmatched'])) {
                    $message .= ' | Unmatched Employees: ' . implode(', ', $result['unmatched']);
                }

                audit_log('etime_attendance_sync_completed', [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'empcode' => $empCode,
                    'imported' => (int) $result['imported'],
                    'skipped' => (int) ($result['skipped'] ?? 0),
                    'unmatched' => $result['unmatched'] ?? [],
                ]);
                flash('success', $message);
            } catch (Throwable $exception) {
                report_exception($exception, 'eTime Office sync failed.', [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'empcode' => $empCode,
                ]);
                flash('error', $exception->getMessage());
            }
            redirect_to($returnPage, $returnArgs);
            break;

        case 'admin_set_status':
            $marker = require_roles(['admin', 'employee', 'corporate_employee']);
            $isHrMarker = employee_is_hr_reviewer($marker);
            if (($marker['role'] ?? '') !== 'admin' && !$isHrMarker) {
                flash('error', 'Only admin or HR can manually mark attendance.');
                redirect_to(home_page_for_user($marker));
            }
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            if (($marker['role'] ?? '') === 'admin') {
                $employee = employee_by_id($employeeId);
            } else {
                $stmt = db()->prepare("SELECT * FROM users WHERE id = :id AND role IN ('employee', 'corporate_employee') AND admin_id = :admin_id");
                $stmt->execute([
                    'id' => $employeeId,
                    'admin_id' => (int) ($marker['admin_id'] ?? 0),
                ]);
                $employee = $stmt->fetch() ?: null;
            }
            if (!$employee) {
                flash('error', 'Employee not found.');
                redirect_to(($marker['role'] ?? '') === 'admin' ? 'admin_attendance' : 'employee_log');
            }
            $status = (string) ($_POST['status'] ?? 'Absent');
            if (!in_array($status, ['Present', 'Absent', 'Half Day', 'Leave', 'Week Off'], true)) {
                $status = 'Absent';
            }
            update_attendance_record((int) $employee['id'], (string) ($_POST['attend_date'] ?? ''), [
                'status' => $status,
                'admin_override_status' => $status,
                'admin_override_by_user_id' => (int) ($marker['id'] ?? 0),
                'admin_override_by_name' => (string) ($marker['name'] ?? 'User'),
                'admin_override_at' => now(),
            ]);
            audit_log('attendance_status_overridden', [
                'attend_date' => (string) ($_POST['attend_date'] ?? ''),
                'status' => $status,
            ], (int) $employee['id']);
            flash('success', 'Attendance status updated.');
            $redirectMonth = substr((string) ($_POST['attend_date'] ?? date('Y-m-d')), 0, 7);
            if (($marker['role'] ?? '') === 'admin') {
                redirect_to('admin_attendance', [
                    'employee_id' => (int) $employee['id'],
                    'month' => $redirectMonth,
                ]);
            }
            redirect_to('employee_log', ['month' => $redirectMonth]);
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
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $companyName = trim((string) ($_POST['company_name'] ?? ''));
            $companyAddress = trim((string) ($_POST['company_address'] ?? ''));
            $companyEmail = trim((string) ($_POST['company_email'] ?? ''));
            $companyPhone = trim((string) ($_POST['company_phone'] ?? ''));
            $representativeName = trim((string) ($_POST['representative_name'] ?? ''));
            $designation = trim((string) ($_POST['designation'] ?? ''));
            $personalEmail = trim((string) ($_POST['personal_email'] ?? ''));
            $personalPhone = trim((string) ($_POST['personal_phone'] ?? ''));
            $gstNo = strtoupper(trim((string) ($_POST['gst_no'] ?? '')));
            $panNo = strtoupper(trim((string) ($_POST['pan_no'] ?? '')));
            $bankAccountNo = trim((string) ($_POST['bank_account_no'] ?? ''));
            $bankIfscCode = strtoupper(trim((string) ($_POST['bank_ifsc_code'] ?? '')));
            $bankBranch = trim((string) ($_POST['bank_branch'] ?? ''));
            $bankName = trim((string) ($_POST['bank_name'] ?? ''));

            if ($isVendorProfile) {
                $name = $companyName !== '' ? $companyName : trim((string) ($admin['name'] ?? ''));
                if ($email === '') {
                    $email = $companyEmail !== '' ? $companyEmail : (string) ($admin['email'] ?? '');
                }
                if ($phone === '') {
                    $phone = $companyPhone !== '' ? $companyPhone : (string) ($admin['phone'] ?? '');
                }
            }

            if ($name === '') {
                flash('error', 'Name is required.');
                redirect_to($returnPage);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid email address.');
                redirect_to($returnPage);
            }
            if ($isVendorProfile) {
                $requiredVendorFields = [
                    'Company name' => $companyName,
                    'Company address' => $companyAddress,
                    'Company mail' => $companyEmail,
                    'Company phone number' => $companyPhone,
                    'Your name' => $representativeName,
                    'Your designation' => $designation,
                    'Personal number' => $personalPhone,
                    'Personal mail' => $personalEmail,
                ];
                foreach ($requiredVendorFields as $label => $value) {
                    if (trim($value) === '') {
                        flash('error', $label . ' is required.');
                        redirect_to($returnPage);
                    }
                }
                if (!filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
                    flash('error', 'Enter a valid company mail.');
                    redirect_to($returnPage);
                }
                if (!filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
                    flash('error', 'Enter a valid personal mail.');
                    redirect_to($returnPage);
                }
            }

            try {
                if ($isVendorProfile) {
                    $fileUpdates = [];
                    foreach (['bank_proof', 'company_logo', 'profile_photo'] as $field) {
                        $file = $_FILES[$field] ?? [];
                        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                            if (trim((string) ($admin[$field . '_path'] ?? '')) === '') {
                                flash('error', ucwords(str_replace('_', ' ', $field)) . ' is required.');
                                redirect_to($returnPage);
                            }
                            continue;
                        }
                        $stored = store_vendor_profile_upload($file, (int) $admin['id'], $field);
                        $fileUpdates[$field . '_path'] = $stored['path'];
                        $fileUpdates[$field . '_name'] = $stored['name'];
                    }

                    $updates = [
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'company_name' => $companyName,
                        'company_address' => $companyAddress,
                        'company_email' => $companyEmail,
                        'company_phone' => $companyPhone,
                        'representative_name' => $representativeName,
                        'designation' => $designation,
                        'personal_email' => $personalEmail,
                        'personal_phone' => $personalPhone,
                        'gst_no' => $gstNo,
                        'pan_no' => $panNo,
                        'bank_account_no' => $bankAccountNo,
                        'bank_ifsc_code' => $bankIfscCode,
                        'bank_branch' => $bankBranch,
                        'bank_name' => $bankName,
                    ] + $fileUpdates;
                    $setParts = [];
                    foreach (array_keys($updates) as $field) {
                        $setParts[] = $field . ' = :' . $field;
                    }
                    $updates['id'] = (int) $admin['id'];
                    $updates['role'] = $admin['role'];
                    db()->prepare('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id AND role = :role')
                        ->execute($updates);
                } else {
                    $updates = [
                        'id' => (int) $admin['id'],
                        'role' => $admin['role'],
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                    ];
                    $profilePhotoFile = $_FILES['profile_photo'] ?? [];
                    if ((int) ($profilePhotoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $stored = store_vendor_profile_upload($profilePhotoFile, (int) $admin['id'], 'profile_photo');
                        $updates['profile_photo_path'] = $stored['path'];
                        $updates['profile_photo_name'] = $stored['name'];
                    }

                    $setParts = ['name = :name', 'email = :email', 'phone = :phone'];
                    if (isset($updates['profile_photo_path'])) {
                        $setParts[] = 'profile_photo_path = :profile_photo_path';
                        $setParts[] = 'profile_photo_name = :profile_photo_name';
                    }
                    db()->prepare('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id AND role = :role')
                        ->execute($updates);
                }
                audit_log('admin_profile_updated', [
                    'email' => $email,
                ], (int) $admin['id']);
                flash('success', 'Profile updated successfully.');
            } catch (RuntimeException $exception) {
                flash('error', $exception->getMessage());
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin profile update failed.', ['admin_id' => (int) $admin['id']]);
                flash('error', 'Unable to update profile.');
            }
            redirect_to($returnPage);
            break;

        case 'admin_biometric_integration_save':
            $admin = require_role('admin');
            $returnPage = (string) ($_POST['return_page'] ?? 'admin_profile_settings');
            if (!str_starts_with($returnPage, 'admin_') || $returnPage === 'admin_profile_settings') {
                $returnPage = 'admin_profile_settings';
            }
            $mode = (string) ($_POST['integration_mode'] ?? 'save');
            $existing = biometric_integration_for_admin((int) $admin['id']);
            $payload = [
                'base_url' => trim((string) ($_POST['base_url'] ?? '')),
                'corporate_id' => trim((string) ($_POST['corporate_id'] ?? '')),
                'username' => trim((string) ($_POST['username'] ?? '')),
                'password' => (string) ($_POST['password'] ?? ''),
                'is_enabled' => !empty($_POST['is_enabled']),
            ];
            try {
                if ($mode === 'test') {
                    $password = trim((string) $payload['password']);
                    if ($password === '' && $existing) {
                        $password = (string) ($existing['password'] ?? '');
                    }
                    $testConfig = etime_config(null, [
                        'base_url' => $payload['base_url'] !== '' ? $payload['base_url'] : 'https://api.etimeoffice.com/api/',
                        'corporate_id' => $payload['corporate_id'],
                        'username' => $payload['username'],
                        'password' => $password,
                        'timeout' => 15,
                    ]);
                    etime_request_json('DownloadInOutPunchData', [
                        'Empcode' => 'ALL',
                        'FromDate' => etime_api_date(date('Y-m-d')),
                        'ToDate' => etime_api_date(date('Y-m-d')),
                    ], null, $testConfig);
                    if ($existing) {
                        db()->prepare("UPDATE biometric_integrations SET last_test_at = :last_test_at WHERE admin_id = :admin_id AND provider = 'etime_office'")
                            ->execute(['last_test_at' => now(), 'admin_id' => (int) $admin['id']]);
                    }
                    flash('success', 'eTime Office connection verified successfully.');
                    redirect_to($returnPage);
                }

                $saved = save_biometric_integration_for_admin((int) $admin['id'], $payload, $existing['password'] ?? null);
                audit_log('biometric_integration_saved', [
                    'provider' => 'etime_office',
                    'enabled' => !empty($saved['is_enabled']),
                ], (int) $admin['id']);
                flash('success', 'Biometric integration saved successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Biometric integration update failed.', [
                    'admin_id' => (int) $admin['id'],
                    'mode' => $mode,
                ]);
                flash('error', $exception->getMessage());
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

        case 'employee_profile_submit':
            $employee = require_roles(['employee', 'corporate_employee']);
            try {
                [$payload, $documentValues] = employee_profile_save_payload($employee);
                $changedLabels = employee_profile_change_labels($employee, $payload, $documentValues);
                $nextStatus = 'pending';
                $setParts = [];
                foreach (array_keys(array_merge($payload, $documentValues)) as $field) {
                    $setParts[] = $field . ' = :' . $field;
                }
                $setParts[] = 'profile_status = :profile_status';
                $setParts[] = 'profile_rejection_reason = NULL';
                $setParts[] = 'profile_changed_fields_json = :profile_changed_fields_json';
                $setParts[] = 'profile_changed_at = :profile_changed_at';
                db()->prepare('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id AND role = :role')
                    ->execute(array_merge($payload, $documentValues, [
                        'profile_status' => $nextStatus,
                        'profile_changed_fields_json' => json_encode($changedLabels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'profile_changed_at' => now(),
                        'id' => (int) $employee['id'],
                        'role' => (string) $employee['role'],
                    ]));

                notify_profile_reviewers(array_merge($employee, $payload, ['profile_status' => $nextStatus]));
                audit_log('employee_profile_submitted', [], (int) $employee['id'], $employee);
                flash('success', 'Profile submitted for admin verification.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee profile submission failed.', ['employee_id' => (int) $employee['id']]);
                flash('error', $exception->getMessage() ?: 'Unable to submit profile verification.');
            }
            redirect_to(home_page_for_user($employee));
            break;

        case 'employee_profile_update':
            $employee = require_roles(['employee', 'corporate_employee']);
            try {
                [$payload, $documentValues] = employee_profile_save_payload($employee);
                $changedLabels = employee_profile_change_labels($employee, $payload, $documentValues);
                $nextStatus = 'pending';
                $setParts = [];
                foreach (array_keys(array_merge($payload, $documentValues)) as $field) {
                    $setParts[] = $field . ' = :' . $field;
                }
                $setParts[] = 'profile_status = :profile_status';
                $setParts[] = 'profile_rejection_reason = NULL';
                $setParts[] = 'profile_changed_fields_json = :profile_changed_fields_json';
                $setParts[] = 'profile_changed_at = :profile_changed_at';
                db()->prepare('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id AND role = :role')
                    ->execute(array_merge($payload, $documentValues, [
                        'profile_status' => $nextStatus,
                        'profile_changed_fields_json' => json_encode($changedLabels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'profile_changed_at' => now(),
                        'id' => (int) $employee['id'],
                        'role' => (string) $employee['role'],
                    ]));

                notify_employee_profile_updated(array_merge($employee, $payload), $changedLabels);
                flash('success', 'Profile updated and sent for admin verification.');
                audit_log('employee_profile_updated', [
                    'profile_status' => $nextStatus,
                    'changed_fields' => $changedLabels,
                ], (int) $employee['id'], $employee);
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee profile update failed.', ['employee_id' => (int) $employee['id']]);
                flash('error', $exception->getMessage() ?: 'Unable to update profile.');
            }
            redirect_to('employee_profile');
            break;

        case 'employee_offer_letter_update':
            $employee = require_roles(['employee', 'corporate_employee']);
            try {
                throw new RuntimeException('Offer letter form is not available.');
                if ((string) ($employee['profile_status'] ?? '') !== 'verified') {
                    throw new RuntimeException('Offer letter is available after profile verification.');
                }

                $offerName = trim((string) ($_POST['offer_letter_name'] ?? ''));
                $offerAddress = trim((string) ($_POST['offer_letter_address'] ?? ''));
                $offerDesignation = trim((string) ($_POST['offer_letter_designation'] ?? ''));
                if ($offerName === '' || $offerAddress === '' || $offerDesignation === '') {
                    throw new RuntimeException('Name, address, and designation are required for the offer letter.');
                }

                $values = [
                    'offer_letter_name' => $offerName,
                    'offer_letter_address' => $offerAddress,
                    'offer_letter_designation' => $offerDesignation,
                    'id' => (int) $employee['id'],
                    'role' => (string) $employee['role'],
                ];
                $setParts = [
                    'offer_letter_name = :offer_letter_name',
                    'offer_letter_address = :offer_letter_address',
                    'offer_letter_designation = :offer_letter_designation',
                ];

                $file = $_FILES['offer_letter_signature'] ?? [];
                if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $stored = store_employee_offer_signature_upload($file, (int) $employee['id']);
                    $values['offer_letter_signature_path'] = $stored['path'];
                    $values['offer_letter_signature_name'] = $stored['name'];
                    $setParts[] = 'offer_letter_signature_path = :offer_letter_signature_path';
                    $setParts[] = 'offer_letter_signature_name = :offer_letter_signature_name';
                } elseif (trim((string) ($employee['offer_letter_signature_path'] ?? '')) === '') {
                    throw new RuntimeException('Signature image is required for the offer letter.');
                }

                db()->prepare('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id AND role = :role')
                    ->execute($values);
                audit_log('employee_offer_letter_updated', [], (int) $employee['id'], $employee);
                $stmt = db()->prepare("SELECT * FROM users WHERE id = :id AND role IN ('employee', 'corporate_employee') LIMIT 1");
                $stmt->execute(['id' => (int) $employee['id']]);
                $updatedEmployee = $stmt->fetch();
                if (!$updatedEmployee) {
                    throw new RuntimeException('Unable to reload offer letter details.');
                }

                stream_employee_offer_letter_pdf($updatedEmployee, employee_offer_letter_employer_name($updatedEmployee));
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee offer letter update failed.', ['employee_id' => (int) $employee['id']]);
                flash('error', $exception->getMessage() ?: 'Unable to update offer letter.');
                redirect_to('employee_profile');
            }
            break;

        case 'employee_project_record':
            $employee = require_roles(['employee', 'corporate_employee']);
            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));

            try {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new RuntimeException('Choose a valid attendance date.');
                }
                if ($date > date('Y-m-d')) {
                    throw new RuntimeException('Future dates are not allowed for attendance.');
                }
                if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                    throw new RuntimeException('Week Off dates do not require attendance.');
                }

                $dateProjects = employee_available_projects_for_date($employee, $date);
                $allowedProjects = [];
                foreach ($dateProjects as $project) {
                    $allowedProjects[(int) ($project['id'] ?? 0)] = $project;
                }
                $projectId = (int) ($_POST['project_id'] ?? 0);
                $selectedProject = $allowedProjects[$projectId] ?? null;
                if (!$selectedProject) {
                    throw new RuntimeException('Select an assigned project available for this date.');
                }

                $collegeName = trim((string) ($_POST['college_name'] ?? ''));
                $sessionName = trim((string) ($_POST['session_name'] ?? ''));
                $topicsHandled = trim((string) ($_POST['topics_handled'] ?? ''));
                $totalStudents = (int) ($_POST['total_students'] ?? 0);
                $presentStudents = (int) ($_POST['present_students'] ?? 0);
                $dayPortion = trim((string) ($_POST['day_portion'] ?? 'Full Day'));
                if (!in_array($dayPortion, ['Full Day', 'Half Day'], true)) {
                    $dayPortion = 'Full Day';
                }
                $location = trim((string) ($_POST['location'] ?? ''));
                if ($location === '') {
                    $location = trim((string) ($selectedProject['location'] ?? ''));
                }
                if ($collegeName === '' || $sessionName === '' || $topicsHandled === '' || $totalStudents <= 0 || $presentStudents < 0) {
                    throw new RuntimeException('Project record requires College Name, Subject, Topics Handled, Total Students, Present, and GPS Photo.');
                }
                if ($presentStudents > $totalStudents) {
                    throw new RuntimeException('Present students cannot be more than total students.');
                }
                $projectSlotLabel = trim((string) ($selectedProject['project_name'] ?? '')) ?: 'Project';
                $slotName = 'Project #' . $projectId . ': ' . $projectSlotLabel;
                $record = ensure_attendance_record((int) $employee['id'], $date);
                $existingSession = attendance_session_by_slot((int) $record['id'], $slotName);
                $hasNewGpsPhoto = (int) (($_FILES['gps_photo']['error'] ?? UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_NO_FILE;
                if (!$hasNewGpsPhoto && !$existingSession) {
                    throw new RuntimeException('GPS photo is required.');
                }

                $photoPayload = $hasNewGpsPhoto
                    ? punch_photo_database_payload($_FILES['gps_photo'] ?? [])
                    : [
                        'punch_in_photo' => $existingSession['punch_in_photo'] ?? null,
                        'punch_in_photo_mime' => $existingSession['punch_in_photo_mime'] ?? null,
                        'punch_in_photo_name' => $existingSession['punch_in_photo_name'] ?? null,
                    ];
                $path = $hasNewGpsPhoto ? handle_upload($_FILES['gps_photo'] ?? []) : (string) ($existingSession['punch_in_path'] ?? '');
                $sessionPayload = [
                    'project_id' => $projectId,
                    'session_mode' => 'project_record',
                    'slot_name' => $slotName,
                    'punch_in_path' => $path,
                    'punch_in_photo' => $photoPayload['punch_in_photo'],
                    'punch_in_photo_mime' => $photoPayload['punch_in_photo_mime'],
                    'punch_in_photo_name' => $photoPayload['punch_in_photo_name'],
                    'punch_in_lat' => trim((string) ($_POST['latitude'] ?? '')) ?: (string) ($existingSession['punch_in_lat'] ?? ''),
                    'punch_in_lng' => trim((string) ($_POST['longitude'] ?? '')) ?: (string) ($existingSession['punch_in_lng'] ?? ''),
                    'punch_in_time' => $existingSession['punch_in_time'] ?? now(),
                    'punch_out_time' => now(),
                    'college_name' => $collegeName,
                    'session_name' => $sessionName,
                    'day_portion' => $dayPortion,
                    'session_duration' => 1,
                    'total_students' => $totalStudents,
                    'present_students' => $presentStudents,
                    'topics_handled' => $topicsHandled,
                    'location' => $location,
                ];

                if ($existingSession) {
                    update_attendance_session((int) $existingSession['id'], $sessionPayload);
                } else {
                    add_attendance_session((int) $record['id'], $sessionPayload);
                }
                update_attendance_record((int) $employee['id'], $date, [
                    'status' => 'Present',
                    'admin_override_status' => null,
                    'admin_override_by_user_id' => null,
                    'admin_override_by_name' => null,
                    'admin_override_at' => null,
                    'punch_in_path' => $sessionPayload['punch_in_path'],
                    'punch_in_photo' => $sessionPayload['punch_in_photo'],
                    'punch_in_photo_mime' => $sessionPayload['punch_in_photo_mime'],
                    'punch_in_photo_name' => $sessionPayload['punch_in_photo_name'],
                    'punch_in_lat' => $sessionPayload['punch_in_lat'],
                    'punch_in_lng' => $sessionPayload['punch_in_lng'],
                    'punch_in_time' => $sessionPayload['punch_in_time'],
                ]);
                audit_log('employee_project_record_submitted', [
                    'attend_date' => $date,
                    'project_id' => $projectId,
                ], (int) $employee['id']);
                flash('success', 'Project record submitted and attendance marked.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee project record failed.', [
                    'employee_id' => (int) $employee['id'],
                    'attend_date' => $date,
                ]);
                flash('error', $exception->getMessage());
            }
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;

        case 'employee_manual_in':
        case 'employee_punch_in':
            $employee = require_roles(['employee', 'corporate_employee']);

            try {
                $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
                if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                    throw new RuntimeException('Week Off dates do not require attendance.');
                }
                $dateProjects = employee_available_projects_for_date($employee, $date);
                if ($dateProjects === []) {
                    throw new RuntimeException('Manual Punch In is available only on assigned project dates.');
                }
                $rules = employee_rules((int) $employee['id']);
                $allowedProjects = [];
                foreach ($dateProjects as $project) {
                    $allowedProjects[(int) ($project['id'] ?? 0)] = $project;
                }
                $projectId = (int) ($_POST['project_id'] ?? 0);
                $selectedProject = $allowedProjects[$projectId] ?? null;
                if (!$selectedProject) {
                    throw new RuntimeException('Select an assigned project available for this date.');
                }
                $slotIndex = max(1, (int) ($_POST['slot_index'] ?? 1));
                $slotLimit = max(1, manual_slot_limit($rules), count($dateProjects));
                if (empty($rules['manual_punch_in']) && $dateProjects === []) {
                    throw new RuntimeException('Manual Punch In is not enabled for this employee.');
                }
                if ($slotIndex > $slotLimit) {
                    throw new RuntimeException('Manual Punch In ' . $slotIndex . ' is not available for this date.');
                }
                if ((int) (($_FILES['punch_photo']['error'] ?? UPLOAD_ERR_NO_FILE)) === UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Manual Punch In ' . $slotIndex . ' requires a photo upload.');
                }

                $projectSlotLabel = trim((string) ($selectedProject['project_name'] ?? '')) ?: 'Project';
                $slotName = 'Project #' . $projectId . ': ' . $projectSlotLabel;
                $record = ensure_attendance_record((int) $employee['id'], $date);
                $existingSession = attendance_session_by_slot((int) $record['id'], $slotName);
                if (!$existingSession && $slotIndex === 1 && !empty($record['punch_in_path'])) {
                    throw new RuntimeException('Manual Punch In 1 is already submitted for this date.');
                }
                if ($existingSession && !empty($existingSession['punch_in_path'])) {
                    throw new RuntimeException('Manual Punch In ' . $slotIndex . ' is already submitted for this date.');
                }

                $photoPayload = punch_photo_database_payload($_FILES['punch_photo'] ?? []);
                $path = handle_upload($_FILES['punch_photo'] ?? []);
                $sessionPayload = [
                    'project_id' => $projectId,
                    'session_mode' => 'manual_pair',
                    'slot_name' => $slotName,
                    'punch_in_path' => $path,
                    'punch_in_photo' => $photoPayload['punch_in_photo'],
                    'punch_in_photo_mime' => $photoPayload['punch_in_photo_mime'],
                    'punch_in_photo_name' => $photoPayload['punch_in_photo_name'],
                    'punch_in_lat' => trim((string) ($_POST['latitude'] ?? '')),
                    'punch_in_lng' => trim((string) ($_POST['longitude'] ?? '')),
                    'punch_in_time' => now(),
                ];

                if ($existingSession) {
                    update_attendance_session((int) $existingSession['id'], $sessionPayload);
                } else {
                    add_attendance_session((int) $record['id'], $sessionPayload);
                }
                $recordFields = [
                    'status' => 'Half Day',
                    'admin_override_status' => null,
                    'admin_override_by_user_id' => null,
                    'admin_override_by_name' => null,
                    'admin_override_at' => null,
                ];
                if ($slotIndex === 1 || empty($record['punch_in_path'])) {
                    $recordFields['punch_in_path'] = $sessionPayload['punch_in_path'];
                    $recordFields['punch_in_photo'] = $sessionPayload['punch_in_photo'];
                    $recordFields['punch_in_photo_mime'] = $sessionPayload['punch_in_photo_mime'];
                    $recordFields['punch_in_photo_name'] = $sessionPayload['punch_in_photo_name'];
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
            $dateProjects = employee_available_projects_for_date($employee, $date);
            if ($dateProjects === []) {
                flash('error', 'Manual Punch Out is available only on assigned project dates.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            $allowedProjects = [];
            foreach ($dateProjects as $project) {
                $allowedProjects[(int) ($project['id'] ?? 0)] = $project;
            }
            $slotIndex = max(1, (int) ($_POST['slot_index'] ?? 1));
            $slotLimit = max(1, manual_slot_limit($rules), count($dateProjects));
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $selectedProject = $allowedProjects[$projectId] ?? null;
            $projectSlotLabel = $selectedProject ? (trim((string) ($selectedProject['project_name'] ?? '')) ?: 'Project') : '';
            $slotName = $selectedProject
                ? 'Project #' . $projectId . ': ' . $projectSlotLabel
                : (trim((string) ($_POST['slot_name'] ?? '')) ?: manual_slot_name($rules, $slotIndex));
            $record = ensure_attendance_record((int) $employee['id'], $date);
            $session = attendance_session_by_slot((int) $record['id'], $slotName);
            $collegeName = trim((string) ($_POST['college_name'] ?? ''));
            $sessionName = trim((string) ($_POST['session_name'] ?? ''));
            $dayPortion = project_session_label((string) ($selectedProject['session_type'] ?? 'FULL_DAY'));
            $sessionDuration = (float) ($_POST['session_duration'] ?? 0);
            $totalStudents = (int) ($_POST['total_students'] ?? 0);
            $presentStudents = (int) ($_POST['present_students'] ?? 0);
            $topicsHandled = trim((string) ($_POST['topics_handled'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));

            if (empty($rules['manual_punch_out']) && $dateProjects === []) {
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
                    'punch_in_photo' => $record['punch_in_photo'] ?? null,
                    'punch_in_photo_mime' => $record['punch_in_photo_mime'] ?? null,
                    'punch_in_photo_name' => $record['punch_in_photo_name'] ?? null,
                    'punch_in_lat' => $record['punch_in_lat'],
                    'punch_in_lng' => $record['punch_in_lng'],
                    'punch_in_time' => $record['punch_in_time'],
                ]);
                $session = attendance_session_by_slot((int) $record['id'], $slotName);
            }
            if (!$session || (empty($session['punch_in_path']) && empty($session['punch_in_photo']))) {
                flash('error', 'Submit Manual Punch In ' . $slotIndex . ' first.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if (session_has_manual_out($session)) {
                flash('error', 'Manual Punch Out ' . $slotIndex . ' is already submitted for this date.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if (!$selectedProject) {
                flash('error', 'Select an assigned project available for this date.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if ($collegeName === '' || $sessionName === '' || $location === '' || $sessionDuration <= 0 || $totalStudents <= 0 || $presentStudents < 0 || $topicsHandled === '') {
                flash('error', 'Manual Punch Out ' . $slotIndex . ' requires Project, College Name, Session Name, Session Duration, Total Students, Present Students, Topics Handled, and Location.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if ($presentStudents > $totalStudents) {
                flash('error', 'Present students cannot be more than total students.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }

            update_attendance_session((int) $session['id'], [
                'project_id' => $projectId,
                'session_mode' => 'manual_pair',
                'college_name' => $collegeName,
                'session_name' => $sessionName,
                'day_portion' => $dayPortion,
                'session_duration' => $sessionDuration,
                'total_students' => $totalStudents,
                'present_students' => $presentStudents,
                'topics_handled' => $topicsHandled,
                'location' => $location,
                'punch_out_time' => now(),
            ]);
            update_attendance_record((int) $employee['id'], $date, [
                'status' => 'Present',
                'admin_override_status' => null,
                'admin_override_by_user_id' => null,
                'admin_override_by_name' => null,
                'admin_override_at' => null,
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
            $returnPage = (string) ($_POST['return_page'] ?? 'employee_reimbursements');
            if (!in_array($returnPage, ['employee_log', 'employee_attendance', 'employee_reimbursements'], true)) {
                $returnPage = 'employee_reimbursements';
            }
            $returnMonth = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['return_month'] ?? ''))
                ? (string) $_POST['return_month']
                : substr($date, 0, 7);
            if (employee_is_vendor_trainer($employee)) {
                flash('error', 'Reimbursement is not available for vendor trainers.');
                redirect_to('employee_attendance', ['month' => $returnMonth]);
            }

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

            redirect_to($returnPage, ['month' => $returnMonth]);
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

        case 'admin_process_accounts_payment':
            $admin = require_power_accounts_access(['admin'], 'pay');
            $filters = payment_filter_params($_POST);

            try {
                $payments = create_accounts_payment_batch($_POST, $_FILES['proof_upload'] ?? []);
                foreach ($payments as $payment) {
                    audit_log('admin_accounts_payment_processed', [
                        'payment_id' => (int) ($payment['id'] ?? 0),
                        'payment_type' => (string) ($payment['payment_type'] ?? ''),
                        'amount' => (float) ($payment['amount'] ?? 0),
                        'reimbursement_id' => !empty($payment['reimbursement_id']) ? (int) $payment['reimbursement_id'] : null,
                    ], (int) ($payment['user_id'] ?? 0), $admin);
                }

                flash('success', count($payments) === 1
                    ? 'Payment processed successfully.'
                    : count($payments) . ' payments processed successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin accounts payment processing failed.', [
                    'admin_id' => (int) $admin['id'],
                    'employee_id' => (int) ($_POST['employee_id'] ?? 0),
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to process the payment right now.');
            }

            redirect_to('admin_accounts', payment_redirect_query($filters));
            break;

        case 'vendor_request_payment_invoice':
            $vendor = require_role('external_vendor');

            try {
                $items = $_POST['invoice_items'] ?? [];
                $isBatchRequest = is_array($items) && $items !== [];
                if (is_array($items) && $items !== []) {
                    $requests = create_vendor_payment_invoice_requests($vendor, $items);
                    foreach ($requests as $request) {
                        audit_log('vendor_payment_invoice_requested', [
                            'request_id' => (int) ($request['id'] ?? 0),
                            'employee_id' => (int) ($request['user_id'] ?? 0),
                            'project_id' => (int) ($request['project_id'] ?? 0),
                            'amount' => (float) ($request['amount'] ?? 0),
                            'invoice_date' => (string) ($request['invoice_date'] ?? ''),
                        ], (int) ($request['user_id'] ?? 0), $vendor);
                    }
                    flash('success', count($requests) . ' payment invoice request(s) sent to admin.');
                } else {
                    $request = create_vendor_payment_invoice_request($vendor, $_POST);
                    audit_log('vendor_payment_invoice_requested', [
                        'request_id' => (int) ($request['id'] ?? 0),
                        'employee_id' => (int) ($request['user_id'] ?? 0),
                        'project_id' => (int) ($request['project_id'] ?? 0),
                        'amount' => (float) ($request['amount'] ?? 0),
                        'invoice_date' => (string) ($request['invoice_date'] ?? ''),
                    ], (int) ($request['user_id'] ?? 0), $vendor);
                    flash('success', 'Payment invoice request sent to admin.');
                }
            } catch (Throwable $exception) {
                report_exception($exception, 'Vendor payment invoice request failed.', [
                    'vendor_id' => (int) $vendor['id'],
                    'employee_id' => (int) ($_POST['employee_id'] ?? 0),
                    'project_id' => (int) ($_POST['project_id'] ?? 0),
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to request payment invoice.');
            }

            if (!empty($isBatchRequest)) {
                redirect_to('vendor_payments');
            }
            redirect_to('vendor_payments', [
                'employee_id' => (int) ($_POST['employee_id'] ?? 0),
                'payment_date' => (string) ($_POST['payment_date'] ?? date('Y-m-d')),
            ]);
            break;

        case 'admin_save_payment':
            $admin = require_power_accounts_access(['admin'], 'pay');
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
            $admin = require_power_accounts_access(['admin'], 'pay');
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

        case 'admin_approve_reimbursement':
            $admin = require_power_accounts_access(['admin'], 'verify');
            $reimbursementId = max(0, (int) ($_POST['reimbursement_id'] ?? 0));
            $filters = payment_filter_params($_POST);
            try {
                $updated = approve_reimbursement_request($reimbursementId, $_POST['approved_amount'] ?? []);
                audit_log('admin_reimbursement_approved', [
                    'reimbursement_id' => (int) $updated['id'],
                    'approved_amount' => (float) ($updated['amount_requested'] ?? 0),
                ], (int) ($updated['user_id'] ?? 0), $admin);
                flash('success', 'Reimbursement approved successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin reimbursement approval failed.', [
                    'admin_id' => (int) $admin['id'],
                    'reimbursement_id' => $reimbursementId,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to approve the reimbursement.');
            }
            redirect_to('admin_accounts', payment_redirect_query($filters));
            break;

        case 'admin_approve_payment_request':
        case 'admin_reject_payment_request':
            $admin = require_power_accounts_access(['admin'], 'verify');
            $requestKey = trim((string) ($_POST['request_key'] ?? ''));
            $filters = payment_filter_params($_POST);
            $isApproval = $action === 'admin_approve_payment_request';
            $approvedAmount = null;
            if ($isApproval) {
                $approvedSource = $_POST['approved_amount']['REIMBURSEMENT'] ?? ($_POST['approved_amount'] ?? null);
                if (is_array($approvedSource)) {
                    $approvedSource = reset($approvedSource);
                }
                $approvedAmount = $approvedSource !== null ? round(max(0.0, (float) $approvedSource), 2) : null;
            }

            try {
                if ($requestKey === '') {
                    throw new RuntimeException('Payment request not found.');
                }
                update_payment_request_status($requestKey, $isApproval ? 'APPROVED' : 'REJECTED', $approvedAmount);
                audit_log($isApproval ? 'payment_request_approved' : 'payment_request_rejected', [
                    'request_key' => $requestKey,
                    'approved_amount' => $approvedAmount,
                ], null, $admin);
                flash('success', $isApproval ? 'Payment request approved.' : 'Payment request denied.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin payment request review failed.', [
                    'admin_id' => (int) $admin['id'],
                    'request_key' => $requestKey,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to review the payment request.');
            }

            redirect_to('admin_accounts', payment_redirect_query($filters));
            break;

        case 'admin_deny_reimbursement':
            $admin = require_power_accounts_access(['admin'], 'verify');
            $reimbursementId = max(0, (int) ($_POST['reimbursement_id'] ?? 0));
            $filters = payment_filter_params($_POST);
            try {
                $updated = deny_reimbursement_request($reimbursementId);
                audit_log('admin_reimbursement_denied', [
                    'reimbursement_id' => (int) $updated['id'],
                ], (int) ($updated['user_id'] ?? 0), $admin);
                flash('success', 'Reimbursement denied successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin reimbursement denial failed.', [
                    'admin_id' => (int) $admin['id'],
                    'reimbursement_id' => $reimbursementId,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to deny the reimbursement.');
            }
            redirect_to('admin_accounts', payment_redirect_query($filters));
            break;

        case 'employee_contractual_payment_request':
            $employee = require_role('corporate_employee');
            $requestDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['request_date'] ?? ''))
                ? (string) $_POST['request_date']
                : date('Y-m-d');

            try {
                $request = create_contractual_payment_request($employee, $requestDate, (string) ($_POST['note'] ?? ''));
                audit_log('contractual_payment_requested', [
                    'request_date' => $requestDate,
                    'amount' => (float) ($request['amount'] ?? 0),
                ], (int) $employee['id'], $employee);
                if (!empty($employee['admin_id'])) {
                    create_notification_entry(
                        (int) $employee['admin_id'],
                        'Payment request',
                        (string) ($employee['name'] ?? 'Contractual employee') . ' requested payment for ' . date('d M Y', strtotime($requestDate)) . '.',
                        'info',
                        'payment_request',
                        (int) ($request['id'] ?? 0),
                        (int) $employee['id']
                    );
                }
                flash('success', 'Payment request sent to admin.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Contractual payment request failed.', [
                    'employee_id' => (int) $employee['id'],
                    'request_date' => $requestDate,
                ]);
                flash('error', $exception->getMessage() ?: 'Unable to send payment request.');
            }

            redirect_to('employee_payments', ['payment_date' => $requestDate]);
            break;

        case 'employee_project_confirmation_download':
            $employee = require_roles(['employee', 'corporate_employee']);
            if (!employee_is_contractual_assignment_target($employee)) {
                throw new RuntimeException('Project confirmation download is available only for contractual employees.');
            }

            $projectId = max(0, (int) ($_POST['project_id'] ?? 0));
            $matchedProject = null;
            foreach (assigned_projects_for_employee((int) ($employee['id'] ?? 0)) as $project) {
                if ((int) ($project['id'] ?? 0) === $projectId) {
                    $matchedProject = $project;
                    break;
                }
            }
            if (!$matchedProject) {
                throw new RuntimeException('Project confirmation letter not found.');
            }

            $admin = contractual_assignment_confirmation_admin(['id' => (int) ($employee['admin_id'] ?? 0)]);
            $letterHtml = contractual_project_confirmation_letter_html($employee, $matchedProject, $matchedProject, $admin);
            $pdf = contractual_project_confirmation_pdf($employee, $matchedProject, $matchedProject, $admin, $letterHtml);
            if ($pdf === '') {
                throw new RuntimeException('Unable to generate the project confirmation PDF.');
            }

            $projectName = preg_replace('/[^a-z0-9]+/i', '_', strtolower((string) ($matchedProject['project_name'] ?? 'project'))) ?: 'project';
            $employeeName = preg_replace('/[^a-z0-9]+/i', '_', strtolower((string) (($employee['emp_id'] ?? '') ?: ($employee['name'] ?? 'employee')))) ?: 'employee';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="project_confirmation_' . trim($projectName, '_') . '_' . trim($employeeName, '_') . '.pdf"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            exit;

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

        case 'admin_employee_details_modal':
            require_power_team_access(['admin', 'freelancer', 'external_vendor']);
            $employeeId = max(0, (int) ($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0));
            $employee = $employeeId > 0 ? employee_by_id($employeeId) : null;
            if (!$employee) {
                render_json(['success' => false, 'message' => 'Employee not found.'], 404);
            }
            ob_start();
            render_employee_rules_detail_modal(
                $employee,
                employee_rules($employeeId),
                assigned_projects_for_employee($employeeId),
                'employee-rules-modal-' . $employeeId
            );
            $html = (string) ob_get_clean();
            render_json(['success' => true, 'html' => $html]);
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
            export_report_csv($data, $filters);
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
            export_report_pdf($data, $filters);
            break;

        case 'export_reimbursements_excel':
            require_role('admin');
            export_reimbursements_excel(admin_recent_reimbursements(50, null, 24));
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
