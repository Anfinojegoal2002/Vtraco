<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function ensure_mail_log_dir(): void
{
    if (!is_dir(MAIL_LOG_PATH)) {
        mkdir(MAIL_LOG_PATH, 0777, true);
    }
}
function mail_sender_identity(): array
{
    $user = current_user();
    $transport = mail_transport_config();
    $fallbackFrom = trim((string) (getenv('VTRACO_MAIL_FROM_FALLBACK') ?: MAIL_SMTP_FROM_FALLBACK));
    $defaultEmail = filter_var((string) ($transport['username'] ?: $fallbackFrom), FILTER_VALIDATE_EMAIL) ?: ($fallbackFrom !== '' ? $fallbackFrom : 'no-reply@vtraco.local');
    $defaultName = APP_NAME;

    if ($user && ($user['role'] ?? '') === 'admin') {
        $candidateName = trim((string) ($user['name'] ?? '')) ?: $defaultName;
        $candidateName = preg_replace('/[\r\n]+/', ' ', $candidateName) ?? $candidateName;
        return [
            'name' => $candidateName,
            'email' => $defaultEmail,
        ];
    }

    return [
        'name' => $defaultName,
        'email' => $defaultEmail,
    ];
}

function mail_reply_to_identity(): ?array
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        return null;
    }

    $candidateEmail = filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$candidateEmail) {
        return null;
    }

    $candidateName = trim((string) ($user['name'] ?? '')) ?: APP_NAME;
    $candidateName = preg_replace('/[\r\n]+/', ' ', $candidateName) ?? $candidateName;

    return [
        'name' => $candidateName,
        'email' => $candidateEmail,
    ];
}
function mail_transport_config(): array
{
    $host = trim((string) (getenv('VTRACO_MAIL_HOST') ?: MAIL_SMTP_HOST));
    $port = (int) (getenv('VTRACO_MAIL_PORT') ?: MAIL_SMTP_PORT);
    $username = trim((string) (getenv('VTRACO_MAIL_USERNAME') ?: MAIL_SMTP_USERNAME));
    $password = (string) (getenv('VTRACO_MAIL_PASSWORD') ?: MAIL_SMTP_PASSWORD);
    $encryption = strtolower(trim((string) (getenv('VTRACO_MAIL_ENCRYPTION') ?: MAIL_SMTP_ENCRYPTION)));

    return [
        'host' => $host,
        'port' => $port > 0 ? $port : 587,
        'username' => $username,
        'password' => $password,
        'encryption' => in_array($encryption, ['ssl', 'tls'], true) ? $encryption : '',
        'use_smtp' => $host !== '',
        'auth' => $username !== '' && $password !== '',
    ];
}

