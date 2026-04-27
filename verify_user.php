<?php
require_once 'php/bootstrap.php';

echo "=== AquaSense User Verification ===\n\n";

try {
    $db = db();
    
    // Check if demo user exists
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => 'admin@example.com']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ Demo user found:\n";
        echo "   User ID: " . $user['user_id'] . "\n";
        echo "   Name: " . $user['name'] . "\n";
        echo "   Email: " . $user['email'] . "\n";
        echo "   Role: " . $user['role'] . "\n";
        echo "   Password Hash: " . substr($user['password_hash'], 0, 30) . "...\n\n";
        
        // Test password verification
        $testPassword = 'password123';
        $isValid = password_verify($testPassword, $user['password_hash']);
        
        if ($isValid) {
            echo "✅ Password 'password123' is VALID\n";
        } else {
            echo "❌ Password 'password123' is INVALID\n";
            echo "   Creating new password hash...\n";
            
            // Update password
            $newHash = password_hash($testPassword, PASSWORD_BCRYPT);
            $updateStmt = $db->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :user_id');
            $updateStmt->execute([
                ':hash' => $newHash,
                ':user_id' => $user['user_id']
            ]);
            
            echo "✅ Password updated successfully!\n";
            echo "   New Hash: " . substr($newHash, 0, 30) . "...\n";
        }
        
        // Check user's farms
        $farmStmt = $db->prepare('SELECT * FROM farms WHERE user_id = :user_id');
        $farmStmt->execute([':user_id' => $user['user_id']]);
        $farms = $farmStmt->fetchAll();
        
        echo "\n🌾 User Farms: " . count($farms) . "\n";
        foreach ($farms as $farm) {
            echo "   - " . $farm['location'] . " (" . $farm['crop_type'] . ")\n";
        }
        
    } else {
        echo "❌ Demo user NOT found!\n";
        echo "   Creating demo user...\n\n";
        
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
        echo "   Email: admin@example.com\n";
        echo "   Password: password123\n";
    }
    
    echo "\n=== Test Complete ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

function uuidv4_php(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);
    return vsprintf('%s%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
?>
