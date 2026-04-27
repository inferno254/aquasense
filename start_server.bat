@echo off
echo Starting AquaSense PHP Server...
cd /d "c:\Users\Inferno\aquasense"
C:\xampp\php\php.exe -S localhost:8000 php\api\index.php
pause
