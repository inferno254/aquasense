@echo off
cd /d C:\Users\Inferno
echo Downloading PHP binary...
https://windows.php.net/downloads/releases/php-8.3.13-nts-Win32-vs16-x64.zip' "
echo Extracting to C:\php...
powershell -Command "Expand-Archive php-bin.zip php"
echo Setting PATH (user)...
setx PATH "%PATH%;C:\Users\Inferno\php"
echo Complete! 
echo 1. Close/reopen VSCode terminal
echo 2. php --version
echo 3. .\serve_php_backend.ps1
echo 4. start login.html - login admin@example.com / password123
pause

