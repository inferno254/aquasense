<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Simulate sensor readings that change every 5 seconds
function generateSensorData() {
    $moistureBase = 65;
    $tempBase = 28;
    $humidityBase = 70;
    
    // Add random variation
    $moisture = $moistureBase + (rand(-10, 10) / 10);
    $temp = $tempBase + (rand(-5, 5) / 10);
    $humidity = $humidityBase + (rand(-15, 15) / 10);
    
    // Ensure values are in reasonable ranges
    $moisture = max(20, min(95, $moisture));
    $temp = max(15, min(40, $temp));
    $humidity = max(30, min(95, $humidity));
    
    // Battery and solar simulation
    $hour = date('H');
    $batteryLevel = $hour >= 6 && $hour <= 18 ? 75 + rand(-5, 5) : 65 + rand(-5, 5);
    $solarLevel = $hour >= 6 && $hour <= 18 ? 80 + rand(-15, 15) : 15 + rand(-5, 5);
    
    return [
        'soilMoisture' => round($moisture, 1),
        'temperature' => round($temp, 1),
        'humidity' => round($humidity, 1),
        'batteryLevel' => max(20, min(100, $batteryLevel)),
        'solarLevel' => max(0, min(100, $solarLevel)),
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => $moisture < 30 ? 'dry' : ($moisture < 50 ? 'low' : 'optimal')
    ];
}

// Generate historical data for 24-hour trend (48 data points = 30 min intervals)
function generateHistoricalData() {
    $data = [];
    $baseMoisture = 65;
    $now = time();
    
    for ($i = 48; $i >= 0; $i--) {
        $time = $now - ($i * 1800); // 30 minutes ago
        $hour = date('H', $time);
        
        // Simulate moisture changes based on time of day
        if ($hour >= 6 && $hour <= 18) {
            // Daytime - moisture decreases slightly
            $moisture = $baseMoisture - ($i * 0.3) + (rand(-5, 5) / 10);
        } else {
            // Nighttime - moisture stays stable or increases slightly
            $moisture = $baseMoisture + ($i * 0.1) + (rand(-3, 3) / 10);
        }
        
        $moisture = max(20, min(95, $moisture));
        
        $data[] = [
            'timestamp' => date('Y-m-d H:i:s', $time),
            'soilMoisture' => round($moisture, 1),
            'temperature' => round(25 + rand(-5, 5) / 10, 1),
            'humidity' => round(65 + rand(-10, 10) / 10, 1)
        ];
    }
    
    return $data;
}

// Generate irrigation events
function generateIrrigationEvents() {
    $events = [];
    $now = time();
    
    // Add some past irrigation events
    $events[] = [
        'timestamp' => date('Y-m-d H:i:s', $now - 7200), // 2 hours ago
        'duration' => 12,
        'type' => 'Manual',
        'zone' => 'Zone A',
        'volume' => 420,
        'triggeredBy' => 'Manual'
    ];
    
    $events[] = [
        'timestamp' => date('Y-m-d H:i:s', $now - 28800), // 8 hours ago
        'duration' => 15,
        'type' => 'Auto',
        'zone' => 'Zone B',
        'volume' => 525,
        'triggeredBy' => 'Auto'
    ];
    
    return $events;
}

// Generate system alerts
function generateAlerts() {
    $alerts = [];
    $now = time();
    
    // Add some alerts
    $alerts[] = [
        'alertType' => 'System',
        'message' => 'System check completed successfully',
        'resolved' => true,
        'timestamp' => date('Y-m-d H:i:s', $now - 60)
    ];
    
    $sensorData = generateSensorData();
    if ($sensorData['soilMoisture'] < 35) {
        $alerts[] = [
            'alertType' => 'Moisture',
            'message' => 'Soil moisture below threshold',
            'resolved' => false,
            'timestamp' => date('Y-m-d H:i:s', $now - 300)
        ];
    }
    
    return $alerts;
}

// Handle different request types
$action = $_GET['action'] ?? 'current';

switch ($action) {
    case 'current':
        echo json_encode(['data' => generateSensorData()]);
        break;
    case 'history':
        echo json_encode(['data' => generateHistoricalData()]);
        break;
    case 'events':
        echo json_encode(['data' => generateIrrigationEvents()]);
        break;
    case 'alerts':
        echo json_encode(['data' => generateAlerts()]);
        break;
    case 'all':
        echo json_encode([
            'current' => generateSensorData(),
            'history' => generateHistoricalData(),
            'events' => generateIrrigationEvents(),
            'alerts' => generateAlerts()
        ]);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}
