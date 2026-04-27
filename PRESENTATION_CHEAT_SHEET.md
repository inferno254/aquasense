# 🌱 AQUASENSE - PRESENTATION CHEAT SHEET

## Quick Reference Guide (One Page)

---

## 🎯 ELEVATOR PITCH (30 seconds)
**"AquaSense is an affordable ($50) IoT irrigation system that automates watering based on soil moisture, sends SMS alerts to farmers without smartphones, and works entirely off-grid with solar power - bringing precision agriculture to small-scale farmers who need it most."**

---

## 📊 KEY NUMBERS TO REMEMBER

| Metric | Value | Context |
|--------|-------|---------|
| **Hardware Cost** | ~$50 | vs $3000+ commercial |
| **Water Savings** | 30-40% | Through precision irrigation |
| **Battery Backup** | 48 hours | Solar + battery system |
| **Sensor Accuracy** | ±3% moisture | Capacitive sensors |
| **Sampling Rate** | Every 15 min | Real-time monitoring |
| **Languages** | 2 | English + Swahili |

---

## 🏗️ SYSTEM ARCHITECTURE (3 Layers)

```
HARDWARE → BACKEND → FRONTEND
────────   ───────   ────────
ESP32      PHP API    HTML/CSS
Sensors    SQLite     JS/Chart.js
Solar      JWT Auth   PWA
Valve      SMS API    Responsive
```

---

## 📱 6 CORE FEATURES (Remember These!)

1. **Smart Moisture** - Real-time soil monitoring with history charts
2. **Auto Irrigation** - Triggers when moisture < 35% (configurable)
3. **Live Dashboard** - Multi-farm monitoring, real-time updates
4. **SMS Alerts** - Critical alerts sent via text (no smartphone needed)
5. **Solar Power** - Battery + solar monitoring, 48hr backup
6. **Analytics** - Water usage reports, efficiency tracking

---

## 🔄 HOW IT WORKS (5 Steps)

```
1. COLLECT → Sensors read every 15 min
2. PROCESS → ESP32 checks thresholds
3. TRIGGER → Valve opens if needed
4. ALERT → SMS + Dashboard update
5. STORE → Data logged to SQLite
```

---

## 🗄️ DATABASE TABLES (6 Core Tables)

| Table | Purpose | Key Field |
|-------|---------|-----------|
| **users** | Farmer accounts | userId (PK) |
| **farms** | Farm locations | FarmId (PK) |
| **sensors** | Sensor configs | SensorId (PK) |
| **sensor_data** | Time-series readings | ReadingId (PK) |
| **irrigation_events** | Water usage logs | EventId (PK) |
| **system_alerts** | Notifications | AlertId (PK) |

**PK = Primary Key | FK = Foreign Key**

---

## 🔐 AUTHENTICATION FLOW

```
Login → JWT Token → localStorage → API Requests (Bearer)
```

**Demo Account:** `admin@example.com` / `password123`

---

## 🌐 KEY API ENDPOINTS

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/auth/login` | POST | Login |
| `/data/farms` | GET | List farms |
| `/data/farms/{id}/sensors` | GET | Latest readings |
| `/irrigation/farms/{id}/irrigate` | POST | Trigger irrigation |

---

## ⚡ OFFLINE CAPABILITIES

| Function | Works Offline? |
|----------|---------------|
| Login | ✅ Yes (localStorage) |
| View Data | ✅ Yes (cached) |
| Edit Farm | ✅ Yes (syncs later) |
| Add Farm | ✅ Yes (syncs later) |
| SMS | ❌ No (needs API) |

---

## 💡 KEY SELLING POINTS (For Q&A)

1. **Affordable** - 95% cheaper than commercial systems
2. **Offline-First** - Works without internet
3. **Solar Powered** - No electrical infrastructure
4. **Local Language** - Swahili support for accessibility
5. **Open Source** - PHP/SQLite, fully customizable
6. **SMS Alerts** - No smartphone required

---

## 🛠️ TECH STACK SUMMARY

| Layer | Technology |
|-------|------------|
| Microcontroller | ESP32-WROOM-32 |
| Backend | PHP 8.x + SQLite |
| Frontend | HTML5 + Vanilla JS |
| Charts | Chart.js |
| Auth | JWT Tokens |
| SMS | Africa's Talking API |
| Styling | CSS Grid + Flexbox |

---

## 🎨 DESIGN PRINCIPLES

- **Mobile-First** - Farmers use phones, not desktops
- **Offline-First** - Rural areas have poor connectivity
- **Solar-First** - No grid electricity required
- **Accessible** - Swahili + simple UI
- **Affordable** - DIY hardware under $50

---

## 📈 BUSINESS MODEL / IMPACT

**Target Users:** Small-scale farmers in water-scarce regions

**Value Proposition:**
- Save 30-40% water
- Increase yields through optimal irrigation
- Reduce manual labor
- Prevent crop loss from over/under-watering

**Scalability:**
- SQLite handles thousands of users
- Can deploy on shared hosting
- Hardware easily replicable

---

## ❓ COMMON QUESTIONS & ANSWERS

**Q: What if internet goes down?**
A: System stores data locally, syncs when connection returns. SMS still works via GSM.

**Q: How accurate is the soil moisture?**
A: Capacitive sensors are ±3% accurate, calibrated for soil type.

**Q: Can one user manage multiple farms?**
A: Yes, dashboard supports multi-farm switching.

**Q: Is it weatherproof?**
A: IP65 enclosure rated for -20°C to +60°C.

**Q: How long does battery last?**
A: 48 hours without sun, recharges via solar.

---

## 🔗 USEFUL LINKS DURING PRESENTATION

- **Dashboard:** `http://aquasense.local/dashboard.html`
- **Database Viewer:** `http://aquasense.local/db_viewer.php`
- **Features Page:** `http://aquasense.local/features.html`

---

## 🎯 CLOSING STATEMENT

**"AquaSense brings precision agriculture to every farmer, regardless of resources or connectivity. Smart irrigation for a water-scarce world."**

---

*Good luck with your presentation! 🚀*
