<?php
require_once 'php/bootstrap.php';

try {
    $db = db();
    echo "✅ Database connection: OK\n";
    
    // Test if tables exist
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "📊 Tables found: " . implode(', ', $tables) . "\n";
    
    // Test if users exist
    $userCount = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo "👥 Users in database: $userCount\n";
    
    // Test if farms exist
    $farmCount = $db->query('SELECT COUNT(*) FROM farms')->fetchColumn();
    echo "🌾 Farms in database: $farmCount\n";
    
    // Test if sensor readings exist
    $readingCount = $db->query('SELECT COUNT(*) FROM sensor_readings')->fetchColumn();
    echo "📡 Sensor readings: $readingCount\n";
    
    // Test demo user
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => 'admin@example.com']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ Demo user found: " . $user['name'] . " (" . $user['email'] . ")\n";
        
        // Get user's farms
        $farmStmt = $db->prepare('SELECT * FROM farms WHERE user_id = :user_id');
        $farmStmt->execute([':user_id' => $user['user_id']]);
        $farms = $farmStmt->fetchAll();
        
        echo "🌾 User farms:\n";
        foreach ($farms as $farm) {
            echo "  - " . $farm['location'] . " (" . $farm['crop_type'] . ")\n";
        }
        
        // Get latest sensor reading
        $sensorStmt = $db->prepare('SELECT * FROM sensor_readings WHERE farm_id = :farm_id ORDER BY timestamp DESC LIMIT 1');
        $sensorStmt->execute([':farm_id' => $farms[0]['farm_id']]);
        $latest = $sensorStmt->fetch();
        
        if ($latest) {
            echo "📡 Latest sensor reading for " . $farms[0]['location'] . ":\n";
            echo "  Moisture: " . $latest['soil_moisture'] . "%\n";
            echo "  Temperature: " . $latest['temperature'] . "°C\n";
            echo "  Humidity: " . $latest['humidity'] . "%\n";
            echo "  Time: " . $latest['timestamp'] . "\n";
        }
        
    } else {
        echo "❌ Demo user not found. Creating...\n";
        
        // Create demo user
        $userId = uuidv4_php();
        $farmId = uuidv4_php();
        
        $insertUser = $db->prepare('INSERT INTO users(user_id, name, email, phone, password_hash, role) VALUES(:user_id, :name, :email, :phone, :password_hash, :role)');
        $insertUser->execute([
            ':user_id' => $userId,
            ':name' => 'Demo Farmer',
            ':email' => 'admin@example.com',
            ':phone' => '+254712345678',
            ':password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            ':role' => 'farmer',
        ]);
        
        $insertFarm = $db->prepare('INSERT INTO farms(farm_id, user_id, location, size, crop_type) VALUES(:farm_id, :user_id, :location, :size, :crop_type)');
        $insertFarm->execute([
            ':farm_id' => $farmId,
            ':user_id' => $userId,
            ':location' => 'Demo Farm',
            ':size' => 2.5,
            ':crop_type' => 'Maize',
        ]);
        
        echo "✅ Demo user created successfully!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

function uuidv4_php(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);
    return vsprintf('%s%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
?>
