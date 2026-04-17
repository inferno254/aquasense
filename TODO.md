# AquaSense Error Fix TODO

## Plan Overview
- Fix PDO_SQLITE "could not find driver" error
- Enable API endpoints (alerts, events, auto-mode, farms POST)
- Verify dashboard loads data

## Steps
- [x] 1. Verify php.ini has pdo_sqlite enabled (DONE: already enabled)
- [x] 2. Check php_pdo_sqlite.dll exists in ext/ (DONE: exists)
- [x] 3. Create phpinfo.php + test_check_db.php (DONE)
- [ ] 4. Test http://aquasense.local/phpinfo.php – confirm pdo_sqlite loaded
- [ ] 5. Test http://aquasense.local/test_check_db.php – DB OK?
- [ ] 6. If fail: http://aquasense.local/php/init_db.php
- [ ] 7. Restart Apache (XAMPP Control Panel)
- [ ] 8. Test API: http://aquasense.local/php/api/data/farms/550e8400-e29b-41d4-a716-446655440002/alerts
- [ ] 9. Refresh dashboard.html – no 500s

**PROGRESS:**
- [x] CLI PHP works perfectly 
- [ ] Apache PHP fails (different config?)
**NEXT: http://aquasense.local/phpinfo.php output needed!**

