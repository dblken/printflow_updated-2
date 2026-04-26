@echo off
setlocal enabledelayedexpansion

rem Starts the PrintFlow Socket.IO signaling server (server.js) on port 3000.
rem Use this instead of `npm start` when PowerShell blocks npm.ps1 (ExecutionPolicy).

cd /d "%~dp0"

for /f "tokens=1-5" %%a in ('netstat -ano ^| findstr ":3000" ^| findstr /i "LISTENING"') do (
  echo [PrintFlow] Call server already running on port 3000 (PID %%e)
  exit /b 0
)

echo [PrintFlow] Starting call server on port 3000...
start "PrintFlow Call Server" /min node server.js
echo [PrintFlow] Started. Visit http://127.0.0.1:3000/ to verify.
exit /b 0

