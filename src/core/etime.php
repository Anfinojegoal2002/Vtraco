<?php

declare(strict_types=1);

function etime_config(?string $passwordOverride = null, ?array $configOverride = null): array
{
    $config = $configOverride ?? require __DIR__ . '/../../config/etime.php';
    if (!is_array($config)) {
        $config = [];
    }

    if ($passwordOverride !== null && trim($passwordOverride) !== '') {
        $config['password'] = trim($passwordOverride);
    }

    $config['base_url'] = rtrim((string) ($config['base_url'] ?? ''), '/') . '/';
    $config['corporate_id'] = trim((string) ($config['corporate_id'] ?? ''));
    $config['username'] = trim((string) ($config['username'] ?? ''));
    $config['password'] = (string) ($config['password'] ?? '');
    $config['timeout'] = max(5, (int) ($config['timeout'] ?? 30));

    return $config;
}

function etime_secret_key(): string
{
    return hash('sha256', DB_NAME . '|' . DB_USERNAME . '|' . DB_PASSWORD . '|vtraco-etime', true);
}

function etime_encrypt_password(string $password): array
{
    if ($password === '') {
        return ['cipher' => null, 'iv' => null];
    }
    if (!function_exists('openssl_encrypt')) {
        return ['cipher' => base64_encode($password), 'iv' => 'plain-base64'];
    }

    $iv = random_bytes(16);
    $cipher = openssl_encrypt($password, 'AES-256-CBC', etime_secret_key(), OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new RuntimeException('Unable to encrypt eTime Office password.');
    }

    return [
        'cipher' => base64_encode($cipher),
        'iv' => base64_encode($iv),
    ];
}

function etime_decrypt_password(?string $cipher, ?string $iv): string
{
    $cipher = (string) $cipher;
    $iv = (string) $iv;
    if ($cipher === '') {
        return '';
    }
    if ($iv === 'plain-base64') {
        return (string) base64_decode($cipher, true);
    }
    if (!function_exists('openssl_decrypt') || $iv === '') {
        return '';
    }

    $decodedCipher = base64_decode($cipher, true);
    $decodedIv = base64_decode($iv, true);
    if ($decodedCipher === false || $decodedIv === false) {
        return '';
    }

    $password = openssl_decrypt($decodedCipher, 'AES-256-CBC', etime_secret_key(), OPENSSL_RAW_DATA, $decodedIv);
    return $password === false ? '' : $password;
}

