# Reparo de dependências PHP ausentes (vendor/autoload.php)

## Sintoma

`Warning: require(.../vendor/autoload.php): Failed to open stream` no arquivo `public/index.php`, seguido de `Fatal error: Failed opening required`.

## Diagnóstico verificado

O ZIP de código-fonte da Aurora Multimarcas foi disponibilizado sem a pasta `vendor/` e sem um `composer.lock`. Ele **não é autossuficiente para abrir direto no navegador**: precisa de instalação prévia das dependências PHP. O arquivo `public/index.php` foi escrito para carregar `vendor/autoload.php`, que é gerado pelo Composer. A falta do arquivo não significa que o MySQL ou as migrations estejam quebrados.

## Recuperação sem apagar o banco

1. Faça backup da pasta do projeto e do banco.
2. No terminal com o ambiente ServBay configurado, entre na pasta onde está o arquivo `artisan`, por exemplo:

   ```powershell
   cd "C:\Users\Estatísticas\Desktop\Projetos\aurora-multimarcas"
   composer install --no-interaction --prefer-dist
   ```

3. Confirme que existe `vendor/autoload.php`.
4. Se o `.env` e a `APP_KEY` já estiverem corretos, limpe o cache com `php artisan optimize:clear`.
5. Abra `https://aurora.test` novamente.

Alternativa: extraia `REPARAR_DEPENDENCIAS_WINDOWS.bat` na raiz do projeto e execute-o em um terminal com PHP e Composer do ServBay. O BAT confere a versão PHP, preserva o `.env`, usa o verificador de `APP_KEY` já incluído no projeto, instala com `composer install`, limpa o cache e valida o boot do Artisan. Ele **não executa migrations nem seeders**, não reseta o banco e não tenta instalar Composer via download inseguro. Requer conexão com a internet para obter bibliotecas da distribuição pública PHP/Composer.

## Se composer não for reconhecido

O ServBay inclui Composer (documentação oficial: https://support.servbay.com/pt/php/composer). Habilite PHP e Composer no ServBay, abra um terminal novo e execute `where php`, `where composer` e `composer --version`. Não use comandos que ignoram requisitos de extensão ou versão PHP.

## Limites da verificação

A existência do arquivo `vendor/autoload.php` e a inicialização do Artisan são verificações iniciais, **não garantem** que todos os módulos e integrações foram homologados no MySQL e navegador. Este script BAT foi conferido estaticamente, mas não foi executado em Windows/ServBay neste ambiente.
