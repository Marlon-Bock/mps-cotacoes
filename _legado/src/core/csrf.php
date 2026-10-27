<?php
/**
 * Proteção CSRF via Synchronizer Token Pattern.
 * Token armazenado na sessão, enviado em campo hidden ou header X-CSRF-Token.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Renderiza o campo hidden (usar dentro de <form>). */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Valida o token vindo do POST ou do header X-CSRF-Token.
 * Lança exceção se inválido — trate no front controller.
 */
function csrf_verify(): void
{
    $token = $_POST['_csrf']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? '';

    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die(json_encode([
            'error'   => 'Token CSRF inválido.',
            // O front-end lê `message` (ver handleFormError em auth.js) — sem essa
            // chave, o erro caía no texto genérico "Verifique os dados informados.",
            // fácil de confundir com senha errada numa tela de login. Isso acontece
            // sobretudo quando a aba de login fica aberta por muito tempo (ex: PC
            // reiniciado, aba restaurada pelo navegador) e a sessão expira no
            // servidor antes do formulário ser enviado — o token ficou desatualizado.
            'message' => 'Sua sessão expirou. Atualize a página e tente novamente.',
            'session_expirada' => true,
        ]));
    }
}
