# AquaSense Presentation Guide - Exam Day

## 🎯 **Project Overview**

**AquaSense** is a smart irrigation management system that uses IoT sensors to monitor soil moisture, temperature, and humidity. It automatically triggers irrigation when moisture levels fall below a threshold, ensuring optimal crop health while conserving water.

---

## 📱 **Frontend Architecture**

### **Technology Stack**
- **HTML5** - Structure and layout
- **CSS3** - Styling with custom CSS framework
- **JavaScript (Vanilla)** - Client-side logic and API interactions
- **Chart.js** - Data visualization for moisture trends
- **Font Awesome** - Icons and UI elements

### **Key Pages**

#### **1. Login Page (`login.html`)**
- User authentication via email/password
- Local storage fallback for offline mode
- Demo mode for testing

#### **2. Dashboard (`dashboard.html`)**
- Real-time sensor data display
- Irrigation controls (manual/auto)
- System alerts monitoring
- Irrigation event history
- 24-hour moisture trend chart

#### **3. Features Page (`features.html`)**
- Interactive feature showcase
- Live data panels for each feature
- Authentication-gated content

#### **4. Home Page (`index.html`)**
- Landing page with project overview
- Feature highlights
- Call-to-action to login

### **Frontend Features**

#### **Real-Time Dashboard**
- **Live Sensor Readings:**
  - Soil Moisture (%)
  - Temperature (°C)
  - Humidity (%)
  - Battery Level (%)
  - Solar Panel (%)

- **Smart Irrigation Controls:**
  - Manual irrigation trigger
  - Auto-mode toggle
  - Moisture threshold slider (20-80%)
  - Emergency stop button

- **Visual Indicators:**
  - Green = Optimal conditions
  - Orange = Attention needed
  - Red = Critical alert
  - Pulsing animations for active alerts

#### **User-Specific Data**
- Each user has isolated localStorage
- Keys: `irrigationEvents_{userId}`, `moistureThreshold_{userId}`, etc.
- Logout clears all user-specific data

#### **Responsive Design**
- Mobile-friendly layout
- Dark/Light mode toggle
- Language toggle (English/Swahili)

---

## 🔧 **Backend Architecture**

### **Technology Stack**
- **PHP 8.x** - Server-side logic
- **MySQL** - Database management
- **PDO** - Database abstraction layer
- **RESTful API** - JSON-based endpoints

### **API Base URL**
```
http://localhost/php/api
```

### **Key API Endpoints**

#### **Authentication**
```
POST /auth/login
POST /auth/register
```

#### **Farms Management**
```
GET  /data/farms                    - List all user farms
POST /data/farms                    - Create new farm
GET  /data/farms/{farmId}           - Get farm details
PUT  /data/farms/{farmId}           - Update farm
DELETE /data/farms/{farmId}         - Delete farm
```

#### **Sensor Data**
```
GET /data/farms/{farmId}/sensors?hours=24
GET /data/farms/{farmId}/latest-reading
```

#### **Irrigation Events**
```
GET  /data/farms/{farmId}/events?limit=5
POST /irrigation/farms/{farmId}/irrigate
PUT  /irrigation/farms/{farmId}/auto-mode
```

#### **System Alerts**
```
GET  /data/farms/{farmId}/alerts
POST /data/alerts/{alertId}/resolve
```

### **Backend Features**

#### **Automatic Irrigation Logic**
```php
function check_auto_irrigation($farmId, $sensorData) {
    // Check if auto-mode is enabled
    // Check moisture threshold (default 35%)
    // Check if already irrigated today
    // Trigger irrigation if conditions met
    // Create irrigation event in database
    // Send SMS notification to farm owner
}
```

#### **SMS Integration**
- Africa's Talking API integration
- Alerts sent for:
  - Low moisture
  - Low battery
  - Irrigation completion
  - Sensor offline

#### **User Authorization**
- Token-based authentication
- Farm access control (users only see their farms)
- Role-based access (admin/farmer)

---

## 🗄️ **Database Schema**

### **Tables**

#### **1. `users`**
```sql
user_id (UUID, PK)
email (VARCHAR, UNIQUE)
password_hash (VARCHAR)
name (VARCHAR)
phone (VARCHAR)
role (ENUM: admin, farmer)
created_at (TIMESTAMP)
```

