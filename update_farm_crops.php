<?php
/**
 * Update Farm Crops in Database
 * Changes Hardy Farm from Tomatoes to Beans
 */

require_once 'php/bootstrap.php';

try {
    $db = db();
    
    echo "Updating farm crops...\n\n";
    
    // Update Hardy Farm crop type
    $stmt = $db->prepare("UPDATE farms SET crop_type = 'Beans' WHERE location = 'Hardy Farm'");
    $stmt->execute();
    
    echo "✓ Hardy Farm crop updated to Beans\n";
    
    // Verify the update
    $stmt = $db->prepare("SELECT farm_id, location, crop_type FROM farms");
    $stmt->execute();
    $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCurrent farms in database:\n";
    foreach ($farms as $farm) {
        echo "  - {$farm['location']}: {$farm['crop_type']}\n";
    }
    
    echo "\n✅ Farm crops updated successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
