<?php
/**
 * Add test alerts to database
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
    
    echo "=== Adding Test Alerts ===\n\n";
    
    // Get farm IDs
    $stmt = $db->prepare("SELECT farm_id FROM farms");
    $stmt->execute();
    $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($farms as $farm) {
        $farmId = $farm['farm_id'];
        
        // Add a low moisture alert
        $alertId1 = uuidv4_php();
        $stmt = $db->prepare('INSERT INTO system_alerts (alert_id, farm_id, alert_type, message, timestamp, resolved) VALUES (:alert_id, :farm_id, :alert_type, :message, :timestamp, :resolved)');
        $stmt->execute([
            ':alert_id' => $alertId1,
            ':farm_id' => $farmId,
            ':alert_type' => 'low_moisture',
            ':message' => 'Soil moisture below threshold (28%)',
            ':timestamp' => date('Y-m-d H:i:s', time() - 3600),
            ':resolved' => 0
        ]);
        
        // Add an irrigation alert
        $alertId2 = uuidv4_php();
        $stmt->execute([
            ':alert_id' => $alertId2,
            ':farm_id' => $farmId,
            ':alert_type' => 'irrigation_completed',
            ':message' => 'Automatic irrigation completed successfully',
            ':timestamp' => date('Y-m-d H:i:s', time() - 7200),
            ':resolved' => 1
        ]);
        
        echo "Added 2 alerts for farm: {$farmId}\n";
    }
    
    echo "\n✅ Test alerts added successfully\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
