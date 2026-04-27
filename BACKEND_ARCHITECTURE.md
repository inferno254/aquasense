# AquaSense Backend Architecture & API Documentation

## 📁 **Key Backend Files**

### **1. `php/config.php` - Configuration**
**Purpose:** Central configuration file for database and app settings

**Key Settings:**
```php
USE_MYSQL = true              // Use MySQL (false = SQLite)
MYSQL_HOST = 'localhost'      // Database host
MYSQL_USER = 'root'          // Database user
MYSQL_PASS = ''              // Database password
MYSQL_DBNAME = 'aquasense'   // Database name
AQUASENSE_TOKEN_SECRET       // JWT signing secret
AQUASENSE_SMS_ENABLED        // SMS notification toggle
```

---

### **2. `php/bootstrap.php` - Core Bootstrap**
**Purpose:** Loads configuration and provides core helper functions

**Key Functions:**

#### **Database Connection**
```php
db(): PDO
- Returns singleton PDO instance
- Supports MySQL and SQLite
- Sets error mode to exceptions
```

#### **JSON Handling**
```php
json_input(): array
- Parses JSON from request body
- Returns empty array if no body

json_response(array $payload, int $status = 200): void
- Sends JSON response with HTTP status
- Sets CORS headers
- Exits after sending
```

#### **Authentication**
```php
issue_token(array $user): string
- Creates JWT token
- Base64 encodes payload
- HMAC-SHA256 signature
- 8-hour expiration

current_user(bool $required = true): ?array
- Validates Bearer token
- Checks signature
- Checks expiration
- Returns user data or 401 error
- Demo mode bypass for testing

ensure_farm_access(array $user, string $farmId): void
- Checks if user owns the farm
- Admins bypass check
- Returns 403 if forbidden
```

#### **State Management**
```php
set_state(string $key, string $value): void
- Stores key-value in app_state table
- Upserts (insert or update)

get_state(string $key, ?string $default = null): ?string
- Retrieves value from app_state
- Returns default if not found
```

#### **Utility Functions**
```php
to_camel_case_row(array $row): array
- Converts snake_case to camelCase
- For database field mapping

now_iso(): string
- Returns current time in ISO 8601 format
```

---

### **3. `php/api/index.php` - Main API Router**
**Purpose:** Handles all HTTP requests and routes to appropriate handlers

**Request Flow:**
1. Loads bootstrap.php
2. Parses URL path into segments
3. Checks HTTP method (GET, POST, PUT, etc.)
4. Matches route patterns
5. Executes handler
6. Returns JSON response

---

## 🔌 **API Endpoints**

### **Authentication Endpoints**

#### **POST `/auth/login`**
**Purpose:** User authentication

**Request Body:**
```json
{
  "email": "admin@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "token": "base64payload.signature",
  "user": {
    "userId": "uuid",
    "name": "Lewis Abuga",
    "email": "admin@example.com",
    "phone": "+254...",
    "role": "admin"
  },
  "farms": [
    {
      "farmId": "uuid",
      "location": "Gataka Farm",
      "cropType": "Maize",
      "size": 1.5
    }
  ],
  "message": "Login successful"
}
```

**Error Responses:**
- `401` - Invalid credentials

---

