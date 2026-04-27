@echo off
echo ========================================
echo   AquaSense Real-Time Data Simulation
echo ========================================
echo.
echo This will generate:
echo   - Sensor readings every 5 minutes
echo   - ~10 alerts per day
echo   - Irrigation events when moisture is low
echo.
echo Press Ctrl+C to stop
echo ========================================
echo.

powershell -ExecutionPolicy Bypass -File "%~dp0simulate_continuous.ps1"

pause
