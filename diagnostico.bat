@echo off
echo === DIAGNOSTICO SISTEMA MEDIRECORD - ACTUALIZADO ===
echo.

echo 1. Verificando directorio actual...
cd /d "C:\xampp\htdocs\web_6C_2025\medirecord_12"
echo Directorio: %CD%

echo.
echo 2. Verificando PHP...
if exist "C:\xampp\php\php.exe" (
    echo PHP encontrado: C:\xampp\php\php.exe
    C:\xampp\php\php.exe -v
) else (
    echo ERROR: PHP no encontrado
)

echo.
echo 3. Verificando archivos CRITICOS...
if exist "enviar_recordatorios.php" (
    echo enviar_recordatorios.php: OK
) else (
    echo enviar_recordatorios.php: NO ENCONTRADO
)

if exist "webhook_confirmaciones.php" (
    echo webhook_confirmaciones.php: OK
) else (
    echo webhook_confirmaciones.php: NO ENCONTRADO
)

if exist "config.php" (
    echo config.php: OK
) else (
    echo config.php: NO ENCONTRADO
)

echo.
echo 4. Verificando carpeta logs...
if exist "logs" (
    echo logs/: EXISTE
    dir logs
) else (
    echo logs/: NO EXISTE - Creando...
    mkdir logs
)

echo.
echo 5. Probando escritura en logs...
echo test > logs/test_diagnostico.txt
if exist "logs\test_diagnostico.txt" (
    echo Escritura en logs: OK
    del logs\test_diagnostico.txt
) else (
    echo Escritura en logs: FALLIDA
)

echo.
echo 6. Probando ejecucion PHP...
C:\xampp\php\php.exe -r "echo 'PHP funciona correctamente';" > logs/php_test.txt 2>&1
if %errorlevel% equ 0 (
    echo Ejecucion PHP: OK
    type logs/php_test.txt
) else (
    echo Ejecucion PHP: FALLIDA
)

echo.
echo 7. Probando enviar_recordatorios.php...
C:\xampp\php\php.exe enviar_recordatorios.php > logs/test_enviar_recordatorios.txt 2>&1
if %errorlevel% equ 0 (
    echo enviar_recordatorios.php: EJECUTADO CORRECTAMENTE
    echo --- PRIMERAS 10 LINEAS DE SALIDA ---
    set /p first10=<logs/test_enviar_recordatorios.txt
    echo %first10%
) else (
    echo enviar_recordatorios.php: ERROR EN EJECUCION
    type logs/test_enviar_recordatorios.txt
)

echo.
echo === DIAGNOSTICO COMPLETADO ===
pause