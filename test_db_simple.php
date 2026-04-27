<?php
require_once 'php/bootstrap.php';

try {
    $db = db();
    echo "Database connection: OK\n";
    
    $userCount = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo "Users: $userCount\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