function send_html_mail(string $to, string $subject, string $html, array $attachments = []): array
{
    ensure_mail_log_dir();
    $sender = mail_sender_identity();
    $replyTo = mail_reply_to_identity();
    $transport = mail_transport_config();
    $fromName = preg_replace('/[\r\n]+/', ' ', (string) ($sender['name'] ?? APP_NAME)) ?: APP_NAME;
    $fromEmail = filter_var((string) ($sender['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: 'no-reply@vtraco.local';
    $safeRecipient = preg_replace('/[^a-z0-9]+/i', '_', strtolower($to)) ?: 'recipient';
    $filename = MAIL_LOG_PATH . '/' . date('Ymd_His') . '_' . $safeRecipient . '.html';
    $document = '<!doctype html><html><head><meta charset="utf-8"><title>' . h($subject) . '</title></head><body style="font-family:Inter,Segoe UI,Arial,sans-serif;color:#172554;line-height:1.65;">'
        . '<h2>' . h($subject) . '</h2><p><strong>From:</strong> ' . h($fromName) . ' &lt;' . h($fromEmail) . '&gt;</p><p><strong>To:</strong> ' . h($to) . '</p>' . $html . '</body></html>';
    file_put_contents($filename, $document);

    $result = [
        'sent' => false,
        'log_file' => basename($filename),
        'log_path' => $filename,
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'transport' => $transport['use_smtp'] ? 'phpmailer-smtp' : 'phpmailer-mail',
        'error' => '',
        'attachments' => [],
    ];

    try {
        $mailer = new PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $document;
        $mailer->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $mailer->setFrom($fromEmail, $fromName, false);
        if ($replyTo) {
            $mailer->addReplyTo($replyTo['email'], $replyTo['name']);
        } else {
            $mailer->addReplyTo($fromEmail, $fromName);
        }
        $mailer->addAddress($to);
        foreach ($attachments as $attachment) {
            $attachmentName = trim((string) ($attachment['name'] ?? ''));
            $attachmentData = $attachment['data'] ?? null;
            if ($attachmentName === '' || !is_string($attachmentData) || $attachmentData === '') {
                continue;
            }

            $attachmentType = trim((string) ($attachment['type'] ?? '')) ?: 'application/octet-stream';
            $attachmentEncoding = trim((string) ($attachment['encoding'] ?? '')) ?: PHPMailer::ENCODING_BASE64;
            $mailer->addStringAttachment($attachmentData, $attachmentName, $attachmentEncoding, $attachmentType);
            $result['attachments'][] = $attachmentName;
        }

        if ($transport['use_smtp']) {
            $mailer->isSMTP();
            $mailer->Host = $transport['host'];
            $mailer->Port = $transport['port'];
            $mailer->SMTPAuth = $transport['auth'];
            $mailer->Username = $transport['username'];
            $mailer->Password = $transport['password'];
            if ($transport['encryption'] === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($transport['encryption'] === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        } else {
            $mailer->isMail();
        }

        $mailer->send();
        $result['sent'] = true;
    } catch (PHPMailerException $exception) {
        $result['error'] = $exception->getMessage();
    } catch (Throwable $exception) {
        $result['error'] = $exception->getMessage();
    }

    return $result;
}

function mail_login_link_for_role(string $role): string
{
    $role = in_array($role, ['admin', 'employee', 'corporate_employee', 'external_vendor', 'freelancer'], true)
        ? $role
        : 'employee';

    return app_url(['auth' => $role]);
}

function send_employee_credentials_email(array $employee, string $password, array $rules): array
{
    $assignedProjectsHtml = assigned_projects_mail_html((int) ($employee['id'] ?? 0));
    $loginLink = mail_login_link_for_role((string) ($employee['role'] ?? 'employee'));
    $html = '<p>Hello ' . h($employee['name']) . ',</p>'
        . '<p>Welcome to V Traco. Your employee account has been created.</p>'
        . '<p><strong>Username:</strong> ' . h((string) $employee['email']) . '<br>'
        . '<strong>Password:</strong> ' . h($password) . '</p>'
        . '<p><strong>Login Link:</strong> <a href="' . h($loginLink) . '">' . h($loginLink) . '</a></p>'
        . '<p><a href="' . h($loginLink) . '" style="display:inline-block;background:#3085d6;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:700;">Open Login</a></p>'
        . '<p>After your first login, complete profile verification before accessing the dashboard.</p>'
        . '<p><strong>Assigned Rules</strong><br>' . rules_explanation_html($rules) . '</p>'
        . $assignedProjectsHtml;
    $attachments = [];
    if ((string) ($employee['role'] ?? '') === 'corporate_employee' || (string) ($employee['employee_type'] ?? '') === 'corporate') {
        $confirmationDoc = contractual_employee_confirmation_docx($employee);
        if ($confirmationDoc !== '') {
            $attachments[] = [
                'name' => contractual_confirmation_docx_filename($employee, [], 'contractual_employee_confirmation'),
                'data' => $confirmationDoc,
                'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];
            $html .= '<p>Your contractual confirmation document is attached for your records.</p>';
        }
    }
    $mailResult = send_html_mail((string) $employee['email'], 'Welcome to V Traco - Login Credentials', $html, $attachments);
    app_log(!empty($mailResult['sent']) ? 'info' : 'warning', 'Employee credential email processed.', [
        'employee_id' => (int) ($employee['id'] ?? 0),
        'email' => (string) ($employee['email'] ?? ''),
        'sent' => !empty($mailResult['sent']),
        'transport' => (string) ($mailResult['transport'] ?? ''),
        'log_file' => (string) ($mailResult['log_file'] ?? ''),
        'error' => (string) ($mailResult['error'] ?? ''),
    ]);

    return $mailResult;
}

function send_employee_profile_status_email(array $employee, string $status, string $reason = ''): array
{
    $loginLink = app_url([
        'page' => 'login',
        'role' => (string) ($employee['role'] ?? 'employee'),
    ]);
    $statusLabel = ucfirst($status);
    $html = '<p>Hello ' . h((string) ($employee['name'] ?? 'Employee')) . ',</p>'
        . '<p>Your V Traco profile verification status is now <strong>' . h($statusLabel) . '</strong>.</p>';
    if ($status === 'rejected' && trim($reason) !== '') {
        $html .= '<p><strong>Reason:</strong> ' . nl2br(h($reason)) . '</p>'
            . '<p>Please update and resubmit your profile verification form.</p>';
    }
    if ($status === 'verified') {
        $html .= '<p>Your dashboard access is now enabled.</p>';
    }
    $html .= '<p><a href="' . h($loginLink) . '">Open V Traco</a></p>';

    return send_html_mail((string) ($employee['email'] ?? ''), 'V Traco Profile Verification ' . $statusLabel, $html);
}

function send_rules_updated_email(array $employee, array $rules, bool $includeAssignedProjects = true): array
{
    $assignedProjectsHtml = $includeAssignedProjects ? assigned_projects_mail_html((int) ($employee['id'] ?? 0)) : '';
    $appliedRulesHtml = rules_explanation_html($rules);
    $shift = normalize_shift_selection((string) ($employee['shift'] ?? ''));
    if ($shift !== '') {
        $shiftHtml = h('Shift Timing: ' . str_replace('-', ' - ', $shift));
        $appliedRulesHtml = $appliedRulesHtml !== '' ? $appliedRulesHtml . '<br>' . $shiftHtml : $shiftHtml;
    }
    if ($appliedRulesHtml === '') {
        $appliedRulesHtml = '<span class="muted">No rules assigned</span>';
    }
    $html = '<p>Hello ' . h($employee['name']) . ',</p>'
        . '<p>Your attendance rules have been updated in V Traco.</p>'
        . '<p><strong>Applied Rules</strong><br>' . $appliedRulesHtml . '</p>'
        . $assignedProjectsHtml;
    return send_html_mail((string) $employee['email'], 'V Traco Rules Updated', $html);
}

function contractual_confirmation_template_path(): string
{
    $basePath = dirname(__DIR__, 2) . '/storage/templates/';
    $newestTermsTemplate = $basePath . 'Karyoun_Innovation_Terms_and_Conditions_4.docx';
    if (is_file($newestTermsTemplate)) {
        return $newestTermsTemplate;
    }

    $latestTermsTemplate = $basePath . 'Karyoun_Innovation_Terms_and_Conditions_3.docx';
    if (is_file($latestTermsTemplate)) {
        return $latestTermsTemplate;
    }

    $termsTemplate = $basePath . 'Karyoun_Innovation_Terms_and_Conditions_1.docx';
    if (is_file($termsTemplate)) {
        return $termsTemplate;
    }

    return $basePath . 'Karyoun_Innovation_Freelance_Trainer_Confirmation_Letter_4.docx';
}

function contractual_confirmation_docx_date(string $date): string
{
    $date = substr(trim($date), 0, 10);
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return '';
    }

    return date('d-m-Y', strtotime($date));
}

function contractual_confirmation_docx_filename(array $employee, array $project = [], string $prefix = 'project_confirmation'): string
{
    $nameParts = [
        $prefix,
        (string) (($project['project_name'] ?? '') ?: 'contractual'),
        (string) (($employee['emp_id'] ?? '') ?: ($employee['name'] ?? 'employee')),
    ];
    $name = preg_replace('/[^a-z0-9]+/i', '_', strtolower(implode('_', $nameParts))) ?: $prefix;

    return trim($name, '_') . '_' . date('Ymd_His') . '.docx';
}

function contractual_confirmation_docx_fill(array $employee, array $project = [], array $assignment = [], array $admin = []): string
{
    $template = contractual_confirmation_template_path();
    if (!class_exists(ZipArchive::class) || !is_file($template) || !is_readable($template)) {
        return '';
    }

    $employeeName = trim((string) ($employee['name'] ?? '')) ?: 'Employee';
    $empId = trim((string) ($employee['emp_id'] ?? ''));
    $employeeLabel = $employeeName . ($empId !== '' ? ' (' . $empId . ')' : '');
    $projectName = trim((string) ($project['project_name'] ?? '')) ?: 'To be assigned';
    $adminName = trim((string) ($admin['name'] ?? $admin['representative_name'] ?? '')) ?: 'Project Coordinator';
    $adminDesignation = trim((string) ($admin['designation'] ?? '')) ?: 'Coordinator';
    $adminPhone = trim((string) ($admin['phone'] ?? ''));
    $adminEmail = trim((string) ($admin['company_email'] ?? $admin['email'] ?? ''));
    $adminAddress = trim((string) ($admin['company_address'] ?? $admin['address'] ?? ''));

    $replacements = [
        '[Trainer\'s Full Name]' => $employeeName,
        '[Trainer\'s Name]' => $employeeName,
        '[Address Line 1], [City – State – PIN]' => trim((string) ($employee['address'] ?? '')) ?: '-',
        '[Project / Course Name]' => $projectName,
        '[Contractual Training Program Name]' => $projectName,
        '[Vendor Name]' => trim((string) ($project['vendor_name'] ?? '')) ?: '-',
        '[ABC Engineering College]' => trim((string) ($project['college_name'] ?? '')) ?: '-',
        '[City, State]' => trim((string) ($project['location'] ?? '')) ?: '-',
        '[Employee Name (EMP ID)]' => $employeeLabel,
        '[DD-MM-YYYY]' => [
            contractual_confirmation_docx_date((string) ($assignment['project_from'] ?? '')) ?: '-',
            contractual_confirmation_docx_date((string) ($assignment['project_to'] ?? '')) ?: '-',
        ],
        '[Name of Project Coordinator]' => $adminName,
        '[Designation]' => $adminDesignation,
        '[+91 XXXXXXXXXX]' => $adminPhone !== '' ? $adminPhone : '-',
        '[coordinator@karyouninnovation.com]' => $adminEmail !== '' ? $adminEmail : '-',
        '[Karyoun Innovation, City – State – PIN]' => $adminAddress !== '' ? $adminAddress : '-',
        'EMP ID: _______________________' => 'EMP ID: ' . ($empId !== '' ? $empId : '-'),
        'Name:  ________________________' => [
            'Name: ' . $adminName,
            'Name: ' . $employeeName,
        ],
        'Date:  ________________________' => [
            'Date: ' . date('d-m-Y'),
            'Date: ' . date('d-m-Y'),
        ],
    ];
    $source = new ZipArchive();
    if ($source->open($template) !== true) {
        return '';
    }

    $targetPath = tempnam(sys_get_temp_dir(), 'vtraco_docx_');
    if ($targetPath === false) {
        $source->close();
        return '';
    }
    $target = new ZipArchive();
    if ($target->open($targetPath, ZipArchive::OVERWRITE) !== true) {
        $source->close();
        @unlink($targetPath);
        return '';
    }

    for ($index = 0; $index < $source->numFiles; $index++) {
        $entryName = (string) $source->getNameIndex($index);
        if ($entryName === '') {
            continue;
        }
        $contents = $source->getFromIndex($index);
        if (!is_string($contents)) {
            continue;
        }
        if ($entryName === 'word/document.xml') {
            foreach ($replacements as $placeholder => $value) {
                $values = is_array($value) ? array_values($value) : [$value];
                foreach ($values as $replacement) {
                    $contents = preg_replace(
                        '/' . preg_quote(htmlspecialchars($placeholder, ENT_XML1 | ENT_QUOTES, 'UTF-8'), '/') . '/',
                        htmlspecialchars((string) $replacement, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                        $contents,
                        1
                    ) ?? $contents;
                }
            }
        }
        $target->addFromString($entryName, $contents);
    }

    $source->close();
    $target->close();
    $output = (string) file_get_contents($targetPath);
    @unlink($targetPath);

    return $output;
}

function contractual_employee_confirmation_docx(array $employee): string
{
    $admin = [];
    if (!empty($employee['admin_id'])) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id AND role = "admin" LIMIT 1');
        $stmt->execute(['id' => (int) $employee['admin_id']]);
        $admin = $stmt->fetch() ?: [];
    }

    return contractual_confirmation_docx_fill($employee, [], [
        'project_from' => (string) ($employee['date_of_joining'] ?? ''),
        'project_to' => '',
    ], $admin);
}

function contractual_confirmation_terms(): array
{
    return [
        'Training Quality & Performance' => 'Trainers are expected to deliver sessions professionally and effectively. Negative feedback, unsuitable conduct, technical concerns, or delivery issues may result in withheld payment.',
        'Anti-Harassment Policy' => 'Karyoun Innovation maintains a zero-tolerance policy towards sexual harassment, discrimination, misconduct, and unprofessional behavior.',
        'Travel & Transportation Responsibility' => 'Trainers must ensure timely arrival for travel arrangements booked by the client or Karyoun Innovation. Missed transport due to personal reasons will not be reimbursed.',
        'Expense Claims & Reimbursements' => 'Original bills and GST invoices must be collected and submitted as per company reimbursement guidelines.',
        'Confidentiality & Non-Circumvention' => 'Trainers must not share personal contact details, business information, commercial arrangements, or directly engage with clients outside Karyoun Innovation.',
        'Compensation Confidentiality' => 'Payment terms and commercial arrangements must remain confidential.',
        'Professional Appearance' => 'Business formal or smart professional attire is mandatory unless otherwise specified by the client.',
        'Self-Promotion Restriction' => 'Self-promotion, marketing, solicitation, promotional material, social media sharing, and independent service offers are prohibited during assignment.',
        'Punctuality & Attendance' => 'Trainers are expected to be punctual and follow the agreed schedule every day.',
        'Payment Terms' => 'Payments will be processed within 15 working days after successful completion, subject to required documents and approvals.',
        'Session Tracker & Reporting Compliance' => 'Session tracker, attendance, topics, learner progress, assessments, and requested reporting must be updated accurately and on time.',
    ];
}

function contractual_confirmation_terms_html(): string
{
    return '<section class="karyoun-letter-preview" style="margin-top:24px;">'
        . '<header><h3>KARYOUN INNOVATION</h3><span>Training & Development Solutions</span><h4>FREELANCE TRAINER GUIDELINES<br>TERMS &amp; CONDITIONS</h4></header>'
        . '<p>By accepting a training assignment from Karyoun Innovation, you agree to comply with the following terms and conditions:</p>'
        . '<ol class="karyoun-terms-list">' . contractual_confirmation_terms_list_html() . '</ol>'
        . '<p><strong>Declaration:</strong> I hereby acknowledge that I have read, understood, and agreed to comply with all the above terms and conditions of Karyoun Innovation.</p>'
        . '</section>';
}

function contractual_confirmation_terms_list_html(): string
{
    $termsHtml = '';
    foreach (contractual_confirmation_terms() as $title => $copy) {
        $termsHtml .= '<li><strong>' . h($title) . '</strong><p>' . h($copy) . '</p></li>';
    }

    return $termsHtml;
}

function contractual_confirmation_template_preview_html(array $employee, array $project, array $assignment, array $admin): string
{
    $employeeName = trim((string) ($employee['name'] ?? 'Employee')) ?: 'Employee';
    $empId = trim((string) ($employee['emp_id'] ?? ''));
    $adminName = trim((string) ($admin['name'] ?? $admin['representative_name'] ?? '')) ?: 'Project Coordinator';

    return '<article class="karyoun-letter-preview">'
        . '<header><h3>KARYOUN INNOVATION</h3><span>Training & Development Solutions</span><h4>FREELANCE TRAINER GUIDELINES<br>TERMS &amp; CONDITIONS</h4></header>'
        . '<p>By accepting a training assignment from Karyoun Innovation, you agree to comply with the following terms and conditions:</p>'
        . '<ol class="karyoun-terms-list">' . contractual_confirmation_terms_list_html() . '</ol>'
        . '<p><strong>Declaration:</strong> I hereby acknowledge that I have read, understood, and agreed to comply with all the above terms and conditions of Karyoun Innovation.</p>'
        . '<div class="karyoun-sign-grid"><div><strong>Authorized Signatory - Karyoun Innovation</strong><span data-signature-slot>Signature: ____________________</span><span>Name: ' . h($adminName) . '</span><span>Date: ' . h(date('d-m-Y')) . '</span><span>Seal / Stamp:</span></div><div><strong>Trainer / Employee Acknowledgement</strong><span data-trainer-signature-slot>Signature: ____________________</span><span>Name: ' . h($employeeName) . '</span><span>Date: ' . h(date('d-m-Y')) . '</span><span>Place: ________________________</span><span>EMP ID: ' . h($empId !== '' ? $empId : '-') . '</span></div></div>'
        . '</article>';
}

function contractual_project_confirmation_letter_html(array $employee, array $project, array $assignment, array $admin): string
{
    $employeeName = trim((string) ($employee['name'] ?? 'Employee')) ?: 'Employee';
    $adminName = trim((string) ($admin['company_name'] ?? $admin['name'] ?? APP_NAME)) ?: APP_NAME;
    $projectName = trim((string) ($project['project_name'] ?? 'Project')) ?: 'Project';
    $payBasis = normalize_project_pay_basis($assignment['project_pay_basis'] ?? 'daily');
    $payBasisLabel = project_pay_basis_options()[$payBasis] ?? ucfirst($payBasis);
    $rateLabel = 'Rs ' . number_format((float) ($assignment['project_daily_salary'] ?? 0), 2) . ' per ' . ($payBasis === 'hourly' ? 'hour' : 'day');
    $range = project_assignment_mail_range((string) ($assignment['project_from'] ?? ''), (string) ($assignment['project_to'] ?? ''));
    $dateLabel = date('d M Y');

    $details = [
        'Project Name' => $projectName,
        'Vendor' => trim((string) ($project['vendor_name'] ?? '')) ?: '-',
        'College Name' => trim((string) ($project['college_name'] ?? '')) ?: '-',
        'Location' => trim((string) ($project['location'] ?? '')) ?: '-',
        'Project Duration' => $range !== '' ? $range : '-',
        'Payment Basis' => $payBasisLabel,
        'Rate' => $rateLabel,
    ];

    $detailRows = '';
    foreach ($details as $label => $value) {
        $detailRows .= '<tr><th style="text-align:left;padding:8px 10px;border:1px solid #dbe4f0;background:#f8fafc;">' . h($label) . '</th><td style="padding:8px 10px;border:1px solid #dbe4f0;">' . h($value) . '</td></tr>';
    }

    return '<div style="font-family:Inter,Segoe UI,Arial,sans-serif;color:#172554;line-height:1.65;">'
        . '<p><strong>Date:</strong> ' . h($dateLabel) . '</p>'
        . '<p>To,<br><strong>' . h($employeeName) . '</strong><br>'
        . h((string) ($employee['emp_id'] ?? '')) . '</p>'
        . '<p>Dear ' . h($employeeName) . ',</p>'
        . '<p>This letter confirms that you have been assigned to the following contractual project by ' . h($adminName) . '.</p>'
        . '<table style="border-collapse:collapse;width:100%;max-width:720px;margin:14px 0;">' . $detailRows . '</table>'
        . '<p>Please follow the assigned project schedule and submit your attendance/project records in V Traco as required.</p>'
        . contractual_confirmation_terms_html()
        . '<p>Regards,<br><strong>' . h($adminName) . '</strong></p>'
        . '</div>';
}

function sanitize_contractual_confirmation_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $html = preg_replace('/<\s*(script|iframe|object|embed|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
    $html = preg_replace('/(href|src)\s*=\s*("[^"]*javascript:[^"]*"|\'[^\']*javascript:[^\']*\'|[^\s>]*javascript:[^\s>]*)/i', '$1="#"', $html) ?? $html;

    return $html;
}

function contractual_project_confirmation_pdf(array $employee, array $project, array $assignment, array $admin, string $letterHtml = ''): string
{
    if (!class_exists(\Dompdf\Dompdf::class)) {
        return '';
    }

    $bodyHtml = sanitize_contractual_confirmation_html($letterHtml);
    if ($bodyHtml === '') {
        $bodyHtml = contractual_project_confirmation_letter_html($employee, $project, $assignment, $admin);
    }
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,Arial,sans-serif;color:#172554;font-size:13px;line-height:1.6;}h1{font-size:22px;margin:0 0 18px;}table{border-collapse:collapse;width:100%;margin:16px 0;}th,td{border:1px solid #dbe4f0;padding:8px 10px;}th{background:#f8fafc;text-align:left;width:34%;}</style></head><body><h1>Project Confirmation Letter</h1>'
        . $bodyHtml
        . '</body></html>';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

function send_contractual_project_confirmation_email(array $employee, array $project, array $assignment, array $admin, string $letterHtml = ''): array
{
    $email = filter_var((string) ($employee['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        return [
            'sent' => false,
            'skipped' => true,
            'error' => 'Employee email is missing or invalid.',
        ];
    }

    $projectName = trim((string) ($project['project_name'] ?? 'Project')) ?: 'Project';
    $html = sanitize_contractual_confirmation_html($letterHtml);
    if ($html === '') {
        $html = contractual_project_confirmation_letter_html($employee, $project, $assignment, $admin);
    }
    $attachments = [];
    $docx = contractual_confirmation_docx_fill($employee, $project, $assignment, $admin);
    if ($docx !== '') {
        $attachments[] = [
            'name' => contractual_confirmation_docx_filename($employee, $project),
            'data' => $docx,
            'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }
    $pdf = contractual_project_confirmation_pdf($employee, $project, $assignment, $admin, $html);
    if ($pdf !== '') {
        $safeProject = preg_replace('/[^a-z0-9]+/i', '_', strtolower($projectName)) ?: 'project';
        $safeEmployee = preg_replace('/[^a-z0-9]+/i', '_', strtolower((string) ($employee['emp_id'] ?? $employee['name'] ?? 'employee'))) ?: 'employee';
        $attachments[] = [
            'name' => 'project_confirmation_' . $safeProject . '_' . $safeEmployee . '.pdf',
            'data' => $pdf,
            'type' => 'application/pdf',
        ];
    }

    $result = send_html_mail($email, 'Project Confirmation Letter - ' . $projectName, $html, $attachments);
    app_log(!empty($result['sent']) ? 'info' : 'warning', 'Contractual project confirmation email processed.', [
        'employee_id' => (int) ($employee['id'] ?? 0),
        'project_id' => (int) ($project['id'] ?? 0),
        'email' => $email,
        'sent' => !empty($result['sent']),
        'log_file' => (string) ($result['log_file'] ?? ''),
        'error' => (string) ($result['error'] ?? ''),
    ]);

    return $result;
}

function send_contractual_project_confirmation_letters(array $project, array $admin, array $letterHtmlByEmployee = []): array
{
    $projectId = (int) ($project['id'] ?? 0);
    $adminId = (int) ($admin['id'] ?? 0);
    $summary = [
        'processed' => 0,
        'sent' => 0,
        'logged' => 0,
        'skipped' => 0,
        'errors' => [],
    ];
    if ($projectId <= 0 || $adminId <= 0) {
        return $summary;
    }

    $stmt = db()->prepare("SELECT u.*, a.project_from, a.project_to, a.project_daily_salary, COALESCE(a.project_pay_basis, 'daily') AS project_pay_basis
        FROM employee_project_assignments a
        INNER JOIN users u ON u.id = a.user_id
        WHERE a.project_id = :project_id
          AND u.admin_id = :admin_id
          AND (u.role = 'corporate_employee' OR u.employee_type = 'corporate')
        ORDER BY u.name");
    $stmt->execute([
        'project_id' => $projectId,
        'admin_id' => $adminId,
    ]);

    foreach ($stmt->fetchAll() as $row) {
        $summary['processed']++;
        $employeeId = (int) ($row['id'] ?? 0);
        $customHtml = is_string($letterHtmlByEmployee[$employeeId] ?? null)
            ? (string) $letterHtmlByEmployee[$employeeId]
            : '';
        $result = send_contractual_project_confirmation_email($row, $project, $row, $admin, $customHtml);
        if (!empty($result['skipped'])) {
            $summary['skipped']++;
            $summary['errors'][] = (string) ($result['error'] ?? 'Email skipped.');
            continue;
        }
        if (!empty($result['sent'])) {
            $summary['sent']++;
        } else {
            $summary['logged']++;
            if (!empty($result['error'])) {
                $summary['errors'][] = (string) $result['error'];
            }
        }
    }

    return $summary;
}

function assigned_projects_mail_html(int $userId): string
{
    if ($userId <= 0) {
        return '';
    }

    $projects = assigned_projects_for_employee($userId);
    if ($projects === []) {
        return '';
    }

    $items = [];
    foreach ($projects as $project) {
        $label = trim((string) ($project['project_name'] ?? ''));
        if ($label === '') {
            continue;
        }

        $range = project_assignment_mail_range((string) ($project['project_from'] ?? ''), (string) ($project['project_to'] ?? ''));
        $items[] = '<li>' . h($label) . ($range !== '' ? ' (' . h($range) . ')' : '') . '</li>';
    }

    if ($items === []) {
        return '';
    }

    return '<p><strong>Assigned Projects</strong></p><ul>' . implode('', $items) . '</ul>';
}

function project_assignment_mail_range(string $from, string $to): string
{
    $from = substr(trim($from), 0, 10);
    $to = substr(trim($to), 0, 10);
    if ($from !== '' && $to !== '') {
        return $from . ' to ' . $to;
    }
    if ($from !== '') {
        return 'from ' . $from;
    }
    if ($to !== '') {
        return 'to ' . $to;
    }

    return '';
}

function employee_credentials_delivery_message(array $employee, array $mailResult, string $password, string $context = 'added'): string
{
    $isReset = $context === 'reset';
    $baseMessage = $isReset
        ? 'Password generated successfully for ' . $employee['name'] . '. '
        : 'Employee added successfully for ' . $employee['name'] . '. ';

    $baseMessage .= 'Login email: ' . $employee['email'] . '. The employee must change the password after signing in.';

    if (!empty($mailResult['sent'])) {
        return $baseMessage . ' Login credentials were emailed successfully.';
    }

    return $baseMessage . ' Email delivery failed or is not configured, so a copy containing the password was saved in storage/emails/' . ($mailResult['log_file'] ?? '') . (($mailResult['error'] ?? '') !== '' ? ' | Mail error: ' . $mailResult['error'] : '') . '.';
}

function send_reimbursement_created_email(array $admin, array $employee, array $reimbursement): array
{
    $html = '<p>Hello ' . h((string) $admin['name']) . ',</p>'
        . '<p>' . h((string) $employee['name']) . ' (' . h((string) ($employee['emp_id'] ?? 'Employee')) . ') submitted a new reimbursement request.</p>'
        . '<p><strong>Date:</strong> ' . h(date('d M Y', strtotime((string) $reimbursement['expense_date']))) . '<br>'
        . '<strong>Category:</strong> ' . h((string) $reimbursement['category']) . '<br>'
        . '<strong>Requested Amount:</strong> Rs ' . h(number_format((float) ($reimbursement['amount_requested'] ?? 0), 2)) . '<br>'
        . '<strong>Description:</strong> ' . nl2br(h((string) $reimbursement['expense_description'])) . '</p>';

    return send_html_mail((string) $admin['email'], 'New Reimbursement Request', $html);
}

function send_reimbursement_status_email(array $employee, array $reimbursement, ?float $paymentAmount = null): array
{
    $paymentCopy = '';
    if ($paymentAmount !== null) {
        $paymentCopy = '<p><strong>Paid Amount:</strong> Rs ' . h(number_format($paymentAmount, 2)) . '</p>';
    }

    $html = '<p>Hello ' . h((string) $employee['name']) . ',</p>'
        . '<p>Your reimbursement request status has been updated.</p>'
        . '<p><strong>Date:</strong> ' . h(date('d M Y', strtotime((string) $reimbursement['expense_date']))) . '<br>'
        . '<strong>Category:</strong> ' . h((string) $reimbursement['category']) . '<br>'
        . '<strong>Status:</strong> ' . h((string) $reimbursement['status']) . '<br>'
        . '<strong>Requested Amount:</strong> Rs ' . h(number_format((float) ($reimbursement['amount_requested'] ?? 0), 2)) . '<br>'
        . '<strong>Total Paid:</strong> Rs ' . h(number_format((float) ($reimbursement['amount_paid'] ?? 0), 2)) . '<br>'
        . '<strong>Remaining Balance:</strong> Rs ' . h(number_format((float) ($reimbursement['remaining_balance'] ?? 0), 2)) . '</p>'
        . $paymentCopy;

    return send_html_mail((string) $employee['email'], 'Reimbursement Status Updated', $html);
}

function send_payment_processed_email(array $employee, array $payment): array
{
    $paymentMethods = function_exists('payment_methods_for_record') ? payment_methods_for_record($payment) : [];
    $paymentMethodLabel = function_exists('payment_methods_label') ? payment_methods_label($paymentMethods) : (string) ($payment['bank_name'] ?? '');
    $attachments = [];
    $attachmentCopy = '';

    if (function_exists('payment_payslip_mail_attachment')) {
        try {
            $attachments[] = payment_payslip_mail_attachment($payment);
            $attachmentCopy = '<p>Your payment slip is attached as a PDF for your records.</p>';
        } catch (Throwable $exception) {
            app_log('warning', 'Payment payslip attachment could not be generated.', [
                'payment_id' => (int) ($payment['id'] ?? 0),
                'employee_id' => (int) ($employee['id'] ?? 0),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    $html = '<p>Hello ' . h((string) $employee['name']) . ',</p>'
        . '<p>Your ' . h(strtolower((string) $payment['payment_type'])) . ' payment of Rs ' . h(number_format((float) ($payment['amount'] ?? 0), 2)) . ' has been processed.</p>'
        . '<p><strong>Payment Date:</strong> ' . h(date('d M Y', strtotime((string) $payment['payment_date']))) . '<br>'
        . '<strong>Payment Method(s):</strong> ' . h($paymentMethodLabel) . '<br>'
        . '<strong>Transfer Mode:</strong> ' . h((string) (($payment['transfer_mode'] ?? '') ?: 'N/A')) . '<br>'
        . '<strong>Transaction ID:</strong> ' . h((string) (($payment['transaction_id'] ?? '') ?: 'N/A')) . '</p>'
        . $attachmentCopy;

    return send_html_mail((string) $employee['email'], 'Payment Processed', $html, $attachments);
}

