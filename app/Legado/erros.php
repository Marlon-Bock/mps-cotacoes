<?php
/**
 * Log central de erros em `sistema_erros` (tela /admin/sistema-erros) —
 * ligado ao tratamento de exceções do Laravel (bootstrap/app.php) e ao
 * handler de warnings (AppServiceProvider). Adicional ao log de arquivo;
 * nunca quebra o sistema: falha ao gravar é engolida.
 */

use Illuminate\Database\QueryException;

/** Registra um erro na tabela — nunca lança. */
function sistema_erro_registrar(
    string $tipo,
    string $mensagem,
    ?string $arquivo = null,
    ?int $linha = null,
    ?string $trace = null,
    ?string $nivel = null
): void {
    static $registrando = false;
    if ($registrando) return; // evita recursão se a própria gravação disparar outro erro
    $registrando = true;

    try {
        $usuario = null;
        try {
            $usuario = app()->runningInConsole() ? null : auth_user();
        } catch (Throwable) {
        }
        $req = app()->runningInConsole() ? null : request();

        db_execute(
            'INSERT INTO sistema_erros
                (tipo, nivel, mensagem, arquivo, linha, trace, url, metodo_http, usuario_id, usuario_tipo, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            ['ssssisssis',
             $tipo, $nivel, mb_substr($mensagem, 0, 60000), $arquivo, $linha, $trace,
             $req?->getRequestUri(), $req?->method(),
             $usuario->uid ?? null, $usuario->role ?? null]
        );
    } catch (Throwable $e) {
        error_log('[sistema_erros] Falha ao registrar (log de arquivo continua funcionando): ' . $e->getMessage());
    } finally {
        $registrando = false;
    }
}

/**
 * MySQL recusando um VALOR (texto longo demais, data/número inválido, fora da
 * faixa): mensagem dizendo ao usuário qual campo corrigir. Outro erro → null.
 */
function sistema_erro_mensagem_dado_invalido(Throwable $e): ?string
{
    if (!($e instanceof QueryException)) return null;

    // 1406 Data too long · 1292 Incorrect date/datetime value · 1366 Incorrect integer/decimal/string value · 1264 Out of range
    $codigo = (int) ($e->errorInfo[1] ?? 0);
    if (!in_array($codigo, [1406, 1292, 1366, 1264], true)) return null;

    $campo = preg_match("/column '([^']+)'/", $e->getMessage(), $m) ? str_replace('_', ' ', $m[1]) : null;
    $alvo  = $campo !== null ? "no campo \"{$campo}\"" : 'em um dos campos';

    return $codigo === 1406
        ? "O texto informado {$alvo} é maior do que o permitido. Encurte e tente novamente."
        : "O valor informado {$alvo} não está em um formato válido. Corrija e tente novamente.";
}
