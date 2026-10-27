<?php
/**
 * Notificações internas via WhatsApp (Z-API) para o atendente responsável
 * pela cotação — nunca para o cliente/imobiliária. Sempre 1:1, disparado só
 * em eventos pontuais (nova cotação, biometria concluída, mensagem no chat).
 * Doc: https://developer.z-api.io/message/send-text
 */

/**
 * Disjuntor simples: se a Z-API falhou recentemente (indisponível, assinatura
 * expirada, etc.), para de tentar por alguns minutos em vez de continuar
 * segurando cada requisição de cotação esperando um timeout. Isso já
 * aconteceu de verdade — 3 chamadas seguidas travaram os 10s inteiros de
 * timeout, o que num servidor compartilhado pode esgotar os workers do
 * PHP-FPM e derrubar OUTROS sites no mesmo servidor com 502, não só o MPS.
 */
const ZAPI_CIRCUIT_BREAKER_MINUTOS = 5;

function zapi_circuito_aberto(): bool
{
    try {
        $ultima = db_fetch_one(
            'SELECT sucesso, criado_em FROM zapi_notificacoes ORDER BY id DESC LIMIT 1'
        );
        if (!$ultima || (int) $ultima['sucesso'] === 1) return false;

        $minutos = (time() - strtotime($ultima['criado_em'])) / 60;
        return $minutos < ZAPI_CIRCUIT_BREAKER_MINUTOS;
    } catch (Throwable $e) {
        return false; // nunca bloqueia por causa de erro no próprio disjuntor
    }
}

/**
 * Envia uma mensagem de texto simples para um número via Z-API.
 * @param string $telefone Com ou sem máscara — normalizado para DDI+DDD+número.
 */
function zapi_enviar_texto(string $telefone, string $mensagem): array
{
    $instanceId = (string) env('ZAPI_INSTANCE_ID', '');
    $token      = (string) env('ZAPI_TOKEN', '');
    if ($instanceId === '' || $token === '') {
        return ['ok' => false, 'erro' => 'Z-API não configurada (.env).'];
    }

    if (zapi_circuito_aberto()) {
        return ['ok' => false, 'erro' => 'Z-API com falha recente — pausado por ' . ZAPI_CIRCUIT_BREAKER_MINUTOS . ' min pra não travar o servidor (disjuntor).'];
    }

    $phone = preg_replace('/\D/', '', $telefone);
    if ($phone === '') {
        return ['ok' => false, 'erro' => 'Telefone do atendente não cadastrado.'];
    }
    if (strlen($phone) <= 11) {
        $phone = '55' . $phone; // sem DDI — assume Brasil
    }

    $url = "https://api.z-api.io/instances/{$instanceId}/token/{$token}/send-text";

    $headers = ['Content-Type: application/json'];
    $clientToken = (string) env('ZAPI_CLIENT_TOKEN', '');
    if ($clientToken !== '') {
        $headers[] = 'Client-Token: ' . $clientToken;
    }

    db_fechar_temporariamente(); // não segura conexão MySQL parada durante a espera da rede

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['phone' => $phone, 'message' => $mensagem]),
        CURLOPT_HTTPHEADER     => $headers,
        // Baixo de propósito — isso roda dentro do request principal da
        // cotação; nunca pode segurar o worker por muito tempo (ver disjuntor
        // acima, motivo real de já ter travado 10s inteiros 3 vezes hoje).
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 4,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'erro' => 'Falha ao conectar na Z-API' . ($curlErr ? " ({$curlErr})" : '.')];
    }
    if ($httpCode >= 400) {
        return ['ok' => false, 'erro' => "Z-API HTTP {$httpCode}: " . substr((string) $resp, 0, 300)];
    }

    return ['ok' => true];
}

/** Nome da imobiliária, para compor as mensagens de notificação. */
function zapi_imobiliaria_nome(int $imobiliariaId): string
{
    static $cache = [];
    if (!isset($cache[$imobiliariaId])) {
        $row = db_fetch_one('SELECT nome FROM imobiliarias WHERE id = ?', ['i', $imobiliariaId]);
        $cache[$imobiliariaId] = (string) ($row['nome'] ?? 'Imobiliária');
    }
    return $cache[$imobiliariaId];
}

/** Nome do atendente, para compor as mensagens de notificação (ex.: saudação). */
function zapi_atendente_nome(int $atendenteId): string
{
    static $cache = [];
    if (!isset($cache[$atendenteId])) {
        $row = db_fetch_one('SELECT username FROM usuario WHERE id = ?', ['i', $atendenteId]);
        $cache[$atendenteId] = (string) ($row['username'] ?? '');
    }
    return $cache[$atendenteId];
}

/** Registra a tentativa de envio em zapi_notificacoes — sucesso ou erro. */
function zapi_registrar_log(?int $atendenteId, ?string $telefone, string $evento, string $protocolo, string $mensagem, bool $sucesso, ?string $erro): void
{
    try {
        db_execute(
            'INSERT INTO zapi_notificacoes (atendente_id, telefone, protocolo, evento, mensagem, sucesso, erro)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            ['issssis', $atendenteId, $telefone, $protocolo ?: null, $evento, $mensagem, $sucesso ? 1 : 0, $erro]
        );
    } catch (Throwable $e) {
        error_log('[zapi] Erro ao registrar log de notificação: ' . $e->getMessage());
    }
}

/**
 * Notifica o atendente responsável por uma cotação (busca o telefone dele)
 * e registra a tentativa (sucesso ou erro) em zapi_notificacoes.
 * Nunca lança exceção — falha silenciosamente, para não travar o fluxo
 * principal que disparou a notificação.
 */
function zapi_notificar_atendente(?int $atendenteId, string $mensagem, string $evento = 'geral', string $protocolo = ''): void
{
    // Desligada enquanto não houver credencial no .env (ver FEATURE_ZAPI_NOTIFICACOES
    // no AppServiceProvider) — nem tenta enviar, nem registra no log.
    if (!defined('FEATURE_ZAPI_NOTIFICACOES') || !FEATURE_ZAPI_NOTIFICACOES) {
        return;
    }

    if (!$atendenteId) {
        zapi_registrar_log(null, null, $evento, $protocolo, $mensagem, false, 'Nenhum atendente atribuído à cotação.');
        return;
    }

    try {
        $atendente = db_fetch_one('SELECT telefone FROM usuario WHERE id = ?', ['i', $atendenteId]);
        $telefone  = trim((string) ($atendente['telefone'] ?? ''));
        if ($telefone === '') {
            zapi_registrar_log($atendenteId, null, $evento, $protocolo, $mensagem, false, 'Atendente sem telefone cadastrado.');
            return;
        }

        $r = zapi_enviar_texto($telefone, $mensagem);
        if (!$r['ok']) {
            error_log('[zapi] Falha ao notificar atendente ' . $atendenteId . ': ' . $r['erro']);
        }
        zapi_registrar_log($atendenteId, $telefone, $evento, $protocolo, $mensagem, $r['ok'], $r['ok'] ? null : $r['erro']);
    } catch (Throwable $e) {
        error_log('[zapi] Erro ao notificar atendente: ' . $e->getMessage());
        zapi_registrar_log($atendenteId, null, $evento, $protocolo, $mensagem, false, $e->getMessage());
    }
}
