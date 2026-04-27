<?php
/**
 * Check database for irrigation events and alerts
 */

require_once 'php/bootstrap.php';

try {
    $db = db();
    
    echo "=== Checking Database Data ===\n\n";
    
    // Check farms
    echo "Farms in database:\n";
    $stmt = $db->prepare("SELECT farm_id, location, crop_type FROM farms");
    $stmt->execute();
    $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($farms as $farm) {
        echo "  - {$farm['location']} ({$farm['farm_id']}): {$farm['crop_type']}\n";
    }
    
    // Check irrigation events
    echo "\nIrrigation events:\n";
    $stmt = $db->prepare("SELECT event_id, farm_id, trigger_type, start_time, status FROM irrigation_events ORDER BY start_time DESC LIMIT 10");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($events) === 0) {
        echo "  No irrigation events found\n";
    } else {
        foreach ($events as $event) {
            echo "  - {$event['trigger_type']} at {$event['start_time']} ({$event['status']})\n";
        }
    }
    
    // Check system alerts
    echo "\nSystem alerts:\n";
    $stmt = $db->prepare("SELECT alert_id, farm_id, alert_type, message, timestamp, resolved FROM system_alerts ORDER BY timestamp DESC LIMIT 10");
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($alerts) === 0) {
        echo "  No system alerts found\n";
    } else {
        foreach ($alerts as $alert) {
            $resolved = $alert['resolved'] ? 'Resolved' : 'Open';
            echo "  - {$alert['alert_type']}: {$alert['message']} ({$resolved})\n";
        }
    }
    
    // Check sensor readings
    echo "\nSensor readings count:\n";
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sensor_readings");
    $stmt->execute();
    $count = $stmt->fetch();
    echo "  Total: {$count['count']} readings\n";
    
    echo "\n✅ Check completed\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
