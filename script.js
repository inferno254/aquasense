// Auto-detect API base URL based on current location
var apiBase = window.apiBase || (() => {
    const currentHost = window.location.host;
    const currentPath = window.location.pathname;
    
    // Check if running on aquasense.local virtual host
    if (currentHost === 'aquasense.local' || currentHost === 'www.aquasense.local') {
        return 'http://aquasense.local/php/api';
    }
    
    // Check if running on XAMPP (localhost with path)
    if (currentHost === 'localhost' && currentPath.includes('/aquasense/')) {
        return 'http://localhost/aquasense/php/api';
    }
    
    // Check if running on PHP built-in server
    if (currentHost === 'localhost:8000') {
        return 'http://localhost:8000/php/api';
    }
    
    // Default fallback
    return 'http://localhost/aquasense/php/api';
})();
const authStorageKey = "aquaSenseAuth";
const localUsersKey = "aquaSenseLocalUsers";



function normalizeUser(user) {
  if (!user) return null;
  return {
    ...user,
    userId: user.userId || user.UserId,
    UserId: user.UserId || user.userId,
    name: user.name || user.Name,
    Name: user.Name || user.name,
    email: user.email || user.Email,
    Email: user.Email || user.email,
    phone: user.phone || user.Phone,
    Phone: user.Phone || user.phone,
    role: user.role || user.Role,
    Role: user.Role || user.role
  };
}

function normalizeFarm(farm) {
  if (!farm) return null;
  return {
    ...farm,
    farmId: farm.farmId || farm.FarmId,
    FarmId: farm.FarmId || farm.farmId,
    location: farm.location || farm.Location,
    Location: farm.Location || farm.location,
    cropType: farm.cropType || farm.CropType,
    CropType: farm.CropType || farm.cropType,
    size: farm.size || farm.Size,
    Size: farm.Size || farm.size
  };
}

function normalizeAuthDataShape(data) {
  if (!data) return null;
  return {
    ...data,
    user: normalizeUser(data.user),
    farms: Array.isArray(data.farms) ? data.farms.map(normalizeFarm) : [],
    farmId: data.farmId || data.FarmId || data.farms?.[0]?.farmId || data.farms?.[0]?.FarmId || "550e8400-e29b-41d4-a716-446655440002"
  };
}

const loginForm = document.getElementById("loginForm");
const registerForm = document.getElementById("registerForm");
const contactForm = document.getElementById("contactForm");

if (loginForm) {
  loginForm.addEventListener("submit", handleLogin);
}

if (registerForm) {
  registerForm.addEventListener("submit", handleRegister);
}

if (contactForm) {
  contactForm.addEventListener("submit", handleContactSubmit);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initPage);
} else {
  initPage();
}

function initPage() {
  initAuthCheck();
  initPageAnimations();
}

function getLocalUsers() {
  return JSON.parse(localStorage.getItem(localUsersKey) || "[]");
}

function findLocalUser(email) {
  return getLocalUsers().find((user) => user.email.toLowerCase() === email.toLowerCase());
}

function saveLocalUser(user) {
  const users = getLocalUsers();
  users.push(user);
  localStorage.setItem(localUsersKey, JSON.stringify(users));
}

// Password validation function
function validatePassword(password) {
  // Only allow letters and numbers
  const regex = /^[a-zA-Z0-9]+$/;
  return regex.test(password);
}

async function handleLogin(event) {
  event.preventDefault();

  const email = document.getElementById("email")?.value?.trim();
  const password = document.getElementById("password")?.value?.trim();
  const loginText = document.getElementById("loginText");
  const loginSpinner = document.getElementById("loginSpinner");
  const loginBtn = event.target?.querySelector(".login-btn");

  if (!email || !password || !loginText || !loginSpinner || !loginBtn) {
    alert("Please enter both email and password.");
    return;
  }

  // Validate password contains only letters and numbers
  if (!validatePassword(password)) {
    alert("Password must contain only letters and numbers. No special characters allowed.");
    return;
  }

  setButtonBusy(loginBtn, loginText, loginSpinner, true, "Signing In...");

  try {
    const result = await backendLogin(email, password);
    setAuthData(normalizeAuthDataShape({
      loggedIn: true,
      user: result.user,
      farms: result.farms,
      token: result.token,
      farmId: result.farms?.[0]?.farmId || result.farms?.[0]?.FarmId
    }));

    alert("Welcome back. Redirecting to your dashboard...");
    window.location.href = "dashboard.html";
  } catch (error) {
    console.error("Login error in handleLogin:", error);
    alert(`Login failed: ${error.message || "Invalid credentials. Please check your email and password."}`);
  } finally {
    setButtonBusy(loginBtn, loginText, loginSpinner, false, "Sign In");
    event.target.reset();
  }
}

async function handleRegister(event) {
  event.preventDefault();

  const name = document.getElementById("registerName")?.value?.trim();
  const email = document.getElementById("registerEmail")?.value?.trim();
  const phone = document.getElementById("registerPhone")?.value?.trim();
  const password = document.getElementById("registerPassword")?.value;
  const confirm = document.getElementById("registerConfirm")?.value;
  const notice = document.getElementById("registerNotice");
  const registerText = document.getElementById("registerText");
  const registerSpinner = document.getElementById("registerSpinner");
  const registerBtn = event.target?.querySelector(".login-btn");

  if (!name || !email || !password || !confirm) {
    showFormNotice(notice, "Please complete all fields.", "error");
    return;
  }

  if (password !== confirm) {
    showFormNotice(notice, "Passwords do not match.", "error");
    return;
  }

  // Validate password contains only letters and numbers
  if (!validatePassword(password)) {
    showFormNotice(notice, "Password must contain only letters and numbers (no special characters).", "error");
    return;
  }

  if (registerBtn && registerText && registerSpinner) {
    setButtonBusy(registerBtn, registerText, registerSpinner, true, "Creating Account...");
  }

  try {
    if (findLocalUser(email)) {
      showFormNotice(notice, "Email already exists. Please login instead.", "error");
      return;
    }

    const response = await fetch(`${apiBase}/auth/register`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, email, phone, password })
    });

    if (response.ok) {
      const data = await response.json();
      setAuthData(normalizeAuthDataShape({
        loggedIn: true,
        user: data.user,
        farms: data.farms,
        token: data.token,
        farmId: data.farms?.[0]?.farmId || data.farms?.[0]?.FarmId
      }));

      showFormNotice(notice, "Registration successful. Redirecting to dashboard...", "success");
      setTimeout(() => {
        window.location.href = "dashboard.html";
      }, 1200);
      return;
    }

    const errorData = await response.json().catch(() => null);
    showFormNotice(notice, errorData?.message || "Registration failed. Please try again.", "error");
  } catch (error) {
    saveLocalUser({ name, email, phone, password });
    showFormNotice(notice, "Offline registration complete. Please login locally.", "success");
    setTimeout(() => {
      window.location.href = "login.html";
    }, 1200);
  } finally {
    if (registerBtn && registerText && registerSpinner) {
      setButtonBusy(registerBtn, registerText, registerSpinner, false, "Register");
    }
  }
}

