<?php
/**
 * GET /api/cotacao/fianca/{protocolo}/seguradoras/{seguradora}/documento/{tipo}
 * PDF da proposta ou da apólice (proxy da seguradora, com cache local).
 * tipo: proposta | apolice.
 *
 * Erros em texto puro (o JS mostra no mpsAlert): 409 = documento ainda não
 * gerado; 424 = a seguradora falhou. Nunca 502/504 — o Cloudflare troca
 * essas respostas pela página de erro HTML dele.
 *
 * Imobiliária: só o que a MPS já enviou — a proposta (api_enviar_proposta)
 * e a apólice (api_enviar_apolice).
 */
$user = require_auth();
header('Content-Type: text/plain; charset=utf-8');

require_once BASE_PATH . '/src/helpers/api.php';

$tipo       = (string) ($params['tipo'] ?? '');
$seguradora = (string) ($params['seguradora'] ?? '');
if (!in_array($tipo, ['proposta', 'apolice'], true) || api_seguradora($seguradora) === null) {
    http_response_code(400);
    exit('Requisição inválida.');
}

$ficha = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''), ['perm_fianca'], true);
if (($user->role ?? '') === 'imobiliaria'
    && !($tipo === 'proposta' ? api_proposta_enviada($ficha) : api_apolice_enviada($ficha))) {
    http_response_code(403);
    exit(($tipo === 'proposta' ? 'A proposta' : 'A apólice') . ' ainda não foi enviada pela MPS.');
}

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
// A seguradora leva ~50s pra gerar o PDF.
set_time_limit(120);

$r = api_documento((int) $ficha['id'], $seguradora, $tipo);

if (!$r['ok']) {
    if (!empty($r['pendente'])) {
        http_response_code(409);
        exit($tipo === 'apolice'
            ? 'A seguradora ainda está gerando o documento da apólice. Use "Atualizar" e tente de novo em alguns minutos.'
            : 'A seguradora ainda está gerando o documento da proposta. Tente de novo em alguns minutos.');
    }
    error_log('[fianca-seguradoras-documento] ' . ($r['erro'] ?? 'erro desconhecido'));
    http_response_code(424);
    if (!empty($r['instavel'])) {
        exit('A seguradora está instável para gerar o PDF agora (o sistema dela não respondeu). '
            . 'Estamos tentando de novo automaticamente em segundo plano — tente baixar em alguns minutos.');
    }
    exit(($user->role ?? '') === 'admin' ? (string) ($r['erro'] ?? 'Erro ao buscar documento.') : 'A seguradora não conseguiu gerar o documento agora. Tente novamente em instantes.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $ficha['protocolo'] . '-' . $tipo . '.pdf"');
echo $r['conteudo'];
