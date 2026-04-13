-- AquaSense PostgreSQL Schema
-- Phase 5 Database Setup
-- Run: psql -U postgres -d aquasense -f schema.sql

-- Enable extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "timescaledb";

-- 1. Users Table
CREATE TABLE users (
    user_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,  -- bcrypt hash
    role ENUM('farmer', 'admin') DEFAULT 'farmer',
    created_at TIMESTAMP DEFAULT NOW()
);

-- 2. Farms Table
CREATE TABLE farms (
    farm_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID REFERENCES users(user_id) ON DELETE CASCADE,
    location VARCHAR(200) NOT NULL,
    size DECIMAL(5,2),  -- hectares
    crop_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT NOW()
);

-- 3. Sensor Readings (TimescaleDB hypertable for performance)
CREATE TABLE sensor_readings (
    reading_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    farm_id UUID REFERENCES farms(farm_id) ON DELETE CASCADE,
    soil_moisture DECIMAL(5,2) CHECK (soil_moisture BETWEEN 0 AND 100),
    temperature DECIMAL(4,2),
    humidity DECIMAL(5,2) CHECK (humidity BETWEEN 0 AND 100),
    timestamp TIMESTAMPTZ DEFAULT NOW()
);

SELECT create_hypertable('sensor_readings', 'timestamp');

-- 4. Irrigation Events
CREATE TABLE irrigation_events (
    event_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    farm_id UUID REFERENCES farms(farm_id) ON DELETE CASCADE,
    trigger_type ENUM('automatic', 'manual') NOT NULL,
    start_time TIMESTAMPTZ NOT NULL,
    end_time TIMESTAMPTZ,
    water_used DECIMAL(8,2),  -- litres
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'completed'
);

-- 5. System Alerts
CREATE TABLE system_alerts (
    alert_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    farm_id UUID REFERENCES farms(farm_id) ON DELETE CASCADE,
    alert_type ENUM('low_moisture', 'sensor_fault', 'battery_low', 'pump_fault') NOT NULL,
    message TEXT,
    timestamp TIMESTAMPTZ DEFAULT NOW(),
    resolved BOOLEAN DEFAULT false,
    resolved_at TIMESTAMPTZ
);

-- Indexes for performance
CREATE INDEX idx_sensor_farm_time ON sensor_readings(farm_id, timestamp DESC);
CREATE INDEX idx_event_farm_time ON irrigation_events(farm_id, start_time DESC);
CREATE INDEX idx_alert_farm_time ON system_alerts(farm_id, timestamp DESC);
CREATE INDEX idx_user_email ON users(email);

-- Sample Demo Data
INSERT INTO users (user_id, name, email, phone, password_hash, role) VALUES
('550e8400-e29b-41d4-a716-446655440000', 'Admin User', 'admin@example.com', '+254700123456', '$2a$11$ISClHPIcWVoh6K5JsEr0Lu6bs/Ced.A2sZoRNpd1KyohSk8d03zNC', 'admin'),
('550e8400-e29b-41d4-a716-446655440001', 'John Farmer', 'farmer@kenya.com', '+254722987654', '$2a$11$0sU1l0kIuqgPFswLId69Gu3x1j10nVipo4fF4aypmtDZXosp2rptO', 'farmer');

INSERT INTO farms (farm_id, user_id, location, size, crop_type) VALUES
('550e8400-e29b-41d4-a716-446655440002', '550e8400-e29b-41d4-a716-446655440000', 'Machakos Farm A', 2.5, 'Maize'),
('550e8400-e29b-41d4-a716-446655440003', '550e8400-e29b-41d4-a716-446655440001', 'Kitui Farm B', 1.8, 'Tomatoes');

-- Recent sample readings (run multiple times for data)
INSERT INTO sensor_readings (farm_id, soil_moisture, temperature, humidity) VALUES
('550e8400-e29b-41d4-a716-446655440002', 28.5, 26.4, 68.2),
('550e8400-e29b-41d4-a716-446655440002', 29.1, 25.8, 67.9);

INSERT INTO irrigation_events (farm_id, trigger_type, start_time, end_time, water_used) VALUES
('550e8400-e29b-41d4-a716-446655440002', 'automatic', NOW() - INTERVAL '15 minutes', NOW() - INTERVAL '3 minutes', 450.0);

INSERT INTO system_alerts (farm_id, alert_type, message) VALUES
('550e8400-e29b-41d4-a716-446655440002', 'low_moisture', 'Soil moisture below 25% threshold');
