<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pdo = db();

$pdo->exec('
CREATE TABLE IF NOT EXISTS users (
    user_id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    phone TEXT,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT "farmer",
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS farms (
    farm_id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    location TEXT NOT NULL,
    size REAL,
    crop_type TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sensor_readings (
    reading_id TEXT PRIMARY KEY,
    farm_id TEXT NOT NULL,
    soil_moisture REAL,
    temperature REAL,
    humidity REAL,
    timestamp TEXT NOT NULL,
    FOREIGN KEY(farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS irrigation_events (
    event_id TEXT PRIMARY KEY,
    farm_id TEXT NOT NULL,
    trigger_type TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT,
    water_used REAL,
    status TEXT NOT NULL DEFAULT "completed",
    FOREIGN KEY(farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS system_alerts (
    alert_id TEXT PRIMARY KEY,
    farm_id TEXT NOT NULL,
    alert_type TEXT NOT NULL,
    message TEXT,
    timestamp TEXT NOT NULL,
    resolved INTEGER NOT NULL DEFAULT 0,
    resolved_at TEXT,
    FOREIGN KEY(farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS app_state (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
');

$users = [
    [
        'user_id' => '22222222-2222-2222-2222-222222222222',
        'name' => 'Lewis Abuga',
        'email' => 'admin@example.com',
        'phone' => '+254700123456',
        'password' => 'password123',
        'role' => 'admin',
    ],
    [
        'user_id' => '11111111-1111-1111-1111-111111111111',
        'name' => 'Lewis Abuga',
        'email' => 'farmer@kenya.com',
        'phone' => '+254712345678',
        'password' => 'irrigate2024',
        'role' => 'farmer',
    ],
];

$insertUser = $pdo->prepare('INSERT OR REPLACE INTO users(user_id, name, email, phone, password_hash, role) VALUES(:user_id, :name, :email, :phone, :password_hash, :role)');
foreach ($users as $user) {
    $insertUser->execute([
        ':user_id' => $user['user_id'],
        ':name' => $user['name'],
        ':email' => $user['email'],
        ':phone' => $user['phone'],
        ':password_hash' => password_hash($user['password'], PASSWORD_BCRYPT),
        ':role' => $user['role'],
    ]);
}

$farms = [
    ['farm_id' => '550e8400-e29b-41d4-a716-446655440002', 'user_id' => '22222222-2222-2222-2222-222222222222', 'location' => 'Gataka Farm', 'size' => 1.5, 'crop_type' => 'Maize'],
    ['farm_id' => '550e8400-e29b-41d4-a716-446655440005', 'user_id' => '22222222-2222-2222-2222-222222222222', 'location' => 'Hardy Farm', 'size' => 2.0, 'crop_type' => 'Beans'],
];

$insertFarm = $pdo->prepare('INSERT OR IGNORE INTO farms(farm_id, user_id, location, size, crop_type) VALUES(:farm_id, :user_id, :location, :size, :crop_type)');
foreach ($farms as $farm) {
    $insertFarm->execute([
        ':farm_id' => $farm['farm_id'],
        ':user_id' => $farm['user_id'],
        ':location' => $farm['location'],
        ':size' => $farm['size'],
        ':crop_type' => $farm['crop_type'],
    ]);
}

$readingCount = (int) $pdo->query('SELECT COUNT(*) FROM sensor_readings')->fetchColumn();
if ($readingCount === 0) {
    $insertReading = $pdo->prepare('INSERT INTO sensor_readings(reading_id, farm_id, soil_moisture, temperature, humidity, timestamp) VALUES(:reading_id, :farm_id, :soil_moisture, :temperature, :humidity, :timestamp)');
    $profiles = [
        '550e8400-e29b-41d4-a716-446655440002' => [28.0, 26.0, 62.0, 0.8, 0.1, 0.4],
        '550e8400-e29b-41d4-a716-446655440005' => [42.0, 24.5, 55.0, 0.7, 0.09, 0.38],
    ];

    foreach ($profiles as $farmId => [$soil, $temp, $humidity, $soilStep, $tempStep, $humidityStep]) {
        for ($i = 0; $i < 18; $i++) {
            $timestamp = gmdate('c', time() - ($i * 1800));
            $insertReading->execute([
                ':reading_id' => uuidv4(),
                ':farm_id' => $farmId,
                ':soil_moisture' => round($soil + ($i * $soilStep), 1),
                ':temperature' => round($temp + ($i * $tempStep), 1),
                ':humidity' => round($humidity + ($i * $humidityStep), 1),
                ':timestamp' => $timestamp,
            ]);
        }
    }
}

$eventCount = (int) $pdo->query('SELECT COUNT(*) FROM irrigation_events')->fetchColumn();
if ($eventCount === 0) {
    $insertEvent = $pdo->prepare('INSERT INTO irrigation_events(event_id, farm_id, trigger_type, start_time, end_time, water_used, status) VALUES(:event_id, :farm_id, :trigger_type, :start_time, :end_time, :water_used, :status)');
    foreach ($farms as $farm) {
        $insertEvent->execute([
            ':event_id' => uuidv4(),
            ':farm_id' => $farm['farm_id'],
            ':trigger_type' => 'automatic',
            ':start_time' => gmdate('c', time() - 18000),
            ':end_time' => gmdate('c', time() - 17280),
            ':water_used' => 420,
            ':status' => 'completed',
        ]);
        $insertEvent->execute([
            ':event_id' => uuidv4(),
            ':farm_id' => $farm['farm_id'],
            ':trigger_type' => 'manual',
            ':start_time' => gmdate('c', time() - 7200),
            ':end_time' => gmdate('c', time() - 6600),
            ':water_used' => 350,
            ':status' => 'completed',
        ]);
    }
}

$alertCount = (int) $pdo->query('SELECT COUNT(*) FROM system_alerts')->fetchColumn();
if ($alertCount === 0) {
    $insertAlert = $pdo->prepare('INSERT INTO system_alerts(alert_id, farm_id, alert_type, message, timestamp, resolved, resolved_at) VALUES(:alert_id, :farm_id, :alert_type, :message, :timestamp, :resolved, :resolved_at)');
    foreach ($farms as $index => $farm) {
        $insertAlert->execute([
            ':alert_id' => uuidv4(),
            ':farm_id' => $farm['farm_id'],
            ':alert_type' => 'low_moisture',
            ':message' => 'Soil moisture dropped below the threshold at ' . $farm['location'] . '.',
            ':timestamp' => gmdate('c', time() - 3600 - ($index * 300)),
            ':resolved' => 0,
            ':resolved_at' => null,
        ]);
        $insertAlert->execute([
            ':alert_id' => uuidv4(),
            ':farm_id' => $farm['farm_id'],
            ':alert_type' => 'sensor_check',
            ':message' => 'Sensor signal restored successfully at ' . $farm['location'] . '.',
            ':timestamp' => gmdate('c', time() - 10800 - ($index * 300)),
            ':resolved' => 1,
            ':resolved_at' => gmdate('c', time() - 9000 - ($index * 300)),
        ]);
    }
}

if (get_state('last_sms_sent_at') === null) {
    set_state('last_sms_sent_at', gmdate('c', time() - 1800));
}

echo "PHP database initialized and seeded.\n";

function uuidv4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