function handleContactSubmit(event) {
  event.preventDefault();

  const name = document.getElementById("contactName")?.value.trim();
  const email = document.getElementById("contactEmail")?.value.trim();
  const message = document.getElementById("contactMessage")?.value.trim();
  const notice = document.getElementById("contactNotice");

  if (!name || !email || !message) {
    showFormNotice(notice, "Please fill in your name, email, and message.", "error");
    return;
  }

  showFormNotice(notice, "Thank you. Your message has been received.", "success");
  event.target.reset();
}

function showFormNotice(element, message, type) {
  if (!element) return;

  if (element.dataset.timeoutId) {
    clearTimeout(parseInt(element.dataset.timeoutId, 10));
  }

  element.textContent = message;
  element.style.display = "block";
  element.style.background = type === "error" ? "#ffe5e5" : "#e8f5e9";
  element.style.color = type === "error" ? "#b71c1c" : "#1b5e20";
  element.style.border = type === "error" ? "1px solid #f44336" : "1px solid #4CAF50";

  element.dataset.timeoutId = setTimeout(() => {
    element.style.display = "none";
    delete element.dataset.timeoutId;
  }, 5000);
}

async function backendLogin(email, password) {
  console.log(`Attempting login with API: ${apiBase}/auth/login`);
  
  try {
    const response = await fetch(`${apiBase}/auth/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password })
    });

    console.log(`Login response status: ${response.status}`);

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: "Unknown error" }));
      console.error("Login failed:", errorData);
      throw new Error(errorData.message || "Backend authentication failed");
    }

    const data = await response.json();
    console.log("Login successful:", data);
    return data;
  } catch (error) {
    console.error("Login error:", error);
    throw error;
  }
}

function setAuthData(data) {
  localStorage.setItem(authStorageKey, JSON.stringify(normalizeAuthDataShape(data)));
  updateLoginIcons();
}

function getAuthData() {
  return normalizeAuthDataShape(JSON.parse(localStorage.getItem(authStorageKey) || "null"));
}

function clearAuthData() {
  localStorage.removeItem(authStorageKey);
  updateLoginIcons();
}

function initAuthCheck() {
  updateLoginIcons();
}

function updateLoginIcons() {
  const auth = getAuthData();
  const loginIcons = document.querySelectorAll(".login-icon");

  // Check if user is logged in: auth exists and has token or farms
  const isLoggedIn = auth && (auth.token || auth.farms?.length > 0);

  loginIcons.forEach((icon) => {
    if (isLoggedIn) {
      icon.innerHTML = '<i class="fas fa-user-check"></i>';
      icon.title = `${auth.user?.email || auth.user?.name || auth.email || auth.name || "Dashboard"}`;
      icon.href = "dashboard.html";
    } else {
      icon.innerHTML = '<i class="fas fa-user-circle"></i>';
      icon.title = "Login";
      icon.href = "login.html";
    }
  });

  // Update navigation links
  const navLinks = document.querySelector(".nav-links");
  if (navLinks) {
    const loginLink = navLinks.querySelector('a[href="login.html"]');
    const registerLink = navLinks.querySelector('a[href="register.html"]');
    
    if (isLoggedIn) {
      // Hide login and register
      if (loginLink) loginLink.style.display = 'none';
      if (registerLink) registerLink.style.display = 'none';
    } else {
      // Show login and register
      if (loginLink) loginLink.style.display = '';
      if (registerLink) registerLink.style.display = '';
    }
    
    // Remove loading state to prevent flickering
    navLinks.classList.remove('auth-loading');
  }
}

// Initialize auth state immediately to prevent flickering
(function initAuthState() {
  const auth = getAuthData();
  const isLoggedIn = auth && (auth.token || auth.farms?.length > 0);
  const navLinks = document.querySelector(".nav-links");
  
  if (navLinks) {
    // Add loading class to hide navigation during auth check
    navLinks.classList.add('auth-loading');
    
    // Set initial state based on auth to prevent flicker
    const loginLink = navLinks.querySelector('a[href="login.html"]');
    const registerLink = navLinks.querySelector('a[href="register.html"]');
    
    if (isLoggedIn) {
      if (loginLink) loginLink.style.display = 'none';
      if (registerLink) registerLink.style.display = 'none';
    }
  }
})();

// Dark Mode Toggle
function toggleDarkMode() {
  document.body.classList.toggle('dark-mode');
  const isDarkMode = document.body.classList.contains('dark-mode');
  localStorage.setItem('darkMode', isDarkMode);
  
  // Update icon
  const icon = document.getElementById('darkModeIcon');
  if (icon) {
    icon.className = isDarkMode ? 'fas fa-sun' : 'fas fa-moon';
  }
  
  // Dispatch custom event so charts and other components can react
  window.dispatchEvent(new CustomEvent('darkmodechange', { detail: { isDarkMode } }));
}

// Initialize dark mode from localStorage
(function initDarkMode() {
  const darkMode = localStorage.getItem('darkMode') === 'true';
  if (darkMode) {
    document.body.classList.add('dark-mode');
    const icon = document.getElementById('darkModeIcon');
    if (icon) {
      icon.className = 'fas fa-sun';
    }
  }
})();

function logout() {
  clearAuthData();
  // Clear all dashboard-related localStorage items
  localStorage.removeItem('irrigationEvents');
  localStorage.removeItem('lastIrrigationTime');
  localStorage.removeItem('moistureThreshold');
  localStorage.removeItem('autoModeEnabled');
  localStorage.removeItem('dashboardState');
  window.location.href = "login.html";
}

function fetchWithAuth(url, options) {
  const auth = getAuthData();
  const headers = {
    "Content-Type": "application/json",
    ...(options?.headers || {})
  };

  if (auth?.token) {
    headers.Authorization = `Bearer ${auth.token}`;
  }

  return fetch(url, { ...options, headers });
}

async function readJsonSafe(response) {
  return response.json().catch(() => null);
}

async function requestJson(url, options) {
  const response = await fetchWithAuth(url, options);
  const payload = await readJsonSafe(response);

  if (!response.ok) {
    const message = payload?.message || payload?.error || "Request failed";
    throw new Error(message);
  }

  return payload;
}

function getSelectedFarmId(fallbackFarmId) {
  const auth = getAuthData();
  const queryFarmId = new URLSearchParams(window.location.search).get("farmId");
  return queryFarmId || fallbackFarmId || auth?.farmId || auth?.farms?.[0]?.farmId || auth?.farms?.[0]?.FarmId || "550e8400-e29b-41d4-a716-446655440002";
}

function setButtonBusy(button, textElement, spinnerElement, isBusy, busyText) {
  if (button) {
    button.disabled = isBusy;
  }

  if (textElement) {
    if (!textElement.dataset.defaultText) {
      textElement.dataset.defaultText = textElement.textContent;
    }
    textElement.textContent = isBusy ? busyText : textElement.dataset.defaultText;
  }

  if (spinnerElement) {
    spinnerElement.style.display = isBusy ? "inline-block" : "none";
  }
}

async function triggerIrrigationForFarm(farmId, button) {
  const targetFarmId = farmId || getSelectedFarmId();
  if (!targetFarmId) {
    throw new Error("No farm selected.");
  }

  toggleActionButton(button, true, "Sending...");

  try {
    return await requestJson(`${apiBase}/irrigation/farms/${targetFarmId}/irrigate`, {
      method: "POST",
      body: JSON.stringify({ manual: true, duration: 12, estimatedLitres: 420 })
    });
  } finally {
    toggleActionButton(button, false);
  }
}

async function simulateSensorReadingForFarm(farmId, button, values) {
  const targetFarmId = farmId || getSelectedFarmId();
  const reading = values || buildSimulatedReading();

  toggleActionButton(button, true, "Simulating...");

  try {
    const response = await fetchWithAuth(`${apiBase}/data/farms/${targetFarmId}/sensors?hours=24`);
    if (!response.ok) {
      console.warn("Sensor API unavailable, using local fallback", response.status);
      throw new Error("Sensor API unavailable");
    }

    const data = await response.json();
    if (data.latest) {
      document.querySelector(".moisture").textContent = `${(data.latest.soilMoisture ?? data.latest.SoilMoisture ?? 0).toFixed(1)}%`;
      document.querySelector(".temp").textContent = `${(data.latest.temperature ?? data.latest.Temperature ?? 0).toFixed(1)}°C`;
      document.querySelector(".humidity").textContent = `${(data.latest.humidity ?? data.latest.Humidity ?? 0).toFixed(0)}%`;
      updateLastSync(data.latest.timestamp || data.latest.Timestamp);
      updateRecentActivity(data.latest);
    }

    if (data.all?.length) {
      updateChart(data.all);
    }
  } catch (error) {
    console.log("Sensor API unavailable, using local fallback", error);
    // Enhanced fallback with realistic data
    const fallbackMoisture = 28 + Math.random() * 15;
    const fallbackTemp = 24 + Math.random() * 4;
    const fallbackHumidity = 60 + Math.random() * 20;
    
    document.querySelector(".moisture").textContent = `${fallbackMoisture.toFixed(1)}%`;
    document.querySelector(".temp").textContent = `${fallbackTemp.toFixed(1)}°C`;
    document.querySelector(".humidity").textContent = `${fallbackHumidity.toFixed(0)}%`;
    updateLastSync();
    updateRecentActivity({
      soilMoisture: fallbackMoisture,
      temperature: fallbackTemp,
      humidity: fallbackHumidity,
      timestamp: new Date().toISOString()
    });
  } finally {
    toggleActionButton(button, false);
  }
}

function toggleActionButton(button, busy, busyLabel) {
  if (!button) return;
  if (!button.dataset.defaultLabel) {
    button.dataset.defaultLabel = button.textContent.trim();
  }
  button.disabled = busy;
  button.textContent = busy ? busyLabel : button.dataset.defaultLabel;
}

async function getAutoModeForFarm(farmId) {
  const targetFarmId = farmId || getSelectedFarmId();
  return requestJson(`${apiBase}/irrigation/farms/${targetFarmId}/auto-mode`);
}

async function setAutoModeForFarm(farmId, enabled) {
  const targetFarmId = farmId || getSelectedFarmId();
  return requestJson(`${apiBase}/irrigation/farms/${targetFarmId}/auto-mode`, {
    method: "PUT",
    body: JSON.stringify({ enabled })
  });
}

async function resolveAlert(alertId) {
  return requestJson(`${apiBase}/data/alerts/${alertId}/resolve`, {
    method: "POST"
  });
}

function buildSimulatedReading() {
  return {
    soilMoisture: Number((24 + Math.random() * 18).toFixed(1)),
    temperature: Number((24 + Math.random() * 5).toFixed(1)),
    humidity: Number((60 + Math.random() * 16).toFixed(1)),
    timestamp: new Date().toISOString()
  };
}

function getSelectedFarmId(fallbackFarmId) {
  const auth = getAuthData();
  const queryFarmId = new URLSearchParams(window.location.search).get("farmId");
  return queryFarmId || fallbackFarmId || auth?.farmId || auth?.farms?.[0]?.farmId || auth?.farms?.[0]?.FarmId || "550e8400-e29b-41d4-a716-446655440002";
}

let demoFarmId = '550e8400-e29b-41d4-a716-446655440002'; // Default farm
let currentThreshold = 35;

const dashboardStateKey = 'aquaDashboardState';

function loadDashboardState() {
  const state = JSON.parse(localStorage.getItem(dashboardStateKey) || '{}');
  currentThreshold = state.threshold || 35;
  if (document.getElementById('thresholdSlider')) document.getElementById('thresholdSlider').value = currentThreshold;
  if (document.getElementById('thresholdValue')) document.getElementById('thresholdValue').textContent = currentThreshold + '%';
  if (state.autoMode !== undefined && document.getElementById('autoModeSwitch')) document.getElementById('autoModeSwitch').checked = state.autoMode;
}

function saveDashboardState() {
  localStorage.setItem(dashboardStateKey, JSON.stringify({threshold: currentThreshold, autoMode: document.getElementById('autoModeSwitch')?.checked, lastAction: Date.now()}));
}

document.getElementById('thresholdSlider')?.addEventListener('input', (e) => {
  currentThreshold = e.target.value;
  document.getElementById('thresholdValue').textContent = currentThreshold + '%';
  saveDashboardState();
});

if (document.getElementById('autoModeSwitch')) {
  document.getElementById('autoModeSwitch').addEventListener('change', saveDashboardState);
}

function showDemo(type) {
  const overlay = document.getElementById(`demo-${type}`);
  if (overlay) overlay.classList.add('active');
}

function closeDemo(type) {
  const overlay = document.getElementById(`demo-${type}`);
  if (overlay) overlay.classList.remove('active');
}

// Enhanced Features Page Demo Functions
let sensorData = { moisture: 45, temperature: 26, humidity: 68 };
let autoMode = { enabled: true, threshold: 35 };
let solarData = { battery: 85, panel: 8.2, uptime: 24 };
let smsData = { enabled: true, sent: 0, history: [] };
let analyticsData = { farms: 2, waterSaved: 420, yieldIncrease: 15 };
let demoChart = null;

function simulateDrySoil() {
  sensorData.moisture = Math.max(15, sensorData.moisture - 15 + Math.random() * 10);
  updateSensorDisplay();
  showAlert('demo-sensors-alerts', 'Dry soil detected! Irrigation triggered automatically.', 'warning');
  
  if (autoMode.enabled && sensorData.moisture < autoMode.threshold) {
    setTimeout(() => {
      sensorData.moisture = Math.min(60, sensorData.moisture + 20 + Math.random() * 15);
      updateSensorDisplay();
      showAlert('demo-sensors-alerts', 'Irrigation completed successfully!', 'success');
    }, 3000);
  }
}

function simulateRain() {
  sensorData.moisture = Math.min(80, sensorData.moisture + 20 + Math.random() * 15);
  sensorData.humidity = Math.min(90, sensorData.humidity + 10 + Math.random() * 5);
  updateSensorDisplay();
  showAlert('demo-sensors-alerts', 'Rain detected! Soil moisture increased.', 'success');
}

function refreshSensors() {
  // Simulate sensor refresh with slight variations
  sensorData.moisture += (Math.random() - 0.5) * 2;
  sensorData.temperature += (Math.random() - 0.5) * 1;
  sensorData.humidity += (Math.random() - 0.5) * 3;
  updateSensorDisplay();
  showAlert('demo-sensors-alerts', 'Sensors refreshed successfully.', 'info');
}

function updateSensorDisplay() {
  document.getElementById('demo-sensors-moisture').textContent = sensorData.moisture.toFixed(1) + '%';
  document.getElementById('demo-sensors-temp').textContent = sensorData.temperature.toFixed(1) + '°C';
  document.getElementById('demo-sensors-humidity').textContent = sensorData.humidity.toFixed(0) + '%';
}

function toggleAutoMode() {
  autoMode.enabled = !autoMode.enabled;
  updateAutoDisplay();
  showAlert('demo-auto-alerts', `Auto mode ${autoMode.enabled ? 'enabled' : 'disabled'}.`, 'info');
}

function manualIrrigate() {
  showAlert('demo-auto-alerts', 'Manual irrigation started (12 minutes)...', 'info');
  setTimeout(() => {
    sensorData.moisture = Math.min(60, sensorData.moisture + 25 + Math.random() * 10);
    updateSensorDisplay();
    showAlert('demo-auto-alerts', 'Manual irrigation completed!', 'success');
  }, 2000);
}

function setThreshold() {
  const newThreshold = prompt('Set moisture threshold (20-60%):', autoMode.threshold);
  if (newThreshold && !isNaN(newThreshold)) {
    const value = Math.max(20, Math.min(60, parseInt(newThreshold)));
    autoMode.threshold = value;
    document.getElementById('threshold-slider').value = value;
    document.getElementById('threshold-display').textContent = value + '%';
    showAlert('demo-auto-alerts', `Threshold set to ${value}%`, 'success');
  }
}

function updateThreshold(value) {
  autoMode.threshold = parseInt(value);
  document.getElementById('threshold-display').textContent = value + '%';
}

function updateAutoDisplay() {
  document.getElementById('demo-auto-status').textContent = autoMode.enabled ? 'ON' : 'OFF';
  document.getElementById('threshold-slider').value = autoMode.threshold;
  document.getElementById('threshold-display').textContent = autoMode.threshold + '%';
}

function sendTestSMS() {
  const messages = [
    'Low moisture alert: 28% detected',
    'Irrigation started automatically',
    'System status: All operational',
    'Power saving mode activated'
  ];
  const randomMessage = messages[Math.floor(Math.random() * messages.length)];
  smsData.sent++;
  smsData.history.unshift({ time: new Date().toLocaleTimeString(), message: randomMessage });
  if (smsData.history.length > 5) smsData.history.pop();
  
  updateSMSDisplay();
  showAlert('demo-sms-alerts', `SMS sent: "${randomMessage}"`, 'success');
}

function toggleSMS() {
  smsData.enabled = !smsData.enabled;
  updateSMSDisplay();
  showAlert('demo-sms-alerts', `SMS notifications ${smsData.enabled ? 'enabled' : 'disabled'}.`, 'info');
}

function viewSMSHistory() {
  const historyDiv = document.getElementById('sms-history');
  if (smsData.history.length === 0) {
    historyDiv.innerHTML = '<p>No SMS history available.</p>';
  } else {
    historyDiv.innerHTML = smsData.history.map(msg => 
      `<div class="sms-item"><span class="sms-time">${msg.time}</span> ${msg.message}</div>`
    ).join('');
  }
}

function updateSMSDisplay() {
  document.getElementById('demo-sms-status').textContent = smsData.enabled ? 'Active' : 'Disabled';
}

function simulateCloudyDay() {
  solarData.battery = Math.max(20, solarData.battery - 15 - Math.random() * 10);
  solarData.panel = Math.max(2, solarData.panel - 3 - Math.random() * 2);
  updateSolarDisplay();
  showAlert('demo-solar-alerts', 'Cloudy day detected - power saving activated', 'warning');
}

function simulateSunnyDay() {
  solarData.battery = Math.min(100, solarData.battery + 10 + Math.random() * 5);
  solarData.panel = Math.min(12, solarData.panel + 2 + Math.random() * 1);
  updateSolarDisplay();
  showAlert('demo-solar-alerts', 'Sunny day detected - optimal power generation', 'success');
}

function checkPowerSaving() {
  const saving = solarData.battery < 30;
  showAlert('demo-solar-alerts', 
    `Power saving mode: ${saving ? 'ACTIVE' : 'INACTIVE'} (Battery: ${solarData.battery.toFixed(0)}%)`, 
    saving ? 'warning' : 'info'
  );
}

function updateSolarDisplay() {
  document.getElementById('demo-solar-battery').textContent = solarData.battery.toFixed(0) + '%';
  document.getElementById('demo-solar-panel').textContent = solarData.panel.toFixed(1) + 'W';
  document.getElementById('demo-solar-uptime').textContent = solarData.uptime + 'hrs';
}

function generateReport() {
  const report = {
    period: 'Last 30 days',
    totalIrrigation: '8 events',
    waterUsage: '2,450L',
    waterSaved: analyticsData.waterSaved + 'L',
    efficiency: '+23%'
  };
  
  alert(`📊 Farm Report\n\nPeriod: ${report.period}\nIrrigation Events: ${report.totalIrrigation}\nWater Usage: ${report.waterUsage}\nWater Saved: ${report.waterSaved}\nEfficiency: ${report.efficiency}`);
}

function exportData() {
  const csvContent = `Date,Moisture,Temperature,Humidity,Irrigation\n2026-01-15,${sensorData.moisture}%,${sensorData.temperature}°C,${sensorData.humidity}%,Auto\n2026-01-14,42%,25°C,65%,Manual`;
  
  const blob = new Blob([csvContent], { type: 'text/csv' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'farm_data.csv';
  a.click();
  
  showAlert('demo-analytics-alerts', 'Data exported successfully!', 'success');
}

function viewTrends() {
  const ctx = document.getElementById('analyticsChart');
  if (demoChart) demoChart.destroy();
  
  demoChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
      datasets: [{
        label: 'Water Usage (L)',
        data: [320, 280, 290, 250],
        borderColor: '#4CAF50',
        backgroundColor: 'rgba(76, 175, 80, 0.1)',
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
  
  showAlert('demo-analytics-alerts', 'Trends chart loaded successfully.', 'info');
}

function updateAnalyticsDisplay() {
  document.getElementById('demo-analytics-farms').textContent = analyticsData.farms;
  document.getElementById('demo-analytics-water-saved').textContent = analyticsData.waterSaved + 'L';
  document.getElementById('demo-analytics-yield').textContent = '+' + analyticsData.yieldIncrease + '%';
}

function showAlert(elementId, message, type = 'info') {
  const alertDiv = document.getElementById(elementId);
  if (!alertDiv) return;
  
  const colors = {
    info: '#2196F3',
    success: '#4CAF50',
    warning: '#FF9800',
    error: '#F44336'
  };
  
  alertDiv.innerHTML = `
    <div class="alert alert-${type}" style="background: ${colors[type]}; color: white; padding: 8px; border-radius: 4px; margin: 5px 0;">
      <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
      ${message}
    </div>
  `;
  
  setTimeout(() => {
    alertDiv.innerHTML = '';
  }, 5000);
}

// Enhanced demo functions with real-time updates
function demoSensors() {
  updateSensorDisplay();
  showAlert('demo-sensors-alerts', 'Live sensor data loaded.', 'info');
}

function demoAuto() {
  updateAutoDisplay();
  showAlert('demo-auto-alerts', 'Auto irrigation status loaded.', 'info');
}

function demoSMS() {
  updateSMSDisplay();
  showAlert('demo-sms-alerts', 'SMS system status loaded.', 'info');
}

function demoSolar() {
  updateSolarDisplay();
  showAlert('demo-solar-alerts', 'Solar system status loaded.', 'info');
}

function demoAnalytics() {
  updateAnalyticsDisplay();
  showAlert('demo-analytics-alerts', 'Farm analytics loaded.', 'info');
}

// Auto-update simulation
setInterval(() => {
  // Simulate real-time sensor variations
  sensorData.moisture += (Math.random() - 0.5) * 0.5;
  sensorData.temperature += (Math.random() - 0.5) * 0.2;
  sensorData.humidity += (Math.random() - 0.5) * 0.8;
  
  // Battery drain simulation
  solarData.battery = Math.max(15, solarData.battery - 0.01);
  
  // Update displays if overlays are open (only on features page)
  const demoSensors = document.getElementById('demo-sensors');
  const demoAuto = document.getElementById('demo-auto');
  const demoSms = document.getElementById('demo-sms');
  const demoSolar = document.getElementById('demo-solar');
  const demoAnalytics = document.getElementById('demo-analytics');
  
  if (demoSensors && demoSensors.classList.contains('active')) {
    updateSensorDisplay();
  }
  if (demoAuto && demoAuto.classList.contains('active')) {
    updateAutoDisplay();
  }
  if (demoSms && demoSms.classList.contains('active')) {
    updateSMSDisplay();
  }
  if (demoSolar && demoSolar.classList.contains('active')) {
    updateSolarDisplay();
  }
  if (demoAnalytics && demoAnalytics.classList.contains('active')) {
    updateAnalyticsDisplay();
  }
}, 3000);




async function triggerIrrigation() {
  const auth = getAuthData();
  if (!auth?.loggedIn) {
    window.location.href = "login.html";
    return;
  }

  try {
    await triggerIrrigationForFarm(auth.farmId);
    alert("Irrigation triggered successfully.");
  } catch (error) {
    console.error(error);
    alert(error.message || "Unable to trigger irrigation.");
  }
}

async function updateEvents() {
  const auth = getAuthData();
  const eventList = document.querySelector(".event-list");
  if (!eventList || !auth?.loggedIn) return;

  const farmId = getSelectedFarmId(auth.farmId);
  eventList.innerHTML = "<li>Loading events...</li>";

  try {
    const events = await requestJson(`${apiBase}/data/farms/${farmId}/events?limit=5`);
    if (!Array.isArray(events) || events.length === 0) {
      eventList.innerHTML = "<li>No irrigation events available.</li>";
      return;
    }

    eventList.innerHTML = events.map((event) => {
      const duration = event.duration != null ? `${event.duration} min` : "In progress";
      return `<li><strong>${event.triggerType}</strong> · ${new Date(event.startTime).toLocaleString()} · ${duration} · ${event.waterUsed ?? "N/A"} L</li>`;
    }).join("");
  } catch (error) {
    console.error(error);
    eventList.innerHTML = "<li>Unable to load events.</li>";
  }
}

async function updateAlerts() {
  const auth = getAuthData();
  const alertList = document.querySelector(".alert-list");
  if (!alertList || !auth?.loggedIn) return;

  const farmId = getSelectedFarmId(auth.farmId);
  alertList.innerHTML = "<li>Loading alerts...</li>";

  try {
    const alerts = await requestJson(`${apiBase}/data/farms/${farmId}/alerts`);
    if (!Array.isArray(alerts) || alerts.length === 0) {
      alertList.innerHTML = "<li>No alerts at this time.</li>";
      return;
    }

    alertList.innerHTML = alerts.map((alert) => {
      const status = alert.resolved ? "Resolved" : "Open";
      const action = alert.resolved
        ? ""
        : ` <button class="inline-action" data-alert-id="${alert.alertId}" onclick="handleResolveAlert(this)">Resolve</button>`;
      return `<li><strong>${alert.alertType}</strong> · ${new Date(alert.timestamp).toLocaleString()} · ${status}<br><span>${alert.message || ""}</span>${action}</li>`;
    }).join("");
  } catch (error) {
    console.error(error);
    alertList.innerHTML = "<li>Unable to load alerts.</li>";
  }
}

async function handleResolveAlert(button) {
  const alertId = button?.dataset?.alertId;
  if (!alertId) return;

  toggleActionButton(button, true, "Resolving...");
  try {
    await resolveAlert(alertId);
    await updateAlerts();
  } catch (error) {
    console.error(error);
    alert(error.message || "Unable to resolve alert.");
    toggleActionButton(button, false);
  }
}

document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function onAnchorClick(event) {
    const target = this.getAttribute("href");
    if (!target || target === "#") return;
    const element = document.querySelector(target);
    if (!element) return;
    event.preventDefault();
    element.scrollIntoView({ behavior: "smooth" });
  });
});

window.addEventListener("scroll", () => {
  const header = document.querySelector("header");
  if (header) {
    header.style.background = window.scrollY > 100 ? "rgba(46,125,50,0.95)" : "linear-gradient(135deg, #4CAF50, #2E7D32)";
  }
});

window.addEventListener("scroll", () => {
  const scrolled = window.pageYOffset;
  const hero = document.querySelector(".hero");
  if (hero) {
    hero.style.transform = `translateY(${scrolled * 0.5}px)`;
  }
});

const observerOptions = {
  threshold: 0.1,
  rootMargin: "0px 0px -50px 0px"
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = "1";
      entry.target.style.transform = "translateY(0)";
    }
  });
}, observerOptions);

function animateCounters() {
  const counters = document.querySelectorAll(".stat-number");
  counters.forEach((counter) => {
    const target = parseInt(counter.dataset.target, 10) || 0;
    const increment = target / 100 || 1;
    let current = 0;
    const timer = setInterval(() => {
      current += increment;
      if (current >= target) {
        counter.textContent = `${target}`;
        clearInterval(timer);
      } else {
        counter.textContent = `${Math.floor(current)}`;
      }
    }, 20);
  });
}

const hamburger = document.querySelector(".hamburger");
const navLinks = document.querySelector(".nav-links");
if (hamburger && navLinks) {
  hamburger.addEventListener("click", () => {
    navLinks.classList.toggle("active");
    hamburger.classList.toggle("active");
  });
}

document.querySelectorAll(".nav-links a").forEach((link) => {
  link.addEventListener("click", () => {
    navLinks?.classList.remove("active");
    hamburger?.classList.remove("active");
  });
});

// Language translations
const translations = {
  // Navigation
  "nav.home": { en: "Home", sw: "Nyumbani" },
  "nav.features": { en: "Features", sw: "Vipengele" },
  "nav.how": { en: "How It Works", sw: "Inavyofanya Kazi" },
  "nav.dashboard": { en: "Dashboard", sw: "Dashibodi" },
  "nav.login": { en: "Login", sw: "Ingia" },
  "nav.register": { en: "Register", sw: "Jisajili" },
  "nav.contact": { en: "Contact", sw: "Wasiliana" },
  "nav.logout": { en: "Logout", sw: "Toka" },
  
  // Hero section
  "hero.title": { en: "Smart Water. Better Harvests.", sw: "Maji Akilifu. Mazao Bora." },
  "hero.subtitle": { en: "An IoT-powered irrigation system designed for Kenya's small-scale farmers", sw: "Mfumo wa umwagiliaji unaotumia IoT ulioandaliwa kwa wakulima wadogo wa Kenya" },
  "hero.cta": { en: "See How It Works", sw: "Ona Inavyofanya Kazi" },
  
  // Login
  "login.welcome": { en: "Welcome Back", sw: "Karibu Tena" },
  "login.subtitle": { en: "Access your AquaSense dashboard", sw: "Fikia dashibodi yako ya AquaSense" },
  "login.email": { en: "Email", sw: "Barua pepe" },
  "login.password": { en: "Password", sw: "Nenosiri" },
  "login.remember": { en: "Remember me", sw: "Kumbuka mimi" },
  "login.forgot": { en: "Forgot Password?", sw: "Umesahau Nenosiri?" },
  "login.signin": { en: "Sign In", sw: "Ingia" },
  "login.noaccount": { en: "Don't have an account?", sw: "Huna akaunti?" },
  "login.signup": { en: "Sign up", sw: "Jisajili" },
  
  // Register
  "register.title": { en: "Create Account", sw: "Unda Akaunti" },
  "register.name": { en: "Full Name", sw: "Jina Kamili" },
  "register.email": { en: "Email Address", sw: "Anwani ya Barua pepe" },
  "register.phone": { en: "Phone Number", sw: "Namba ya Simu" },
  "register.password": { en: "Password", sw: "Nenosiri" },
  "register.confirm": { en: "Confirm Password", sw: "Thibitisha Nenosiri" },
  "register.create": { en: "Create Account", sw: "Unda Akaunti" },
  "register.hasaccount": { en: "Already have an account?", sw: "Una akaunti tayari?" },
  "register.signin": { en: "Sign In", sw: "Ingia" },
  
  // Dashboard
  "dashboard.welcome": { en: "Welcome,", sw: "Karibu," },
  "dashboard.title": { en: "My Farm Dashboard", sw: "Dashibodi ya Shamba Langu" },
  "dashboard.farm": { en: "Active Farm", sw: "Shamba Linalofanya Kazi" },
  "dashboard.addfarm": { en: "Add Farm", sw: "Ongeza Shamba" },
  "dashboard.online": { en: "Online", sw: "Mtandaoni" },
  "dashboard.last": { en: "Last:", sw: "Mwisho:" },
  
  // Dashboard sections
  "dashboard.live": { en: "Live Readings", sw: "Kusoma Moja kwa Moja" },
  "dashboard.activity": { en: "Recent Activity", sw: "Shughuli za Hivi Karibuni" },
  "dashboard.events": { en: "Recent Irrigation Events", sw: "Matukio ya Umwagiliaji ya Hivi Karibuni" },
  "dashboard.alerts": { en: "System Alerts", sw: "Matangazo ya Mfumo" },
  "dashboard.trend": { en: "24hr Moisture Trend", sw: "Mwenendo wa Unyevu wa Masaa 24" },
  
  // Sensor readings
  "sensor.moisture": { en: "Soil Moisture", sw: "Unyevu wa Mchanga" },
  "sensor.temp": { en: "Temperature", sw: "Joto" },
  "sensor.humidity": { en: "Humidity", sw: "Unyevu" },
  "sensor.battery": { en: "Battery", sw: "Beti" },
  "sensor.solar": { en: "Solar", sw: "Jua" },
  
  // Controls
  "control.irrigate": { en: "Irrigate Now", sw: "Mwagilia Sasa" },
  "control.auto": { en: "Auto Mode", sw: "Hali ya Kiotomatiki" },
  "control.threshold": { en: "Moisture Threshold", sw: "Kiwango cha Unyevu" },
  "control.stop": { en: "Emergency Stop", sw: "Kuacha Dharura" },
  
  // Features
  "features.title": { en: "Key Features", sw: "Vipengele Vikuu" },
  "features.moisture": { en: "Soil Moisture Monitoring", sw: "Ufuatiliaji wa Unyevu wa Mchanga" },
  "features.moisture.desc": { en: "Capacitive sensors measure real-time moisture levels. Alerts when critical thresholds reached.", sw: "Vihisi vya uwezo hupima viwango vya unyevu wakati halisi. Onyo wakati kufikia viwango muhimu." },
  "features.auto": { en: "Auto Irrigation", sw: "Umwagiliaji wa Kiotomatiki" },
  "features.auto.desc": { en: "ESP32 microcontroller triggers water valve automatically based on sensor data and weather.", sw: "Kidhibiti cha ESP32 cha mikrosasa inachochoa vali ya maji kiotomatiki kulingana na data ya vihisi na hali ya hewa." },
  "features.dashboard": { en: "Web Dashboard", sw: "Dashibodi ya Mtandao" },
  "features.dashboard.desc": { en: "Live graphs, historical data, irrigation logs. Mobile-responsive ASP.NET dashboard.", sw: "Mchoro moja kwa moja, data ya kihistoria, kumbukumbu za umwagiliaji. Dashibodi ya ASP.NET inayofaa kwa simu." },
  "features.sms": { en: "SMS Alerts", sw: "Onyo za SMS" },
  "features.sms.desc": { en: "Simple SMS for basic phones: 'Irrigation ON', low moisture warnings, system status.", sw: "SMS rahisi kwa simu za msingi: 'Umwagiliaji UMEOLEWA', onyo za unyevu chini, hali ya mfumo." },
  "features.solar": { en: "Solar Powered", sw: "Inatumia Nishati ya Jua" },
  "features.solar.desc": { en: "Off-grid operation with solar panel. Works anywhere in rural Kenya.", sw: "Uendeshaji wa gridi-kwa-jua na paneli ya jua. Inafanya kazi popote Kenya vijijini." },
  "features.data": { en: "Cloud Data", sw: "Data ya Wingu" },
  "features.data.desc": { en: "SQLite database stores all readings. Analyze trends, predict problems.", sw: "Hifadhidata ya SQLite inahifadhi kusoma zote. Chunguza mwenendo, tabiri matatizo." },
  
  // Footer
  "footer.copyright": { en: "© 2026 Lewis Ondigi Abuga | The Cooperative University of Kenya", sw: "© 2026 Lewis Ondigi Abuga | Chuo Kikuu cha Ushirika cha Kenya" },
  
  // Index page
  "objective.title": { en: "Get Started", sw: "Anza" },
  "objective.subtitle": { en: "Sign in or sign up to access your smart irrigation dashboard", sw: "Ingia au jisajili kufikia dashibodi yako ya umwagiliaji akilifu" },
  "about.title": { en: "About AquaSense", sw: "Kuhusu AquaSense" },
  "about.desc1": { en: "AquaSense is a Kenyan smart irrigation platform that combines soil sensors, real-time data, and automated water control to help smallholder farmers save water, improve yields, and reduce labor.", sw: "AquaSense ni jukwaa la umwagiliaji akilifu la Kenya linalochanganya vihisi vya mchanga, data ya wakati halisi, na udhibiti wa maji otomatiki kusaidia wakulima wadogo kuokoa maji, kuboresha mavuno, na kupunguza kazi." },
  "about.desc2": { en: "Designed for rural deployment, it delivers both a mobile dashboard and SMS alerts so farmers can monitor crops with or without a smartphone.", sw: "Imeundwa kwa ajili ya uanzishaji vijijini, inatoa dashibodi ya simu na onyo za SMS ili wakulima waweze kufuatilia mazao kwa au bila simu janja." },
  "about.learnmore": { en: "Learn More", sw: "Jifunze Zaidi" },
  "objectives.title": { en: "Project Objectives", sw: "Malengo ya Mradi" },
  "obj1.title": { en: "1. Sensor Network Deployment", sw: "1. Uanzishaji wa Mtandao wa Vihisi" },
  "obj1.desc": { en: "Deploy soil moisture, temperature, and humidity sensors across the farm to capture field conditions in real time.", sw: "Weka vihisi vya unyevu wa mchanga, joto, na unyevu katika shamba ili kukamata hali za uwanja wakati halisi." },
  "obj2.title": { en: "2. Microcontroller Control System", sw: "2. Mfumo wa Udhibiti wa Microcontroller" },
  "obj2.desc": { en: "Use a central ESP32-based controller to collect sensor data and operate irrigation valves automatically.", sw: "Tumia kidhibiti cha kuhesabiwa cha ESP32 kukusanya data ya vihisi na kuendesha vali za umwagiliaji kiotomatiki." },
  "obj3.title": { en: "3. Web Dashboard Monitoring", sw: "3. Ufuatiliaji wa Dashibodi ya Mtandao" },
  "obj3.desc": { en: "Provide farmers with a responsive web dashboard for live soil condition updates, irrigation activity, and system status.", sw: "Toa wakulima dashibodi ya mtandao inayoitikia kwa masasisho ya hali ya mchanga, shughuli za umwagiliaji, na hali ya mfumo." },
  "obj4.title": { en: "4. Threshold-Based Irrigation", sw: "4. Umwagiliaji kwa Kiwango" },
  "obj4.desc": { en: "Trigger irrigation automatically whenever soil moisture drops below configured thresholds to protect crop health.", sw: "Chochea umwagiliaji kiotomatiki wakati unyevu wa mchanga unapopungua chini ya viwango vilivyowekwa ili kulinda afya ya mazao." },
  "obj5.title": { en: "5. SMS Alerts and Status Updates", sw: "5. Onyo za SMS na Masasisho ya Hali" },
  "obj5.desc": { en: "Send system alerts and basic status updates by SMS so farmers can stay informed even without a smartphone.", sw: "Tuma onyo za mfumo na masasisho ya hali ya msingi kwa SMS ili wakulima waweze kufahamu hata bila simu janja." },
  "stats.water": { en: "% Water Saved", sw: "% Maji Yamehifadhiwa" },
  "stats.sensors": { en: "Sensor Types", sw: "Aina za Vihisi" },
  "stats.monitoring": { en: "Hour Monitoring", sw: "Ufuatiliaji wa Masaa" },
  "stats.uptime": { en: "% Uptime", sw: "% Wakati wa Utumiaji" },
  "problem.title": { en: "The Water Crisis in Kenyan Agriculture", sw: "Mgogoro wa Maji katika Kilimo cha Kenya" },
  "problem.subtitle": { en: "Kenyan farmers lose 40% of water through inefficient manual irrigation. AquaSense changes that.", sw: "Wakulima wa Kenya wanapoteza 40% ya maji kupitia umwagiliaji wa kawaida usiofaa. AquaSense inabadilisha hilo." },
  "waterwaste.title": { en: "Water Waste", sw: "Upotezaji wa Maji" },
  "waterwaste.desc": { en: "Manual timing leads to over-watering (60%) or under-watering (40%). Crops suffer, costs rise.", sw: "Muda wa kawaida unaleta umwagiliaji mwingi (60%) au wa chini (40%). Mazao yanateseka, gharama zaongezeka." },
  "blindsched.title": { en: "Blind Scheduling", sw: "Ratiba ya Bila Uoni" },
  "blindsched.desc": { en: "Farmers guess based on experience. No real-time soil data = poor decisions.", sw: "Wakulima wanabahatisha kulingana na uzoefu. Hakuna data ya wakati halisi ya mchanga = maamuzi mabaya." },
  "features.key": { en: "Key Features", sw: "Vipengele Vikuu" },
  "features.moisture": { en: "Soil Moisture Monitoring", sw: "Ufuatiliaji wa Unyevu wa Mchanga" },
  "features.moisture.desc": { en: "Capacitive sensors measure real-time moisture levels. Alerts when critical thresholds reached.", sw: "Vihisi vya uwezo hupima viwango vya unyevu wakati halisi. Onyo wakati kufikia viwango muhimu." },
  "features.auto": { en: "Auto Irrigation", sw: "Umwagiliaji wa Kiotomatiki" },
  "features.auto.desc": { en: "ESP32 microcontroller triggers water valve automatically based on sensor data and weather.", sw: "Kidhibiti cha ESP32 cha mikrosasa inachochoa vali ya maji kiotomatiki kulingana na data ya vihisi na hali ya hewa." },
  "features.dashboard": { en: "Web Dashboard", sw: "Dashibodi ya Mtandao" },
  "features.dashboard.desc": { en: "Live graphs, historical data, irrigation logs. Mobile-responsive ASP.NET dashboard.", sw: "Mchoro moja kwa moja, data ya kihistoria, kumbukumbu za umwagiliaji. Dashibodi ya ASP.NET inayofaa kwa simu." },
  "features.sms": { en: "SMS Alerts", sw: "Onyo za SMS" },
  "features.sms.desc": { en: "Simple SMS for basic phones: 'Irrigation ON', low moisture warnings, system status.", sw: "SMS rahisi kwa simu za msingi: 'Umwagiliaji UMEOLEWA', onyo za unyevu chini, hali ya mfumo." },
  "features.solar": { en: "Solar Powered", sw: "Inatumia Nishati ya Jua" },
  "features.solar.desc": { en: "Off-grid operation with solar panel. Works anywhere in rural Kenya.", sw: "Uendeshaji wa gridi-kwa-jua na paneli ya jua. Inafanya kazi popote Kenya vijijini." },
  "features.data": { en: "Cloud Data", sw: "Data ya Wingu" },
  "features.data.desc": { en: "SQLite database stores all readings. Analyze trends, predict problems.", sw: "Hifadhidata ya SQLite inahifadhi kusoma zote. Chunguza mwenendo, tabiri matatizo." },
  
  // Features page detailed translations
  "features.moisture.accuracy": { en: "Accuracy ±3%", sw: "Ukweli ±3%" },
  "features.moisture.interval": { en: "15min intervals", sw: "Kila dakika 15" },
  "features.moisture.depth": { en: "Multi-depth sensing", sw: "Kufuatilia kina mbalimbali" },
  "features.moisture.login": { en: "Sign in to view moisture data", sw: "Ingia kuona data ya unyevu" },
  
  "features.auto.valve": { en: "Valve duration optimized", sw: "Muda wa valve umepangwa" },
  "features.auto.zone": { en: "Multi-zone control", sw: "Kudhibiti maeneo mengi" },
  "features.auto.pump": { en: "Pump protection", sw: "Ulinzi wa pampu" },
  "features.auto.login": { en: "Sign in to configure auto-irrigation", sw: "Ingia kusanidi umwagiliaji wa kiotomatiki" },
  
  "features.dashboard.live": { en: "Live sensor data", sw: "Data ya sensa muda halisi" },
  "features.dashboard.history": { en: "Historical trends", sw: "Mwelekeo wa kihistoria" },
  "features.dashboard.manual": { en: "Manual override", sw: "Kudhibiti kwa mkono" },
  "features.dashboard.login": { en: "Login to Access Dashboard", sw: "Ingia Kufikia Dashibodi" },
  "features.dashboard.note": { en: "View live farm data, control irrigation, and monitor sensors after logging in.", sw: "Tazama data ya shamba, simamia umwagiliaji, na fuatilia sensa baada ya kuingia." },
  "features.dashboard.open": { en: "Open Dashboard", sw: "Fungua Dashibodi" },
  
  "features.sms.allphones": { en: "Works on all phones", sw: "Inafanya kazi kwa simu zote" },
  "features.sms.nointernet": { en: "No internet required", sw: "Hakuna intaneti inayohitajika" },
  "features.sms.thresholds": { en: "Custom thresholds", sw: "Kizuizi kwa kila shamba" },
  "features.sms.login": { en: "Sign in to configure SMS alerts", sw: "Ingia kusanidi arifa za SMS" },
  
  "features.solar.panel": { en: "10W panel included", sw: "Paneli ya 10W pamoja" },
  "features.solar.battery": { en: "48hr battery", sw: "Betri ya masaa 48" },
  "features.solar.lowpower": { en: "Low power design", sw: "Muundo wa chini wa nguvu" },
  "features.solar.login": { en: "Sign in to monitor power levels", sw: "Ingia kufuatilia viwango vya nguvu" },
  
  "features.analytics": { en: "Cloud Analytics", sw: "Analytics ya Wingu" },
  "features.analytics.desc": { en: "MySQL database stores all readings for trend analysis, yield prediction, system optimization.", sw: "Hifadhidata ya MySQL inahifadhi masomo yote kwa uchanganuzi wa mwelekeo, utabiri wa mavuno." },
  "features.analytics.retention": { en: "1yr data retention", sw: "Kuhifadhi data kwa mwaka 1" },
  "features.analytics.ai": { en: "AI crop insights", sw: "Maoni ya mimea kwa AI" },
  "features.analytics.export": { en: "Export reports", sw: "Ripoti za kuhamisha" },
  "features.analytics.login": { en: "Sign in to view analytics", sw: "Ingia kuona analytics" },
  
  // Technical Specifications
  "specs.title": { en: "Technical Specifications", sw: "Vipengele vya Kiufundi" },
  "specs.hardware": { en: "Hardware", sw: "Vifaa vya Kihisia" },
  "specs.hardware.val": { en: "ESP32 + Capacitive sensors + Solenoid valve + Solar 10W + Battery 48hr", sw: "ESP32 + Vihisi vya uwezo + Vali ya solenoid + Sola 10W + Betri 48hr" },
  "specs.backend": { en: "Backend", sw: "Backend" },
  "specs.backend.val": { en: "MySQL DB + PHP API + SMS notifications + Web dashboard", sw: "Hifadhidata MySQL + PHP API + Arifa za SMS + Dashibodi ya Mtandao" },
  "specs.mobile": { en: "Mobile", sw: "Simu" },
  "specs.mobile.val": { en: "Responsive PWA + SMS fallback + Offline sync", sw: "PWA inayoitikia + Arifa za SMS + Usawishaji nje ya mtandao" },
  
  "preview.title": { en: "Preview the Dashboard", sw: "Hakiki Dashibodi" },
  "preview.subtitle": { en: "Track moisture, trigger irrigation, and see real-time alerts from a smart farm dashboard.", sw: "Fuatilia unyevu, chochea umwagiliaji, na ona onyo za wakati halisi kutoka kwa dashibodi akilifu ya shamba." },
  "preview.open": { en: "Open Dashboard", sw: "Fungua Dashibodi" },
  "backtotop": { en: "Back to Top", sw: "Rudi Juu" },
  
  // Additional translations for index page
  "table.feature": { en: "", sw: "" },
  "table.traditional": { en: "Traditional", sw: "Jadi" },
  "table.aquasense": { en: "AquaSense", sw: "AquaSense" },
  "table.water": { en: "Water Efficiency", sw: "Ufanisi wa Maji" },
  "table.waste": { en: "40% wasted", sw: "40% kupotea" },
  "table.efficient": { en: "95% efficient", sw: "95% ufanisi" },
  "table.data": { en: "Data", sw: "Data" },
  "table.guess": { en: "Manual guess", sw: "Kukisia kwa mkono" },
  "table.sensors": { en: "Real-time sensors", sw: "Vihisi vya wakati halisi" },
  "table.control": { en: "Control", sw: "Udhibiti" },
  "table.manual": { en: "Manual labor", sw: "Kazi ya mkono" },
  "table.autotrigger": { en: "Auto trigger", sw: "Kuchochea kiotomatiki" },
  "table.monitoring": { en: "Monitoring", sw: "Ufuatiliaji" },
  "table.daily": { en: "Daily visits", sw: "Ziara za kila siku" },
  "table.smsdash": { en: "SMS + Dashboard", sw: "SMS + Dashibodi" }
};

// Apply language to all elements
function applyLanguage(lang) {
  // Update all elements with data-lang attribute
  document.querySelectorAll("[data-lang]").forEach(element => {
    const key = element.getAttribute("data-lang");
    if (translations[key] && translations[key][lang]) {
      element.textContent = translations[key][lang];
    }
  });
  
  // Apply translations to placeholders
  document.querySelectorAll("[data-lang-placeholder]").forEach(element => {
    const key = element.getAttribute("data-lang-placeholder");
    if (translations[key] && translations[key][lang]) {
      element.placeholder = translations[key][lang];
    }
  });
}

// Language toggle function
function toggleLanguage() {
  const currentLang = localStorage.getItem("lang") || "en";
  const newLang = currentLang === "en" ? "sw" : "en";
  localStorage.setItem("lang", newLang);
  
  // Update button text
  const langText = document.getElementById("langText");
  if (langText) {
    langText.textContent = newLang === "en" ? "EN" : "SW";
  }
  
  // Apply translations
  applyLanguage(newLang);
}

document.addEventListener('DOMContentLoaded', function() {
  const currentLang = localStorage.getItem('lang') || 'en';
  const langText = document.getElementById('langText');
  if (langText) {
    langText.textContent = currentLang === 'en' ? 'EN' : 'SW';
  }
  
  // Apply saved language translations
  applyLanguage(currentLang);
});

function initPageAnimations() {
  if (document.querySelector(".stats-section")) {
    document.querySelectorAll(".stat-number").forEach((element) => {
      element.textContent = "0";
    });
    setTimeout(animateCounters, 500);
  }

  document.querySelectorAll(".feature-card, .timeline-content").forEach((element) => {
    element.style.opacity = "0";
    element.style.transform = "translateY(30px)";
    element.style.transition = "opacity 0.6s ease, transform 0.6s ease";
    observer.observe(element);
  });
}
