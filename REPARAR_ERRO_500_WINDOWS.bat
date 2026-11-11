@echo off
setlocal EnableExtensions
chcp 65001 >nul
cd /d "%~dp0"
title Aurora Multimarcas - Reparar APP_KEY / erro 500
cls
echo =====================================================
echo   AURORA MULTIMARCAS - REPARO DE APP_KEY (SERVBAY)
echo =====================================================
echo.
where php >nul 2>&1
if errorlevel 1 (
 echo ERRO: PHP nao esta no PATH. Abra um terminal do ServBay
 echo com a versao de PHP do site selecionada e execute este BAT.
 goto :erro
)
if not exist "scripts\ensure_app_key.php" (
 echo ERRO: scripts\ensure_app_key.php nao encontrado.
 goto :erro
)
echo [1/3] Conferindo chave, .env e backups, sem alterar o banco...
php "scripts\ensure_app_key.php"
if errorlevel 1 goto :erro
if not exist "vendor\autoload.php" (
 echo ERRO: dependencias Laravel nao encontradas. Execute composer install.
 goto :erro
)
echo [2/3] Limpando cache de configuracao e views...
php artisan optimize:clear
if errorlevel 1 goto :erro
echo [3/3] Validando inicializacao do Laravel...
php artisan about --only=environment
if errorlevel 1 goto :erro
echo.
echo =====================================================
echo   REPARO FINALIZADO. Abra https://aurora.test
 echo   Se houver novo erro, anote a excecao exibida.
echo =====================================================
pause
exit /b 0
:erro
echo.
echo Falha no reparo. Nenhum comando de exclusao foi executado.
echo Nao use migrate:fresh, db:wipe ou key:generate --force.
pause
exit /b 1
