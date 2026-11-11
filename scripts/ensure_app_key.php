<?php
/**
 * AURORA MULTIMARCAS - verificador de APP_KEY para ServBay / Windows.
 * Nao carrega Laravel, nao altera banco, nunca imprime chaves.
 * Uso: php scripts/ensure_app_key.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
$code = 0;

function report(string $message, int $exitCode = 0): void
{
    $stream = $exitCode ? STDERR : STDOUT;
    fwrite($stream, $message . PHP_EOL);
    if ($exitCode) {
        exit($exitCode);
    }
}

function envValue(string $contents, string $name): ?string
{
    $matches = [];
    $count = preg_match_all('/^\h*' . preg_quote($name, '/') . '\h*=\h*(.*)$/m', $contents, $matches);
    if (!$count) {
        return null;
    }
    if ($count !== 1) {
        report("ERRO: {$name} esta duplicada no .env. Corrija manualmente para evitar ambiguidades.", 2);
    }
    $value = trim($matches[1][0]);
    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
        (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
        $value = substr($value, 1, -1);
    }
    return trim($value);
}

function validKey(?string $key): bool
{
    if (!$key) {
        return false;
    }
    if (str_starts_with($key, 'base64:')) {
        $decoded = base64_decode(substr($key, 7), true);
        return $decoded !== false && strlen($decoded) === 32;
    }
    return strlen($key) === 32;
}

function writeEnv(string $envPath, string $newContents): void
{
    $backup = dirname($envPath) . DIRECTORY_SEPARATOR . '.env.antes_da_correcao_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.bak';
    if (!copy($envPath, $backup)) {
        report('ERRO: nao foi possivel criar backup do .env. Nada foi alterado.', 3);
    }
    if (file_put_contents($envPath, $newContents, LOCK_EX) === false) {
        copy($backup, $envPath);
        report('ERRO ao salvar .env. Tentamos restaurar o backup. Confira permissoes.', 4);
    }
    @chmod($envPath, 0600);
    report('Backup privado do .env criado: ' . basename($backup));
}

function setKeyInEnv(string $contents, string $key): string
{
    $pattern = '/^(\h*APP_KEY\h*=\h*).*$/m';
    if (preg_match($pattern, $contents)) {
        return preg_replace_callback($pattern, static fn(array $m): string => $m[1] . $key, $contents, 1) ?? $contents;
    }
    $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
    return rtrim($contents, "\r\n") . $eol . 'APP_KEY=' . $key . $eol;
}

$backups = [];
foreach (['.env.bak', '.env.backup', '.env.old', '.env.save'] as $name) {
    if (is_file($root . DIRECTORY_SEPARATOR . $name)) {
        $backups[] = $root . DIRECTORY_SEPARATOR . $name;
    }
}
foreach (glob($root . DIRECTORY_SEPARATOR . '.env.*.bak') ?: [] as $name) {
    $backups[] = $name;
}
foreach (glob($root . DIRECTORY_SEPARATOR . '.env.antes_da_correcao_*.bak') ?: [] as $name) {
    $backups[] = $name;
}
$backups = array_values(array_unique($backups));

if (!is_file($envPath)) {
    $recoverable = [];
    foreach ($backups as $backupPath) {
        $candidate = file_get_contents($backupPath);
        if ($candidate !== false && validKey(envValue($candidate, 'APP_KEY'))) {
            $recoverable[] = $backupPath;
        }
    }
    if (count($recoverable) === 1) {
        if (!copy($recoverable[0], $envPath)) {
            report('ERRO: nao foi possivel restaurar o .env do backup.', 3);
        }
        report('.env restaurado de ' . basename($recoverable[0]) . '; nenhum dado do MySQL foi modificado.');
    } else {
        report('ERRO: arquivo .env ausente na raiz do Laravel. Nao e seguro substitui-lo pelo .env.example, pois suas credenciais do MySQL seriam perdidas. Restaure seu .env do backup e execute novamente. Se for uma instalacao nova, copie .env.example para .env e configure o banco antes.', 2);
    }
}

$contents = file_get_contents($envPath);
if ($contents === false) {
    report('ERRO: nao foi possivel ler .env.', 3);
}
$current = envValue($contents, 'APP_KEY');
if (validKey($current)) {
    report('OK: APP_KEY valida e existente. Nenhuma chave foi modificada.');
    report('Proximo passo: php artisan optimize:clear');
    exit(0);
}
if ($current !== null && $current !== '' && $current !== '""' && $current !== "''") {
    report('ERRO: APP_KEY presente mas invalida. Por seguranca, nao sera sobrescrita. Restaure a chave correta do backup.', 2);
}

$recovered = [];
foreach ($backups as $backupPath) {
    $candidate = file_get_contents($backupPath);
    if ($candidate === false) {
        continue;
    }
    $key = envValue($candidate, 'APP_KEY');
    if (validKey($key)) {
        $recovered[$key] = basename($backupPath);
    }
}
$cache = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'config.php';
if (is_file($cache)) {
    try {
        $config = require $cache;
        $cacheKey = is_array($config) ? ($config['app']['key'] ?? null) : null;
        if (is_string($cacheKey) && validKey($cacheKey)) {
            $recovered[$cacheKey] = 'bootstrap/cache/config.php';
        }
    } catch (Throwable $exception) {
        report('Aviso: nao foi possivel ler a configuracao em cache.');
    }
}
if (count($recovered) > 1) {
    report('ERRO: foram encontradas chaves diferentes em backups. Por seguranca, escolha e restaure a chave original manualmente; nenhuma alteracao foi feita.', 2);
}
if (count($recovered) === 1) {
    $key = array_key_first($recovered);
    writeEnv($envPath, setKeyInEnv($contents, $key));
    report('APP_KEY original recuperada do backup/configuracao: ' . reset($recovered));
    report('Proximo passo: php artisan optimize:clear');
    exit(0);
}

$env = envValue($contents, 'APP_ENV') ?? 'production';
if (!in_array($env, ['local', 'testing'], true)) {
    report('ERRO: sem APP_KEY e sem backup, em ambiente nao local. Nao sera criada chave nova; restaure a chave original para evitar perder acesso a dados criptografados.', 2);
}

$key = 'base64:' . base64_encode(random_bytes(32));
writeEnv($envPath, setKeyInEnv($contents, $key));
report('APP_KEY de desenvolvimento gerada com seguranca. Chaves anteriores nao foram encontradas neste projeto.');
report('ATENCAO: se a instalacao ja tinha uma chave antiga, restaurar essa chave do backup e preferivel; cookies e outros dados previamente criptografados podem nao abrir com a nova chave.');
report('Proximo passo: php artisan optimize:clear');
