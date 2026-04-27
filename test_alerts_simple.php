<?php
/**
 * Simple test to check if alerts can be fetched via API
 */

header('Content-Type: application/json');

try {
    $db = new PDO('mysql:host=localhost;dbname=aquasense', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get farm ID from query param
    $farmId = $_GET['farm_id'] ?? '550e8400-e29b-41d4-a716-446655440002';
    
    $query = 'SELECT alert_id, alert_type, message, timestamp, resolved FROM system_alerts WHERE farm_id = :farm_id ORDER BY timestamp DESC LIMIT 20';
    $stmt = $db->prepare($query);
    $stmt->execute([':farm_id' => $farmId]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = array_map(function($row) {
        return [
            'alertId' => $row['alert_id'],
            'alertType' => $row['alert_type'],
            'message' => $row['message'],
            'timestamp' => $row['timestamp'],
            'resolved' => (bool) $row['resolved'],
        ];
    }, $alerts);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
