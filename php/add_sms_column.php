<?php
/**
 * Add sms_sent column to system_alerts table
 */

header('Content-Type: text/plain');

$mysqli = new mysqli('localhost', 'root', '', 'aquasense');

echo "=== Adding sms_sent column ===\n\n";

// Check if column exists
$result = $mysqli->query("SHOW COLUMNS FROM system_alerts LIKE 'sms_sent'");
if ($result->num_rows > 0) {
    echo "✓ sms_sent column already exists\n";
} else {
    // Add the column
    $mysqli->query("ALTER TABLE system_alerts ADD COLUMN sms_sent TINYINT(1) NOT NULL DEFAULT 0 AFTER resolved_at");
    echo "✓ Added sms_sent column\n";
}

echo "\nDone.\n";
