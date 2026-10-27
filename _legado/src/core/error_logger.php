<?php
/**
 * Log central de erros do sistema — captura exceptions não tratadas, erros
 * PHP (warning/notice/etc.) e erros fatais, gravando em `sistema_erros` pra
 * a tela de diagnóstico em /admin/sistema-erros (só o Suporte Sami 2 vê).
 *
 * Isso é ADICIONAL ao error_log() que o código já usa em toda parte — não
 * substitui os try/catch existentes (que continuam tratando cada erro do
 * jeito específico deles), é uma rede de segurança pra pegar o que passa
 * batido, sem estourar 500 em branco sem deixar rastro.
 *
 * Nunca deve, ela mesma, quebrar o sistema: qualquer falha ao gravar no
 * banco é engolida em silêncio (só vai pro error_log de arquivo mesmo).
 */

/** Registra um erro na tabela — nunca lança, sempre engole falha própria. */
function sistema_erro_registrar(
    string $tipo,
    string $mensagem,
    ?string $arquivo = null,
    ?int $linha = null,
    ?string $trace = null,
    ?string $nivel = null
): void {
    static $registrando = false;
    if ($registrando) return; // evita recursão se o próprio db_execute() disparar outro erro
    $registrando = true;

    try {
        $usuario = function_exists('auth_user') ? @auth_user() : null;

        db_execute(
            'INSERT INTO sistema_erros
                (tipo, nivel, mensagem, arquivo, linha, trace, url, metodo_http, usuario_id, usuario_tipo, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            ['ssssisssis',
             $tipo, $nivel, mb_substr($mensagem, 0, 60000), $arquivo, $linha, $trace,
             $_SERVER['REQUEST_URI'] ?? null, $_SERVER['REQUEST_METHOD'] ?? null,
             $usuario->uid ?? null, $usuario->role ?? null]
        );
    } catch (Throwable $e) {
        error_log('[sistema_erros] Falha ao registrar (log de arquivo continua funcionando): ' . $e->getMessage());
    } finally {
        $registrando = false;
    }
}

/** Handler de exceptions não capturadas — loga e mostra uma tela genérica. */
function sistema_erro_handler_excecao(Throwable $e): void
{
    error_log('[uncaught] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    sistema_erro_registrar('exception', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString(), get_class($e));

    if (!headers_sent()) {
        // request_path() já tira o BASE_URI — com o sistema em subpasta o
        // REQUEST_URI cru começa com /<pasta>/api/ e nunca era tratado como API.
        $caminho = function_exists('request_path') ? request_path() : ($_SERVER['REQUEST_URI'] ?? '');
        $ehApi   = str_starts_with($caminho, '/api/');

        // Dado que o banco recusou (STRICT_TRANS_TABLES): é o usuário que precisa
        // corrigir o campo, não "erro interno" — responde 422 dizendo qual campo.
        $msgCampo = sistema_erro_mensagem_dado_invalido($e);
        if ($msgCampo !== null) {
            http_response_code(422);
            if ($ehApi) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'ok' => false, 'message' => $msgCampo], JSON_UNESCAPED_UNICODE);
            } else {
                echo '<h1>Dados inválidos</h1><p>' . htmlspecialchars($msgCampo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
            return;
        }

        http_response_code(500);
        if ($ehApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente em instantes.']);
        } else {
            echo '<h1>Erro interno</h1><p>Algo deu errado. Tente novamente em instantes.</p>';
        }
    }
}

/**
 * Se a exceção for o MySQL recusando um VALOR (texto longo demais, data/número
 * em formato inválido, fora da faixa), devolve uma mensagem que diz ao usuário
 * qual campo corrigir. Qualquer outro erro → null (segue como erro interno).
 */
function sistema_erro_mensagem_dado_invalido(Throwable $e): ?string
{
    if (!($e instanceof mysqli_sql_exception)) return null;

    // 1406 Data too long · 1292 Incorrect date/datetime value · 1366 Incorrect integer/decimal/string value · 1264 Out of range
    $codigo = (int) $e->getCode();
    if (!in_array($codigo, [1406, 1292, 1366, 1264], true)) return null;

    $campo = preg_match("/column '([^']+)'/", $e->getMessage(), $m) ? str_replace('_', ' ', $m[1]) : null;
    $alvo  = $campo !== null ? "no campo \"{$campo}\"" : 'em um dos campos';

    return $codigo === 1406
        ? "O texto informado {$alvo} é maior do que o permitido. Encurte e tente novamente."
        : "O valor informado {$alvo} não está em um formato válido. Corrija e tente novamente.";
}

/** Handler de erros PHP tradicionais (warning, notice, deprecated, etc.). */
function sistema_erro_handler_erro(int $nivel, string $mensagem, string $arquivo = '', int $linha = 0): bool
{
    // Respeita o error_reporting configurado — não loga o que está suprimido de propósito (@ ou nível filtrado).
    if (!(error_reporting() & $nivel)) return false;

    $niveisNome = [
        E_WARNING => 'E_WARNING', E_NOTICE => 'E_NOTICE', E_DEPRECATED => 'E_DEPRECATED',
        E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED', E_STRICT => 'E_STRICT',
    ];

    sistema_erro_registrar('error', $mensagem, $arquivo, $linha, null, $niveisNome[$nivel] ?? (string) $nivel);

    return false; // deixa o handler padrão do PHP seguir também (não engole)
}

/** Handler de erro fatal (shutdown) — pega o que nem exception nem error handler capturam. */
function sistema_erro_handler_fatal(): void
{
    $erro = error_get_last();
    if (!$erro) return;

    $fataisRelevantes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($erro['type'], $fataisRelevantes, true)) return;

    sistema_erro_registrar('fatal', $erro['message'], $erro['file'] ?? null, $erro['line'] ?? null, null, 'fatal');
}

/** Instala os 3 handlers — chamado uma vez em src/bootstrap.php. */
function sistema_erro_handlers_instalar(): void
{
    set_exception_handler('sistema_erro_handler_excecao');
    set_error_handler('sistema_erro_handler_erro');
    register_shutdown_function('sistema_erro_handler_fatal');
}
