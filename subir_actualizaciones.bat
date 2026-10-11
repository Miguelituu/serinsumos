@echo off
chcp 65001 > nul
echo ===================================================
echo   SERINSUMOS - SUBIR ACTUALIZACIONES A GITHUB
echo ===================================================
echo.

REM Verificar si existe remote configurado
git remote get-url origin >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [!] No hay un repositorio remoto (GitHub) vinculado todavia.
    echo.
    echo Para vincular tu repositorio por primera vez, ejecuta:
    echo   git remote add origin https://github.com/TU-USUARIO/TU-REPOSITORIO.git
    echo.
    set /p REPO_URL="Pega aca la URL de tu repositorio GitHub (o presiona ENTER para cancelar): "
    if "%REPO_URL%"=="" goto fin
    git remote add origin %REPO_URL%
)

echo [*] Agregando archivos modificados...
git add .

echo.
set /p MENSAJE="Mensaje de actualizacion (ej: 'Actualizacion de estilos y logo'): "
if "%MENSAJE%"=="" set MENSAJE="Actualizaciones generales"

echo.
echo [*] Guardando cambios locales (commit)...
git commit -m "%MENSAJE%"

echo.
echo [*] Subiendo al repositorio remoto (push a main)...
git push -u origin main

if %ERRORLEVEL% equ 0 (
    echo.
    echo ===================================================
    echo   [OK] ACTUALIZACION SUBIDA CON EXITO!
    echo ===================================================
) else (
    echo.
    echo [X] Ocurrio un error al subir los cambios.
    echo Verifica tus credenciales de GitHub o la conexion de red.
)

:fin
echo.
pause
