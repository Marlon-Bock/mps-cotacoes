<?php
/**
 * Acesso dos endpoints/telas da API das seguradoras.
 *
 * Mesma regra do resto do sistema (cotacao_pode_acessar): admin MPS vê
 * tudo; imobiliária só as próprias fichas; operador comum (sem imob_admin)
 * só as que ele criou, e com as permissões exigidas pela ação.
 */

/**
 * Ficha (linha de seguro_fianca) autorizada pro usuário, ou encerra a
 * requisição com 404/403 — em JSON, ou texto puro quando $texto (endpoint
 * de PDF, que o JS lê como texto).
 *
 * @param string[] $perms Permissões exigidas do operador da imobiliária.
 */
function api_ficha_autorizada(object $user, string $protocolo, array $perms = ['perm_fianca'], bool $texto = false): array
{
    $falhar = static function (int $code, string $mensagem) use ($texto): void {
        if ($texto) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response($mensagem, $code, ['Content-Type' => 'text/plain; charset=utf-8'])
            );
        }
        json_response(['success' => false, 'message' => $mensagem], $code);
    };

    $resolvido = $protocolo !== '' ? cotacao_resolver($protocolo) : null;
    if (!$resolvido || !in_array($resolvido['tipo'], ['fianca', 'residencial'], true)) {
        $falhar(404, 'Cotação não encontrada.');
    }
    // Residencial: a permissão do produto é perm_seguro_residencial (no lugar de perm_fianca).
    if ($resolvido['tipo'] === 'residencial') {
        $perms = array_map(fn($p) => $p === 'perm_fianca' ? 'perm_seguro_residencial' : $p, $perms);
    }
    if (!cotacao_pode_acessar($resolvido, $user)) {
        $falhar(403, 'Sem acesso a essa cotação.');
    }
    if (($user->role ?? '') === 'imobiliaria' && empty($user->imob_admin)) {
        foreach ($perms as $perm) {
            if (($user->{$perm} ?? 'N') !== 'S') {
                $falhar(403, 'Você não tem permissão para essa ação.');
            }
        }
    }

    return $resolvido['row'] + ['produto' => $resolvido['tipo']];
}

/** Seguradora válida (slug do registro) ou encerra com 404 JSON. */
function api_seguradora_ou_404(string $slug): string
{
    if (api_seguradora($slug) === null) {
        json_response(['success' => false, 'message' => 'Seguradora não encontrada.'], 404);
    }
    return $slug;
}

/** Ficha encerrada (desistência, recusada pela MPS, concluída) não aceita mais ação na API. */
function api_ficha_encerrada(array $ficha): bool
{
    return cotacao_status_encerrado((string) ($ficha['status'] ?? ''));
}
