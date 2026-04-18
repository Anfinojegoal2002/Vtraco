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

function send_html_mail(string $to, string $subject, string $html): array
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

function send_employee_credentials_email(array $employee, string $password, array $rules): array
{
    $html = '<p>Hello ' . h($employee['name']) . ',</p>'
        . '<p>Your V Traco employee account has been created.</p>'
        . '<p>A temporary password was created for your account. Please use the credentials below to sign in and change your password immediately from Profile Settings.</p>'
        . '<p><strong>Employee Email:</strong> ' . h($employee['email']) . '<br>'
        . '<strong>Temporary Password:</strong> ' . h($password) . '</p>'
        . '<p><strong>Assigned Rules</strong><br>' . rules_explanation_html($rules) . '</p>';
    return send_html_mail((string) $employee['email'], 'Your V Traco Login Credentials', $html);
}

function send_rules_updated_email(array $employee, array $rules): array
{
    $html = '<p>Hello ' . h($employee['name']) . ',</p>'
        . '<p>Your attendance rules have been updated in V Traco.</p>'
        . '<p><strong>Applied Rules</strong><br>' . rules_explanation_html($rules) . '</p>';
    return send_html_mail((string) $employee['email'], 'V Traco Rules Updated', $html);
}

function employee_credentials_delivery_message(array $employee, array $mailResult, string $password, string $context = 'added'): string
{
    $isReset = $context === 'reset';
    $baseMessage = $isReset
        ? 'Temporary password generated successfully for ' . $employee['name'] . '. '
        : 'Employee added successfully for ' . $employee['name'] . '. ';

    $baseMessage .= 'Login email: ' . $employee['email'] . '. The employee must change the temporary password after signing in.';

    if (!empty($mailResult['sent'])) {
        return $baseMessage . ' A notification email was sent successfully.';
    }

    return $baseMessage . ' Email delivery is not configured yet, so a copy containing the temporary password was saved in storage/emails/' . ($mailResult['log_file'] ?? '') . (($mailResult['error'] ?? '') !== '' ? ' | Error: ' . $mailResult['error'] : '') . '.';
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

    $html = '<p>Hello ' . h((string) $employee['name']) . ',</p>'
        . '<p>Your ' . h(strtolower((string) $payment['payment_type'])) . ' payment of Rs ' . h(number_format((float) ($payment['amount'] ?? 0), 2)) . ' has been processed.</p>'
        . '<p><strong>Payment Date:</strong> ' . h(date('d M Y', strtotime((string) $payment['payment_date']))) . '<br>'
        . '<strong>Payment Method(s):</strong> ' . h($paymentMethodLabel) . '<br>'
        . '<strong>Transfer Mode:</strong> ' . h((string) (($payment['transfer_mode'] ?? '') ?: 'N/A')) . '<br>'
        . '<strong>Transaction ID:</strong> ' . h((string) (($payment['transaction_id'] ?? '') ?: 'N/A')) . '</p>';

    return send_html_mail((string) $employee['email'], 'Payment Processed', $html);
}

