@echo off
setlocal EnableExtensions DisableDelayedExpansion
rem Versao 2: abre CMD /K para impedir que a janela feche automaticamente.
rem O parametro __INTERNO evita um loop de reabertura.
if /I "%~1"=="__INTERNO" goto :VERIFICAR
%ComSpec% /D /K call "%~f0" __INTERNO
exit /b

:VERIFICAR
chcp 65001 >nul 2>&1
title AURORA - Diagnostico de produto e estoque - janela permanente
set "RET=1"
pushd "%~dp0"
if errorlevel 1 (
  echo [ERRO] Nao foi possivel acessar a pasta do arquivo BAT.
  goto :FIM
)
set "LOG=%CD%\VERIFICACAO_AURORA.log"
>"%LOG%" echo AURORA MULTIMARCAS - VERIFICACAO DE PRODUTO E ESTOQUE
>>"%LOG%" echo Pasta do projeto: %CD%
>>"%LOG%" echo Data e hora: %date% %time%

echo.
echo ============================================================
echo   AURORA MULTIMARCAS - VERIFICACAO SEM FECHAMENTO AUTOMATICO
echo ============================================================
echo.
echo Pasta: %CD%
echo Log: %LOG%
echo.

if not exist "artisan" (
 echo [ERRO] artisan nao encontrado. Extraia o PATCH na pasta raiz do Laravel.
 goto :FALHA
)
if not exist "vendor\autoload.php" (
 echo [ERRO] vendor\autoload.php ausente. Execute composer install na raiz.
 goto :FALHA
)
if not exist ".env" (
 echo [ERRO] Arquivo .env ausente. Restaure o .env original.
 goto :FALHA
)
where php >>"%LOG%" 2>&1
if errorlevel 1 (
 echo [ERRO] PHP nao esta disponivel neste terminal.
 echo Abra o terminal pelo ServBay ou ajuste o PATH do PHP.
 goto :FALHA
)

echo [1/6] Conferindo PHP ativo...
where php
php -v
php -r "exit(version_compare(PHP_VERSION, '8.3.0', '>=') ? 0 : 1);" >>"%LOG%" 2>&1
if errorlevel 1 (
 echo [ERRO] Este projeto requer PHP 8.3 ou mais recente.
 goto :FALHA
)

echo.
echo [2/6] Conferindo sintaxe dos arquivos PHP corrigidos...
call :LINT "app\Models\StockMovement.php"
if errorlevel 1 goto :FALHA
call :LINT "app\Http\Controllers\ShopController.php"
if errorlevel 1 goto :FALHA

echo.
echo [3/6] Limpando caches do Laravel...
call :ARTISAN optimize:clear
if errorlevel 1 goto :FALHA

echo.
echo [4/6] Compilando todas as views Blade...
call :ARTISAN view:cache
if errorlevel 1 goto :FALHA

echo.
echo [5/6] Conferindo a sintaxe das views compiladas...
set "VIEW_COUNT=0"
for /R "storage\framework\views" %%F in (*.php) do (
  call :LINT "%%~fF"
  if errorlevel 1 goto :FALHA
  set /a VIEW_COUNT+=1 >nul
)
if "%VIEW_COUNT%"=="0" (
 echo [ERRO] Nenhuma view compilada foi encontrada.
 goto :FALHA
)
echo [OK] %VIEW_COUNT% arquivos compilados foram verificados.

echo.
echo [6/6] Verificando rotas e limpando apenas o cache das views...
call :ARTISAN route:list --path=produto
if errorlevel 1 goto :FALHA
call :ARTISAN route:list --path=admin/estoque
if errorlevel 1 goto :FALHA
call :ARTISAN view:clear
if errorlevel 1 goto :FALHA

set "RET=0"
echo.
echo [SUCESSO] Todas as etapas do verificador terminaram.
echo Confira https://aurora.test/produto/camiseta-algodao-premium-12
echo e https://aurora.test/admin/estoque
goto :FIM

:LINT
if not exist "%~1" (
 echo [ERRO] Arquivo nao encontrado: %~1
 >>"%LOG%" echo Arquivo nao encontrado: %~1
 exit /b 1
)
php -l "%~1" >>"%LOG%" 2>&1
if errorlevel 1 (
 echo [ERRO] Falha de sintaxe em: %~1
 php -l "%~1"
 exit /b 1
)
exit /b 0

:ARTISAN
>>"%LOG%" echo.
>>"%LOG%" echo COMANDO: php artisan %*
php artisan %* >>"%LOG%" 2>&1
if errorlevel 1 (
 echo [ERRO] Comando falhou: php artisan %*
 echo.
 echo ===== DETALHES DO LOG =====
 type "%LOG%"
 echo ===== FIM DO LOG =====
 exit /b 1
)
echo [OK] php artisan %*
exit /b 0

:FALHA
echo.
echo [FALHOU] A verificacao parou. Veja os detalhes acima.
echo Se precisar de suporte, envie o texto de VERIFICACAO_AURORA.log.
echo Nao envie o arquivo .env nem sua APP_KEY.

:FIM
echo.
echo Nenhum banco foi apagado: nao executamos migrations nem seeders.
if defined LOG echo Arquivo de log: %LOG%
echo.
echo A JANELA PERMANECERA ABERTA. Nao e necessario pressionar tecla.
if "%RET%"=="0" (echo Status: SUCESSO) else (echo Status: FALHA)
exit /b %RET%
