@echo off
setlocal EnableExtensions DisableDelayedExpansion
chcp 65001 >nul 2>&1
title Aurora Multimarcas - Reparar dependencia vendor do Laravel
cls
echo ================================================================
echo   AURORA MULTIMARCAS - REPARO DAS DEPENDENCIAS PHP / SERVBAY
echo ================================================================
echo.
rem Entra na pasta deste BAT, mesmo em caminhos com espacos ou acentos.
pushd "%~dp0"
if errorlevel 1 goto :err_path
if not exist "artisan" goto :err_folder
if not exist "composer.json" goto :err_folder
if not exist "scripts\ensure_app_key.php" goto :err_folder

echo Projeto: %CD%
echo.
where php >nul 2>&1
if errorlevel 1 goto :err_php
echo [1/6] Conferindo versao PHP (minimo 8.3)...
php -v
php -r "exit(version_compare(PHP_VERSION, '8.3.0', '>=') ? 0 : 1);"
if errorlevel 1 goto :err_php_version

echo.
echo [2/6] Conferindo ambiente existente sem modificar banco...
if not exist ".env" goto :err_env
php "scripts\ensure_app_key.php"
if errorlevel 1 goto :err_appkey

echo.
echo [3/6] Conferindo Composer...
where composer >nul 2>&1
if errorlevel 1 goto :err_composer
call composer --version
if errorlevel 1 goto :err_composer

echo.
echo [4/6] Instalando bibliotecas PHP e gerando vendor\autoload.php...
echo Esta etapa requer internet e pode baixar varios pacotes.
call composer install --no-interaction --prefer-dist --optimize-autoloader
if errorlevel 1 goto :err_install
if not exist "vendor\autoload.php" goto :err_autoload

echo.
echo [5/6] Limpando caches de configuracao e rotas...
php artisan optimize:clear
if errorlevel 1 goto :err_artisan

echo.
echo [6/6] Verificando inicializacao do Laravel...
php artisan about --only=environment
if errorlevel 1 goto :err_artisan

echo.
echo ================================================================
echo   SUCESSO: vendor\autoload.php existe e Artisan iniciou.
echo ================================================================
echo Acesse: https://aurora.test
echo Se a pagina mostrar OUTRO erro, fotografe a nova excecao.
echo Este reparo nao apagou banco, pedidos, imagens ou usuarios.
goto :success

:err_path
echo ERRO: Nao foi possivel entrar na pasta do reparador.
goto :failure
:err_folder
echo ERRO: Copie este BAT para a raiz existente do projeto Laravel,
echo na mesma pasta dos arquivos artisan e composer.json.
goto :failure
:err_php
echo ERRO: php nao encontrado. Abra um terminal com o ambiente ServBay ativo.
goto :failure
:err_php_version
echo ERRO: PHP do terminal e anterior ao 8.3.
echo Ative no ServBay o PHP 8.3 ou mais recente e abra novo terminal.
goto :failure
:err_env
echo ERRO: .env ausente. Para proteger seu banco, nenhum .env foi criado.
echo Restaure o arquivo .env original com suas credenciais.
goto :failure
:err_appkey
echo ERRO: Verificacao do .env / APP_KEY nao passou.
echo Corrija o aviso acima sem gerar outra chave sobre a original.
goto :failure
:err_composer
echo ERRO: Composer nao encontrado ou nao executavel neste terminal.
echo O ServBay inclui Composer: habilite-o e abra um terminal novo.
echo Verifique com os comandos: where php  e  where composer
goto :failure
:err_install
echo ERRO: composer install falhou. Leia a mensagem acima.
echo Verifique conexao com a internet e extensoes PHP faltantes.
echo Nao use --ignore-platform-reqs para esconder requisitos.
goto :failure
:err_autoload
echo ERRO: Composer terminou, mas vendor\autoload.php nao foi criado.
goto :failure
:err_artisan
echo ERRO: Dependencias instaladas, mas Laravel apresentou outro erro.
echo Copie a mensagem acima; nao e necessario apagar o banco.
goto :failure
:success
popd
echo.
pause
exit /b 0
:failure
echo.
echo O reparador parou sem executar migrations ou seeders.
if exist "vendor\autoload.php" echo Confirmado: vendor\autoload.php foi criado.
popd
echo.
pause
exit /b 1
