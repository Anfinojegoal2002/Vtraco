<?php
require_once 'c:/xampp/htdocs/vtraco/src/config.php';
require_once 'c:/xampp/htdocs/vtraco/src/core/database.php';

$approved = db()->query("SELECT id, name, email, role, representative_name, company_name, status FROM users WHERE role = 'admin' AND status IN ('ACTIVE', 'BLOCKED') ORDER BY company_name ASC, name ASC")->fetchAll();
echo "Found " . count($approved) . " approved admins." . PHP_EOL;
foreach ($approved as $row) {
    echo " - " . $row['name'] . " (" . $row['email'] . ") Status: " . $row['status'] . PHP_EOL;
}
