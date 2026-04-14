// AquaSense ESP32 Firmware - Intelligent Irrigation IoT Node
// Objectives 1-2: Sensor network + microcontroller control
// Hardware: ESP32, Capacitive Soil Moisture v1.2, DHT22 (temp/humidity), Relay for valve/pump
// Install: Arduino IDE + ESP32 board (https://espressif.com), Libraries: WiFi, HTTPClient, ArduinoJson, DHT sensor library

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <DHT.h>

// === CONFIG ===
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";
const char* apiUrl = "http://localhost:5000/api/sensor/hardware/YOUR_FARM_ID/reading";  // ESP32 Hardware Webhook
const char* apiKey = "Bearer YOUR_JWT_TOKEN";  // From login or long-lived

// Pins
#define SOIL_MOISTURE_PIN 34  // Analog A0 equivalent
#define DHT_PIN 22
#define DHT_TYPE DHT22
#define RELAY_PIN 23

DHT dht(DHT_PIN, DHT_TYPE);

// Thresholds (match backend)
const float MOISTURE_LOW = 35.0;  // %
const int IRRIGATION_DURATION = 720000;  // 12 min in ms

// Timing
unsigned long lastReading = 0;
const unsigned long READING_INTERVAL = 300000;  // 5 min
bool irrigationActive = false;
unsigned long irrigationStart = 0;

// Calibrate soil sensor (dry=0%, wet=100%)
const int DRY_CAL = 3100;  // ADC dry air
const int WET_CAL = 1300;  // ADC in water

void setup() {
  Serial.begin(115200);
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);  // Valve off
  
  dht.begin();
  
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting WiFi...");
  }
  Serial.println("WiFi Connected: " + WiFi.localIP().toString());
}

void loop() {
  unsigned long now = millis();
  
  // Read & send every INTERVAL
  if (now - lastReading > READING_INTERVAL) {
    sendSensorData();
    lastReading = now;
  }
  
  // Check irrigation state
  if (irrigationActive) {
    if (now - irrigationStart > IRRIGATION_DURATION) {
      stopIrrigation();
    }
  }
  
  delay(10000);  // Check every 10s
}

float readSoilMoisture() {
  int raw = analogRead(SOIL_MOISTURE_PIN);
  // Invert & map 0-100%
  float moisture = 100.0 * (DRY_CAL - raw) / (DRY_CAL - WET_CAL);
  return constrain(moisture, 0, 100);
}

float readTemperature() {
  return dht.readTemperature();
}

float readHumidity() {
  return dht.readHumidity();
}

void sendSensorData() {
  float moisture = readSoilMoisture();
  float temp = readTemperature();
  float hum = readHumidity();
  
  Serial.printf("Sensors: Moisture=%.1f%%, Temp=%.1fC, Hum=%.1f%%\n", moisture, temp, hum);
  
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(apiUrl);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", apiKey);
    
    DynamicJsonDocument doc(1024);
    doc["soilMoisture"] = moisture;
    doc["temperature"] = temp;
    doc["humidity"] = hum;
    doc["timestamp"] = millis() / 1000;
    
    String payload;
    serializeJson(doc, payload);
    
    int httpCode = http.POST(payload);
    
    if (httpCode == 200) {
      Serial.println("Data sent OK");
      // Check response for irrigation command
      if (http.getString().indexOf("alert\":true") > 0 && !irrigationActive) {
        startIrrigation();
      }
    } else {
      Serial.printf("HTTP Error: %d\n", httpCode);
    }
    
    http.end();
  }
}

void startIrrigation() {
  irrigationActive = true;
  irrigationStart = millis();
  digitalWrite(RELAY_PIN, HIGH);  // Valve ON
  Serial.println("🚿 Irrigation STARTED (auto)");
}

void stopIrrigation() {
  irrigationActive = false;
  digitalWrite(RELAY_PIN, LOW);  // Valve OFF
  Serial.println("⏹️ Irrigation STOPPED");
}

