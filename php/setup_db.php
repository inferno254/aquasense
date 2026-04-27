<?php
/**
 * Standalone Database Setup
 * Run this directly in browser: http://aquasense.local/php/setup_db.php
 */

header('Content-Type: text/plain');

echo "=== AquaSense Database Setup ===\n\n";

// Database path
$dbDir = __DIR__ . '/../database';
$dbPath = $dbDir . '/aquasense.db';

echo "1. Checking database directory...\n";
if (!is_dir($dbDir)) {
    echo "   Creating directory: $dbDir\n";
    mkdir($dbDir, 0777, true);
}
echo "   ✓ Directory ready\n\n";

echo "2. Creating database connection...\n";
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "   ✓ Connected to: $dbPath\n\n";
} catch (PDOException $e) {
    die("   ✗ Error: " . $e->getMessage() . "\n");
}

echo "3. Creating tables...\n";

// Users table
$pdo->exec("DROP TABLE IF EXISTS users");
$pdo->exec('
CREATE TABLE users (
    user_id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    phone TEXT,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT "farmer",
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
echo "   ✓ users table\n";

// Farms table
$pdo->exec("DROP TABLE IF EXISTS farms");
$pdo->exec('
CREATE TABLE farms (
    farm_id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    location TEXT NOT NULL,
    size REAL,
    crop_type TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
)');
echo "   ✓ farms table\n";

// Sensor readings table
$pdo->exec("DROP TABLE IF EXISTS sensor_readings");
$pdo->exec('
CREATE TABLE sensor_readings (
    reading_id TEXT PRIMARY KEY,
    farm_id TEXT NOT NULL,
    soil_moisture REAL,
    temperature REAL,
    humidity REAL,
    timestamp TEXT NOT NULL,
    FOREIGN KEY(farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
)');
echo "   ✓ sensor_readings table\n";

// Irrigation events table
$pdo->exec("DROP TABLE IF EXISTS irrigation_events");
$pdo->exec('
CREATE TABLE irrigation_events (
    event_id TEXT PRIMARY KEY,
    farm_id TEXT NOT NULL,
    trigger_type TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT,
    water_used REAL,
    status TEXT NOT NULL DEFAULT "completed",
    FOREIGN KEY(farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
)');
echo "   ✓ irrigation_events table\n";

// System alerts table
$pdo->exec("DROP TABLE IF EXISTS system_alerts");
$pdo->exec('
CREATE TABLE system_alerts (
    alert_id TEXT PRIMARY KEY,
    farm_id TEXT NOT NULL,
    alert_type TEXT NOT NULL,
    message TEXT,
    timestamp TEXT NOT NULL,
    resolved INTEGER NOT NULL DEFAULT 0,
    resolved_at TEXT,
    FOREIGN KEY(farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
)');
echo "   ✓ system_alerts table\n";

// App state table
$pdo->exec("DROP TABLE IF EXISTS app_state");
$pdo->exec('
CREATE TABLE app_state (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
)');
echo "   ✓ app_state table\n\n";

// Helper function
function uuidv4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

echo "4. Inserting demo users...\n";
$users = [
    ['22222222-2222-2222-2222-222222222222', 'Lewis Abuga', 'admin@example.com', '+254700123456', password_hash('password123', PASSWORD_BCRYPT), 'admin'],
    ['11111111-1111-1111-1111-111111111111', 'Lewis Abuga', 'farmer@kenya.com', '+254712345678', password_hash('irrigate2024', PASSWORD_BCRYPT), 'farmer'],
];
$stmt = $pdo->prepare('INSERT INTO users (user_id, name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)');
foreach ($users as $user) {
    $stmt->execute($user);
}
echo "   ✓ 2 users inserted\n\n";

echo "5. Inserting demo farms...\n";
$farms = [
    ['550e8400-e29b-41d4-a716-446655440002', '22222222-2222-2222-2222-222222222222', 'Gataka Farm', 1.5, 'Maize'],
    ['550e8400-e29b-41d4-a716-446655440005', '22222222-2222-2222-2222-222222222222', 'Hardy Farm', 2.0, 'Beans'],
];
$stmt = $pdo->prepare('INSERT INTO farms (farm_id, user_id, location, size, crop_type) VALUES (?, ?, ?, ?, ?)');
foreach ($farms as $farm) {
    $stmt->execute($farm);
}
echo "   ✓ 2 farms inserted\n\n";

echo "6. Inserting sensor readings...\n";
$stmt = $pdo->prepare('INSERT INTO sensor_readings (reading_id, farm_id, soil_moisture, temperature, humidity, timestamp) VALUES (?, ?, ?, ?, ?, ?)');
$profiles = [
    '550e8400-e29b-41d4-a716-446655440002' => [28.0, 26.0, 62.0, 0.8, 0.1, 0.4],
    '550e8400-e29b-41d4-a716-446655440005' => [42.0, 24.5, 55.0, 0.7, 0.09, 0.38],
];
$count = 0;
foreach ($profiles as $farmId => [$soil, $temp, $humidity, $soilStep, $tempStep, $humidityStep]) {
    for ($i = 0; $i < 18; $i++) {
        $timestamp = gmdate('c', time() - ($i * 1800));
        $stmt->execute([
            uuidv4(),
            $farmId,
            round($soil + ($i * $soilStep), 1),
            round($temp + ($i * $tempStep), 1),
            round($humidity + ($i * $humidityStep), 1),
            $timestamp
        ]);
        $count++;
    }
}
echo "   ✓ $count sensor readings inserted\n\n";

echo "7. Inserting irrigation events...\n";
$stmt = $pdo->prepare('INSERT INTO irrigation_events (event_id, farm_id, trigger_type, start_time, end_time, water_used, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
$count = 0;
foreach ($farms as $farm) {
    $stmt->execute([uuidv4(), $farm[0], 'automatic', gmdate('c', time() - 18000), gmdate('c', time() - 17280), 420, 'completed']);
    $stmt->execute([uuidv4(), $farm[0], 'manual', gmdate('c', time() - 7200), gmdate('c', time() - 6600), 350, 'completed']);
    $count += 2;
}
echo "   ✓ $count irrigation events inserted\n\n";

echo "8. Inserting system alerts...\n";
$stmt = $pdo->prepare('INSERT INTO system_alerts (alert_id, farm_id, alert_type, message, timestamp, resolved, resolved_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
$count = 0;
foreach ($farms as $index => $farm) {
    $stmt->execute([uuidv4(), $farm[0], 'low_moisture', "Soil moisture dropped below the threshold at {$farm[2]}.", gmdate('c', time() - 3600 - ($index * 300)), 0, null]);
    $stmt->execute([uuidv4(), $farm[0], 'sensor_check', "Sensor signal restored successfully at {$farm[2]}.", gmdate('c', time() - 10800 - ($index * 300)), 1, gmdate('c', time() - 9000 - ($index * 300))]);
    $count += 2;
}
echo "   ✓ $count system alerts inserted\n\n";

echo "9. Setting app state...\n";
$pdo->prepare('INSERT INTO app_state (key, value) VALUES (?, ?)')->execute(['last_sms_sent_at', gmdate('c', time() - 1800)]);
echo "   ✓ App state set\n\n";

echo "=== DATABASE SETUP COMPLETE ===\n\n";
echo "Database location: $dbPath\n";
echo "Tables created: 6\n";
echo "Test login: admin@example.com / password123\n\n";
echo "Next steps:\n";
echo "1. Open: http://aquasense.local/db_viewer.php\n";
echo "2. Or login at: http://aquasense.local/login.html\n";
