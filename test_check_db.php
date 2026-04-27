<?php
require_once __DIR__ . '/php/bootstrap.php';
$pdo = db();
echo "<h2>DB Connection OK!</h2>";
echo "=== USERS ===<br>";
$stmt = $pdo->query('SELECT user_id, name, email, role FROM users LIMIT 3');
print_r($stmt->fetchAll());
echo "<br>=== FARMS ===<br>";
$stmt = $pdo->query('SELECT farm_id, user_id, location, size, crop_type FROM farms LIMIT 3');
print_r($stmt->fetchAll());
echo "<br>=== ALERTS ===<br>";
$stmt = $pdo->query('SELECT alert_id, farm_id, alert_type, message, resolved FROM system_alerts LIMIT 3');
print_r($stmt->fetchAll());
?>
