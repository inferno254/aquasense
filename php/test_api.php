<?php
declare(strict_types=1);

// Disable error display to prevent HTML in JSON
ini_set('display_errors', '0');
error_reporting(0);

try {
    require_once __DIR__ . '/bootstrap.php';
    echo json_encode(['status' => 'ok', 'message' => 'API is working']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
