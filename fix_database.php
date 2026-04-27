<?php
/**
 * Clean and Fix Database
 * Removes duplicates and sets correct crop types
 */

// Define uuidv4 function before bootstrap
function uuidv4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

require_once 'php/bootstrap.php';

try {
    $db = db();
    
    echo "=== AquaSense Database Cleanup ===\n\n";
    
    // Delete all farms first
    echo "Clearing existing farms...\n";
    $db->exec("DELETE FROM farms");
    echo "✓ Farms cleared\n";
    
    // Delete all sensor readings (will be regenerated)
    echo "Clearing sensor readings...\n";
    $db->exec("DELETE FROM sensor_readings");
    echo "✓ Sensor readings cleared\n";
    
    // Delete all irrigation events (will be regenerated)
    echo "Clearing irrigation events...\n";
    $db->exec("DELETE FROM irrigation_events");
    echo "✓ Irrigation events cleared\n";
    
    // Insert correct farms
    echo "\nInserting correct farms...\n";
    $farms = [
        ['550e8400-e29b-41d4-a716-446655440002', '22222222-2222-2222-2222-222222222222', 'Gataka Farm', 1.5, 'Maize'],
        ['550e8400-e29b-41d4-a716-446655440005', '22222222-2222-2222-2222-222222222222', 'Hardy Farm', 2.0, 'Beans'],
    ];
    
    $stmt = $db->prepare("INSERT INTO farms (farm_id, user_id, location, size, crop_type) VALUES (?, ?, ?, ?, ?)");
    foreach ($farms as $farm) {
        $stmt->execute($farm);
        echo "  ✓ {$farm[2]}: {$farm[4]}\n";
    }
    
    // Generate sensor readings for both farms
    echo "\nGenerating sensor readings...\n";
    $insertReading = $db->prepare("INSERT INTO sensor_readings (reading_id, farm_id, soil_moisture, temperature, humidity, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
    
    $profiles = [
        '550e8400-e29b-41d4-a716-446655440002' => [28.0, 26.0, 62.0, 0.8, 0.1, 0.4], // Gataka Farm - starting low moisture
        '550e8400-e29b-41d4-a716-446655440005' => [45.0, 25.0, 58.0, 0.5, 0.1, 0.3], // Hardy Farm - starting higher
    ];
    
    foreach ($profiles as $farmId => [$soil, $temp, $humidity, $soilStep, $tempStep, $humidityStep]) {
        for ($i = 0; $i < 24; $i++) {
            $timestamp = gmdate('c', time() - ($i * 3600));
            $insertReading->execute([
                uuidv4(),
                $farmId,
                round($soil + ($i * $soilStep), 1),
                round($temp + ($i * $tempStep), 1),
                round($humidity + ($i * $humidityStep), 1),
                $timestamp,
            ]);
        }
        echo "  ✓ Generated 24 readings for farm {$farmId}\n";
    }
    
    // Generate irrigation events
    echo "\nGenerating irrigation events...\n";
    $insertEvent = $db->prepare("INSERT INTO irrigation_events (event_id, farm_id, trigger_type, start_time, end_time, water_used, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($farms as $farm) {
        $eventId = uuidv4();
        $startTime = gmdate('c', time() - 7200); // 2 hours ago
        $endTime = gmdate('c', time() - 6600); // 1 hour 50 minutes ago
        
        $insertEvent->execute([
            $eventId,
            $farm[0],
            'automatic',
            $startTime,
            $endTime,
            150.0,
            'completed'
        ]);
        echo "  ✓ Irrigation event for {$farm[2]}\n";
    }
    
    // Verify final state
    echo "\n=== Final Database State ===\n";
    echo "Farms:\n";
    $stmt = $db->prepare("SELECT farm_id, location, crop_type, size FROM farms");
    $stmt->execute();
    $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($farms as $farm) {
        echo "  - {$farm['location']}: {$farm['crop_type']} ({$farm['size']} ha)\n";
    }
    
    echo "\nSensor readings count:\n";
    $count = $db->query("SELECT COUNT(*) FROM sensor_readings")->fetchColumn();
    echo "  - Total: {$count} readings\n";
    
    echo "\nIrrigation events count:\n";
    $count = $db->query("SELECT COUNT(*) FROM irrigation_events")->fetchColumn();
    echo "  - Total: {$count} events\n";
    
    echo "\n✅ Database cleanup and setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
