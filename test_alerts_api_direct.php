<?php
/**
 * Direct test of the alerts API endpoint with authentication bypass
 */

header('Content-Type: application/json');

try {
    require_once 'php/bootstrap.php';
    
    $db = db();
    
    // Get farm ID from query param
    $farmId = $_GET['farm_id'] ?? '550e8400-e29b-41d4-a716-446655440002';
    
    echo json_encode([
        'farm_id' => $farmId,
        'query' => 'SELECT alert_id, alert_type, message, timestamp, resolved FROM system_alerts WHERE farm_id = :farm_id ORDER BY timestamp DESC LIMIT 20'
    ]);
    
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
    
    echo json_encode(['alerts' => $result, 'count' => count($result)]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
