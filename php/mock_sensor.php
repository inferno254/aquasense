<?php
/**
 * Mock Sensor Data Generator for AquaSense PHP Backend
 * Simulates ESP32 sensor readings for testing
 * Usage: php mock_sensor.php [farmId] [--dry] [--low-moisture] [--count N] [--interval S]
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$opts = getopt('', ['dry', 'low-moisture', 'count:', 'interval::']);
$farmId = $argv[1] ?? AQUASENSE_DEFAULT_FARM_ID;
$count = (int) ($opts['count'] ?? 1);
$interval = (int) ($opts['interval'] ?? 1);
$isDry = isset($opts['low-moisture']);
$isDryRun = isset($opts['dry']);

echo "Mock Sensor Generator for Farm ID: $farmId\n";
echo "Count: $count, Interval: {$interval}s, Dry: " . ($isDry ? 'YES' : 'NO') . ", DryRun: " . ($isDryRun ? 'YES' : 'NO') . "\n";

$baseSoil = $isDry ? 28.0 : 52.0;
$soilDelta = $isDry ? -1.2 : 0.8;
$tempBase = 25.0 + (rand(0, 8) / 10);
$humBase = 62.0 + rand(-5, 8);

for ($i = 0; $i < $count; $i++) {
    $soilMoisture = round($baseSoil + ($i * $soilDelta) + (rand(-50, 50)/10), 1);
    $temperature = round($tempBase + (rand(-20, 20)/10), 1);
    $humidity = round($humBase + (rand(-30, 30)/10), 1);
    $timestamp = gmdate('c', time() - ($count - $i) * $interval * 60);

    if ($isDryRun) {
        echo "DRY: {$timestamp} | Soil: {$soilMoisture}% Temp: {$temperature}°C Hum: {$humidity}%\n";
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('INSERT INTO sensor_readings(reading_id, farm_id, soil_moisture, temperature, humidity, timestamp) VALUES(:id, :farm, :soil, :temp, :hum, :ts)');
            $stmt->execute([
                ':id' => 'mock-' . uniqid(),
                ':farm' => $farmId,
                ':soil' => $soilMoisture,
                ':temp' => $temperature,
                ':hum' => $humidity,
                ':ts' => $timestamp
            ]);
            echo "✓ Posted: Soil {$soilMoisture}% @ {$timestamp}\n";
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }

    if ($i < $count - 1) {
        sleep($interval);
    }
}

echo "Mock complete.\n";
?>