function biometric_integration_for_admin(int $adminId): ?array
{
    $stmt = db()->prepare("SELECT * FROM biometric_integrations WHERE admin_id = :admin_id AND provider = 'etime_office' LIMIT 1");
    $stmt->execute(['admin_id' => $adminId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $row['password'] = etime_decrypt_password($row['password_cipher'] ?? null, $row['password_iv'] ?? null);
    return $row;
}

function etime_config_for_admin(?int $adminId = null): array
{
    $adminId = $adminId ?? current_admin_id();
    if ($adminId) {
        $integration = biometric_integration_for_admin($adminId);
        if ($integration && !empty($integration['is_enabled'])) {
            return etime_config(null, [
                'base_url' => (string) ($integration['base_url'] ?? ''),
                'corporate_id' => (string) ($integration['corporate_id'] ?? ''),
                'username' => (string) ($integration['username'] ?? ''),
                'password' => (string) ($integration['password'] ?? ''),
                'timeout' => 30,
            ]);
        }
    }

    return etime_config();
}

function save_biometric_integration_for_admin(int $adminId, array $data, ?string $existingPassword = null): array
{
    $baseUrl = trim((string) ($data['base_url'] ?? ''));
    $corporateId = trim((string) ($data['corporate_id'] ?? ''));
    $username = trim((string) ($data['username'] ?? ''));
    $password = trim((string) ($data['password'] ?? ''));
    $enabled = !empty($data['is_enabled']) ? 1 : 0;

    if ($baseUrl === '') {
        $baseUrl = 'https://api.etimeoffice.com/api/';
    }
    if ($corporateId === '' || $username === '') {
        throw new RuntimeException('Corporate ID and username are required.');
    }
    if ($password === '') {
        $password = (string) $existingPassword;
    }
    if ($password === '') {
        throw new RuntimeException('eTime Office password is required.');
    }

    $encrypted = etime_encrypt_password($password);
    $existing = biometric_integration_for_admin($adminId);
    if ($existing) {
        db()->prepare("UPDATE biometric_integrations SET base_url = :base_url, corporate_id = :corporate_id, username = :username, password_cipher = :password_cipher, password_iv = :password_iv, is_enabled = :is_enabled, updated_at = :updated_at WHERE admin_id = :admin_id AND provider = 'etime_office'")
            ->execute([
                'admin_id' => $adminId,
                'base_url' => rtrim($baseUrl, '/') . '/',
                'corporate_id' => $corporateId,
                'username' => $username,
                'password_cipher' => $encrypted['cipher'],
                'password_iv' => $encrypted['iv'],
                'is_enabled' => $enabled,
                'updated_at' => now(),
            ]);
    } else {
        db()->prepare("INSERT INTO biometric_integrations (admin_id, provider, base_url, corporate_id, username, password_cipher, password_iv, is_enabled, created_at, updated_at) VALUES (:admin_id, 'etime_office', :base_url, :corporate_id, :username, :password_cipher, :password_iv, :is_enabled, :created_at, :updated_at)")
            ->execute([
                'admin_id' => $adminId,
                'base_url' => rtrim($baseUrl, '/') . '/',
                'corporate_id' => $corporateId,
                'username' => $username,
                'password_cipher' => $encrypted['cipher'],
                'password_iv' => $encrypted['iv'],
                'is_enabled' => $enabled,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    return biometric_integration_for_admin($adminId) ?: [];
}

function etime_auth_header(array $config): string
{
    foreach (['corporate_id', 'username', 'password'] as $key) {
        if ((string) ($config[$key] ?? '') === '') {
            throw new RuntimeException('eTime Office API credentials are incomplete. Enter the password for this sync or configure config/etime.local.php.');
        }
    }

    return 'Authorization: Basic ' . base64_encode($config['corporate_id'] . ':' . $config['username'] . ':' . $config['password'] . ':true');
}

function etime_request_json(string $endpoint, array $query, ?string $passwordOverride = null, ?array $configOverride = null): array
{
    $config = etime_config($passwordOverride, $configOverride);
    $url = $config['base_url'] . ltrim($endpoint, '/') . '?' . http_build_query($query);
    $headers = [
        etime_auth_header($config),
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $config['timeout'],
            CURLOPT_CONNECTTIMEOUT => min(10, $config['timeout']),
        ]);
        $body = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $body === '') {
            throw new RuntimeException('eTime Office API request failed' . ($error !== '' ? ': ' . $error : '.'));
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => $config['timeout'],
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $statusCode = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches) === 1) {
            $statusCode = (int) $matches[1];
        }
        if ($body === false || $body === '') {
            throw new RuntimeException('eTime Office API request failed.');
        }
    }

    if ($statusCode >= 400) {
        throw new RuntimeException('eTime Office API returned HTTP ' . $statusCode . '.');
    }

    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        throw new RuntimeException('eTime Office API returned an unreadable response.');
    }

    if (!empty($payload['Error'])) {
        throw new RuntimeException('eTime Office API error: ' . (string) ($payload['Msg'] ?? 'Unknown error'));
    }

    return $payload;
}

function etime_api_date(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsed) {
        throw new InvalidArgumentException('Invalid eTime Office sync date.');
    }

    return $parsed->format('d/m/Y');
}

function etime_parse_record_date(string $dateString): ?string
{
    $dateString = trim($dateString);
    foreach (['d/m/Y', 'd/m/Y H:i:s', 'd/m/Y H:i'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $dateString);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->format('Y-m-d');
        }
    }

    return null;
}

