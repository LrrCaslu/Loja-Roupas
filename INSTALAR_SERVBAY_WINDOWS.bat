@echo off
chcp 65001 >nul
setlocal EnableExtensions
cd /d "%~dp0"
title Instalacao Aurora Multimarcas - ServBay
cls
echo =====================================================
echo   AURORA MULTIMARCAS - INSTALACAO (SERVBAY/APACHE)
echo =====================================================
echo.
where php >nul 2>&1 || goto :no_php
where composer >nul 2>&1 || goto :no_composer
where npm >nul 2>&1 || goto :no_npm
if not exist ".env" (
  copy ".env.example" ".env" >nul
  if errorlevel 1 goto :fail
  echo .env criado. Configure APP_URL e credenciais DB no editor.
  start "" notepad ".env"
  echo.
  pause
)
echo [1/7] Validando o .env e gerando a APP_KEY somente se necessaria...
php "scripts\ensure_app_key.php"
if errorlevel 1 goto :fail
echo [2/7] Instalando dependencias PHP...
call composer install --no-interaction --prefer-dist
if errorlevel 1 goto :fail
echo [3/7] Compilando recursos de interface...
call npm install
if errorlevel 1 goto :fail
call npm run build
if errorlevel 1 goto :fail
echo [4/7] Limpando cache antigo...
php artisan optimize:clear
if errorlevel 1 goto :fail
echo [5/7] Executando migrations e dados iniciais de desenvolvimento...
if exist "storage\framework\aurora_ja_instalado.flag" (
 echo Instalacao ja executada neste diretorio. Rodando migrations sem seeders.
 php artisan migrate --force
) else (
 echo ATENCAO: esta opcao instala dados de demonstracao para ambiente novo.
 php artisan migrate --seed --force
 if not errorlevel 1 type nul >"storage\framework\aurora_ja_instalado.flag"
)
if errorlevel 1 goto :fail
echo [6/7] Criando link de imagens...
if not exist "public\storage" (
  php artisan storage:link
  if errorlevel 1 echo Aviso: verifique permissoes para links.
)
echo [7/7] Validando inicializacao...
php artisan about --only=environment
if errorlevel 1 goto :fail
echo.
echo Instalacao concluida. Configure Apache apontando para a pasta public.
echo Exemplo: https://aurora.test - Painel: https://aurora.test/admin
echo Login de desenvolvimento: admin@auroramultimarcas.test
pause
exit /b 0
:no_php
echo PHP nao encontrado. Use o terminal do ServBay.
goto :fail
:no_composer
echo Composer nao encontrado. Instale ou habilite Composer no terminal.
goto :fail
:no_npm
echo npm nao encontrado. Instale ou habilite Node.js no terminal.
goto :fail
:fail
echo.
echo ERRO na instalacao. A janela nao sera fechada automaticamente.
pause
exit /b 1
