<?php
header('Content-Type: text/plain');

$mysqli = new mysqli('localhost', 'root', '', 'aquasense');

echo "=== AquaSense Data Check ===\n\n";

echo "Irrigation Events: " . $mysqli->query("SELECT COUNT(*) FROM irrigation_events")->fetch_row()[0] . "\n";
echo "System Alerts: " . $mysqli->query("SELECT COUNT(*) FROM system_alerts")->fetch_row()[0] . "\n";
echo "Alerts with SMS sent: " . $mysqli->query("SELECT COUNT(*) FROM system_alerts WHERE sms_sent = 1")->fetch_row()[0] . "\n";

echo "\nRecent Alerts:\n";
$result = $mysqli->query("SELECT alert_type, message, sms_sent, resolved FROM system_alerts ORDER BY timestamp DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "  [{$row['alert_type']}] {$row['message']} - SMS: " . ($row['sms_sent'] ? 'Yes' : 'No') . " - Resolved: " . ($row['resolved'] ? 'Yes' : 'No') . "\n";
}
