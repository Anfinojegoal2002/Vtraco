<?php
$pdo = new PDO('mysql:host=localhost;dbname=vtraco', 'root', '');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$approved = $pdo->query("SELECT id, name, email, role, representative_name, company_name, status FROM users WHERE role = 'admin' AND status IN ('ACTIVE', 'BLOCKED') ORDER BY company_name ASC, name ASC")->fetchAll();
$pending = $pdo->query("SELECT id, name, email, role, representative_name, company_name, created_at FROM users WHERE role = 'admin' AND status = 'PENDING' ORDER BY created_at DESC")->fetchAll();

echo json_encode(['approved' => $approved, 'pending' => $pending]);
