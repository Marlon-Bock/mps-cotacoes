<?php
/**
 * "Corrigir dados": as seguradoras não aceitaram a ficha por um DADO (campo
 * faltando/inválido — HTTP 4xx no envio). Em vez de recusar, a imobiliária
 * corrige só os campos com problema na tela das seguradoras e a ficha é
 * reenviada (fianca-segimob-corrigir.php).
 *
 * Os campos vêm de duas fontes, juntas:
 *   - o erro da API ("Pretendentes[0].Email: ...", "Locacao.Endereco.Numero: ...");
 *   - as regras da Segimob conferidas na ficha (e-mail, celular com 11
 *     dígitos, nascimento, número só com dígitos, complemento no apartamento),
 *     pra corrigir tudo de uma vez em vez de um erro por reenvio.
 */

/** O erro do envio é de dado (corrigível pela imobiliária)? */
function segimob_erro_de_dados(?string $erro): bool
{
    return (bool) preg_match('/Segimob HTTP 4\d\d/', (string) $erro) && !str_contains((string) $erro, 'HTTP 401');
}

/**
 * Campos que a imobiliária precisa corrigir, ou null se não há o que corrigir.
 *
 * @return array{mensagem:string, campos: array<string, array{rotulo:string, tipo:string, valor:string, erro:?string}>}|null
 */
function segimob_correcoes(int $fiancaId): ?array
{
    $envio = segimob_ficha($fiancaId);
    if (!empty($envio['codigo_seguro']) || !segimob_erro_de_dados($envio['erro'] ?? null)) {
        return null;
    }
    $ficha = db_fetch_one('SELECT * FROM seguro_fianca WHERE id = ?', ['i', $fiancaId]);
    if (!$ficha) {
        return null;
    }
    $extras = db_fetch_all('SELECT * FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente', ['i', $fiancaId]);

    // Pessoas: titular = Pretendentes[0], adicionais = [1..n] na ordem do envio.
    $pessoas = [0 => ['linha' => $ficha, 'quem' => 'titular', 'nome' => 'do titular']];
    foreach ($extras as $i => $p) {
        $pessoas[$i + 1] = ['linha' => $p, 'quem' => 'pretendente' . ($i + 1), 'nome' => 'do ' . ($i + 1) . 'º pretendente adicional'];
    }

    $campos = [];
    $data   = static fn($v): string => $v ? date('d/m/Y', strtotime((string) $v)) : '';
    $marcar = static function (string $chave, string $rotulo, string $tipo, $valor, ?string $erro) use (&$campos): void {
        $campos[$chave] ??= ['rotulo' => $rotulo, 'tipo' => $tipo, 'valor' => (string) ($valor ?? ''), 'erro' => null];
        // A mensagem da API vale mais que a da regra (vem primeiro).
        if ($erro !== null && $campos[$chave]['erro'] === null) $campos[$chave]['erro'] = $erro;
    };
    $campoPessoa = static function (int $i, string $campo, ?string $erro) use ($pessoas, $marcar, $data): void {
        if (!isset($pessoas[$i])) return;
        ['linha' => $l, 'quem' => $quem, 'nome' => $de] = $pessoas[$i];
        match ($campo) {
            'email'           => $marcar("{$quem}.email", "E-mail {$de}", 'email', $l['email'] ?? '', $erro),
            'tel_celular'     => $marcar("{$quem}.tel_celular", "Celular {$de}", 'celular', $l['tel_celular'] ?? '', $erro),
            'data_nascimento' => $marcar("{$quem}.data_nascimento", "Nascimento / fundação {$de}", 'data', $data($l['data_nascimento'] ?? null), $erro),
            'nome_completo'   => $marcar("{$quem}.nome_completo", "Nome {$de}", 'texto', $l['nome_completo'] ?? '', $erro),
            default           => null,
        };
    };
    $campoImovel = static function (string $campo, ?string $erro) use ($ficha, $marcar): void {
        $rotulos = ['cep' => ['CEP do imóvel', 'cep'], 'endereco' => ['Rua / avenida', 'texto'], 'numero' => ['Número do imóvel', 'numero'],
                    'complemento' => ['Complemento', 'texto'], 'bairro' => ['Bairro', 'texto']];
        if (isset($rotulos[$campo])) {
            $marcar("imovel.{$campo}", $rotulos[$campo][0], $rotulos[$campo][1], $ficha[$campo] ?? '', $erro);
        }
    };

    // 1) O que a API apontou.
    $mapaPessoa = ['email' => 'email', 'celular' => 'tel_celular', 'datanascimentofundacao' => 'data_nascimento', 'nome' => 'nome_completo'];
    $mapaImovel = ['cep' => 'cep', 'logradouro' => 'endereco', 'numero' => 'numero', 'complemento' => 'complemento', 'bairro' => 'bairro'];
    $erroApi = preg_replace('/^.*?Segimob HTTP \d+:\s*/', '', (string) $envio['erro']);
    if (preg_match_all('/Pretendentes\[(\d+)\]\.(\w+):\s*([^;]+)/i', $erroApi, $m, PREG_SET_ORDER)) {
        foreach ($m as [, $i, $campo, $msg]) {
            if (isset($mapaPessoa[strtolower($campo)])) $campoPessoa((int) $i, $mapaPessoa[strtolower($campo)], trim($msg));
        }
    }
    if (preg_match_all('/Locacao\.Endereco\.(\w+):\s*([^;]+)/i', $erroApi, $m, PREG_SET_ORDER)) {
        foreach ($m as [, $campo, $msg]) {
            if (isset($mapaImovel[strtolower($campo)])) $campoImovel($mapaImovel[strtolower($campo)], trim($msg));
        }
    }

    // 2) As regras da Segimob conferidas na ficha.
    foreach ($pessoas as $i => ['linha' => $l]) {
        if (!filter_var((string) ($l['email'] ?? ''), FILTER_VALIDATE_EMAIL)) $campoPessoa($i, 'email', 'Obrigatório.');
        if (strlen(preg_replace('/\D/', '', (string) ($l['tel_celular'] ?? ''))) !== 11) $campoPessoa($i, 'tel_celular', 'Celular com DDD (11 dígitos).');
        if (empty($l['data_nascimento'])) $campoPessoa($i, 'data_nascimento', 'Obrigatório.');
    }
    if (!preg_match('/^\d+$/', trim((string) $ficha['numero']))) $campoImovel('numero', 'Só números (letra vai no complemento).');
    if (str_contains(mb_strtolower((string) $ficha['tipo_imovel']), 'apartamento') && trim((string) $ficha['complemento']) === '') {
        $campoImovel('complemento', 'Obrigatório para apartamento.');
    }

    // Nada que a imobiliária consiga corrigir aqui (ex: tipo de imóvel não
    // aceito pela API) — sem botão; a tela mostra o motivo.
    if ($campos === []) {
        return null;
    }

    return [
        'mensagem' => segimob_erro_amigavel((string) $envio['erro']),
        'campos'   => $campos,
    ];
}

