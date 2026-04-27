<?php
/**
 * Quick Database Update Script
 * Updates Hardy Farm crop type to Beans
 */

require_once 'php/bootstrap.php';

try {
    $db = db();
    
    echo "=== AquaSense Database Update ===\n\n";
    
    // Check current farms
    echo "Current farms in database:\n";
    $stmt = $db->prepare("SELECT farm_id, location, crop_type FROM farms");
    $stmt->execute();
    $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($farms as $farm) {
        echo "  - {$farm['location']}: {$farm['crop_type']}\n";
    }
    
    echo "\nUpdating Hardy Farm crop type...\n";
    
    // Update Hardy Farm
    $stmt = $db->prepare("UPDATE farms SET crop_type = 'Beans' WHERE location = 'Hardy Farm'");
    $stmt->execute();
    
    $affected = $stmt->rowCount();
    if ($affected > 0) {
        echo "✓ Hardy Farm updated to Beans\n";
    } else {
        echo "ℹ Hardy Farm already has correct crop type or not found\n";
    }
    
    // Verify the update
    echo "\nUpdated farms:\n";
    $stmt = $db->prepare("SELECT farm_id, location, crop_type FROM farms");
    $stmt->execute();
    $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($farms as $farm) {
        echo "  - {$farm['location']}: {$farm['crop_type']}\n";
    }
    
    echo "\n✅ Database update completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
