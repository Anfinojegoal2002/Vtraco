<?php
$pdo = new PDO('mysql:host=localhost;dbname=vtraco', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$approved = $pdo->query("SELECT id, name, email, role, representative_name, company_name, status FROM users WHERE role = 'admin' AND status IN ('ACTIVE', 'BLOCKED') ORDER BY company_name ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($approved) . " approved admins." . PHP_EOL;
foreach ($approved as $row) {
    echo " - " . $row['name'] . " (" . $row['email'] . ") Status: " . $row['status'] . " Role: " . $row['role'] . PHP_EOL;
}
