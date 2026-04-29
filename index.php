<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}
require __DIR__ . '/src/core.php';
require __DIR__ . '/src/layout.php';
require __DIR__ . '/src/views/landing.php';
require __DIR__ . '/src/views/auth.php';
require __DIR__ . '/src/views/admin.php';
require __DIR__ . '/src/views/employee.php';
require __DIR__ . '/src/views/notifications.php';
require __DIR__ . '/src/views/super_admin.php';

initialize_database();

$page = $_GET['page'] ?? 'landing';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$basePath = str_replace('\\', '/', dirname($scriptName));
$basePath = rtrim($basePath, '/');
$relativeUri = '/' . ltrim(substr($uri, strlen($basePath)), '/');

if ($relativeUri === '/super-admin') {
    $page = 'super_admin_dashboard';
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action) {
    try {
        handle_post_action((string) $action);
    } catch (Throwable $exception) {
        if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json' || isset($_GET['action'])) {
            render_json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
        report_exception($exception, 'Unhandled action failed.', ['action' => $action]);
        flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to complete the request right now.');
        $user = null;
        try {
            $user = current_user();
        } catch (Throwable) {
            $user = null;
        }
        if ($user) {
            redirect_to(home_page_for_user($user));
        }
        redirect_to('landing');
    }
}

$currentUser = current_user();
if ($page === 'landing' && $currentUser) {
    $page = home_page_for_user($currentUser);
}

render_page($page);
