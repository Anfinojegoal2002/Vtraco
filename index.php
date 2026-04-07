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

initialize_database();

$page = $_GET['page'] ?? 'landing';
$action = $_POST['action'] ?? null;

if ($action) {
    handle_post_action($action);
}

$currentUser = current_user();
if ($page === 'landing' && $currentUser) {
    $page = $currentUser['role'] === 'admin' ? 'admin_dashboard' : 'employee_attendance';
}

render_page($page);
