# AquaSense - Intelligent Irrigation Website
**Project Location:** C:/Users/Inferno/aquasense
**Tech:** HTML/CSS/JS Static Site (Demo Auth with .NET API)
**Status:** Phase 2 Complete ✓ | Phase 6 API Integration Complete ✓


## Phase 1 — Planning & Design ✓
- [x] Target audience defined
- [x] Colors: Greens/Blues agri-water theme
- [x] Fonts: Inter
- [x] Tech: HTML/CSS/JS
- [x] Wireframes planned

## Phase 2 — Pages to Build
- [x] 1. Landing Page (index.html) ✓
- [x] 2. About (about.html) ✓
- [x] 3. Problem & Solution (problem.html) ✓
- [x] 4. Features (features.html) ✓
- [x] 5. How It Works (how.html) ✓
- [x] 6. Tech Stack (tech.html) ✓
- [x] 7. Dashboard Demo (demo.html) ✓
- [x] 8. Research (research.html) ✓
- [x] 9. Contact (contact.html) ✓
- [x] 10. Login (login.html) ✓
- [x] 11. Registration page (register.html) ✓

## Phase 3 — Components ✓
- [x] Responsive navbar (w/ login icon)
- [x] Hero animations
- [x] Feature cards
- [x] Timeline
- [x] Stats counter
- [x] Footer
- [x] Footer standardization across pages

## Phase 4 — Authentication ✓
- [x] Login icon top-right nav
- [x] Full login page (login.html)
- [x] Registration page (register.html)
- [x] Form validation + demo auth (localStorage)
- [x] User dashboard post-login (demo.html)
- [x] Register link added to all pages
- [x] Demo dashboard events and controls patched

## Phase 5 — Database (PostgreSQL) 📊
- [x] Install PostgreSQL locally/Windows ✓
- [x] Create DB `aquasense` ✓
- [x] 5 Tables: `Users`, `Farms`, `Sensor_Readings`, `Irrigation_Events`, `System_Alerts` ✓
- [x] Primary/Foreign keys + relationships (Users→Farms→Readings/Events/Alerts)
- [x] Sample data for demo
- [x] .NET API connection (Phase 6)
- [x] Multi-farm selection UI on dashboard

**Next:** Verify local API server and extend farm management  
**Run schema:** `psql -U postgres -d aquasense -f ../aquasense/schema.sql`  
**Demo Users:** admin@example.com / password123 | farmer@kenya.com / irrigate2024  
**Site:** `start ../aquasense/index.html`

