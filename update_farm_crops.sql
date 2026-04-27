-- Update Farm Crops in Database
-- Run this in phpMyAdmin SQL tab

UPDATE farms SET crop_type = 'Beans' WHERE location = 'Hardy Farm';

-- Verify the update
SELECT farm_id, location, crop_type FROM farms;
