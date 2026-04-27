<?php
/**
 * Generate Historical Irrigation Events and System Alerts
 * Run this in browser: http://localhost:8000/php/generate_demo_data.php
 */

header('Content-Type: text/plain');

echo "=== AquaSense Demo Data Generator ===\n\n";

// MySQL connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'aquasense';

try {
    $mysqli = new mysqli($host, $user, $pass, $dbname);
    if ($mysqli->connect_error) {
        die("   ✗ MySQL connection failed: " . $mysqli->connect_error . "\n");
    }
    echo "✓ Connected to MySQL\n\n";
} catch (Exception $e) {
    die("   ✗ Error: " . $e->getMessage() . "\n");
}

// Get farm IDs
$farmIds = [];
$result = $mysqli->query("SELECT farm_id FROM farms");
while ($row = $result->fetch_assoc()) {
    $farmIds[] = $row['farm_id'];
}

if (empty($farmIds)) {
    die("✗ No farms found. Please run mysql_setup.php first.\n");
}

echo "Found " . count($farmIds) . " farms\n\n";

// Helper function to generate UUID
function generateUuid() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Clear existing data
echo "1. Clearing existing demo data...\n";
$mysqli->query("DELETE FROM irrigation_events");
$mysqli->query("DELETE FROM system_alerts");
echo "   ✓ Cleared existing data\n\n";

// Generate irrigation events for past 30 days
echo "2. Generating irrigation events (past 30 days)...\n";
$irrigationStmt = $mysqli->prepare("INSERT INTO irrigation_events (event_id, farm_id, trigger_type, start_time, end_time, water_used, status) VALUES (?, ?, ?, ?, ?, ?, ?)");

$eventCount = 0;
$now = time();

foreach ($farmIds as $farmId) {
    // Generate 2-3 events per day for 30 days
    for ($day = 0; $day < 30; $day++) {
        $dayStart = $now - ($day * 86400);
        
        // Morning irrigation (automatic)
        $eventId = generateUuid();
        $startTime = date('Y-m-d H:i:s', $dayStart + 3600); // 1 AM
        $duration = rand(10, 20); // 10-20 minutes
        $endTime = date('Y-m-d H:i:s', strtotime($startTime) + ($duration * 60));
        $waterUsed = $duration * 35; // ~35L per minute
        $triggerType = rand(0, 10) > 3 ? 'automatic' : 'manual'; // 70% automatic
        
        $irrigationStmt->bind_param("ssssdds", $eventId, $farmId, $triggerType, $startTime, $endTime, $waterUsed, $status);
        $status = 'completed';
        $irrigationStmt->execute();
        $eventCount++;
        
        // Afternoon irrigation (if needed)
        if (rand(0, 10) > 5) {
            $eventId = generateUuid();
            $startTime = date('Y-m-d H:i:s', $dayStart + 46800); // 1 PM
            $duration = rand(8, 15);
            $endTime = date('Y-m-d H:i:s', strtotime($startTime) + ($duration * 60));
            $waterUsed = $duration * 35;
            $triggerType = rand(0, 10) > 3 ? 'automatic' : 'manual';
            
            $irrigationStmt->bind_param("ssssdds", $eventId, $farmId, $triggerType, $startTime, $endTime, $waterUsed, $status);
            $status = 'completed';
            $irrigationStmt->execute();
            $eventCount++;
        }
    }
}

echo "   ✓ Generated $eventCount irrigation events\n\n";

// Generate system alerts for past 30 days
echo "3. Generating system alerts (past 30 days)...\n";
$alertStmt = $mysqli->prepare("INSERT INTO system_alerts (alert_id, farm_id, alert_type, message, timestamp, resolved, resolved_at, sms_sent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

if (!$alertStmt) {
    die("✗ Failed to prepare alert statement: " . $mysqli->error . "\n");
}

$alertTypes = [
    'low_moisture' => 'Soil moisture dropped below 35% threshold',
    'temp_high' => 'Temperature exceeded 35°C',
    'battery_low' => 'Battery level below 20%',
    'irrigation_started' => 'Irrigation started automatically',
    'irrigation_complete' => 'Irrigation completed successfully',
    'sensor_offline' => 'Sensor signal lost',
    'sensor_restored' => 'Sensor signal restored',
    'valve_malfunction' => 'Valve not responding',
    'water_pressure_low' => 'Water pressure below normal',
    'system_restart' => 'System restarted successfully'
];

$alertCount = 0;
$smsSentCount = 0;

foreach ($farmIds as $farmId) {
    // Generate 5-8 alerts per day for 30 days
    for ($day = 0; $day < 30; $day++) {
        $dayStart = $now - ($day * 86400);
        $dailyAlerts = rand(5, 8);
        
        for ($i = 0; $i < $dailyAlerts; $i++) {
            $alertId = generateUuid();
            $alertType = array_rand($alertTypes);
            $message = $alertTypes[$alertType];
            
            // Add farm location to message
            $farmResult = $mysqli->query("SELECT location FROM farms WHERE farm_id = '$farmId' LIMIT 1");
            $farmRow = $farmResult->fetch_assoc();
            $location = $farmRow['location'];
            $message .= " at $location";
            
            $timestamp = date('Y-m-d H:i:s', $dayStart + rand(0, 86400));
            
            // 70% of alerts are resolved
            $resolved = rand(0, 10) > 3 ? 1 : 0;
            $resolvedAt = $resolved ? date('Y-m-d H:i:s', strtotime($timestamp) + rand(300, 3600)) : null;
            
            // 80% of critical alerts have SMS sent
            $smsSent = rand(0, 10) > 2 ? 1 : 0;
            if ($smsSent) $smsSentCount++;
            
            $alertStmt->bind_param("ssssisis", $alertId, $farmId, $alertType, $message, $timestamp, $resolved, $resolvedAt, $smsSent);
            $alertStmt->execute();
            $alertCount++;
        }
    }
}

echo "   ✓ Generated $alertCount system alerts\n";
echo "   ✓ $smsSentCount alerts with SMS sent\n\n";

// Update app state
echo "4. Updating app state...\n";
$mysqli->query("DELETE FROM app_state");
$mysqli->query("INSERT INTO app_state (`key`, value) VALUES ('last_sms_sent_at', '" . date('Y-m-d H:i:s', $now - 1800) . "')");
$mysqli->query("INSERT INTO app_state (`key`, value) VALUES ('total_irrigation_events', '$eventCount')");
$mysqli->query("INSERT INTO app_state (`key`, value) VALUES ('total_alerts', '$alertCount')");
$mysqli->query("INSERT INTO app_state (`key`, value) VALUES ('sms_sent_count', '$smsSentCount')");
echo "   ✓ App state updated\n\n";

$mysqli->close();

echo "=== DEMO DATA GENERATION COMPLETE ===\n\n";
echo "Summary:\n";
echo "  Irrigation Events: $eventCount\n";
echo "  System Alerts: $alertCount\n";
echo "  SMS Sent: $smsSentCount\n";
echo "  Farms: " . count($farmIds) . "\n";
echo "  Time Range: Past 30 days\n\n";
echo "Next steps:\n";
echo "1. Refresh dashboard: http://localhost:8000/dashboard.html\n";
echo "2. View alerts with SMS status\n";
echo "3. Check irrigation history\n";
