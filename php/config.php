<?php

declare(strict_types=1);

// Database Configuration
// Set USE_MYSQL to true to use MySQL, false to use SQLite
const USE_MYSQL = true;

// MySQL Configuration
const MYSQL_HOST = 'localhost';
const MYSQL_USER = 'root';
const MYSQL_PASS = '';
const MYSQL_DBNAME = 'aquasense';

// SQLite Configuration (fallback)
const AQUASENSE_DB_PATH = __DIR__ . '/../aquasense.sqlite';

// App Configuration
const AQUASENSE_TOKEN_SECRET = 'aquasense-php-local-secret';
const AQUASENSE_SMS_ENABLED = false;
const AQUASENSE_SMS_MODE = 'sandbox';
const AQUASENSE_DEFAULT_FARM_ID = '550e8400-e29b-41d4-a716-446655440002';
