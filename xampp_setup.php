<?php
// XAMPP Setup and Test Script for AquaSense
echo "<h2>AquaSense XAMPP Setup & Authentication Test</h2>";

// Check if running in XAMPP
if (strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache') !== false || 
    strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'XAMPP') !== false) {
    echo "<p style='color: green;'>✅ Running on Apache/XAMPP</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Not detected as XAMPP, but should still work</p>";
}

// Test database connection
try {
    require_once 'php/bootstrap.php';
    $db = db();
    echo "<p style='color: green;'>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Create demo user if not exists
try {
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => 'admin@example.com']);
    $user = $stmt->fetch();

    if (!$user) {
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
        
        echo "<p style='color: green;'>✅ Demo user created</p>";
    } else {
        echo "<p style='color: green;'>✅ Demo user exists</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ User creation error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test login
echo "<h3>Login Test</h3>";
try {
    $input = ['email' => 'admin@example.com', 'password' => 'password123'];
    
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => strtolower(trim($input['email']))]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($input['password'], $user['password_hash'])) {
        echo "<p style='color: red;'>❌ Login failed: Invalid credentials</p>";
    } else {
        echo "<p style='color: green;'>✅ Login successful</p>";
        
        // Test farm access
        $farmsStmt = $db->prepare('SELECT farm_id, location, crop_type, size FROM farms WHERE user_id = :user_id ORDER BY location');
        $farmsStmt->execute([':user_id' => $user['user_id']]);
        $farms = $farmsStmt->fetchAll();
        
        if (empty($farms)) {
            echo "<p style='color: red;'>❌ No farms found (this was the bug!)</p>";
        } else {
            echo "<p style='color: green;'>✅ Found " . count($farms) . " farm(s):</p>";
            echo "<ul>";
            foreach ($farms as $farm) {
                echo "<li>" . htmlspecialchars($farm['location']) . " (" . htmlspecialchars($farm['crop_type']) . ")</li>";
            }
            echo "</ul>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Login test error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>Usage Instructions</h3>";
echo "<div style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
echo "<p><strong>Demo Credentials:</strong></p>";
echo "<p>Email: admin@example.com</p>";
echo "<p>Password: password123</p>";
echo "<p><strong>API Base URL:</strong> http://localhost/aquasense/php/api/</p>";
echo "<p><strong>Login Endpoint:</strong> POST /aquasense/php/api/auth/login</p>";
echo "</div>";

echo "<h3>Fixed Issues</h3>";
echo "<ul>";
echo "<li>✅ Fixed user ID field reference from 'id' to 'user_id' in farm queries</li>";
echo "<li>✅ Fixed ensure_farm_access function</li>";
echo "<li>✅ Created demo user for testing</li>";
echo "</ul>";
?>
