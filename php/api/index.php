<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

// SMS Alert Function - Uses Africa's Talking or similar SMS gateway
function send_sms_alert(string $phone, string $message): bool {
    // Configuration - these would come from environment variables in production
    $apiKey = getenv('SMS_API_KEY') ?: 'your_api_key_here';
    $username = getenv('SMS_USERNAME') ?: 'your_username';
    $senderId = getenv('SMS_SENDER_ID') ?: 'AquaSense';
    
    // Africa's Talking API endpoint
    $url = 'https://api.africastalking.com/version1/messaging';
    
    $postData = http_build_query([
        'username' => $username,
        'to' => $phone,
        'message' => $message,
        'from' => $senderId,
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'apikey: ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log SMS attempt for debugging
    error_log("SMS to $phone: $message (HTTP $httpCode)");
    
    return $httpCode === 201 || $httpCode === 200;
}

// Get user phone number for SMS alerts
function get_user_phone(string $userId): ?string {
    $stmt = db()->prepare('SELECT phone FROM users WHERE user_id = :user_id LIMIT 1');
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch();
    return $user ? $user['phone'] : null;
}

// Send SMS notification to farm owner
function notify_farm_owner(string $farmId, string $message): void {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT u.phone, u.user_id, f.location FROM farms f JOIN users u ON f.user_id = u.user_id WHERE f.farm_id = :farm_id LIMIT 1');
    $stmt->execute([':farm_id' => $farmId]);
    $owner = $stmt->fetch();
    
    if ($owner && !empty($owner['phone'])) {
        $fullMessage = "AquaSense Alert - {$owner['location']}: $message";
        send_sms_alert($owner['phone'], $fullMessage);
    }
}

// Check and trigger automatic irrigation based on thresholds
function check_auto_irrigation(string $farmId, array $sensorData): void {
    $autoEnabled = get_state('auto_mode_' . $farmId, '1') !== '0';
    if (!$autoEnabled) return;
    
    $moistureLow = (float) get_state('threshold_moisture_low_' . $farmId, '35');
    $moistureHigh = (float) get_state('threshold_moisture_high_' . $farmId, '70');
    $tempHigh = (float) get_state('threshold_temp_high_' . $farmId, '35');
    $batteryLow = (float) get_state('threshold_battery_' . $farmId, '20');
    
    $soilMoisture = $sensorData['soilMoisture'] ?? 50;
    $temperature = $sensorData['temperature'] ?? 25;
    $battery = $sensorData['batteryLevel'] ?? 100;
    
    // Check if already irrigating today
    $today = date('Y-m-d');
    $lastIrrigationKey = 'last_auto_irrigation_' . $farmId;
    $lastIrrigation = get_state($lastIrrigationKey, '1970-01-01');
    
    // Trigger irrigation if moisture is low AND not already done today
    if ($soilMoisture < $moistureLow && $lastIrrigation !== $today) {
        // Create irrigation event
        $pdo = db();
        $eventId = uuidv4_php();
        $stmt = $pdo->prepare('INSERT INTO irrigation_events (event_id, farm_id, event_type, duration_minutes, status, scheduled_time) VALUES (:event_id, :farm_id, :event_type, :duration, :status, :scheduled_time)');
        $stmt->execute([
            ':event_id' => $eventId,
            ':farm_id' => $farmId,
            ':event_type' => 'auto',
            ':duration' => 20,
            ':status' => 'pending',
            ':scheduled_time' => now_iso(),
        ]);
        
        // Mark as done today
        set_state($lastIrrigationKey, $today);
        
        // Send SMS notification
        notify_farm_owner($farmId, "Umwagiliaji wa kiotomatiki umeanzishwa. Unyevu wa udongo ni $soilMoisture%. Maji yatanzwa kwa dakika 20.");
        
        // Create system alert
        $alertId = uuidv4_php();
        $alertStmt = $pdo->prepare('INSERT INTO system_alerts (alert_id, farm_id, alert_type, severity, message, created_at) VALUES (:alert_id, :farm_id, :type, :severity, :message, :created_at)');
        $alertStmt->execute([
            ':alert_id' => $alertId,
            ':farm_id' => $farmId,
            ':type' => 'auto_irrigation_triggered',
            ':severity' => 'info',
            ':message' => "Automatic irrigation started. Soil moisture at $soilMoisture%",
            ':created_at' => now_iso(),
        ]);
    }
    
    // Send battery low alert
    if ($battery < $batteryLow) {
        notify_farm_owner($farmId, "Tahadhari: Betri ya sensa ni chini ($battery%). Tafadhali chaji mfumo wa sensa.");
    }
}

// Helper function to get farm details
function get_farm_by_id(string $farmId): ?array {
    $stmt = db()->prepare('SELECT * FROM farms WHERE farm_id = :farm_id LIMIT 1');
    $stmt->execute([':farm_id' => $farmId]);
    return $stmt->fetch() ?: null;
}

// Helper function to get farm sensors
function get_farm_sensors(string $farmId): array {
    $stmt = db()->prepare('SELECT * FROM farm_sensors WHERE farm_id = :farm_id');
    $stmt->execute([':farm_id' => $farmId]);
    return $stmt->fetchAll();
}

// Helper function to get irrigation schedule
function get_farm_irrigation_schedule(string $farmId): array {
    $stmt = db()->prepare('SELECT * FROM irrigation_events WHERE farm_id = :farm_id ORDER BY scheduled_time DESC LIMIT 10');
    $stmt->execute([':farm_id' => $farmId]);
    return $stmt->fetchAll();
}

// Helper function to update irrigation schedule
function update_irrigation_schedule(string $farmId, array $schedule): void {
    $pdo = db();
    foreach ($schedule as $event) {
        if (isset($event['eventId'])) {
            $stmt = $pdo->prepare('UPDATE irrigation_events SET duration_minutes = :duration, scheduled_time = :scheduled_time WHERE event_id = :event_id AND farm_id = :farm_id');
            $stmt->execute([
                ':event_id' => $event['eventId'],
                ':farm_id' => $farmId,
                ':duration' => $event['durationMinutes'] ?? 15,
                ':scheduled_time' => $event['scheduledTime'] ?? now_iso(),
            ]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['ok' => true], 200);
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
// Strip both /php/api and /api prefixes
$path = preg_replace('#^/php/api#', '', $path);
$path = preg_replace('#^/api#', '', $path);
$path = trim($path, '/');
$segments = $path === '' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Alerts endpoint - NO AUTHENTICATION REQUIRED (for dashboard compatibility)
if (count($segments) >= 4 && $segments[0] === 'data' && $segments[1] === 'farms' && $segments[3] === 'alerts' && $method === 'GET') {
    $farmId = $segments[2];
    
    $query = 'SELECT alert_id, alert_type, message, timestamp, resolved FROM system_alerts WHERE farm_id = :farm_id';
    $params = [':farm_id' => $farmId];
    if (isset($_GET['resolved'])) {
        $query .= ' AND resolved = :resolved';
        $params[':resolved'] = $_GET['resolved'] === 'true' ? 1 : 0;
    }
    $query .= ' ORDER BY timestamp DESC LIMIT 20';
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $alerts = array_map(static fn(array $row) => [
        'alertId' => $row['alert_id'],
        'alertType' => $row['alert_type'],
        'message' => $row['message'],
        'timestamp' => $row['timestamp'],
        'resolved' => (bool) $row['resolved'],
    ], $stmt->fetchAll());
    json_response($alerts);
}

if ($segments === ['auth', 'login'] && $method === 'POST') {
    $input = json_input();
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => strtolower(trim((string) ($input['email'] ?? '')))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify((string) ($input['password'] ?? ''), $user['password_hash'])) {
        json_response(['message' => 'Invalid credentials'], 401);
    }

    $farmsStmt = db()->prepare('SELECT farm_id, location, crop_type, size FROM farms WHERE user_id = :user_id ORDER BY location');
    $farmsStmt->execute([':user_id' => $user['user_id']]);
    $farms = array_map('to_camel_case_row', $farmsStmt->fetchAll());

    json_response([
        'token' => issue_token($user),
        'user' => to_camel_case_row([
            'user_id' => $user['user_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role'],
        ]),
        'farms' => $farms,
        'message' => 'Login successful',
    ]);
}

if ($segments === ['auth', 'register'] && $method === 'POST') {
    $input = json_input();
    $name = trim((string) ($input['name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    $phone = trim((string) ($input['phone'] ?? ''));

    if ($name === '' || $email === '' || $password === '') {
        json_response(['message' => 'Name, email, and password are required.'], 400);
    }

    $check = db()->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
    $check->execute([':email' => $email]);
    if ($check->fetchColumn()) {
        json_response(['message' => 'An account with that email already exists.'], 409);
    }

    $userId = uuidv4_php();
    $farmId = uuidv4_php();

    $insertUser = db()->prepare('INSERT INTO users(user_id, name, email, phone, password_hash, role) VALUES(:user_id, :name, :email, :phone, :password_hash, :role)');
    $insertUser->execute([
        ':user_id' => $userId,
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone !== '' ? $phone : null,
        ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ':role' => 'farmer',
    ]);

    $insertFarm = db()->prepare('INSERT INTO farms(farm_id, user_id, location, size, crop_type) VALUES(:farm_id, :user_id, :location, :size, :crop_type)');
    $insertFarm->execute([
        ':farm_id' => $farmId,
        ':user_id' => $userId,
        ':location' => 'New Farm',
        ':size' => 1.0,
        ':crop_type' => 'Maize',
    ]);

    json_response([
        'token' => issue_token(['user_id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'farmer']),
        'user' => [
            'userId' => $userId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'role' => 'farmer',
        ],
        'farms' => [[
            'farmId' => $farmId,
            'location' => 'New Farm',
            'cropType' => 'Maize',
            'size' => 1.0,
        ]],
        'message' => 'Registration successful',
    ]);
}

if ($segments === ['notifications', 'status'] && $method === 'GET') {
    current_user();
    json_response(send_sms_status());
}

if ($segments === ['data', 'farms'] && $method === 'POST') {
    $user = current_user();
    $input = json_input();
    
    $location = trim((string) ($input['location'] ?? ''));
    $cropType = trim((string) ($input['cropType'] ?? ''));
    $size = (float) ($input['size'] ?? 1.0);
    
    if ($location === '' || $cropType === '') {
        json_response(['message' => 'Location and crop type are required.'], 400);
    }
    
    $farmId = uuidv4_php();
    
    $insertFarm = db()->prepare('INSERT INTO farms(farm_id, user_id, location, size, crop_type) VALUES(:farm_id, :user_id, :location, :size, :crop_type)');
    $insertFarm->execute([
        ':farm_id' => $farmId,
        ':user_id' => $user['user_id'],
        ':location' => $location,
        ':size' => $size,
        ':crop_type' => $cropType,
    ]);
    
    json_response([
        'farmId' => $farmId,
        'location' => $location,
        'cropType' => $cropType,
        'size' => $size,
        'message' => 'Farm created successfully',
    ]);
}

if ($segments === ['data', 'farms'] && $method === 'GET') {
    $user = current_user();
    $query = '
        SELECT
            f.farm_id,
            f.location,
            f.crop_type,
            f.size,
            (SELECT COUNT(*) FROM sensor_readings sr WHERE sr.farm_id = f.farm_id) AS sensor_count,
            (SELECT COUNT(*) FROM system_alerts sa WHERE sa.farm_id = f.farm_id AND sa.resolved = 0) AS open_alert_count,
            (SELECT soil_moisture FROM sensor_readings sr WHERE sr.farm_id = f.farm_id ORDER BY timestamp DESC LIMIT 1) AS latest_soil_moisture,
            (SELECT temperature FROM sensor_readings sr WHERE sr.farm_id = f.farm_id ORDER BY timestamp DESC LIMIT 1) AS latest_temperature,
            (SELECT humidity FROM sensor_readings sr WHERE sr.farm_id = f.farm_id ORDER BY timestamp DESC LIMIT 1) AS latest_humidity,
            (SELECT timestamp FROM sensor_readings sr WHERE sr.farm_id = f.farm_id ORDER BY timestamp DESC LIMIT 1) AS latest_timestamp
        FROM farms f
    ';

    $params = [];
    if (($user['role'] ?? 'farmer') !== 'admin') {
        $query .= ' WHERE f.user_id = :user_id';
        $params[':user_id'] = $user['user_id'];
    }
    $query .= ' ORDER BY f.location';

    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $farms = [];

    foreach ($stmt->fetchAll() as $row) {
        $status = 'Offline';
        $soil = $row['latest_soil_moisture'];
        $openAlerts = (int) $row['open_alert_count'];
        if ($openAlerts > 0) {
            $status = 'Attention';
        } elseif ($soil === null) {
            $status = 'Offline';
        } elseif ((float) $soil < 35) {
            $status = 'Dry';
        } elseif ((float) $soil < 55) {
            $status = 'Stable';
        } else {
            $status = 'Optimal';
        }

        $farms[] = [
            'farmId' => $row['farm_id'],
            'location' => $row['location'],
            'cropType' => $row['crop_type'],
            'size' => $row['size'] !== null ? (float) $row['size'] : null,
            'sensorCount' => (int) $row['sensor_count'],
            'openAlertCount' => $openAlerts,
            'latest' => $row['latest_timestamp'] ? [
                'soilMoisture' => $row['latest_soil_moisture'] !== null ? (float) $row['latest_soil_moisture'] : null,
                'temperature' => $row['latest_temperature'] !== null ? (float) $row['latest_temperature'] : null,
                'humidity' => $row['latest_humidity'] !== null ? (float) $row['latest_humidity'] : null,
                'timestamp' => $row['latest_timestamp'],
            ] : null,
            'status' => $status,
        ];
    }

    json_response($farms);
}

if (count($segments) >= 3 && $segments[0] === 'data' && $segments[1] === 'farms' && $method === 'GET') {
    $user = current_user();
    $farmId = $segments[2];
    ensure_farm_access($user, $farmId);

    if (($segments[3] ?? '') === 'sensors') {
        $hours = max(1, (int) ($_GET['hours'] ?? 24));
        $threshold = gmdate('c', time() - ($hours * 3600));
        $stmt = db()->prepare('SELECT timestamp, soil_moisture, temperature, humidity FROM sensor_readings WHERE farm_id = :farm_id AND timestamp >= :threshold ORDER BY timestamp DESC LIMIT 100');
        $stmt->execute([':farm_id' => $farmId, ':threshold' => $threshold]);
        $rows = array_map(static fn(array $row) => [
            'timestamp' => $row['timestamp'],
            'soilMoisture' => $row['soil_moisture'] !== null ? (float) $row['soil_moisture'] : null,
            'temperature' => $row['temperature'] !== null ? (float) $row['temperature'] : null,
            'humidity' => $row['humidity'] !== null ? (float) $row['humidity'] : null,
        ], $stmt->fetchAll());
        json_response([
            'latest' => $rows[0] ?? null,
            'all' => $rows,
        ]);
    }

    if (($segments[3] ?? '') === 'latest-reading') {
        $stmt = db()->prepare('SELECT soil_moisture, temperature, humidity, timestamp FROM sensor_readings WHERE farm_id = :farm_id ORDER BY timestamp DESC LIMIT 1');
        $stmt->execute([':farm_id' => $farmId]);
        $reading = $stmt->fetch();
        
        if ($reading) {
            json_response([
                'soilMoisture' => (float) $reading['soil_moisture'],
                'temperature' => (float) $reading['temperature'],
                'humidity' => (float) $reading['humidity'],
                'timestamp' => $reading['timestamp'],
            ]);
        } else {
            json_response(null, 404);
        }
    }

    if (($segments[3] ?? '') === 'events' || ($segments[3] ?? '') === 'irrigation-events') {
        $limit = max(1, min(50, (int) ($_GET['limit'] ?? 10)));
        $stmt = db()->prepare('SELECT event_id, trigger_type, start_time, end_time, water_used, status FROM irrigation_events WHERE farm_id = :farm_id ORDER BY start_time DESC LIMIT :limit');
        $stmt->bindValue(':farm_id', $farmId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $duration = null;
            if ($row['end_time']) {
                $duration = (int) round((strtotime($row['end_time']) - strtotime($row['start_time'])) / 60);
            }
            $events[] = [
                'eventId' => $row['event_id'],
                'triggerType' => $row['trigger_type'],
                'startTime' => $row['start_time'],
                'duration' => $duration,
                'waterUsed' => $row['water_used'] !== null ? (float) $row['water_used'] : null,
                'status' => $row['status'],
            ];
        }
        json_response($events);
    }
}

if ($segments === ['data', 'alerts'] && $method === 'GET') {
    $user = current_user();
    $limit = max(1, min(50, (int) ($_GET['limit'] ?? 10)));
    $query = '
        SELECT sa.alert_id, sa.alert_type, sa.message, sa.timestamp, sa.resolved, sa.farm_id, f.location AS farm_location
        FROM system_alerts sa
        JOIN farms f ON f.farm_id = sa.farm_id
    ';
    $params = [];
    if (($user['role'] ?? 'farmer') !== 'admin') {
        $query .= ' WHERE f.user_id = :user_id';
        $params[':user_id'] = $user['user_id'];
    }
    $query .= ' ORDER BY sa.timestamp DESC LIMIT :limit';
    $stmt = db()->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $alerts = array_map(static fn(array $row) => [
        'alertId' => $row['alert_id'],
        'alertType' => $row['alert_type'],
        'message' => $row['message'],
        'timestamp' => $row['timestamp'],
        'resolved' => (bool) $row['resolved'],
        'farmId' => $row['farm_id'],
        'farmLocation' => $row['farm_location'],
    ], $stmt->fetchAll());
    json_response($alerts);
}

// Irrigation auto-mode endpoints
if (count($segments) >= 4 && $segments[0] === 'irrigation' && $segments[1] === 'farms') {
    $user = current_user();
    $farmId = $segments[2];
    ensure_farm_access($user, $farmId);

    if ($segments[3] === 'auto-mode' && $method === 'GET') {
        // Get auto-mode status from app_state
        $key = "auto_mode_{$farmId}";
        $enabled = get_state($key) === 'true';
        json_response(['enabled' => $enabled]);
    }

    if ($segments[3] === 'auto-mode' && $method === 'PUT') {
        $input = json_input();
        $enabled = (bool) ($input['enabled'] ?? false);
        $key = "auto_mode_{$farmId}";
        set_state($key, $enabled ? 'true' : 'false');
        json_response(['enabled' => $enabled, 'message' => 'Auto mode updated']);
    }

    if ($segments[3] === 'irrigate' && $method === 'POST') {
        $input = json_input();
        $manual = (bool) ($input['manual'] ?? false);
        $duration = (int) ($input['duration'] ?? 12);
        $estimatedLitres = (int) ($input['estimatedLitres'] ?? 420);

        $eventId = uuidv4_php();
        $stmt = db()->prepare('INSERT INTO irrigation_events(event_id, farm_id, trigger_type, start_time, end_time, water_used, status) VALUES(:event_id, :farm_id, :trigger_type, :start_time, :end_time, :water_used, :status)');
        $stmt->execute([
            ':event_id' => $eventId,
            ':farm_id' => $farmId,
            ':trigger_type' => $manual ? 'manual' : 'automatic',
            ':start_time' => gmdate('c'),
            ':end_time' => gmdate('c', time() + ($duration * 60)),
            ':water_used' => $estimatedLitres,
            ':status' => 'in_progress',
        ]);
        json_response(['eventId' => $eventId, 'message' => 'Irrigation started']);
    }
}

if (count($segments) === 3 && $segments[0] === 'data' && $segments[1] === 'alerts' && $method === 'POST' && ($segments[2] ?? '') !== '') {
    current_user();
}

if (count($segments) === 4 && $segments[0] === 'data' && $segments[1] === 'alerts' && $segments[3] === 'resolve' && $method === 'POST') {
    $user = current_user();
    $alertId = $segments[2];
    $stmt = db()->prepare('SELECT alert_id, farm_id FROM system_alerts WHERE alert_id = :alert_id LIMIT 1');
    $stmt->execute([':alert_id' => $alertId]);
    $alert = $stmt->fetch();
    if (!$alert) {
        json_response(['message' => 'Alert not found.'], 404);
    }
    ensure_farm_access($user, $alert['farm_id']);
    $resolvedAt = now_iso();
    $update = db()->prepare('UPDATE system_alerts SET resolved = 1, resolved_at = :resolved_at WHERE alert_id = :alert_id');
    $update->execute([':resolved_at' => $resolvedAt, ':alert_id' => $alertId]);
    json_response(['message' => 'Alert resolved.', 'alertId' => $alertId, 'resolvedAt' => $resolvedAt]);
}

if (count($segments) >= 4 && $segments[0] === 'sensor' && $segments[1] === 'hardware' && $segments[3] === 'reading' && $method === 'POST') {
    $farmId = $segments[2];
    $sensorData = json_input();
    record_sensor_reading($farmId, $sensorData, false);
    // Check for automatic irrigation and alerts after recording
    check_auto_irrigation($farmId, $sensorData);
}

if (count($segments) >= 5 && $segments[0] === 'sensor' && $segments[1] === 'farms' && $segments[3] === 'sensor-readings' && $method === 'POST') {
    $user = current_user();
    $farmId = $segments[2];
    ensure_farm_access($user, $farmId);
    record_sensor_reading($farmId, json_input(), true);
}

if (count($segments) >= 5 && $segments[0] === 'irrigation' && $segments[1] === 'farms' && $segments[3] === 'irrigate' && $method === 'POST') {
    $user = current_user();
    $farmId = $segments[2];
    ensure_farm_access($user, $farmId);
    $input = json_input();
    $event = create_irrigation_event(
        $farmId,
        !empty($input['manual']),
        max(1, (int) ($input['duration'] ?? 10)),
        (float) ($input['estimatedLitres'] ?? 500)
    );
    json_response([
        'message' => 'Irrigation event created',
        'eventId' => $event['eventId'],
        'triggerType' => $event['triggerType'],
        'startedAt' => $event['startTime'],
    ]);
}

if (count($segments) >= 5 && $segments[0] === 'irrigation' && $segments[1] === 'farms' && $segments[3] === 'auto-mode') {
    $user = current_user();
    $farmId = $segments[2];
    ensure_farm_access($user, $farmId);
    $stateKey = 'auto_mode_' . $farmId;

    if ($method === 'GET') {
        json_response(['farmId' => $farmId, 'enabled' => get_state($stateKey, '1') !== '0']);
    }

    if ($method === 'PUT') {
        $input = json_input();
        $enabled = !empty($input['enabled']);
        set_state($stateKey, $enabled ? '1' : '0');
        json_response([
            'farmId' => $farmId,
            'enabled' => $enabled,
            'message' => $enabled ? 'Automatic irrigation enabled' : 'Automatic irrigation paused',
        ]);
    }
}

// Farm management endpoints
if (count($segments) >= 4 && $segments[0] === 'data' && $segments[1] === 'farms' && $segments[3] === 'details') {
    $user = current_user();
    $farmId = $segments[2];
    ensure_farm_access($user, $farmId);

    if ($method === 'GET') {
        $farm = get_farm_by_id($farmId);
        $sensors = get_farm_sensors($farmId);
        $irrigation = get_farm_irrigation_schedule($farmId);
        
        // Get latest sensor reading
        $latestStmt = db()->prepare('SELECT soil_moisture, temperature, humidity, timestamp FROM sensor_readings WHERE farm_id = :farm_id ORDER BY timestamp DESC LIMIT 1');
        $latestStmt->execute([':farm_id' => $farmId]);
        $latestReading = $latestStmt->fetch();
        
        json_response([
            'farmId' => $farm['farm_id'],
            'location' => $farm['location'],
            'cropType' => $farm['crop_type'],
            'size' => $farm['size'],
            'sensors' => $sensors,
            'irrigationSchedule' => $irrigation,
            'autoIrrigationEnabled' => get_state('auto_mode_' . $farmId, '1') !== '0',
            'thresholds' => [
                'soilMoistureLow' => get_state('threshold_moisture_low_' . $farmId, '35'),
                'soilMoistureHigh' => get_state('threshold_moisture_high_' . $farmId, '70'),
                'temperatureHigh' => get_state('threshold_temp_high_' . $farmId, '35'),
                'batteryLow' => get_state('threshold_battery_' . $farmId, '20'),
            ],
            'latestReading' => $latestReading ? [
                'soilMoisture' => (float) $latestReading['soil_moisture'],
                'temperature' => (float) $latestReading['temperature'],
                'humidity' => (float) $latestReading['humidity'],
                'timestamp' => $latestReading['timestamp'],
            ] : null,
        ]);
    }

    if ($method === 'PUT') {
        $input = json_input();
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE farms SET location = :location, crop_type = :crop_type, size = :size, updated_at = :updated_at WHERE farm_id = :farm_id');
        $stmt->execute([
            ':farm_id' => $farmId,
            ':location' => $input['location'] ?? '',
            ':crop_type' => $input['cropType'] ?? '',
            ':size' => (int) ($input['size'] ?? 0),
            ':updated_at' => now_iso(),
        ]);

        // Update thresholds if provided
        if (isset($input['thresholds'])) {
            $t = $input['thresholds'];
            if (isset($t['soilMoistureLow'])) set_state('threshold_moisture_low_' . $farmId, (string) $t['soilMoistureLow']);
            if (isset($t['soilMoistureHigh'])) set_state('threshold_moisture_high_' . $farmId, (string) $t['soilMoistureHigh']);
            if (isset($t['temperatureHigh'])) set_state('threshold_temp_high_' . $farmId, (string) $t['temperatureHigh']);
            if (isset($t['batteryLow'])) set_state('threshold_battery_' . $farmId, (string) $t['batteryLow']);
        }

        // Update irrigation schedule if provided
        if (isset($input['irrigationSchedule'])) {
            update_irrigation_schedule($farmId, $input['irrigationSchedule']);
        }

        json_response(['message' => 'Farm updated successfully', 'farmId' => $farmId]);
    }

    if ($method === 'DELETE') {
        $pdo = db();
        // Delete related data first
        $pdo->prepare('DELETE FROM sensor_readings WHERE farm_id = :farm_id')->execute([':farm_id' => $farmId]);
        $pdo->prepare('DELETE FROM irrigation_events WHERE farm_id = :farm_id')->execute([':farm_id' => $farmId]);
        $pdo->prepare('DELETE FROM system_alerts WHERE farm_id = :farm_id')->execute([':farm_id' => $farmId]);
        $pdo->prepare('DELETE FROM sensor_alerts WHERE farm_id = :farm_id')->execute([':farm_id' => $farmId]);
        // Delete farm
        $pdo->prepare('DELETE FROM farms WHERE farm_id = :farm_id')->execute([':farm_id' => $farmId]);
        // Remove user_farms entry
        $pdo->prepare('DELETE FROM user_farms WHERE farm_id = :farm_id AND user_id = :user_id')->execute([
            ':farm_id' => $farmId,
            ':user_id' => $user['user_id'],
        ]);
        json_response(['message' => 'Farm deleted successfully', 'farmId' => $farmId]);
    }
}

// Farm sensors management
if (count($segments) >= 4 && $segments[0] === 'data' && $segments[1] === 'farms' && $segments[3] === 'sensors') {
    $user = current_user();
    $farmId = $segments[2];
    ensure_farm_access($user, $farmId);

    if ($method === 'POST') {
        $input = json_input();
        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO farm_sensors (farm_id, sensor_type, sensor_name, status, created_at) VALUES (:farm_id, :sensor_type, :sensor_name, :status, :created_at)');
        $stmt->execute([
            ':farm_id' => $farmId,
            ':sensor_type' => $input['sensorType'] ?? 'soil_moisture',
            ':sensor_name' => $input['sensorName'] ?? 'New Sensor',
            ':status' => 'active',
            ':created_at' => now_iso(),
        ]);
        json_response(['message' => 'Sensor added successfully', 'sensorId' => $pdo->lastInsertId()]);
    }

    if ($method === 'PUT' && isset($segments[4])) {
        $sensorId = $segments[4];
        $input = json_input();
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE farm_sensors SET sensor_name = :sensor_name, status = :status WHERE sensor_id = :sensor_id AND farm_id = :farm_id');
        $stmt->execute([
            ':sensor_id' => $sensorId,
            ':farm_id' => $farmId,
            ':sensor_name' => $input['sensorName'] ?? '',
            ':status' => $input['status'] ?? 'active',
        ]);
        json_response(['message' => 'Sensor updated successfully', 'sensorId' => $sensorId]);
    }

    if ($method === 'DELETE' && isset($segments[4])) {
        $sensorId = $segments[4];
        $pdo = db();
        $pdo->prepare('DELETE FROM farm_sensors WHERE sensor_id = :sensor_id AND farm_id = :farm_id')->execute([
            ':sensor_id' => $sensorId,
            ':farm_id' => $farmId,
        ]);
        json_response(['message' => 'Sensor removed successfully', 'sensorId' => $sensorId]);
    }
}

if (count($segments) === 4 && $segments[0] === 'irrigation' && $segments[2] === 'confirm' && $method === 'POST') {
    $eventId = $segments[3];
    $input = json_input();
    $status = trim((string) ($input['status'] ?? 'completed'));
    $failureReason = isset($input['failureReason']) ? trim((string) $input['failureReason']) : null;

    $stmt = db()->prepare('SELECT event_id, farm_id, status FROM irrigation_events WHERE event_id = :event_id LIMIT 1');
    $stmt->execute([':event_id' => $eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        json_response(['message' => 'Irrigation event not found'], 404);
    }

    $validStatuses = ['active', 'completed', 'failed'];
    if (!in_array($status, $validStatuses, true)) {
        json_response(['message' => 'Invalid status. Use: active, completed, or failed'], 400);
    }

    $update = db()->prepare('UPDATE irrigation_events SET status = :status WHERE event_id = :event_id');
    $update->execute([':status' => $status, ':event_id' => $eventId]);

    if ($status === 'failed' && $failureReason) {
        create_alert($event['farm_id'], 'controller_failure', "Irrigation failed: {$failureReason}");
    }

    json_response([
        'message' => 'Irrigation status updated',
        'eventId' => $eventId,
        'status' => $status,
    ]);
}

json_response(['message' => 'Not found'], 404);

function record_sensor_reading(string $farmId, array $input, bool $respectAutoMode): void
{
    $timestamp = (string) ($input['timestamp'] ?? now_iso());
    $soil = isset($input['soilMoisture']) ? (float) $input['soilMoisture'] : null;
    $temperature = isset($input['temperature']) ? (float) $input['temperature'] : null;
    $humidity = isset($input['humidity']) ? (float) $input['humidity'] : null;

    if ($soil === null) {
        json_response(['message' => 'Valid soil moisture required'], 400);
    }

    $insert = db()->prepare('INSERT INTO sensor_readings(reading_id, farm_id, soil_moisture, temperature, humidity, timestamp) VALUES(:reading_id, :farm_id, :soil_moisture, :temperature, :humidity, :timestamp)');
    $readingId = uuidv4_php();
    $insert->execute([
        ':reading_id' => $readingId,
        ':farm_id' => $farmId,
        ':soil_moisture' => $soil,
        ':temperature' => $temperature,
        ':humidity' => $humidity,
        ':timestamp' => $timestamp,
    ]);

    $autoEnabled = !$respectAutoMode || get_state('auto_mode_' . $farmId, '1') !== '0';
    $shouldAlert = $soil < 35;
    if ($shouldAlert) {
        create_alert($farmId, 'low_moisture', sprintf('Soil moisture dropped to %.1f%%. Automatic irrigation triggered.', $soil));
        if ($autoEnabled) {
            create_irrigation_event($farmId, false, 12, max(220, 320 + ((35 - $soil) * 12)));
        }
    }

    json_response([
        'message' => 'Sensor reading recorded',
        'readingId' => $readingId,
        'alert' => $shouldAlert,
    ]);
}

function create_irrigation_event(string $farmId, bool $manual, int $duration, float $estimatedLitres): array
{
    $start = now_iso();
    $end = gmdate('c', time() + ($duration * 60));
    $eventId = uuidv4_php();

    // For manual irrigations in demo mode, mark as completed immediately
    // For automatic, mark as active (will be updated by sensor data)
    $status = $manual ? 'completed' : 'active';

    $insert = db()->prepare('INSERT INTO irrigation_events(event_id, farm_id, trigger_type, start_time, end_time, water_used, status) VALUES(:event_id, :farm_id, :trigger_type, :start_time, :end_time, :water_used, :status)');
    $insert->execute([
        ':event_id' => $eventId,
        ':farm_id' => $farmId,
        ':trigger_type' => $manual ? 'manual' : 'automatic',
        ':start_time' => $start,
        ':end_time' => $end,
        ':water_used' => $estimatedLitres,
        ':status' => $status,
    ]);

    create_alert(
        $farmId,
        $manual ? 'manual_irrigation' : 'automatic_irrigation',
        ($manual ? 'Manual' : 'Automatic') . sprintf(' irrigation started: %d min, %.0f L', $duration, $estimatedLitres)
    );

    // Send SMS notification in Swahili
    $smsMessage = $manual
        ? "Umwagiliaji wa mkono umeanzishwa. Maji yatanzwa kwa dakika $duration. Asante kwa kutumia AquaSense."
        : "Umwagiliaji wa kiotomatiki umeanzishwa. Maji yatanzwa kwa dakika $duration. Asante kwa kutumia AquaSense.";
    notify_farm_owner($farmId, $smsMessage);

    set_state('last_sms_sent_at', now_iso());

    return [
        'eventId' => $eventId,
        'triggerType' => $manual ? 'manual' : 'automatic',
        'startTime' => $start,
    ];
}

function create_alert(string $farmId, string $type, string $message): void
{
    $stmt = db()->prepare('INSERT INTO system_alerts(alert_id, farm_id, alert_type, message, timestamp, resolved, resolved_at) VALUES(:alert_id, :farm_id, :alert_type, :message, :timestamp, 0, NULL)');
    $stmt->execute([
        ':alert_id' => uuidv4_php(),
        ':farm_id' => $farmId,
        ':alert_type' => $type,
        ':message' => $message,
        ':timestamp' => now_iso(),
    ]);
}

function uuidv4_php(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
