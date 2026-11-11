@echo off
chcp 65001 >nul
setlocal EnableExtensions
cd /d "%~dp0"
title Aurora Multimarcas - Atualizacao segura
cls
echo =====================================================
echo    AURORA MULTIMARCAS - ATUALIZACAO PARA SERVBAY
 echo =====================================================
echo.
where php >nul 2>&1
if errorlevel 1 (
 echo ERRO: PHP nao encontrado no PATH. Utilize o terminal do ServBay.
 goto :falha
)
if not exist ".env" (
 echo ATENCAO: .env ausente. Tentando localizar copia de seguranca...
)
echo [1/6] Conferindo .env e APP_KEY ANTES de executar o Laravel...
php "scripts\ensure_app_key.php"
if errorlevel 1 goto :falha
if not exist "vendor\autoload.php" (
 echo ERRO: vendor ausente. Execute composer install na raiz do projeto.
 goto :falha
)
echo [2/6] Limpando caches antigos antes das migrations...
php artisan optimize:clear
if errorlevel 1 goto :falha
echo [3/6] Executando somente migrations pendentes (sem seeders)...
php artisan migrate --force
if errorlevel 1 goto :falha
echo [4/6] Conferindo rotas...
php artisan route:list --path=categoria
if errorlevel 1 goto :falha
echo [5/6] Atualizando recursos de interface...
where npm >nul 2>&1
if errorlevel 1 (
 echo AVISO: npm nao encontrado. Se a interface estiver desatualizada, execute npm run build.
) else (
 if not exist "node_modules\vite" (
   call npm install
   if errorlevel 1 goto :falha
 )
 call npm run build
 if errorlevel 1 goto :falha
)
echo [6/6] Conferindo link de imagens...
if not exist "public\storage" (
  php artisan storage:link
  if errorlevel 1 echo AVISO: confira permissoes do Windows para links simbolicos.
)
echo.
echo Atualizacao finalizada com verificacao de APP_KEY.
echo .env, contas, banco e imagens existentes foram preservados.
pause
exit /b 0
:falha
echo.
echo ERRO na atualizacao. Veja mensagem acima. Nao zere o banco.
pause
exit /b 1
