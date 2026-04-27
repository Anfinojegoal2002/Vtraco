<?php
ini_set('session.save_path', 'C:/xampp/tmp');
require 'C:/xampp/htdocs/vtraco/src/bootstrap.php';
require 'C:/xampp/htdocs/vtraco/src/core.php';
$rows = parse_employee_csv('C:/Users/hp/Downloads/employee-data.xlsx', 'employee-data.xlsx');
echo 'parsed_rows=' . count($rows) . PHP_EOL;
foreach ($rows as $i => $row) {
    echo ($i + 1) . ': ' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
