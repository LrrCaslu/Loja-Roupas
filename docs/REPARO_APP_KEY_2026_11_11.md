# Reparação de APP_KEY no ServBay — Aurora Multimarcas

## Erro observado

`Illuminate\Encryption\MissingAppKeyException`: `No application encryption key has been specified.` em `https://aurora.test`.

## Causa e falha encontrada

O script `ATUALIZAR_SERVBAY_WINDOWS.bat` anterior verificava apenas a **existência** de `.env`, não a validade de `APP_KEY`. Por isso terminava como se o site tivesse sido verificado e podia deixar um HTTP 500. A instalação antiga verificava somente a sintaxe exata `APP_KEY=`; formas como `APP_KEY=""` eram tratadas como chave existente, apesar de vazias.

## Atualização entregue

- `scripts/ensure_app_key.php` detecta ausente, vazia, inválida ou válida, sem depender de Laravel.
- Preserva chave válida; busca chave anterior em `.env.bak`, `.env.backup`, `.env.old`, `.env.save`, `.env.*.bak` e `bootstrap/cache/config.php`.
- Bloqueia alteração quando encontra chaves distintas ou configuração invalida.
- Em modo local/teste, se nenhuma chave prévia puder ser encontrada, gera uma chave AES-256 aleatória e faz backup do `.env` antes de escrever.
- Nunca registra nem imprime o segredo.
- `REPARAR_ERRO_500_WINDOWS.bat`: executa preflight, limpa cache e confere `php artisan about --only=environment`; **não roda migrations ou seeders**.
- `ATUALIZAR_SERVBAY_WINDOWS.bat`: executa o preflight antes de migrations; não apaga dados nem recria chaves válidas.
- `INSTALAR_SERVBAY_WINDOWS.bat`: usa o mesmo verificador em vez de `findstr` para decidir se a chave está preenchida.

## Como aplicar com o mínimo de risco

1. Copie o diretório e faça backup do MySQL. Não envie `.env` ou backups com credenciais para terceiros.
2. Extraia o ZIP corretivo dentro da pasta atual que contém `artisan`.
3. Execute `REPARAR_ERRO_500_WINDOWS.bat`, preferencialmente no terminal do ServBay.
4. Abra `https://aurora.test` e teste login e navegação.
5. Se aparecer outro erro, veja `storage/logs/laravel.log`; não execute `migrate:fresh` ou `key:generate --force`.

## Limitações da validação

Foram escritos testes locais independentes do Laravel para o verificador de `APP_KEY` e executada revisão de sintaxe. A execução real em ServBay/Apache/MySQL não pode ser feita sem acesso ao ambiente Windows do usuário.
