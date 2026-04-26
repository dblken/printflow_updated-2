@echo off
echo ========================================
echo  PrintFlow Socket.io Setup Verification
echo ========================================
echo.

cd /d "%~dp0"

echo [1/5] Checking Node.js...
node --version >nul 2>&1
if errorlevel 1 (
    echo ❌ FAIL: Node.js not installed
    echo    Install from: https://nodejs.org/
    goto :end
) else (
    echo ✅ PASS: Node.js installed
    node --version
)
echo.

echo [2/5] Checking package.json...
if exist "package.json" (
    echo ✅ PASS: package.json exists
) else (
    echo ❌ FAIL: package.json not found
    goto :end
)
echo.

echo [3/5] Checking dependencies...
if exist "node_modules\socket.io" (
    echo ✅ PASS: socket.io installed
) else (
    echo ⚠️  WARN: Dependencies not installed
    echo    Run: npm install
)
echo.

echo [4/5] Checking Socket.io version...
npm list socket.io 2>nul | findstr "socket.io@4"
if errorlevel 1 (
    echo ❌ FAIL: Socket.io v4 not found
    echo    Run: npm install socket.io@4.8.3
) else (
    echo ✅ PASS: Socket.io v4 installed
)
echo.

echo [5/5] Checking server.js...
if exist "server.js" (
    echo ✅ PASS: server.js exists
) else (
    echo ❌ FAIL: server.js not found
    goto :end
)
echo.

echo ========================================
echo  Verification Complete!
echo ========================================
echo.
echo To start the server, run: start.bat
echo.

:end
pause
