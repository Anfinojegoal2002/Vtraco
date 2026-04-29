<?php
$ch = curl_init('http://localhost/vtraco/index.php?action=super_admin_get_data');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// We don't have the session cookie here, so it will likely redirect to landing.
$response = curl_exec($ch);
echo "Response Length: " . strlen($response) . PHP_EOL;
echo "Response Start: " . substr($response, 0, 100) . PHP_EOL;
