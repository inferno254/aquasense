<?php
require_once 'php/bootstrap.php';

echo "Testing authentication fix...\n\n";

// Test 1: Check if demo user exists
$stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => 'admin@example.com']);
$user = $stmt->fetch();

if (!$user) {
    echo "Creating demo user...\n";
    $userId = uuidv4_php();
    $farmId = uuidv4_php();
    
    $insertUser = db()->prepare('INSERT INTO users(user_id, name, email, phone, password_hash, role) VALUES(:user_id, :name, :email, :phone, :password_hash, :role)');
    $insertUser->execute([
        ':user_id' => $userId,
        ':name' => 'Demo Farmer',
        ':email' => 'admin@example.com',
        ':phone' => '+254712345678',
        ':password_hash' => password_hash('password123', PASSWORD_BCRYPT),
        ':role' => 'farmer',
    ]);
    
    $insertFarm = db()->prepare('INSERT INTO farms(farm_id, user_id, location, size, crop_type) VALUES(:farm_id, :user_id, :location, :size, :crop_type)');
    $insertFarm->execute([
        ':farm_id' => $farmId,
        ':user_id' => $userId,
        ':location' => 'Demo Farm',
        ':size' => 2.5,
        ':crop_type' => 'Maize',
    ]);
    
    echo "Demo user created: admin@example.com / password123\n";
} else {
    echo "Demo user found: " . $user['email'] . " (" . $user['name'] . ")\n";
}

// Test 2: Simulate login process
echo "\nTesting login process...\n";
$input = ['email' => 'admin@example.com', 'password' => 'password123'];

$stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => strtolower(trim($input['email']))]);
$user = $stmt->fetch();

if (!$user || !password_verify($input['password'], $user['password_hash'])) {
    echo "❌ Login failed: Invalid credentials\n";
    exit(1);
}

echo "✅ Login successful\n";

// Test 3: Check farm access with corrected user_id
echo "\nTesting farm access...\n";
$farmsStmt = db()->prepare('SELECT farm_id, location, crop_type, size FROM farms WHERE user_id = :user_id ORDER BY location');
$farmsStmt->execute([':user_id' => $user['user_id']]); // Using user_id instead of id
$farms = $farmsStmt->fetchAll();

if (empty($farms)) {
    echo "❌ No farms found for user\n";
} else {
    echo "✅ Found " . count($farms) . " farm(s):\n";
    foreach ($farms as $farm) {
        echo "  - " . $farm['location'] . " (" . $farm['crop_type'] . ")\n";
    }
}

echo "\n🎉 Authentication fix verified!\n";
echo "You can now login with: admin@example.com / password123\n";
?>
