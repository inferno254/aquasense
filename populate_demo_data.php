<?php
/**
 * Populate database with realistic irrigation events and alerts
 */

function uuidv4_php(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

try {
    $db = new PDO('mysql:host=localhost;dbname=aquasense', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Populating Demo Data ===\n\n";
    
    // Get farm IDs
    $stmt = $db->prepare("SELECT farm_id, location FROM farms");
    $stmt->execute();
    $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clear existing data
    echo "Clearing existing irrigation events and alerts...\n";
    $db->exec("DELETE FROM irrigation_events");
    $db->exec("DELETE FROM system_alerts");
    
    $now = time();
    
    foreach ($farms as $farm) {
        $farmId = $farm['farm_id'];
        $location = $farm['location'];
        
        echo "\nPopulating data for: {$location}\n";
        
        // Add irrigation events (mix of manual and automatic)
        $irrigationEvents = [
            [
                'type' => 'automatic',
                'trigger' => 'Threshold Breach',
                'time' => $now - 7200, // 2 hours ago
                'duration' => 15,
                'water' => 180,
                'status' => 'completed'
            ],
            [
                'type' => 'manual',
                'trigger' => 'Manual',
                'time' => $now - 14400, // 4 hours ago
                'duration' => 20,
                'water' => 240,
                'status' => 'completed'
            ],
            [
                'type' => 'automatic',
                'trigger' => 'Threshold Breach',
                'time' => $now - 28800, // 8 hours ago
                'duration' => 12,
                'water' => 150,
                'status' => 'completed'
            ],
            [
                'type' => 'manual',
                'trigger' => 'Manual',
                'time' => $now - 43200, // 12 hours ago
                'duration' => 18,
                'water' => 210,
                'status' => 'completed'
            ],
            [
                'type' => 'automatic',
                'trigger' => 'Threshold Breach',
                'time' => $now - 86400, // 24 hours ago
                'duration' => 15,
                'water' => 180,
                'status' => 'completed'
            ],
        ];
        
        foreach ($irrigationEvents as $event) {
            $eventId = uuidv4_php();
            $startTime = date('Y-m-d H:i:s', $event['time']);
            $endTime = date('Y-m-d H:i:s', $event['time'] + ($event['duration'] * 60));
            
            $stmt = $db->prepare('INSERT INTO irrigation_events (event_id, farm_id, trigger_type, start_time, end_time, water_used, status) VALUES (:event_id, :farm_id, :trigger_type, :start_time, :end_time, :water_used, :status)');
            $stmt->execute([
                ':event_id' => $eventId,
                ':farm_id' => $farmId,
                ':trigger_type' => $event['type'],
                ':start_time' => $startTime,
                ':end_time' => $endTime,
                ':water_used' => $event['water'],
                ':status' => $event['status']
            ]);
            
            echo "  - Added {$event['type']} irrigation: {$event['water']}L, {$event['duration']}min\n";
        }
        
        // Add realistic alerts
        $alerts = [
            [
                'type' => 'low_moisture',
                'message' => 'Soil moisture critically low at 28%. Auto-irrigation triggered.',
                'time' => $now - 7200,
                'resolved' => 1
            ],
            [
                'type' => 'irrigation_completed',
                'message' => 'Automatic irrigation completed successfully. 180L water delivered.',
                'time' => $now - 7050,
                'resolved' => 1
            ],
            [
                'type' => 'low_battery',
                'message' => 'Sensor battery at 15%. Please charge or replace battery soon.',
                'time' => $now - 3600,
                'resolved' => 0
            ],
            [
                'type' => 'manual_irrigation',
                'message' => 'Manual irrigation started by user. 240L water scheduled.',
                'time' => $now - 14400,
                'resolved' => 1
            ],
            [
                'type' => 'sensor_offline',
                'message' => 'Sensor temporarily offline. Connection restored after 5 minutes.',
                'time' => $now - 21600,
                'resolved' => 1
            ],
            [
                'type' => 'low_moisture',
                'message' => 'Soil moisture below optimal range (32%). Consider irrigation.',
                'time' => $now - 28800,
                'resolved' => 1
            ],
            [
                'type' => 'high_temperature',
                'message' => 'Temperature elevated at 35°C. Monitor crop stress.',
                'time' => $now - 57600,
                'resolved' => 1
            ],
            [
                'type' => 'irrigation_completed',
                'message' => 'Manual irrigation completed. 210L water delivered successfully.',
                'time' => $now - 43200,
                'resolved' => 1
            ],
        ];
        
        foreach ($alerts as $alert) {
            $alertId = uuidv4_php();
            $timestamp = date('Y-m-d H:i:s', $alert['time']);
            
            $stmt = $db->prepare('INSERT INTO system_alerts (alert_id, farm_id, alert_type, message, timestamp, resolved) VALUES (:alert_id, :farm_id, :alert_type, :message, :timestamp, :resolved)');
            $stmt->execute([
                ':alert_id' => $alertId,
                ':farm_id' => $farmId,
                ':alert_type' => $alert['type'],
                ':message' => $alert['message'],
                ':timestamp' => $timestamp,
                ':resolved' => $alert['resolved']
            ]);
            
            $status = $alert['resolved'] ? 'Resolved' : 'Open';
            echo "  - Added alert: {$alert['type']} ({$status})\n";
        }
    }
    
    echo "\n✅ Demo data populated successfully\n";
    echo "Total irrigation events: " . (count($farms) * 5) . "\n";
    echo "Total alerts: " . (count($farms) * 8) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