#### **2. `farms`**
```sql
farm_id (UUID, PK)
user_id (UUID, FK → users.user_id)
location (VARCHAR)
crop_type (VARCHAR)
size (FLOAT) - in hectares
created_at (TIMESTAMP)
updated_at (TIMESTAMP)
```

#### **3. `sensor_readings`**
```sql
reading_id (UUID, PK)
farm_id (UUID, FK → farms.farm_id)
soil_moisture (FLOAT)
temperature (FLOAT)
humidity (FLOAT)
battery_level (FLOAT)
solar_level (FLOAT)
timestamp (TIMESTAMP)
```

#### **4. `irrigation_events`**
```sql
event_id (UUID, PK)
farm_id (UUID, FK → farms.farm_id)
trigger_type (ENUM: manual, automatic)
start_time (TIMESTAMP)
end_time (TIMESTAMP)
water_used (FLOAT) - in liters
status (ENUM: pending, in_progress, completed, failed)
```

#### **5. `system_alerts`**
```sql
alert_id (UUID, PK)
farm_id (UUID, FK → farms.farm_id)
alert_type (VARCHAR)
message (TEXT)
timestamp (TIMESTAMP)
resolved (BOOLEAN)
resolved_at (TIMESTAMP)
sms_sent (BOOLEAN)
```

#### **6. `app_state`**
```sql
state_key (VARCHAR, PK)
state_value (TEXT)
updated_at (TIMESTAMP)
```

### **Relationships**
```
users (1) ----< (N) farms
farms (1) ----< (N) sensor_readings
farms (1) ----< (N) irrigation_events
farms (1) ----< (N) system_alerts
```

### **Data Flow**
1. **Sensors** → Send readings to `/sensor/hardware/reading`
2. **Backend** → Stores in `sensor_readings` table
3. **Backend** → Checks thresholds → Triggers irrigation if needed
4. **Backend** → Creates `irrigation_events` record
5. **Backend** → Creates `system_alerts` record
6. **Frontend** → Polls API every 5 seconds for updates
7. **Frontend** → Displays real-time data to user

---

## 🎬 **Presentation Demo Script**

### **Setup (Before Presentation)**
1. ✅ Start XAMPP (Apache + MySQL)
2. ✅ Open browser to `http://localhost/login.html`
3. ✅ Open phpMyAdmin at `http://localhost/phpmyadmin`
4. ✅ Clear browser cache (F12 → Console → `localStorage.clear()`)

### **Demo Flow**

#### **Step 1: Introduction (2 minutes)**
- "Welcome to AquaSense - a smart irrigation management system"
- "It uses IoT sensors to monitor soil conditions and automatically irrigate when needed"
- "This helps farmers save water while ensuring optimal crop health"

#### **Step 2: Login (1 minute)**
- Navigate to login page
- Login with:
  - Email: `admin@example.com`
  - Password: `password123`
- "The system authenticates users and shows only their farms"

#### **Step 3: Dashboard Overview (2 minutes)**
- "This is the main dashboard showing real-time sensor data"
- Point out:
  - **Live Readings**: Soil moisture, temperature, humidity, battery, solar
  - **Recent Activity**: Latest sensor status
  - **Manual Controls**: Irrigate, auto-mode, threshold slider
  - **Irrigation History**: Past irrigation events
  - **System Alerts**: Warnings and notifications

#### **Step 4: Smart Irrigation Demo (5 minutes)**
- **Select Gataka Farm** (has lower moisture ~28%)
- **Show current moisture is below threshold (35%)**
- **Explain auto-mode is ON**
- **Watch the automatic trigger:**
  1. Red alert appears: "Low Moisture Alert: 28% < 35%"
  2. Auto-irrigation activates with spinning icon
  3. Status shows: "Auto-Irrigation Activated"
  4. After 3 seconds: "Irrigation Complete - Moisture Recovered"
  5. Event recorded in irrigation history
- **Explain the flow:**
  - Sensor detects low moisture
  - System checks threshold
  - Auto-irrigation triggers
  - Water delivered (150L)
  - Moisture recovers
  - Event saved to database

