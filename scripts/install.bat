@echo off
:: ============================================================================
:: LM-PATH LABORATORY SYSTEM - 1-CLICK CLIENT INSTALLER
:: ============================================================================
:: Double-click this file to automatically install and setup the entire system.
:: ============================================================================

title LM-Path Laboratory System Installer

:: Check for Administrator privileges and elevate if needed
net session >nul 2>&1
if %errorLevel% == 0 (
    goto :RunInstaller
) else (
    echo Requesting Administrator privileges...
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

:RunInstaller
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"

if %errorLevel% neq 0 (
    echo.
    echo Installation stopped or encountered an error.
    pause
)
