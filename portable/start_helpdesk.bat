@echo off
setlocal
cd /d "%~dp0.."

REM Puerto (cámbialo si está ocupado)
set HOST=127.0.0.1
set PORT=8080

echo.
echo ==========================================
echo   Helpdesk Portable PHP + SQLite
echo ==========================================
echo Iniciando servidor en http://%HOST%:%PORT% ...
echo (Cierra esta ventana para detenerlo)
echo.

REM Abre el navegador (espera 1 seg para que levante)
start "" "http://%HOST%:%PORT%/Casos"

php -S %HOST%:%PORT% -t public
endlocal