/**
 * Aplica a correção (só os campos de segimob_correcoes) e devolve o que mudou.
 *
 * @param array<string, string> $valores chave ("titular.email", "imovel.numero"...) => valor digitado
 * @return array{ok:bool, erro?:string, mudancas?:string[]}
 */
function segimob_aplicar_correcao(int $fiancaId, array $valores): array
{
    $correcoes = segimob_correcoes($fiancaId);
    if ($correcoes === null) {
        return ['ok' => false, 'erro' => 'Não há dados para corrigir nessa cotação.'];
    }
    $extras = db_fetch_all('SELECT id FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente', ['i', $fiancaId]);

    $mudancas = [];
    foreach ($correcoes['campos'] as $chave => $campo) {
        $valor = trim((string) ($valores[$chave] ?? ''));
        // Validação por tipo — mesma regra da Segimob.
        $erro = match ($campo['tipo']) {
            'email'   => filter_var($valor, FILTER_VALIDATE_EMAIL) ? null : 'informe um e-mail válido',
            'celular' => strlen(preg_replace('/\D/', '', $valor)) === 11 ? null : 'informe o celular com DDD (11 dígitos)',
            'data'    => date_from_br($valor) ? null : 'use a data no formato DD/MM/AAAA',
            'numero'  => preg_match('/^\d+$/', $valor) ? null : 'use só números',
            'cep'     => strlen(preg_replace('/\D/', '', $valor)) === 8 ? null : 'informe o CEP com 8 dígitos',
            default   => $valor !== '' || str_ends_with($chave, '.complemento') && !str_contains((string) $campo['erro'], 'apartamento') ? null : 'preencha o campo',
        };
        if ($erro !== null) {
            return ['ok' => false, 'erro' => $campo['rotulo'] . ': ' . $erro . '.'];
        }

        $gravar = match ($campo['tipo']) {
            'data'    => date_from_br($valor),
            'celular' => preg_replace('/\D/', '', $valor),
            'cep'     => preg_replace('/\D/', '', $valor),
            default   => $valor,
        };
        [$quem, $coluna] = explode('.', $chave, 2);
        if ($quem === 'titular' || $quem === 'imovel') {
            db_execute("UPDATE seguro_fianca SET {$coluna} = ?, data_atualizacao = NOW() WHERE id = ?", ['si', $gravar, $fiancaId]);
        } elseif (preg_match('/^pretendente(\d+)$/', $quem, $m) && isset($extras[(int) $m[1] - 1])) {
            db_execute("UPDATE pretendentes SET {$coluna} = ? WHERE id = ?", ['si', $gravar, (int) $extras[(int) $m[1] - 1]['id']]);
        } else {
            continue;
        }
        if ($valor !== $campo['valor']) {
            $mudancas[] = $campo['rotulo'] . ': ' . ($campo['valor'] !== '' ? $campo['valor'] . ' → ' : '') . $valor;
        }
    }

    return ['ok' => true, 'mudancas' => $mudancas];
}
