@echo off
setlocal
cd /d "%~dp0.."

echo.
echo ==========================================
echo   Crear/Reset Admin (Helpdesk)
echo ==========================================
echo.

set /p EMAIL=Email admin (ej: admin@local) :
set /p NAME=Nombre (ej: Alessandro) :
set /p PASS=Password :

php bin/init.php "%EMAIL%" "%NAME%" "%PASS%"

echo.
pause
endlocal
