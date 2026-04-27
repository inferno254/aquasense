<?php
require_once 'bootstrap.php';
$pdo = db();

echo "=== USERS ===\n";
$stmt = $pdo->query('SELECT user_id, name, email, role FROM users');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

echo "\n=== FARMS ===\n";
$stmt = $pdo->query('SELECT farm_id, user_id, location, size, crop_type FROM farms');
$farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($farms);

echo "\n=== ALERTS ===\n";
$stmt = $pdo->query('SELECT alert_id, farm_id, alert_type, message, resolved FROM system_alerts LIMIT 10');
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($alerts);
