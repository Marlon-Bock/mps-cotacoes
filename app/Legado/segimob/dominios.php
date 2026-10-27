<?php
/**
 * Domínios da Segimob — profissão, vínculo empregatício, ramo de atividade,
 * tipo de imóvel e índice de reajuste. O formulário mostra as opções da
 * tabela segimob_dominios (rápido e funciona com a Segimob fora do ar); a
 * tabela é preenchida por segimob_dominio_sincronizar_todos(), a rodar
 * quando a Segimob avisar de mudança nas listas.
 */

const SEGIMOB_TIPOS_DOMINIO = ['profissao', 'vinculo-empregaticio', 'ramo-atividade', 'tipo-imovel', 'indice-reajuste'];

/** Domínio direto da API, com cache em arquivo de 3 dias (fallback quando a tabela ainda está vazia). */
function segimob_dominio(string $tipo): array
{
    $cache = BASE_PATH . "/storage/cache/segimob_dominio_{$tipo}.json";
    $ler   = static fn(): ?array => is_file($cache) && is_array($d = json_decode((string) file_get_contents($cache), true)) ? $d : null;

    if (is_file($cache) && time() - filemtime($cache) < 3 * 86400 && ($dados = $ler()) !== null) {
        return $dados;
    }

    $r = segimob_request('GET', "/fianca/dominio/{$tipo}");
    if (!$r['ok'] || !is_array($r['data'])) {
        error_log("[segimob] Falha ao buscar domínio '{$tipo}': " . ($r['erro'] ?? ''));
        return $ler() ?? []; // sem rede/credenciais: cache antigo, se houver
    }

    @file_put_contents($cache, json_encode($r['data']));
    @chmod($cache, 0666);   // site e cron gravam o mesmo cache
    return $r['data'];
}

/** Busca um domínio na API e grava em segimob_dominios. */
function segimob_dominio_sincronizar(string $tipo): array
{
    $r = segimob_request('GET', "/fianca/dominio/{$tipo}");
    if (!$r['ok'] || !is_array($r['data'])) {
        return ['ok' => false, 'erro' => $r['erro'] ?? 'Resposta inválida da Segimob.'];
    }

    $total = 0;
    foreach ($r['data'] as $item) {
        $codigo    = (int) ($item['codigo'] ?? $item['id'] ?? 0);
        $descricao = (string) ($item['descricao'] ?? $item['nome'] ?? '');
        if (!$codigo || $descricao === '') {
            continue;
        }
        db_execute(
            'INSERT INTO segimob_dominios (tipo, codigo, descricao) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), atualizado_em = NOW()',
            ['sis', $tipo, $codigo, $descricao]
        );
        $total++;
    }

    return ['ok' => true, 'total' => $total];
}

/** Sincroniza todos os domínios usados no formulário. */
function segimob_dominio_sincronizar_todos(): array
{
    $resultado = [];
    foreach (SEGIMOB_TIPOS_DOMINIO as $tipo) {
        $resultado[$tipo] = segimob_dominio_sincronizar($tipo);
    }
    return $resultado;
}

/** Opções [codigo => descricao] de um domínio, pros <select> do formulário. */
function segimob_dominio_opcoes(string $tipo): array
{
    try {
        $linhas = db_fetch_all('SELECT codigo, descricao FROM segimob_dominios WHERE tipo = ? ORDER BY descricao ASC', ['s', $tipo]);
    } catch (Throwable $e) {
        return [];
    }
    return array_column($linhas, 'descricao', 'codigo');
}

/** Texto normalizado pra comparação (sem acento, minúsculo, espaços simples). */
function segimob_normalizar_texto(string $texto): string
{
    $texto = preg_replace('/\s+/', ' ', trim(mb_strtolower($texto)));
    $semAcento = @iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    return $semAcento !== false ? $semAcento : $texto;
}

/**
 * Código do domínio a partir do valor salvo na ficha — o código direto ou a
 * descrição (o formulário grava a descrição; ver segimob_dominio_opcoes()).
 */
function segimob_resolver_codigo(string $tipo, ?string $valor): ?int
{
    $valor = trim((string) $valor);
    if ($valor === '') {
        return null;
    }
    if (ctype_digit($valor)) {
        return (int) $valor;
    }

    $alvo = segimob_normalizar_texto($valor);

    // 1º a tabela (de onde o formulário tirou a opção); depois a API.
    foreach (segimob_dominio_opcoes($tipo) as $codigo => $descricao) {
        if (segimob_normalizar_texto((string) $descricao) === $alvo) {
            return (int) $codigo;
        }
    }
    foreach (segimob_dominio($tipo) as $item) {
        if (segimob_normalizar_texto((string) ($item['descricao'] ?? $item['nome'] ?? '')) === $alvo) {
            return (int) ($item['codigo'] ?? $item['id'] ?? 0) ?: null;
        }
    }

    error_log("[segimob] Sem correspondência no domínio '{$tipo}' para \"{$valor}\".");
    return null;
}