#### **POST `/auth/register`**
**Purpose:** New user registration

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securepass",
  "phone": "+254712345678"
}
```

**Response:**
```json
{
  "token": "base64payload.signature",
  "user": {
    "userId": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+254712345678",
    "role": "farmer"
  },
  "farms": [
    {
      "farmId": "uuid",
      "location": "New Farm",
      "cropType": "Maize",
      "size": 1.0
    }
  ],
  "message": "Registration successful"
}
```

**Error Responses:**
- `400` - Missing required fields
- `409` - Email already exists

---

### **Farm Management Endpoints**

#### **GET `/data/farms`**
**Purpose:** List all user farms

**Authentication:** Required

**Response:**
```json
[
  {
    "farmId": "uuid",
    "location": "Gataka Farm",
    "cropType": "Maize",
    "size": 1.5,
    "sensorCount": 24,
    "openAlertCount": 2,
    "latest": {
      "soilMoisture": 42.5,
      "temperature": 24.3,
      "humidity": 65.0,
      "timestamp": "2026-04-27T09:00:00Z"
    },
    "status": "Attention"
  }
]
```

**Status Values:**
- `Offline` - No sensor data
- `Dry` - Moisture < 35%
- `Stable` - Moisture 35-55%
- `Optimal` - Moisture > 55%
- `Attention` - Has open alerts

---

#### **POST `/data/farms`**
**Purpose:** Create new farm

**Authentication:** Required

**Request Body:**
```json
{
  "location": "Nakuru Farm",
  "cropType": "Tomato",
  "size": 2.5
}
```

**Response:**
```json
{
  "farmId": "uuid",
  "location": "Nakuru Farm",
  "cropType": "Tomato",
  "size": 2.5,
  "message": "Farm created successfully"
}
```

**Error Responses:**
- `400` - Missing required fields

---

### **Sensor Data Endpoints**

#### **GET `/data/farms/{farmId}/sensors?hours=24`**
**Purpose:** Get historical sensor readings

**Authentication:** Required

**Query Parameters:**
- `hours` - Number of hours to fetch (default: 24, max: 100)

**Response:**
```json
{
  "latest": {
    "timestamp": "2026-04-27T09:00:00Z",
    "soilMoisture": 42.5,
    "temperature": 24.3,
    "humidity": 65.0
  },
  "all": [
    {
      "timestamp": "2026-04-27T09:00:00Z",
      "soilMoisture": 42.5,
      "temperature": 24.3,
      "humidity": 65.0
    },
    ...
  ]
}
```

---

#### **GET `/data/farms/{farmId}/latest-reading`**
**Purpose:** Get most recent sensor reading

**Authentication:** Required

**Response:**
```json
{
  "soilMoisture": 42.5,
  "temperature": 24.3,
  "humidity": 65.0,
  "timestamp": "2026-04-27T09:00:00Z"
}
```

**Error Responses:**
- `404` - No readings found

---

### **Irrigation Event Endpoints**

#### **GET `/data/farms/{farmId}/events?limit=5`**
**Purpose:** Get irrigation history

**Authentication:** Required

**Query Parameters:**
- `limit` - Number of events (default: 10, max: 50)

**Response:**
```json
[
  {
    "eventId": "uuid",
    "triggerType": "manual",
    "startTime": "2026-04-27T08:00:00Z",
    "duration": 12,
    "waterUsed": 420.0,
    "status": "completed"
  },
  {
    "eventId": "uuid",
    "triggerType": "automatic",
    "startTime": "2026-04-27T06:00:00Z",
    "duration": 15,
    "waterUsed": 180.0,
    "status": "completed"
  }
]
```

**Trigger Types:**
- `manual` - User-triggered
- `automatic` - Threshold-triggered

**Status Values:**
- `pending` - Scheduled
- `in_progress` - Currently running
- `completed` - Finished
- `failed` - Error occurred

---

#### **POST `/irrigation/farms/{farmId}/irrigate`**
**Purpose:** Trigger irrigation (manual or automatic)

**Authentication:** Required

**Request Body:**
```json
{
  "manual": true,
  "duration": 12,
  "estimatedLitres": 420
}
```

**Response:**
```json
{
  "eventId": "uuid",
  "triggerType": "manual",
  "startedAt": "2026-04-27T09:00:00Z",
  "message": "Irrigation event created"
}
```

**Backend Actions:**
1. Creates irrigation event in database
2. Sets `trigger_type` based on `manual` flag
3. Creates alert notification
4. Sends SMS to farm owner (Swahili message)
5. Updates `last_sms_sent_at` state

---

### **Auto-Mode Endpoints**

#### **GET `/irrigation/farms/{farmId}/auto-mode`**
**Purpose:** Get auto-mode status

**Authentication:** Required

**Response:**
```json
{
  "enabled": true
}
```

---

#### **PUT `/irrigation/farms/{farmId}/auto-mode`**
**Purpose:** Toggle auto-mode

**Authentication:** Required

**Request Body:**
```json
{
  "enabled": true
}
```

**Response:**
```json
{
  "enabled": true,
  "message": "Auto mode updated"
}
```

**Backend Actions:**
- Stores state in `app_state` table
- Key: `auto_mode_{farmId}`
- Value: `'true'` or `'false'`

---

### **Alert Endpoints**

#### **GET `/data/farms/{farmId}/alerts`**
**Purpose:** Get farm-specific alerts

**Authentication:** NOT required (for dashboard compatibility)

**Query Parameters:**
- `resolved` - Filter by status (`true` or `false`)

**Response:**
```json
[
  {
    "alertId": "uuid",
    "alertType": "low_moisture",
    "message": "Soil moisture below threshold (28%)",
    "timestamp": "2026-04-27T08:00:00Z",
    "resolved": false
  },
  {
    "alertId": "uuid",
    "alertType": "irrigation_completed",
    "message": "Automatic irrigation completed successfully",
    "timestamp": "2026-04-27T07:00:00Z",
    "resolved": true
  }
]
```

**Alert Types:**
- `low_moisture` - Soil moisture below threshold
- `low_battery` - Sensor battery low
- `high_temperature` - Temperature elevated
- `manual_irrigation` - Manual irrigation started
- `automatic_irrigation` - Auto irrigation started
- `irrigation_completed` - Irrigation finished
- `sensor_offline` - Sensor connection lost

---

#### **GET `/data/alerts`**
**Purpose:** Get all alerts (admin only)

**Authentication:** Required

**Query Parameters:**
- `limit` - Number of alerts (default: 10, max: 50)

**Response:**
```json
[
  {
    "alertId": "uuid",
    "alertType": "low_moisture",
    "message": "Soil moisture below threshold (28%)",
    "timestamp": "2026-04-27T08:00:00Z",
    "resolved": false,
    "farmId": "uuid",
    "farmLocation": "Gataka Farm"
  }
]
```

**Access Control:**
- Admins see all alerts
- Farmers see only their farm's alerts

---

#### **POST `/data/alerts/{alertId}/resolve`**
**Purpose:** Mark alert as resolved

**Authentication:** Required

**Response:**
```json
{
  "message": "Alert resolved.",
  "alertId": "uuid",
  "resolvedAt": "2026-04-27T09:00:00Z"
}
```

**Error Responses:**
- `404` - Alert not found
- `403` - User doesn't own the farm

---

### **Sensor Hardware Endpoints**

#### **POST `/sensor/hardware/{farmId}/reading`**
**Purpose:** Receive sensor data from IoT hardware

**Authentication:** NOT required (for hardware access)

**Request Body:**
```json
{
  "soilMoisture": 42.5,
  "temperature": 24.3,
  "humidity": 65.0,
  "batteryLevel": 85.0,
  "solarLevel": 72.0
}
```

**Response:**
```json
{
  "message": "Sensor reading recorded"
}
```

**Backend Actions:**
1. Stores reading in `sensor_readings` table
2. Calls `check_auto_irrigation()` to trigger auto-irrigation if needed
3. Creates alerts if thresholds breached

---

#### **POST `/sensor/farms/{farmId}/sensor-readings`**
**Purpose:** Manual sensor data entry (user-triggered)

**Authentication:** Required

**Request Body:** Same as hardware endpoint

**Response:** Same as hardware endpoint

---

### **Notification Endpoints**

#### **GET `/notifications/status`**
**Purpose:** Get SMS notification status

**Authentication:** Required

**Response:**
```json
{
  "enabled": false,
  "configured": true,
  "lastSentAt": "2026-04-27T08:00:00Z",
  "mode": "sandbox"
}
```

---

## 🔧 **Backend Helper Functions**

### **SMS Integration**
```php
send_sms_alert(string $phone, string $message): bool
- Uses Africa's Talking API
- Sends SMS to farmer
- Returns success/failure
- Logs attempts for debugging

