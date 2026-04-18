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

initialize_database();

$page = $_GET['page'] ?? 'landing';
$action = $_POST['action'] ?? null;

if ($action) {
    try {
        handle_post_action($action);
    } catch (Throwable $exception) {
        report_exception($exception, 'Unhandled POST action failed.', ['action' => $action]);
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
