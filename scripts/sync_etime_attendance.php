<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/scripts/sync_etime_attendance.php';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/scripts/sync_etime_attendance.php';

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/core.php';

initialize_database();

$fromDate = date('Y-m-d');
$toDate = date('Y-m-d');
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $fromDate = substr($arg, 7);
    } elseif (str_starts_with($arg, '--to=')) {
        $toDate = substr($arg, 5);
    }
}

$lockPath = APP_LOG_DIR . '/etime-sync.lock';
if (!is_dir(APP_LOG_DIR)) {
    mkdir(APP_LOG_DIR, 0775, true);
}
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo '[' . now() . "] eTime sync already running.\n";
    exit(0);
}

try {
    $stmt = db()->query("SELECT bi.admin_id, u.name AS admin_name FROM biometric_integrations bi INNER JOIN users u ON u.id = bi.admin_id WHERE bi.provider = 'etime_office' AND bi.is_enabled = 1 AND u.role = 'admin' ORDER BY bi.admin_id");
    $integrations = $stmt->fetchAll();
    if (!$integrations) {
        echo '[' . now() . "] No enabled eTime Office integrations found.\n";
        exit(0);
    }  

    $previousUserId = $_SESSION['user_id'] ?? null;
    foreach ($integrations as $integration) {
        $adminId = (int) ($integration['admin_id'] ?? 0);
        if ($adminId <= 0) {
            continue;
        }

        $_SESSION['user_id'] = $adminId;
        try {
            $result = sync_etime_inout_attendance($fromDate, $toDate, 'ALL');
            db()->prepare("UPDATE biometric_integrations SET last_sync_at = :last_sync_at WHERE admin_id = :admin_id AND provider = 'etime_office'")
                ->execute(['last_sync_at' => now(), 'admin_id' => $adminId]);
            audit_log('etime_attendance_background_sync_completed', [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'imported' => (int) ($result['imported'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'unmatched' => $result['unmatched'] ?? [],
            ], $adminId);
            echo '[' . now() . '] Admin #' . $adminId . ' synced. Imported: ' . (int) ($result['imported'] ?? 0) . ' | Skipped: ' . (int) ($result['skipped'] ?? 0) . "\n";
        } catch (Throwable $exception) {
            report_exception($exception, 'Background eTime Office sync failed.', [
                'admin_id' => $adminId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);
            echo '[' . now() . '] Admin #' . $adminId . ' failed: ' . $exception->getMessage() . "\n";
        }
    }

    if ($previousUserId !== null) {
        $_SESSION['user_id'] = $previousUserId;
    } else {
        unset($_SESSION['user_id']);
    }
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
