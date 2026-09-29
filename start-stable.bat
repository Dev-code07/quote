@echo off
REM QuoteFlow - smooth daily start (double-click this file)
REM Uses PHP 8.4 (project needs >=8.4.1), removes stale Vite "hot" file,
REM ensures DB exists + migrated, then serves on 127.0.0.1:8000.

set PHP=C:\tools\php84\php.exe
set MYSQL=C:\wamp64\bin\mysql\mysql8.0.31\bin\mysql.exe
cd /d "%~dp0"

echo [1/4] Checking PHP...
%PHP% --version | findstr "PHP"
if errorlevel 1 ( echo PHP 8.4 not found at %PHP% & pause & exit /b 1 )

echo [2/4] Frontend mode check...
if exist "public\hot" (
  echo   Stale public\hot found - deleting so app uses public\build (stable^).
  del /Q "public\hot"
) else (
  echo   OK - using compiled public\build assets.
)

echo [3/4] Ensuring database...
if exist "%MYSQL%" (
  "%MYSQL%" -u root -e "CREATE DATABASE IF NOT EXISTS quote CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  %PHP% artisan migrate --force
) else (
  echo   MySQL exe not found, skipping auto-create. Make sure DB is up, then run: %PHP% artisan migrate --force
)

echo [4/4] Starting Laravel on http://127.0.0.1:8000 ...
echo   Keep this window open. Open http://127.0.0.1:8000 in Chrome.
echo   Only if editing CSS/JS, open a 2nd terminal and run: npm run dev
%PHP% artisan serve --host=127.0.0.1 --port=8000
pause
