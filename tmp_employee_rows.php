<?php
require 'C:/xampp/htdocs/vtraco/src/bootstrap.php';
$rows = attendance_report_rows('C:/Users/hp/Downloads/employee-data.xlsx', 'employee-data.xlsx');
echo 'raw_rows=' . count($rows) . PHP_EOL;
foreach ($rows as $i => $row) {
    echo ($i + 1) . ': ' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