get_user_phone(string $userId): ?string
- Retrieves user's phone number
- Returns null if not set

notify_farm_owner(string $farmId, string $message): void
- Gets farm owner's phone
- Sends SMS with farm location prefix
- Silent fail if no phone
```

### **Automatic Irrigation Logic**
```php
check_auto_irrigation(string $farmId, array $sensorData): void
- Checks if auto-mode enabled
- Checks moisture threshold (default 35%)
- Checks if already irrigated today
- Triggers irrigation if conditions met
- Creates irrigation event
- Marks as done in app_state
- Sends SMS notification
```

### **Sensor Recording**
```php
record_sensor_reading(string $farmId, array $sensorData, bool $manual): void
- Stores sensor reading in database
- Checks thresholds
- Creates alerts if needed
- Manual flag distinguishes source
```

---

## 🗄️ **Database Tables**

### **users**
- `user_id` (UUID, PK)
- `email` (VARCHAR, UNIQUE)
- `password_hash` (VARCHAR)
- `name` (VARCHAR)
- `phone` (VARCHAR)
- `role` (ENUM: admin, farmer)
- `created_at` (TIMESTAMP)

### **farms**
- `farm_id` (UUID, PK)
- `user_id` (UUID, FK → users.user_id)
- `location` (VARCHAR)
- `crop_type` (VARCHAR)
- `size` (FLOAT)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

### **sensor_readings**
- `reading_id` (UUID, PK)
- `farm_id` (UUID, FK → farms.farm_id)
- `soil_moisture` (FLOAT)
- `temperature` (FLOAT)
- `humidity` (FLOAT)
- `battery_level` (FLOAT)
- `solar_level` (FLOAT)
- `timestamp` (TIMESTAMP)

### **irrigation_events**
- `event_id` (UUID, PK)
- `farm_id` (UUID, FK → farms.farm_id)
- `trigger_type` (ENUM: manual, automatic)
- `start_time` (TIMESTAMP)
- `end_time` (TIMESTAMP)
- `water_used` (FLOAT)
- `status` (ENUM: pending, in_progress, completed, failed)

### **system_alerts**
- `alert_id` (UUID, PK)
- `farm_id` (UUID, FK → farms.farm_id)
- `alert_type` (VARCHAR)
- `message` (TEXT)
- `timestamp` (TIMESTAMP)
- `resolved` (BOOLEAN)
- `resolved_at` (TIMESTAMP)
- `sms_sent` (BOOLEAN)

### **app_state**
- `key` (VARCHAR, PK)
- `value` (TEXT)
- `updated_at` (TIMESTAMP)

---

## 🔐 **Security Features**

### **Token-Based Authentication**
- JWT-like tokens with HMAC-SHA256 signatures
- 8-hour expiration
- Base64 encoded payload
- Demo mode bypass for testing

### **Access Control**
- Farm-level access control
- Admins bypass farm checks
- Farmers only see their farms
- Role-based permissions

### **Password Security**
- BCrypt password hashing
- Never store plain text passwords

### **CORS**
- Allows cross-origin requests
- Configured for frontend access
- Headers: Content-Type, Authorization

---

## 📊 **Data Flow Diagram**

```
[IoT Sensors]
    ↓ POST /sensor/hardware/{farmId}/reading
[API: record_sensor_reading]
    ↓ Store in sensor_readings
[Database]
    ↓ check_auto_irrigation()
[API: check thresholds]
    ↓ If moisture < threshold
[API: create_irrigation_event]
    ↓ Store in irrigation_events
[Database]
    ↓ create_alert()
[API: create notification]
    ↓ Store in system_alerts
[Database]
    ↓ notify_farm_owner()
[API: send_sms_alert]
    ↓ Africa's Talking API
[Farmer Phone]
```

---

## 🚀 **API Base URL**

```
http://localhost/php/api
```

All endpoints are relative to this base URL.

---

## 📝 **Example API Calls**

### **Login**
```bash
curl -X POST http://localhost/php/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}'
```

### **Get Farms**
```bash
curl -X GET http://localhost/php/api/data/farms \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### **Trigger Irrigation**
```bash
curl -X POST http://localhost/php/api/irrigation/farms/{farmId}/irrigate \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"manual":true,"duration":12,"estimatedLitres":420}'
```

### **Get Alerts**
```bash
curl -X GET http://localhost/php/api/data/farms/{farmId}/alerts
```

---

This documentation covers all key backend files and API endpoints for the AquaSense system.
