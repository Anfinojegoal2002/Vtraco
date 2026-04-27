<?php
require 'C:/xampp/htdocs/vtraco/vendor/autoload.php';
$path = 'C:/Users/hp/Downloads/employee-data.xlsx';
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->rangeToArray('A1:Z5', null, true, true, false);
foreach ($rows as $index => $row) {
    echo ($index + 1) . ': ' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
