<?php
/**
 * Create MySQL Database for AquaSense
 * Run this in browser: http://localhost:8000/php/mysql_setup.php
 */

header('Content-Type: text/plain');

echo "=== AquaSense MySQL Database Setup ===\n\n";

// MySQL connection details
$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$dbname = 'aquasense';

echo "1. Connecting to MySQL...\n";
try {
    $mysqli = new mysqli($host, $user, $pass);
    if ($mysqli->connect_error) {
        die("   ✗ Connection failed: " . $mysqli->connect_error . "\n");
    }
    echo "   ✓ Connected to MySQL\n\n";
} catch (Exception $e) {
    die("   ✗ Error: " . $e->getMessage() . "\n");
}

echo "2. Creating database '$dbname'...\n";
$mysqli->query("DROP DATABASE IF EXISTS `$dbname`");
if ($mysqli->query("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    echo "   ✓ Database created\n\n";
} else {
    die("   ✗ Error creating database: " . $mysqli->error . "\n");
}

$mysqli->select_db($dbname);

echo "3. Creating tables...\n";

// Users table
$mysqli->query("DROP TABLE IF EXISTS users");
$sql = "CREATE TABLE users (
    user_id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'farmer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($mysqli->query($sql)) {
    echo "   ✓ users table\n";
} else {
    echo "   ✗ Error creating users: " . $mysqli->error . "\n";
}

// Farms table
$mysqli->query("DROP TABLE IF EXISTS farms");
$sql = "CREATE TABLE farms (
    farm_id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    location VARCHAR(255) NOT NULL,
    size DECIMAL(10,2),
    crop_type VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";
if ($mysqli->query($sql)) {
    echo "   ✓ farms table\n";
} else {
    echo "   ✗ Error creating farms: " . $mysqli->error . "\n";
}

// Sensor readings table
$mysqli->query("DROP TABLE IF EXISTS sensor_readings");
$sql = "CREATE TABLE sensor_readings (
    reading_id VARCHAR(36) PRIMARY KEY,
    farm_id VARCHAR(36) NOT NULL,
    soil_moisture DECIMAL(5,2),
    temperature DECIMAL(5,2),
    humidity DECIMAL(5,2),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE,
    INDEX idx_farm_time (farm_id, timestamp)
)";
if ($mysqli->query($sql)) {
    echo "   ✓ sensor_readings table\n";
} else {
    echo "   ✗ Error creating sensor_readings: " . $mysqli->error . "\n";
}

// Irrigation events table
$mysqli->query("DROP TABLE IF EXISTS irrigation_events");
$sql = "CREATE TABLE irrigation_events (
    event_id VARCHAR(36) PRIMARY KEY,
    farm_id VARCHAR(36) NOT NULL,
    trigger_type VARCHAR(50) NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NULL,
    water_used DECIMAL(10,2),
    status VARCHAR(50) NOT NULL DEFAULT 'completed',
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
)";
if ($mysqli->query($sql)) {
    echo "   ✓ irrigation_events table\n";
} else {
    echo "   ✗ Error creating irrigation_events: " . $mysqli->error . "\n";
}

// System alerts table
$mysqli->query("DROP TABLE IF EXISTS system_alerts");
$sql = "CREATE TABLE system_alerts (
    alert_id VARCHAR(36) PRIMARY KEY,
    farm_id VARCHAR(36) NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    message TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved TINYINT(1) NOT NULL DEFAULT 0,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
)";
if ($mysqli->query($sql)) {
    echo "   ✓ system_alerts table\n";
} else {
    echo "   ✗ Error creating system_alerts: " . $mysqli->error . "\n";
}

// App state table
$mysqli->query("DROP TABLE IF EXISTS app_state");
$sql = "CREATE TABLE app_state (
    `key` VARCHAR(100) PRIMARY KEY,
    value TEXT NOT NULL
)";
if ($mysqli->query($sql)) {
    echo "   ✓ app_state table\n\n";
} else {
    echo "   ✗ Error creating app_state: " . $mysqli->error . "\n";
}

echo "4. Inserting demo users...\n";
$users = [
    ['22222222-2222-2222-2222-222222222222', 'Lewis Abuga', 'admin@example.com', '+254700123456', password_hash('password123', PASSWORD_BCRYPT), 'admin'],
    ['11111111-1111-1111-1111-111111111111', 'Lewis Abuga', 'farmer@kenya.com', '+254712345678', password_hash('irrigate2024', PASSWORD_BCRYPT), 'farmer'],
];
$stmt = $mysqli->prepare("INSERT INTO users (user_id, name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($users as $user) {
    $stmt->bind_param("ssssss", $user[0], $user[1], $user[2], $user[3], $user[4], $user[5]);
    $stmt->execute();
}
echo "   ✓ 2 users inserted\n\n";

