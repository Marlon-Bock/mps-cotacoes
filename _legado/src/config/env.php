<?php
/**
 * Carrega o arquivo .env na raiz do projeto em $_ENV e getenv().
 * Chamado uma única vez no bootstrap.
 */
function load_env(string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException(".env não encontrado em: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // ignora comentários e linhas sem '='
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // remove aspas opcionais ao redor do valor
        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && $value[-1] === '"') ||
             ($value[0] === "'" && $value[-1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

/** Retorna variável de ambiente ou lança exceção se não encontrada. */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false) {
        if ($default === null) {
            throw new RuntimeException("Variável de ambiente obrigatória não definida: {$key}");
        }
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true'  => true,
        'false' => false,
        'null'  => null,
        ''      => $default ?? '',
        default => $value,
    };
}
