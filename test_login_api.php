<?php
// Test login API endpoint
require_once 'php/bootstrap.php';

echo "=== Testing Login API ===\n\n";

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'email' => 'admin@example.com',
    'password' => 'password123'
];

// Capture output
ob_start();

// Include the API index (this will process the request)
try {
    // Manually test the login logic
    $input = ['email' => 'admin@example.com', 'password' => 'password123'];
    
    echo "Input received:\n";
    echo "   Email: " . $input['email'] . "\n";
    echo "   Password: " . $input['password'] . "\n\n";
    
    // Test database query
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => strtolower(trim($input['email']))]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ User found in database\n";
        echo "   User ID: " . $user['user_id'] . "\n";
        echo "   Password Hash: " . substr($user['password_hash'], 0, 20) . "...\n\n";
        
        // Test password verification
        $passwordValid = password_verify($input['password'], $user['password_hash']);
        echo "Password verification result: " . ($passwordValid ? "✅ VALID" : "❌ INVALID") . "\n\n";
        
        if ($passwordValid) {
            echo "✅ Login should be successful!\n";
            
            // Test farms query
            $farmsStmt = db()->prepare('SELECT farm_id, location, crop_type, size FROM farms WHERE user_id = :user_id ORDER BY location');
            $farmsStmt->execute([':user_id' => $user['user_id']]);
            $farms = $farmsStmt->fetchAll();
            
            echo "🌾 Farms found: " . count($farms) . "\n";
            foreach ($farms as $farm) {
                echo "   - " . $farm['location'] . " (" . $farm['crop_type'] . ")\n";
            }
        } else {
            echo "❌ Password verification failed!\n";
        }
    } else {
        echo "❌ User not found in database!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
echo $output;
?>