function etime_parse_time(?string $time, string $date): ?string
{
    $time = trim((string) $time);
    if ($time === '' || str_contains($time, '--')) {
        return null;
    }

    foreach (['H:i:s', 'H:i'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d ' . $format, $date . ' ' . $time);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function etime_status_to_attendance(string $status, ?string $inTime, ?string $outTime): string
{
    $status = strtoupper(trim($status));
    if (str_contains($status, 'WO')) {
        return 'Week Off';
    }
    if (str_contains($status, 'A') && $inTime === null && $outTime === null) {
        return 'Absent';
    }
    if (str_contains($status, 'P/2') || (($inTime !== null) !== ($outTime !== null))) {
        return 'Half Day';
    }
    if (str_contains($status, 'P') || $inTime !== null || $outTime !== null) {
        return 'Present';
    }

    return 'Absent';
}

function sync_etime_inout_attendance(string $fromDate, string $toDate, string $empCode = 'ALL', ?string $passwordOverride = null): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        throw new InvalidArgumentException('Select a valid eTime Office date range.');
    }
    if ($fromDate > $toDate) {
        throw new InvalidArgumentException('From date cannot be after To date.');
    }

    $empCode = trim($empCode) !== '' ? trim($empCode) : 'ALL';
    $payload = etime_request_json('DownloadInOutPunchData', [
        'Empcode' => $empCode,
        'FromDate' => etime_api_date($fromDate),
        'ToDate' => etime_api_date($toDate),
    ], $passwordOverride, etime_config_for_admin());

    $rows = is_array($payload['InOutPunchData'] ?? null) ? $payload['InOutPunchData'] : [];
    $result = [
        'imported' => 0,
        'skipped' => 0,
        'unmatched' => [],
        'dates' => [],
    ];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            $result['skipped']++;
            continue;
        }

        $rowDate = etime_parse_record_date((string) ($row['DateString'] ?? ''));
        if ($rowDate === null) {
            $result['skipped']++;
            continue;
        }

        $employee = employee_by_attendance_identity((string) ($row['Empcode'] ?? ''), (string) ($row['Name'] ?? ''));
        if (!$employee) {
            $label = trim(((string) ($row['Empcode'] ?? '')) . ' ' . ((string) ($row['Name'] ?? '')));
            if ($label !== '') {
                $result['unmatched'][$label] = $label;
            }
            $result['skipped']++;
            continue;
        }

        $inTime = etime_parse_time($row['INTime'] ?? null, $rowDate);
        $outTime = etime_parse_time($row['OUTTime'] ?? null, $rowDate);
        update_attendance_record((int) $employee['id'], $rowDate, [
            'status' => etime_status_to_attendance((string) ($row['Status'] ?? ''), $inTime, $outTime),
            'biometric_in_time' => $inTime,
            'biometric_out_time' => $outTime,
        ]);

        $result['imported']++;
        $result['dates'][$rowDate] = $rowDate;
    }

    $result['unmatched'] = array_values($result['unmatched']);
    $result['dates'] = array_values($result['dates']);

    return $result;
}

function auto_sync_etime_attendance_for_month(string $month): ?array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        return null;
    }

    $adminId = current_admin_id();
    $config = etime_config_for_admin($adminId);
    if (($config['corporate_id'] ?? '') === '' || ($config['username'] ?? '') === '' || ($config['password'] ?? '') === '') {
        return null;
    }

    $fromDate = date('Y-m-01', strtotime($month . '-01'));
    $toDate = date('Y-m-t', strtotime($month . '-01'));
    $today = date('Y-m-d');
    if ($fromDate > $today) {
        return null;
    }
    if ($toDate > $today) {
        $toDate = $today;
    }

    $syncKey = 'etime_auto_sync_' . ($adminId ?: 'global') . '_' . $month;
    if (!empty($_SESSION[$syncKey])) {
        return null;
    }

    try {
        $result = sync_etime_inout_attendance($fromDate, $toDate, 'ALL');
        if ($adminId) {
            db()->prepare("UPDATE biometric_integrations SET last_sync_at = :last_sync_at WHERE admin_id = :admin_id AND provider = 'etime_office'")
                ->execute(['last_sync_at' => now(), 'admin_id' => $adminId]);
        }
        $_SESSION[$syncKey] = [
            'synced_at' => now(),
            'imported' => (int) ($result['imported'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
        ];
        return $result;
    } catch (Throwable $exception) {
        report_exception($exception, 'Automatic eTime Office attendance sync failed.', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);
        $_SESSION[$syncKey] = [
            'synced_at' => now(),
            'error' => $exception->getMessage(),
        ];
        return null;
    }
}
