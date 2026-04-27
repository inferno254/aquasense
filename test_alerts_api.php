<?php
/**
 * Test alerts API endpoint
 */

try {
    $db = new PDO('mysql:host=localhost;dbname=aquasense', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Testing Alerts API ===\n\n";
    
    // Get farm ID
    $stmt = $db->prepare("SELECT farm_id FROM farms LIMIT 1");
    $stmt->execute();
    $farm = $stmt->fetch();
    $farmId = $farm['farm_id'];
    
    echo "Testing alerts for farm: {$farmId}\n\n";
    
    // Query alerts
    $query = 'SELECT alert_id, alert_type, message, timestamp, resolved FROM system_alerts WHERE farm_id = :farm_id ORDER BY timestamp DESC LIMIT 20';
    $stmt = $db->prepare($query);
    $stmt->execute([':farm_id' => $farmId]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($alerts) . " alerts:\n";
    foreach ($alerts as $alert) {
        $resolved = $alert['resolved'] ? 'Resolved' : 'Open';
        echo "  - {$alert['alert_type']}: {$alert['message']} ({$resolved})\n";
    }
    
    echo "\n✅ Test completed\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