#### **Step 5: Irrigation History (2 minutes)**
- Show "Recent Irrigation Events" panel
- Point out mix of:
  - **Automatic** irrigations (threshold-triggered)
  - **Manual** irrigations (user-triggered)
- Show details: time, duration, water used
- "All events are persisted in the database"

#### **Step 6: System Alerts (2 minutes)**
- Show "System Alerts" panel
- Point out different alert types:
  - **Low moisture** - Critical soil condition
  - **Low battery** - Sensor needs charging
  - **Irrigation completed** - Confirmation
  - **Sensor offline** - Connection issue
- Show resolved vs open alerts
- "Alerts help farmers stay informed about system status"

#### **Step 7: Database Backend (3 minutes)**
- Switch to phpMyAdmin
- Show `aquasense` database
- Walk through tables:
  - `users` - User accounts
  - `farms` - Farm information
  - `sensor_readings` - 48 readings in database
  - `irrigation_events` - 10 events (5 manual, 5 auto)
  - `system_alerts` - 16 alerts
- Show relationships between tables
- "All data is persisted and can be queried"

#### **Step 8: API Endpoints (2 minutes)**
- Open browser console (F12)
- Show API calls in Network tab:
  - `/data/farms/{farmId}/latest-reading` - Sensor data
  - `/data/farms/{farmId}/events` - Irrigation history
  - `/data/farms/{farmId}/alerts` - System alerts
- Explain RESTful API structure
- "Frontend polls these endpoints every 5 seconds"

#### **Step 9: User Isolation (2 minutes)**
- Logout from admin account
- Login with farmer account:
  - Email: `farmer@kenya.com`
  - Password: `irrigate2024`
- Show that:
  - Different user sees different farms
  - Irrigation events are user-specific
  - Alerts are user-specific
- "Each user has isolated data for privacy"

#### **Step 10: Conclusion (1 minute)**
- "AquaSense provides:"
  - Real-time monitoring
  - Automatic irrigation
  - Water conservation
  - User-friendly interface
  - Data persistence
  - Multi-user support
- "Thank you for your attention"

---

## 💡 **Key Talking Points**

### **Problem Solved**
- Farmers waste water by over-irrigating
- Manual monitoring is time-consuming
- Crops suffer from inconsistent watering

### **Solution**
- IoT sensors provide real-time data
- Automatic irrigation based on thresholds
- Alerts notify farmers of issues
- History tracking for analysis

### **Technical Highlights**
- **Frontend**: Vanilla JS, no frameworks needed
- **Backend**: PHP RESTful API
- **Database**: MySQL with proper relationships
- **Security**: Token-based authentication
- **Scalability**: Multi-user, multi-farm support

### **Future Enhancements**
- Mobile app
- Weather API integration
- Machine learning for predictive irrigation
- More sensor types (pH, nutrients)

---

## 🚨 **Troubleshooting**

### **If Dashboard Shows Demo Mode**
- Clear localStorage: `localStorage.clear()`
- Refresh page
- Login again

### **If Alerts Don't Load**
- Check browser console for errors
- Verify API endpoint is accessible
- Check database has alerts

### **If Irrigation Events Don't Show**
- Verify database has events
- Check API response in Network tab
- Ensure farm ID is correct

---

## 📊 **Database Statistics**

- **Users**: 2 (admin, farmer)
- **Farms**: 2 (Gataka Farm, Hardy Farm)
- **Sensor Readings**: 48 (24 per farm)
- **Irrigation Events**: 10 (5 manual, 5 automatic)
- **System Alerts**: 16 (8 per farm)

---

## 🔐 **Login Credentials**

**Admin Account:**
- Email: `admin@example.com`
- Password: `password123`
- Farms: Gataka Farm (Maize), Hardy Farm (Beans)

**Farmer Account:**
- Email: `farmer@kenya.com`
- Password: `irrigate2024`
- Farms: (None - can add farms)

---

## 🌐 **Access URLs**

- **Frontend**: `http://localhost/dashboard.html`
- **Login**: `http://localhost/login.html`
- **phpMyAdmin**: `http://localhost/phpmyadmin`
- **API Base**: `http://localhost/php/api`

---

**Good luck with your presentation! 🎓**
