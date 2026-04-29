<?php
$url = 'http://localhost/vtraco/index.php?action=super_admin_get_data';
// We need a session. I'll just use the DB to see what would be returned.
require_once 'c:/xampp/htdocs/vtraco/src/config.php';
require_once 'c:/xampp/htdocs/vtraco/src/core/database.php';

$approved = db()->query("SELECT id, name, email, role, representative_name, company_name, status FROM users WHERE role = 'admin' AND status IN ('ACTIVE', 'BLOCKED') ORDER BY company_name ASC, name ASC")->fetchAll();
$pending = db()->query("SELECT id, name, email, role, representative_name, company_name, created_at FROM users WHERE role = 'admin' AND status = 'PENDING' ORDER BY created_at DESC")->fetchAll();

echo json_encode(['approved' => $approved, 'pending' => $pending]);
