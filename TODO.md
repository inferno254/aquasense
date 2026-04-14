# Aquasense Phase 2 Frontend Polish - Detailed Steps

## Phase 2 Status: 🔄 In Progress

**Instructions:** Mark each step ✅ after completion. Use tools to edit files step-by-step.

### Step 2.1: Fix farm selector logic in demo.html + script.js
- ✅ Edit demo.html inline script: Replace all `farmId` with `selectedFarmId` in API calls (updateSensors, updateEvents, updateAlerts, triggerIrrigation)
- ✅ Test: Switch farms, verify data updates per farm

### Step 2.2: Add Swahili toggle
- ✅ Edit demo.html: Add toggle button near farm-selector (`<button id="langToggle">Swahili</button>`)
- ✅ Edit script.js: Add i18n object (Eng/Swa for key strings: dashboard titles, sensors, buttons, alerts)
- ✅ Implement lang switch: Update DOM texts, localStorage lang, reload on toggle
- ✅ Add Swahili translations (use accurate terms: e.g., "Soil Moisture" → "Unyevu wa Udongo")

### Step 2.3: Add SMS status indicator
- ✅ Edit demo.html: Add div in controls card (`<div id="smsStatus" class="sms-indicator">SMS: Ready</div>`)
- ✅ Edit script.js: Add updateSmsStatus() fetching recent notifications or mock backend /data/sms-status
- ✅ Style: Green/red pill in style.css

### Step 2.4: Enhance dashboard (WebSocket/polling, responsiveness)
- ✅ Edit script.js: Reduce setInterval to 10s, add EventSource for real-time if backend supports
- ✅ Test multi-farm: Create test farms via register/login

### Step 2.5: Edit style.css for new elements
- ✅ Add .lang-toggle, .sms-indicator styles (responsive)

### Step 2.6: Create admin.html
- ✅ Create admin.html: Multi-farm table/chart overview, link to demo.html per farm
- ✅ Reuse components from demo.html (auth, apiBase, etc.)

### Step 2.7: Test & Mark Complete
- ✅ Full test: serve_frontend.ps1, backend running, login, switch lang/farm, irrigate, check SMS
- ✅ Update TODO.md: Phase 2 ✅ → Proceed to Phase 3 Hardware

**Phase 2 Status: ✅ COMPLETE**

**Next:** Phase 3: Hardware Simulation & IoT



