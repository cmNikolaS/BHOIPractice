@echo off
REM Double-click to launch the BHOI Arhiva local preview.
title BHOI Arhiva - Preview Server
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0preview.ps1"
echo.
echo Web server stopped.
pause
