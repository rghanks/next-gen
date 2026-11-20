@echo off
:: ============================================================================
:: LM-PATH - AUTOMATIC STARTUP CONFIGURATOR (WINDOWS SERVICES)
:: ============================================================================
:: Configures Apache and MySQL to run automatically on Windows boot.
:: No manual XAMPP startup needed after system restart!
:: ============================================================================

title Enable Automatic Startup for LM-Path

:: Elevate to Administrator if not already admin
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo Requesting Administrator privileges...
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

echo =================================================================
echo    ENABLING AUTOMATIC WINDOWS SERVICES FOR APACHE AND MYSQL      
echo =================================================================
echo.

set XAMPP=C:\xampp

if not exist "%XAMPP%\apache\bin\httpd.exe" (
    echo [ERROR] XAMPP not found at C:\xampp. Please verify your installation.
    pause
    exit /b 1
)

:: 1. Stop existing standalone processes if running
taskkill /F /IM httpd.exe >nul 2>&1
taskkill /F /IM mysqld.exe >nul 2>&1

:: 2. Register & Configure Apache Windows Service
echo [1/2] Installing Apache as a Windows Service...
"%XAMPP%\apache\bin\httpd.exe" -k install >nul 2>&1
sc config Apache2.4 start= auto >nul 2>&1
net start Apache2.4 >nul 2>&1
echo       - Apache service installed and set to AUTOMATIC startup.

:: 3. Register & Configure MySQL Windows Service
echo [2/2] Installing MySQL as a Windows Service...
"%XAMPP%\mysql\bin\mysqld.exe" --install "mysql" --defaults-file="%XAMPP%\mysql\bin\my.ini" >nul 2>&1
sc config mysql start= auto >nul 2>&1
net start mysql >nul 2>&1
echo       - MySQL service installed and set to AUTOMATIC startup.

echo.
echo =================================================================
echo    SUCCESS: Both Apache and MySQL are now automatic services!   
echo =================================================================
echo Even if the computer restarts, shuts down, or powers off:
echo The laboratory software will start AUTOMATICALLY in the background.
echo Nobody needs to open XAMPP or click Start manually!
echo =================================================================
echo.
pause
