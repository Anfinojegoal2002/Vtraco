<?php
$pdo = new PDO('mysql:host=localhost;dbname=vtraco', 'root', '');
$stmt = $pdo->query('SELECT role, status, COUNT(*) as count FROM users GROUP BY role, status');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['role'] . ' (' . ($row['status'] ?? 'NULL') . '): ' . $row['count'] . PHP_EOL;
}
