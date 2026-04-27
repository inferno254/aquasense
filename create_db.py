#!/usr/bin/env python3
"""
Create AquaSense SQLite Database
Run this script to initialize the database
"""

import sqlite3
import os
import hashlib
import uuid
import random
from datetime import datetime, timedelta

# Database path
db_dir = os.path.dirname(os.path.abspath(__file__))
db_path = os.path.join(db_dir, 'database', 'aquasense.db')

print(f"Creating database at: {db_path}")

# Ensure directory exists
os.makedirs(os.path.dirname(db_path), exist_ok=True)

# Connect to database
conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# Create tables
cursor.execute('''
CREATE TABLE IF NOT EXISTS users (
    user_id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    phone TEXT,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT "farmer",
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
''')

cursor.execute('''
CREATE TABLE IF NOT EXISTS farms (
    farm_id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    location TEXT NOT NULL,
    size REAL,
    crop_type TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
''')

cursor.execute('''
CREATE TABLE IF NOT EXISTS sensor_readings (
    reading_id TEXT PRIMARY KEY,
    farm_id TEXT NOT NULL,
    soil_moisture REAL,
    temperature REAL,
    humidity REAL,
    timestamp TEXT NOT NULL,
    FOREIGN KEY(farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
);
''')

cursor.execute('''
CREATE TABLE IF NOT EXISTS irrigation_events (
    event_id TEXT PRIMARY KEY,
    farm_id TEXT NOT NULL,
    trigger_type TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT,
    water_used REAL,
    status TEXT NOT NULL DEFAULT "completed",
    FOREIGN KEY(farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
);
''')

cursor.execute('''
CREATE TABLE IF NOT EXISTS system_alerts (
    alert_id TEXT PRIMARY KEY,
    farm_id TEXT NOT NULL,
    alert_type TEXT NOT NULL,
    message TEXT,
    timestamp TEXT NOT NULL,
    resolved INTEGER NOT NULL DEFAULT 0,
    resolved_at TEXT,
    FOREIGN KEY(farm_id) REFERENCES farms(farm_id) ON DELETE CASCADE
);
''')

cursor.execute('''
CREATE TABLE IF NOT EXISTS app_state (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
''')

print("✓ Tables created")

# Insert demo users
def hash_password(password):
    # Simple hash for demo (in real app use bcrypt)
    return hashlib.sha256(password.encode()).hexdigest()

def generate_uuid():
    return str(uuid.uuid4())

users = [
    ('22222222-2222-2222-2222-222222222222', 'Lewis Abuga', 'admin@example.com', '+254700123456', hash_password('password123'), 'admin'),
    ('11111111-1111-1111-1111-111111111111', 'Lewis Abuga', 'farmer@kenya.com', '+254712345678', hash_password('irrigate2024'), 'farmer'),
]

cursor.executemany('''
    INSERT OR REPLACE INTO users (user_id, name, email, phone, password_hash, role)
    VALUES (?, ?, ?, ?, ?, ?)
''', users)

print("✓ Users inserted")

# Insert demo farms
farms = [
    ('550e8400-e29b-41d4-a716-446655440002', '22222222-2222-2222-2222-222222222222', 'Gataka Farm', 1.5, 'Maize'),
    ('550e8400-e29b-41d4-a716-446655440005', '22222222-2222-2222-2222-222222222222', 'Hardy Farm', 2.0, 'Tomatoes'),
]

cursor.executemany('''
    INSERT OR REPLACE INTO farms (farm_id, user_id, location, size, crop_type)
    VALUES (?, ?, ?, ?, ?)
''', farms)

print("✓ Farms inserted")

# Insert sensor readings
now = datetime.utcnow()
readings = []

profiles = {
    '550e8400-e29b-41d4-a716-446655440002': (28.0, 26.0, 62.0, 0.8, 0.1, 0.4),
    '550e8400-e29b-41d4-a716-446655440005': (42.0, 24.5, 55.0, 0.7, 0.09, 0.38),
}

for farm_id, (soil, temp, humidity, soil_step, temp_step, humidity_step) in profiles.items():
    for i in range(18):
        timestamp = (now - timedelta(minutes=i * 30)).isoformat()
        readings.append((
            generate_uuid(),
            farm_id,
            round(soil + (i * soil_step), 1),
            round(temp + (i * temp_step), 1),
            round(humidity + (i * humidity_step), 1),
            timestamp
        ))

cursor.executemany('''
    INSERT INTO sensor_readings (reading_id, farm_id, soil_moisture, temperature, humidity, timestamp)
    VALUES (?, ?, ?, ?, ?, ?)
''', readings)

print("✓ Sensor readings inserted")

# Insert irrigation events
events = []
for farm in farms:
    events.append((
        generate_uuid(),
        farm[0],
        'automatic',
        (now - timedelta(hours=5)).isoformat(),
        (now - timedelta(hours=4, minutes=48)).isoformat(),
        420,
        'completed'
    ))
    events.append((
        generate_uuid(),
        farm[0],
        'manual',
        (now - timedelta(hours=2)).isoformat(),
        (now - timedelta(hours=1, minutes=50)).isoformat(),
        350,
        'completed'
    ))

cursor.executemany('''
    INSERT INTO irrigation_events (event_id, farm_id, trigger_type, start_time, end_time, water_used, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
''', events)

print("✓ Irrigation events inserted")

# Insert system alerts
alerts = []
for idx, farm in enumerate(farms):
    alerts.append((
        generate_uuid(),
        farm[0],
        'low_moisture',
        f'Soil moisture dropped below the threshold at {farm[2]}.',
        (now - timedelta(hours=1, minutes=idx*5)).isoformat(),
        0,
        None
    ))
    alerts.append((
        generate_uuid(),
        farm[0],
        'sensor_check',
        f'Sensor signal restored successfully at {farm[2]}.',
        (now - timedelta(hours=3, minutes=idx*5)).isoformat(),
        1,
        (now - timedelta(hours=2, minutes=30)).isoformat()
    ))

cursor.executemany('''
    INSERT INTO system_alerts (alert_id, farm_id, alert_type, message, timestamp, resolved, resolved_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
''', alerts)

print("✓ System alerts inserted")

# Insert app state
cursor.execute('''
    INSERT OR REPLACE INTO app_state (key, value)
    VALUES ('last_sms_sent_at', ?)
''', ((now - timedelta(minutes=30)).isoformat(),))

print("✓ App state inserted")

# Commit and close
conn.commit()
conn.close()

print(f"\n✅ Database created successfully!")
print(f"   Path: {db_path}")
print(f"   Tables: users, farms, sensor_readings, irrigation_events, system_alerts, app_state")
print(f"   Users: 2")
print(f"   Farms: 2")
print(f"   Sensor readings: {len(readings)}")
print(f"   Irrigation events: {len(events)}")
print(f"   Alerts: {len(alerts)}")
