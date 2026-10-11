@echo off
chcp 65001 > nul
echo ===================================================
echo   SERINSUMOS - GENERAR PAQUETE ZIP PARA HOSTINGER
echo ===================================================
echo.
php crear_zip.php
echo.
pause
