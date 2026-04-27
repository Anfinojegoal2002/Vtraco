<?php
ini_set('session.save_path', 'C:/Windows/Temp');
require 'C:/xampp/htdocs/vtraco/src/bootstrap.php';
require 'C:/xampp/htdocs/vtraco/src/core.php';
$emails = [
 'anfinojegoal@gmail.com',
 'aathiajayb@gmail.com',
 'm.anbuselvam1512@gmail.com',
 'srividhyasarani@gmail.com'
];
foreach ($emails as $email) {
  $stmt = db()->prepare('SELECT id, role, emp_id, email FROM users WHERE LOWER(email)=LOWER(:email)');
  $stmt->execute(['email'=>$email]);
  $rows = $stmt->fetchAll();
  echo $email . ': ' . json_encode($rows) . PHP_EOL;
}
