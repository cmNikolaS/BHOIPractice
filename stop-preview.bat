@echo off
REM Double-click to stop the BHOI Arhiva preview database + web server.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0stop-preview.ps1"
pause
