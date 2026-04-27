<?php

declare(strict_types=1);

// Prevent PHP warnings/notices from breaking JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Global exception handler to catch fatal errors and return JSON
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
        'file' => $e->getFile() . ':' . $e->getLine()
    ]);
    exit;
});

// Shutdown handler to catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Fatal error',
            'error' => $error['message'],
            'file' => $error['file'] . ':' . $error['line']
        ]);
    }
});

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (USE_MYSQL) {
        // MySQL connection
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', MYSQL_HOST, MYSQL_DBNAME);
        $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } else {
        // SQLite connection
        if (!file_exists(AQUASENSE_DB_PATH)) {
            throw new RuntimeException('Database file not found: ' . AQUASENSE_DB_PATH);
        }
        $pdo = new PDO('sqlite:' . AQUASENSE_DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }

    return $pdo;
}

function json_input(): array
{
    $body = file_get_contents('php://input');
    if ($body === false || trim($body) === '') {
        return [];
    }

    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function issue_token(array $user): string
{
    $payload = [
        'user_id' => $user['user_id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'role' => $user['role'],
        'exp' => time() + (8 * 60 * 60),
    ];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encoded, AQUASENSE_TOKEN_SECRET);

    return $encoded . '.' . $signature;
}

function current_user(bool $required = true): ?array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        if ($required) {
            json_response(['message' => 'Unauthorized'], 401);
        }
        return null;
    }

    $token = trim($matches[1]);
    
    // Demo mode bypass for testing
    if ($token === 'demo-token-12345') {
        return [
            'user_id' => '22222222-2222-2222-2222-222222222222',
            'email' => 'admin@example.com',
            'name' => 'Lewis Abuga',
            'role' => 'admin',
            'exp' => time() + 86400,
        ];
    }

    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        json_response(['message' => 'Invalid token'], 401);
    }

    [$encoded, $signature] = $parts;
    $expected = hash_hmac('sha256', $encoded, AQUASENSE_TOKEN_SECRET);
    if (!hash_equals($expected, $signature)) {
        json_response(['message' => 'Invalid token signature'], 401);
    }

    $decoded = base64_decode(strtr($encoded, '-_', '+/'));
    $payload = json_decode($decoded ?: '', true);
    if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
        json_response(['message' => 'Token expired'], 401);
    }

    return $payload;
}

function ensure_farm_access(array $user, string $farmId): void
{
    if (($user['role'] ?? 'farmer') === 'admin') {
        return;
    }

    $stmt = db()->prepare('SELECT 1 FROM farms WHERE farm_id = :farm_id AND user_id = :user_id LIMIT 1');
    $stmt->execute([
        ':farm_id' => $farmId,
        ':user_id' => $user['user_id'],
    ]);

    if (!$stmt->fetchColumn()) {
        json_response(['message' => 'Forbidden'], 403);
    }
}

function to_camel_case_row(array $row): array
{
    $mapped = [];
    foreach ($row as $key => $value) {
        $mapped[preg_replace_callback('/_([a-z])/', static fn(array $m) => strtoupper($m[1]), $key)] = $value;
    }
    return $mapped;
}

function send_sms_status(): array
{
    $stmt = db()->query('SELECT value FROM app_state WHERE key = "last_sms_sent_at" LIMIT 1');
    $lastSent = $stmt->fetchColumn() ?: null;

    return [
        'enabled' => AQUASENSE_SMS_ENABLED,
        'configured' => true,
        'lastSentAt' => $lastSent,
        'mode' => AQUASENSE_SMS_MODE,
    ];
}

function set_state(string $key, string $value): void
{
    if (USE_MYSQL) {
        $stmt = db()->prepare('INSERT INTO app_state(`key`, value) VALUES(:key, :value)
            ON DUPLICATE KEY UPDATE value = VALUES(value)');
    } else {
        $stmt = db()->prepare('INSERT INTO app_state(key, value) VALUES(:key, :value)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    }
    $stmt->execute([':key' => $key, ':value' => $value]);
}

function get_state(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT value FROM app_state WHERE key = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function now_iso(): string
{
    return gmdate('c');
}