echo "5. Inserting demo farms...\n";
$farms = [
    ['550e8400-e29b-41d4-a716-446655440002', '22222222-2222-2222-2222-222222222222', 'Gataka Farm', 1.5, 'Maize'],
    ['550e8400-e29b-41d4-a716-446655440005', '22222222-2222-2222-2222-222222222222', 'Hardy Farm', 2.0, 'Beans'],
];
$stmt = $mysqli->prepare("INSERT INTO farms (farm_id, user_id, location, size, crop_type) VALUES (?, ?, ?, ?, ?)");
foreach ($farms as $farm) {
    $stmt->bind_param("ssssd", $farm[0], $farm[1], $farm[2], $farm[3], $farm[4]);
    $stmt->execute();
}
echo "   ✓ 2 farms inserted\n\n";

echo "6. Inserting sensor readings...\n";
$stmt = $mysqli->prepare("INSERT INTO sensor_readings (reading_id, farm_id, soil_moisture, temperature, humidity, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
$profiles = [
    '550e8400-e29b-41d4-a716-446655440002' => [28.0, 26.0, 62.0, 0.8, 0.1, 0.4],
    '550e8400-e29b-41d4-a716-446655440005' => [42.0, 24.5, 55.0, 0.7, 0.09, 0.38],
];
$count = 0;
foreach ($profiles as $farmId => [$soil, $temp, $humidity, $soilStep, $tempStep, $humidityStep]) {
    for ($i = 0; $i < 18; $i++) {
        $readingId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $timestamp = date('Y-m-d H:i:s', time() - ($i * 1800));
        $moisture = round($soil + ($i * $soilStep), 1);
        $temperature = round($temp + ($i * $tempStep), 1);
        $humid = round($humidity + ($i * $humidityStep), 1);
        $stmt->bind_param("ssddds", $readingId, $farmId, $moisture, $temperature, $humid, $timestamp);
        $stmt->execute();
        $count++;
    }
}
echo "   ✓ $count sensor readings inserted\n\n";

echo "7. Inserting irrigation events...\n";
$stmt = $mysqli->prepare("INSERT INTO irrigation_events (event_id, farm_id, trigger_type, start_time, end_time, water_used, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
$count = 0;
foreach ($farms as $farm) {
    $eventId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $startTime = date('Y-m-d H:i:s', time() - 18000);
    $endTime = date('Y-m-d H:i:s', time() - 17280);
    $stmt->bind_param("ssssdds", $eventId, $farm[0], 'automatic', $startTime, $endTime, 420, 'completed');
    $stmt->execute();
    $count++;
    
    $eventId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $startTime = date('Y-m-d H:i:s', time() - 7200);
    $endTime = date('Y-m-d H:i:s', time() - 6600);
    $stmt->bind_param("ssssdds", $eventId, $farm[0], 'manual', $startTime, $endTime, 350, 'completed');
    $stmt->execute();
    $count++;
}
echo "   ✓ $count irrigation events inserted\n\n";

echo "8. Inserting system alerts...\n";
$stmt = $mysqli->prepare("INSERT INTO system_alerts (alert_id, farm_id, alert_type, message, timestamp, resolved, resolved_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
$count = 0;
foreach ($farms as $index => $farm) {
    $alertId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $timestamp = date('Y-m-d H:i:s', time() - 3600 - ($index * 300));
    $message = "Soil moisture dropped below the threshold at {$farm[2]}.";
    $stmt->bind_param("sssssis", $alertId, $farm[0], 'low_moisture', $message, $timestamp, 0, $null);
    $stmt->execute();
    $count++;
    
    $alertId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $timestamp = date('Y-m-d H:i:s', time() - 10800 - ($index * 300));
    $resolvedAt = date('Y-m-d H:i:s', time() - 9000 - ($index * 300));
    $message = "Sensor signal restored successfully at {$farm[2]}.";
    $stmt->bind_param("sssssis", $alertId, $farm[0], 'sensor_check', $message, $timestamp, 1, $resolvedAt);
    $stmt->execute();
    $count++;
}
echo "   ✓ $count system alerts inserted\n\n";

echo "9. Setting app state...\n";
$mysqli->query("INSERT INTO app_state (`key`, value) VALUES ('last_sms_sent_at', '" . date('Y-m-d H:i:s', time() - 1800) . "')");
echo "   ✓ App state set\n\n";

$mysqli->close();

echo "=== MYSQL DATABASE SETUP COMPLETE ===\n\n";
echo "Database: $dbname\n";
echo "Tables created: 6\n";
echo "Test login: admin@example.com / password123\n\n";
echo "Next steps:\n";
echo "1. Update bootstrap.php to use MySQL\n";
echo "2. Access via phpMyAdmin: http://localhost/phpmyadmin\n";
echo "3. Database: aquasense\n";
