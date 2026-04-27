<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = db();
echo "DB init complete!<br>";
echo "Users: " . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . "<br>";
echo "Farms: " . $pdo->query('SELECT COUNT(*) FROM farms')->fetchColumn() . "<br>";
echo "Alerts: " . $pdo->query('SELECT COUNT(*) FROM system_alerts')->fetchColumn() . "<br>";
?>
