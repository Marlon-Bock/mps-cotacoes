<?php
/**
 * Geração do PDF de cotação (fiança) — usa FPDF (vendorizada em
 * src/lib/fpdf/fpdf.php), reproduzindo fielmente a estrutura/layout do
 * gerador de PDF legado (admin/includes/acao/gerar_pdf.php do sistema
 * antigo): mesmos blocos, mesmas posições/larguras, mesma ordem — só com
 * cor da marca nos cabeçalhos de seção (era preto/branco) e todo campo
 * vazio mostrando "Não preenchido", igual à tela de detalhe.
 */


const CPDF_MARCA = [133, 0, 0]; // --color-primary-600 (#850000)

/**
 * UTF-8 → Windows-1252 (codificação das fontes core da FPDF). Substitui o
 * utf8_decode(), obsoleto no PHP 8.2 — cada chamada dele gerava um
 * "Deprecated" gravado em sistema_erros, dezenas por PDF baixado.
 */
function cpdf_latin1($texto): string
{
    return mb_convert_encoding((string) ($texto ?? ''), 'Windows-1252', 'UTF-8');
}

/** "Não preenchido" quando vazio — mesma convenção da tela de detalhe. */
function cpdf_v($valor): string
{
    $s = trim((string) ($valor ?? ''));
    return $s === '' ? 'Não preenchido' : $s;
}

function cpdf_t($valor): string
{
    return cpdf_latin1(cpdf_v($valor));
}

/** Normaliza valor monetário que pode vir decimal puro ou mascarado ("R$ 7.000,00"). */
function cpdf_money_float($valor): ?float
{
    $s = trim((string)($valor ?? ''));
    if ($s === '') return null;
    if (!is_numeric($s)) {
        $s = preg_replace('/[^\d,.-]/', '', $s);
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
    }
    return is_numeric($s) ? (float)$s : null;
}

/** Aplica máscara de CPF (11 dígitos) ou CNPJ (14 dígitos) a um valor já só com dígitos. */
function cpdf_mascara_cpf_cnpj($valor): string
{
    $digitos = preg_replace('/\D/', '', (string) ($valor ?? ''));
    if ($digitos === '') return '';
    if (strlen($digitos) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digitos);
    }
    if (strlen($digitos) === 14) {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digitos);
    }
    return (string) $valor;
}

/** Igual a cpdf_t(), mas aplicando máscara de CPF/CNPJ antes. */
function cpdf_t_cpf_cnpj($valor): string
{
    return cpdf_latin1(cpdf_v(cpdf_mascara_cpf_cnpj($valor)));
}

/** Aplica máscara de CEP (00000-000) a um valor já só com dígitos. */
function cpdf_mascara_cep($valor): string
{
    $digitos = preg_replace('/\D/', '', (string) ($valor ?? ''));
    if ($digitos === '') return '';
    if (strlen($digitos) === 8) {
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digitos);
    }
    return (string) $valor;
}

/** Igual a cpdf_t(), mas aplicando máscara de CEP antes. */
function cpdf_t_cep($valor): string
{
    return cpdf_latin1(cpdf_v(cpdf_mascara_cep($valor)));
}

/** Aplica máscara de telefone ((99) 99999-9999 ou (99) 9999-9999). */
function cpdf_mascara_telefone($valor): string
{
    $digitos = preg_replace('/\D/', '', (string) ($valor ?? ''));
    if ($digitos === '') return '';
    if (strlen($digitos) === 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digitos);
    }
    if (strlen($digitos) === 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digitos);
    }
    return (string) $valor;
}

/** Igual a cpdf_t(), mas aplicando máscara de telefone antes. */
function cpdf_t_telefone($valor): string
{
    return cpdf_latin1(cpdf_v(cpdf_mascara_telefone($valor)));
}

/** Checkbox desenhado (não depende de glyph Unicode ☒/☐, que não existe nas fontes core). */
function cpdf_checkbox(FPDF $pdf, float $x, float $y, bool $marcado, float $tamanho = 3.4): void
{
    $pdf->SetDrawColor(90, 90, 90);
    $pdf->SetLineWidth(0.25);
    $pdf->Rect($x, $y, $tamanho, $tamanho);
    if ($marcado) {
        $pdf->SetDrawColor(...CPDF_MARCA);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($x + 0.5, $y + $tamanho * 0.5, $x + $tamanho * 0.4, $y + $tamanho * 0.8);
        $pdf->Line($x + $tamanho * 0.4, $y + $tamanho * 0.8, $x + $tamanho - 0.3, $y + 0.4);
    }
    $pdf->SetDrawColor(0, 0, 0);
}

/** Checkbox + rótulo lado a lado, alinhados verticalmente na posição atual do cursor. */
function cpdf_checkbox_label(FPDF $pdf, bool $marcado, string $rotulo, float $fonte = 9): void
{
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    cpdf_checkbox($pdf, $x, $y + 0.7, $marcado);
    $pdf->SetFont('Arial', $marcado ? 'B' : '', $fonte);
    $pdf->SetXY($x + 5, $y);
    $largura = $pdf->GetStringWidth(cpdf_latin1($rotulo)) + 3;
    $pdf->Cell($largura, 5, cpdf_latin1($rotulo), 0, 0, 'L');
}

/** Barra de título de seção, na cor da marca, ocupando a largura útil (190mm). */
function cpdf_secao_barra(FPDF $pdf, string $titulo, string $alinhar = 'C', float $altura = 7): void
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(...CPDF_MARCA);
    $pdf->SetX(10);
    $pdf->Cell(190, $altura, cpdf_latin1($titulo), 1, 1, $alinhar, true);
    $pdf->SetTextColor(0, 0, 0);
}

/** Data atual por extenso, em português (ex: "26 de Maio de 2025"). */
function cpdf_data_extenso(): string
{
    $meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    return (int) date('j') . ' de ' . $meses[(int) date('n') - 1] . ' de ' . date('Y');
}

/**
 * Linha de coberturas (COBERTURAS A SEREM CONTRATADA), com nome + LMI
 * pequeno sobreposto e valor à direita — igual ao addCobertura() legado.
 */
function cpdf_addCobertura(FPDF $pdf, string $nome, string $lmi, $valor, float $largCobertura, float $largValor, float $alturaLinha): void
{
    $xInicio = 10;
    $yLinha = $pdf->GetY();

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX($xInicio);
    $pdf->Cell($largCobertura, $alturaLinha, '', 1, 0, 'L');

    $xTexto = $xInicio + 2;
    $yTexto = $yLinha + 2;
    $pdf->SetXY($xTexto, $yTexto);
    $pdf->Cell(0, 4, cpdf_latin1($nome . ':'), 0, 0, 'L');

    $pdf->SetFont('Arial', '', 6);
    $largNomeAprox = strlen($nome) * 1.7;
    $xLmi = $xTexto + $largNomeAprox + 4.5;
    $yLmi = $yLinha + 1.2;
    $pdf->SetXY($xLmi, $yLmi);
    $pdf->Cell(0, 3, cpdf_latin1($lmi), 0, 0, 'L');

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY($xInicio + $largCobertura, $yLinha);
    $temValor = $valor !== null && $valor !== '' && (float) $valor !== 0.0;
    $textoValor = $temValor ? 'R$ ' . number_format((float) $valor, 2, ',', '.') : 'R$ 0,00';
    $pdf->Cell($largValor, $alturaLinha, $textoValor, 1, 0, 'R');

    $pdf->SetY($yLinha + $alturaLinha);
}

/**
 * Monta e retorna o PDF pronto (ainda não enviado) pra cotação de fiança —
 * mesma estrutura do gerador legado, com os dados reais desta cotação.
 */
function cotacao_fianca_montar_pdf(array $cotacao, array $pretendentesAdicionais = []): FPDF
{
    $marca = CPDF_MARCA;

    $protocolo = (string) ($cotacao['protocolo'] ?? '');

    $pdf = new FPDF();
    $pdf->SetTitle('Cotação ' . $protocolo . ' - Seguro Fiança Locatícia', true);
    $pdf->SetAuthor('Mesquita Prime & Sami', true);
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);

    // Logo
    $logo = BASE_PATH . '/public/assets/img/mesquita-prime-e-sami-corretora-de-seguros-color.png';
    if (is_file($logo)) {
        $pdf->Image($logo, 10, 8, 40);
    }

    // Título principal
    $pdf->SetTextColor(...$marca);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetXY(60, 10);
    $pdf->Cell(0, 6, cpdf_latin1('DADOS DO IMÓVEL PARA A CONTRATAÇÃO'), 0, 1, 'L');
    $pdf->SetXY(60, 16);
    $pdf->Cell(0, 6, cpdf_latin1('SEGURO FIANÇA LOCATÍCIA'), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);

    // Código da ficha
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY(180, 8);
    $pdf->Cell(0, 6, 'SF-1.1.2024', 0, 1, 'R');

    // ===== BLOCO PRETENDENTE =====
    $y = 40;

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetXY(10, $y);
    $pdf->Cell(45, 5, cpdf_latin1('PRETENDENTE AO IMÓVEL:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(80, 5, cpdf_t($cotacao['nome_completo'] ?? null), 0, 0, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(25, 5, cpdf_latin1('ESTADO CIVIL:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(30, 5, cpdf_t($cotacao['estado_civil'] ?? null), 0, 1, 'L');

    $y += 7;
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetXY(10, $y);
    $pdf->Cell(18, 5, cpdf_latin1('CPF/CNPJ:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(45, 5, cpdf_t_cpf_cnpj($cotacao['cpf_cnpj'] ?? null), 0, 0, 'L');

    // CPFs dos pretendentes extras (Nº 2, 3...): em ficha comercial vão no
    // campo "SÓCIO CPF"; em ficha residencial vão em "ANÁLISE DE CPF
    // ADICIONAL" — múltiplos CPFs separados por vírgula.
    $cpfsExtras = array_filter(array_map(
        static fn($p) => cpdf_mascara_cpf_cnpj($p['cpf_cnpj'] ?? ''),
        $pretendentesAdicionais
    ));
    $cpfsExtrasTexto = implode(', ', $cpfsExtras);

    $ehComercial = strtolower((string) ($cotacao['tipo_ficha'] ?? '')) === 'comercial';
    $socioCpf       = $ehComercial ? $cpfsExtrasTexto : null;
    $cpfAdicionalTexto = $ehComercial ? null : $cpfsExtrasTexto;

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(20, 5, cpdf_latin1('SÓCIO CPF:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(100, 5, cpdf_t($socioCpf), 0, 1, 'L');

    $y += 7;
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetXY(10, $y);
    $pdf->Cell(47, 5, cpdf_latin1('ANÁLISE DE CPF ADICIONAL:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(140, 5, cpdf_t($cpfAdicionalTexto), 0, 1, 'L');

    $y += 7;
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetXY(10, $y);
    $pdf->Cell(80, 5, cpdf_latin1('CELULAR PARA CONTATO (validação de biometria):'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(60, 5, cpdf_t_telefone($cotacao['tel_celular'] ?? null), 0, 1, 'L');

    $y += 7;

    // ===== TIPO DE IMÓVEL / PERÍODO DO SEGURO =====
    $tipoFichaLower = strtolower((string) ($cotacao['tipo_ficha'] ?? ''));
    $tipoImovel = $tipoFichaLower === 'comercial' ? 'Comercial' : ($tipoFichaLower === 'residencial' ? 'Residencial' : '');
    $vigencia = $cotacao['vigencia_contrato'] ?? null;

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetXY(10, $y);
    $pdf->Cell(29, 6, cpdf_latin1('TIPO DE IMÓVEL:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(39, 6, cpdf_t($tipoImovel), 0, 0, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(8, 6, '', 0, 0);
    $pdf->Cell(38, 6, cpdf_latin1('PERÍODO DO SEGURO:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $textoVigencia = !empty($vigencia) ? $vigencia . ' meses' : '';
    $pdf->Cell(35, 6, cpdf_t($textoVigencia), 0, 0, 'L');
    $pdf->Ln(10);

    // ===== DADOS DO IMÓVEL =====
    $pdf->SetTextColor(...$marca);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 5, cpdf_latin1('DADOS DO IMÓVEL PARA LOCAÇÃO - (LOCAL DO RISCO)'), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);

    // Larguras dos valores calculadas para sempre caber "Não preenchido"
    // (~22.4mm a 9pt) sem invadir o próximo rótulo — as células originais do
    // legado (ex: Nº com 15mm, UF com 10mm) foram dimensionadas só para
    // valores curtos e estouravam com o fallback.
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(22, 5, cpdf_latin1('ENDEREÇO :'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(58, 5, cpdf_t($cotacao['endereco'] ?? null), 0, 0, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(8, 5, cpdf_latin1('Nº'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(26, 5, cpdf_t($cotacao['numero'] ?? null), 0, 0, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 5, cpdf_latin1('COMPLEMENTO:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(36, 5, cpdf_t($cotacao['complemento'] ?? null), 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(15, 5, cpdf_latin1('BAIRRO:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(42, 5, cpdf_t($cotacao['bairro'] ?? null), 0, 0, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(15, 5, cpdf_latin1('CIDADE:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(35, 5, cpdf_t($cotacao['cidade'] ?? null), 0, 0, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(7, 5, 'UF:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(26, 5, cpdf_t($cotacao['uf'] ?? null), 0, 0, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(10, 5, 'CEP:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(30, 5, cpdf_t_cep($cotacao['cep'] ?? null), 0, 1, 'L');

    $pdf->Ln(4);

    // ===== TABELA COBERTURAS + ATENÇÃO =====
    $yTab = $pdf->GetY();
    $alturaLinha = 7;
    $largCobertura = 85;
    $largValor = 35;
    $largAtencao = 70;

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(...$marca);
    $pdf->SetXY(10, $yTab);
    $pdf->Cell($largCobertura, $alturaLinha, cpdf_latin1('COBERTURAS A SEREM CONTRATADA'), 1, 0, 'C', true);
    $pdf->Cell($largValor, $alturaLinha, cpdf_latin1('VALOR MENSAL'), 1, 0, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($largAtencao, $alturaLinha, '', 0, 1, 'C');

    $coberturas = [
        ['ALUGUEL', 'LMI3', $cotacao['valor_aluguel'] ?? null],
        ['CONDOMÍNIO', 'LMI4', $cotacao['valor_condominio'] ?? null],
        ['IPTU + TCRS', 'LMI4', $cotacao['valor_iptu'] ?? null],
        ['ÁGUA/ESGOTO', 'LMI5', $cotacao['valor_agua'] ?? null],
        ['ENERGIA ELÉTRICA', 'LMI5', $cotacao['valor_energia'] ?? null],
        ['GÁS CANALIZADO', 'LMI5', $cotacao['valor_gas'] ?? null],
    ];
    foreach ($coberturas as [$nome, $lmi, $valor]) {
        cpdf_addCobertura($pdf, $nome, $lmi, $valor, $largCobertura, $largValor, $alturaLinha);
    }

    $total = 0.0;
    foreach ($coberturas as [, , $valor]) {
        $total += (float) ($valor ?? 0);
    }
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetX(10);
    $pdf->Cell($largCobertura, $alturaLinha, cpdf_latin1('TOTAL'), 1, 0, 'R');
    $pdf->SetTextColor(...$marca);
    $pdf->Cell($largValor, $alturaLinha, 'R$ ' . number_format($total, 2, ',', '.'), 1, 0, 'R');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln();

    // Coluna ATENÇÃO
    $linhasTotal = 1 + count($coberturas) + 1;
    $alturaTotalAtencao = $linhasTotal * $alturaLinha;
    $xAtencao = 10 + $largCobertura + $largValor;
    $yAtencao = $yTab;

    $pdf->SetDrawColor(...$marca);
    $pdf->SetXY($xAtencao, $yAtencao);
    $pdf->Cell($largAtencao, $alturaTotalAtencao, '', 1, 0);
    $pdf->SetDrawColor(0, 0, 0);

    $alturaTitulo = 6;
    $alturaLinhaTxt = 4;
    $alturaConteudo = $alturaTitulo + (2 * $alturaLinhaTxt);
    $yConteudo = $yAtencao + ($alturaTotalAtencao - $alturaConteudo) / 2;

    $pdf->SetTextColor(...$marca);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetXY($xAtencao, $yConteudo);
    $pdf->Cell($largAtencao, $alturaTitulo, cpdf_latin1('ATENÇÃO'), 0, 2, 'C');
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetX($xAtencao);
    $pdf->MultiCell($largAtencao, $alturaLinhaTxt, cpdf_latin1("SÓ SERÃO CONTRATADAS AS COBERTURAS\nQUE ESTIVEREM COM VALORES PREENCHIDOS"), 0, 'C');

    $pdf->SetY($yTab + $alturaTotalAtencao + 10);

    // ===== COBERTURAS ADICIONAIS =====
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(...$marca);
    $pdf->SetX(10);
    $pdf->Cell(190, 7, cpdf_latin1('COBERTURAS ADICIONAIS'), 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);

    $coberturasAdicionais = [];
    if (!empty($cotacao['danos_imovel']) && (int) $cotacao['danos_imovel'] === 1) {
        $coberturasAdicionais[] = ['texto' => 'Danos ao Imóvel', 'lmi' => 'LMI1'];
    }
    if (!empty($cotacao['multa_contratual']) && (int) $cotacao['multa_contratual'] === 1) {
        $coberturasAdicionais[] = ['texto' => 'Multa Rescisão Contratual', 'lmi' => 'LMI2'];
    }
    if (!empty($cotacao['pintura_interna']) && (int) $cotacao['pintura_interna'] === 1) {
        $coberturasAdicionais[] = ['texto' => 'Pintura Interna', 'lmi' => 'LMI2'];
    }
    if (!empty($cotacao['pintura_externa']) && (int) $cotacao['pintura_externa'] === 1) {
        $coberturasAdicionais[] = ['texto' => 'Pintura Externa', 'lmi' => 'LMI2'];
    }
    if (!empty($cotacao['cobertura_moveis']) && (int) $cotacao['cobertura_moveis'] === 1) {
        $coberturasAdicionais[] = ['texto' => 'Cobertura de Móveis', 'lmi' => 'LMI2'];
    }

    $qtdCob = count($coberturasAdicionais);
    if ($qtdCob > 0) {
        $larguraPorCobertura = 190 / $qtdCob;
        $alturaLinhaAdic = 7;
        $pdf->SetFont('Arial', '', 9);
        $startX = 10;
        $startY = $pdf->GetY();

        foreach ($coberturasAdicionais as $idx => $cob) {
            $x = $startX + $idx * $larguraPorCobertura;
            $pdf->SetXY($x, $startY);
            $pdf->Cell($larguraPorCobertura, $alturaLinhaAdic, '', 1, 0, 'L');

            // Encolhe a fonte do rótulo se necessário pra sempre caber na
            // largura da caixa (que varia conforme quantas coberturas estão
            // marcadas) + o espaço do LMI à direita — evita que rótulos
            // longos como "Multa Rescisão Contratual" invadam a caixa vizinha.
            $disponivel = $larguraPorCobertura - 4 - 6;
            $fonteTexto = 9;
            $pdf->SetFont('Arial', '', $fonteTexto);
            while ($fonteTexto > 6.5 && $pdf->GetStringWidth(cpdf_latin1($cob['texto'])) > $disponivel) {
                $fonteTexto -= 0.5;
                $pdf->SetFont('Arial', '', $fonteTexto);
            }
            $pdf->SetXY($x + 2, $startY + 1.5);
            $pdf->Cell(0, 4, cpdf_latin1($cob['texto']), 0, 0, 'L');
            $textoLargura = $pdf->GetStringWidth(cpdf_latin1($cob['texto']));

            $pdf->SetFont('Arial', '', 6);
            $xLmi = $x + 2 + $textoLargura + 1.5;
            $pdf->SetXY($xLmi, $startY + 1);
            $pdf->Cell(0, 3, cpdf_latin1($cob['lmi']), 0, 0, 'L');
        }
        $pdf->SetY($startY + $alturaLinhaAdic);
    } else {
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetX(10);
        $pdf->Cell(190, 7, cpdf_latin1('Nenhuma cobertura adicional selecionada.'), 1, 1, 'C');
    }
    $pdf->Ln(12);

    // ===== LMI - LIMITES MÁXIMOS DE INDENIZAÇÃO =====
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(...$marca);
    $pdf->SetX(10);
    $pdf->Cell(190, 7, cpdf_latin1('LMI - LIMITES MÁXIMOS DE INDENIZAÇÃO'), 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetX(10);
    $larg3 = 190 / 3;
    $pdf->Cell($larg3, 5, cpdf_latin1('LMI1 - até 6 vezes o valor do aluguel'), 1, 0, 'L');
    $pdf->Cell($larg3, 5, cpdf_latin1('LMI2 - até 3 vezes o valor do aluguel'), 1, 0, 'L');
    $pdf->Cell($larg3, 5, cpdf_latin1('LMI3 - até 30 vezes o valor do aluguel'), 1, 1, 'L');

    $pdf->SetX(10);
    $larg2 = 190 / 2;
    $pdf->Cell($larg2, 5, cpdf_latin1('LMI4 - até 30 vezes o valor declarado para o item'), 1, 0, 'L');
    $pdf->Cell($larg2, 5, cpdf_latin1('LMI5 - até 6 vezes o valor declarado para o item'), 1, 1, 'L');
    $pdf->Ln(12);

    // ===== MODALIDADE DE REAJUSTE / PROPRIETÁRIO =====
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(...$marca);
    $pdf->SetX(10);
    $pdf->Cell(95, 7, cpdf_latin1('Modalidade de índice de reajuste do contrato'), 1, 0, 'C', true);
    $pdf->Cell(95, 7, cpdf_latin1('PROPRIETÁRIO DO IMÓVEL'), 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);

    $alturaLinhaMod = 7;
    $yLinha = $pdf->GetY();

    $pdf->SetX(10);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(25, $alturaLinhaMod, cpdf_latin1('Modalidade:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(70, $alturaLinhaMod, cpdf_t($cotacao['indice_reajuste'] ?? null), 0, 0, 'L');

    $pdf->SetXY(10, $yLinha);
    $pdf->Cell(95, $alturaLinhaMod, '', 1, 0);

    $pdf->SetXY(10 + 95, $yLinha);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(12, $alturaLinhaMod / 2, cpdf_latin1('Nome:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(83, $alturaLinhaMod / 2, cpdf_t($cotacao['proprietario_imovel'] ?? null), 0, 1, 'L');

    $pdf->SetX(10 + 95);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(20, $alturaLinhaMod / 2, cpdf_latin1('CPF/CNPJ:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(75, $alturaLinhaMod / 2, cpdf_t_cpf_cnpj($cotacao['cpf_cnpj_proprietario'] ?? null), 0, 0, 'L');

    $pdf->SetXY(10 + 95, $yLinha);
    $pdf->Cell(95, $alturaLinhaMod, '', 1, 1);
    $pdf->Ln(12);

    // ===== RODAPÉ =====
    $pdf->Ln(6);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $yRodape = $pdf->GetY();

    $pdf->Cell(95, 5, cpdf_t($cotacao['imob_nome'] ?? null), 0, 0, 'C');
    $pdf->Cell(95, 5, cpdf_t($cotacao['atendente_nome'] ?? null), 0, 1, 'C');

    $pdf->SetDrawColor(...$marca);
    $pdf->Line(10, $yRodape + 5, 105, $yRodape + 5);
    $pdf->Line(105, $yRodape + 5, 200, $yRodape + 5);
    $pdf->SetDrawColor(0, 0, 0);

    $pdf->SetX(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(110, 110, 110);
    $pdf->Cell(95, 5, cpdf_latin1('Gerente/Carimbo/Nome da imobiliária'), 0, 0, 'C');
    $pdf->Cell(95, 5, cpdf_latin1('Nome atendente da locação'), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);

    $pdf->Ln(3);
    $cidadeUf = cpdf_v($cotacao['cidade'] ?? null) . '/' . cpdf_v($cotacao['uf'] ?? null);
    $dataHoje = date('d/m/Y H:i');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $yData = $pdf->GetY();
    $pdf->Cell(0, 5, cpdf_latin1($cidadeUf . ' ' . $dataHoje), 0, 1, 'L');
    $pdf->Line(10, $yData + 4.5, 80, $yData + 4.5);

    $pdf->Ln(2);
    $pdf->SetX(10);
    $pdf->MultiCell(190, 5, cpdf_latin1('OBS: Favor, assim que finalizar o seguro enviar o contrato de locação, aditamento, telefone de contato e e-mail do segurado.'), 0, 'L');

    return $pdf;
}

function cpdf_data($v): string
{
    $s = trim((string) ($v ?? ''));
    if ($s === '') return 'Não preenchido';
    $ts = strtotime($s);
    return $ts ? date('d/m/Y', $ts) : $s;
}

/** Rodapé de endereço da corretora, repetido no fim de cada bloco de página. */
function cpdf_rodape_endereco(FPDF $pdf): void
{
    $pdf->SetFont('Arial', 'I', 7.5);
    $pdf->SetTextColor(130, 130, 130);
    $pdf->SetX(10);
    $pdf->Cell(190, 5, cpdf_latin1('Av. Mauro Ramos, 1450 - Platinum Tower Loja 02 Térreo - Centro - Florianópolis - (48) 3307-8525'), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

/**
 * Monta e retorna o PDF pronto (ainda não enviado) pra cotação de título de
 * capitalização — segue a mesma estrutura do formulário físico "TC-1.0.2024"
 * (dados do imóvel, inquilino PF/PJ, endereço atual do inquilino, pessoa
 * politicamente exposta, proprietário), com assinatura em página própria.
 */
function cotacao_titulo_montar_pdf(array $cotacao): FPDF
{
    $protocolo = (string) ($cotacao['protocolo'] ?? '');
    $inquilinoTipo = strtolower(trim((string) ($cotacao['inquilino_tipo'] ?? 'pf')));

    $pdf = new FPDF();
    $pdf->SetTitle('Cotação ' . $protocolo . ' - Título de Capitalização', true);
    $pdf->SetAuthor('Mesquita Prime & Sami', true);
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);

    // Cabeçalho: código do template + logo/título centralizados
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetXY(160, 8);
    $pdf->Cell(40, 5, 'TC-1.0.2024', 0, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);

    $logo = BASE_PATH . '/public/assets/img/mesquita-prime-e-sami-corretora-de-seguros-color.png';
    if (is_file($logo)) {
        $pdf->Image($logo, 82.5, 12, 45);
    }
    $pdf->SetTextColor(...CPDF_MARCA);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetXY(10, 40);
    $pdf->Cell(190, 8, cpdf_latin1('TÍTULO DE CAPITALIZAÇÃO'), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);

    // Prazo do contrato / Finalidade
    $pdf->Ln(4);
    $finalidadeLower = strtolower((string) ($cotacao['finalidade'] ?? ''));
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $prazo = $cotacao['prazo_contrato'] ?? null;
    $textoPrazo = !empty($prazo) ? $prazo . ' meses' : 'Não preenchido';
    $pdf->Cell(65, 6, cpdf_latin1('Prazo do contrato de locação: ' . $textoPrazo), 0, 0, 'L');

    $pdf->SetX(120);
    $pdf->Cell(20, 6, cpdf_latin1('Finalidade:'), 0, 0, 'L');
    $pdf->SetX(140);
    cpdf_checkbox_label($pdf, $finalidadeLower === 'residencial', 'Residencial');
    $pdf->SetX(172);
    cpdf_checkbox_label($pdf, $finalidadeLower === 'comercial', 'Comercial');
    $pdf->Ln(10);

    // Dados para contratação
    cpdf_secao_barra($pdf, 'DADOS PARA CONTRATAÇÃO');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetX(10);
    $valorTitulo = cpdf_money_float($cotacao['valor_titulo'] ?? null);
    $valorAluguel = cpdf_money_float($cotacao['valor_aluguel'] ?? null);
    $pdf->Cell(95, 7, cpdf_latin1('Valor do título: ') . ($valorTitulo !== null ? 'R$ ' . number_format($valorTitulo, 2, ',', '.') : cpdf_latin1('Não preenchido')), 0, 0, 'L');
    $pdf->Cell(95, 7, cpdf_latin1('Valor do aluguel mensal: ') . ($valorAluguel !== null ? 'R$ ' . number_format($valorAluguel, 2, ',', '.') : cpdf_latin1('Não preenchido')), 0, 1, 'L');
    $pdf->Ln(3);

    // Endereço do imóvel a ser alugado
    cpdf_secao_barra($pdf, 'ENDEREÇO DO IMÓVEL A SER ALUGADO', 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $pdf->Cell(190, 6, cpdf_latin1('(Rua, Av, Praça): ') . cpdf_t($cotacao['imovel_logradouro'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(63, 6, cpdf_latin1('Nº ') . cpdf_t($cotacao['imovel_numero'] ?? null), 0, 0, 'L');
    $pdf->Cell(63, 6, cpdf_latin1('Complemento: ') . cpdf_t($cotacao['imovel_complemento'] ?? null), 0, 0, 'L');
    $pdf->Cell(64, 6, cpdf_latin1('Bairro ') . cpdf_t($cotacao['imovel_bairro'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(90, 6, cpdf_latin1('Cidade: ') . cpdf_t($cotacao['imovel_cidade'] ?? null), 0, 0, 'L');
    $pdf->Cell(40, 6, cpdf_latin1('UF: ') . cpdf_t($cotacao['imovel_uf'] ?? null), 0, 0, 'L');
    $pdf->Cell(60, 6, cpdf_latin1('CEP ') . cpdf_t_cep($cotacao['imovel_cep'] ?? null), 0, 1, 'L');
    $pdf->Ln(3);

    // Inquilino pessoa física
    cpdf_secao_barra($pdf, 'INQUILINO PESSOA FÍSICA', 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $pfNome = $inquilinoTipo === 'pf' ? ($cotacao['inq_nome'] ?? null) : null;
    $pdf->Cell(190, 6, cpdf_latin1('Nome: ') . cpdf_t($pfNome), 0, 1, 'L');
    $pdf->SetX(10);
    $pfDados = $inquilinoTipo === 'pf' ? $cotacao : [];
    $pdf->Cell(63, 6, cpdf_latin1('CPF: ') . cpdf_t_cpf_cnpj($pfDados['inq_cpf'] ?? null), 0, 0, 'L');
    $pdf->Cell(63, 6, cpdf_latin1('Data Nascimento: ') . cpdf_latin1(cpdf_data($pfDados['inq_data_nasc'] ?? null)), 0, 0, 'L');
    $pdf->Cell(64, 6, cpdf_latin1('Estado Civil: ') . cpdf_t($pfDados['inq_estado_civil'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(63, 6, cpdf_latin1('RG: ') . cpdf_t($pfDados['inq_rg'] ?? null), 0, 0, 'L');
    $pdf->Cell(63, 6, cpdf_latin1('Data Expedição: ') . cpdf_latin1(cpdf_data($pfDados['inq_rg_expedicao'] ?? null)), 0, 0, 'L');
    $pdf->Cell(64, 6, cpdf_latin1('Órgão Emissor: ') . cpdf_t($pfDados['inq_orgao_emissor'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(190, 6, cpdf_t($pfDados['inq_email'] ?? null), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $telsPf = trim(cpdf_v(cpdf_mascara_telefone($pfDados['inq_telefone1'] ?? null)) . (!empty($pfDados['inq_telefone2']) ? ' ou ' . cpdf_mascara_telefone($pfDados['inq_telefone2']) : ''));
    $pdf->Cell(110, 6, cpdf_latin1('Profissão: ') . cpdf_t($pfDados['inq_profissao'] ?? null), 0, 0, 'L');
    $pdf->Cell(80, 6, cpdf_latin1('Telefones: ') . cpdf_latin1($telsPf), 0, 1, 'L');
    $pdf->Ln(3);

    // Endereço atual do inquilino
    cpdf_secao_barra($pdf, 'ENDEREÇO ATUAL DO INQUILINO', 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $pdf->Cell(190, 6, cpdf_latin1('(Rua, Av, Praça) ') . cpdf_t($pfDados['inq_logradouro'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(63, 6, cpdf_latin1('Nº ') . cpdf_t($pfDados['inq_numero'] ?? null), 0, 0, 'L');
    $pdf->Cell(63, 6, cpdf_latin1('Complemento ') . cpdf_t($pfDados['inq_complemento'] ?? null), 0, 0, 'L');
    $pdf->Cell(64, 6, cpdf_latin1('Bairro: ') . cpdf_t($pfDados['inq_bairro'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(90, 6, cpdf_latin1('Cidade: ') . cpdf_t($pfDados['inq_cidade'] ?? null), 0, 0, 'L');
    $pdf->Cell(40, 6, cpdf_latin1('UF: ') . cpdf_t($pfDados['inq_uf'] ?? null), 0, 0, 'L');
    $pdf->Cell(60, 6, cpdf_latin1('CEP ') . cpdf_t_cep($pfDados['inq_cep'] ?? null), 0, 1, 'L');
    $pdf->Ln(3);

    // Inquilino pessoa jurídica
    cpdf_secao_barra($pdf, 'INQUILINO PESSOA JURÍDICA', 'L');
    $pdf->SetFont('Arial', 'I', 7.5);
    $pdf->SetX(10);
    $pdf->Cell(190, 5, cpdf_latin1('Preencher conforme site do GOV BR (Consulte CNPJ)'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pjDados = $inquilinoTipo === 'pj' ? $cotacao : [];
    $pdf->SetX(10);
    $pdf->Cell(190, 6, cpdf_latin1('Razão Social do Inquilino: ') . cpdf_t($pjDados['inq_razao_social'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(95, 6, cpdf_latin1('CNPJ: ') . cpdf_t_cpf_cnpj($pjDados['inq_cnpj'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(95, 6, cpdf_latin1('Representante Legal: ') . cpdf_t($pjDados['inq_representante'] ?? null), 0, 0, 'L');
    $pdf->Cell(95, 6, cpdf_latin1('CPF: ') . cpdf_t_cpf_cnpj($pjDados['inq_cpf_representante'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(95, 6, cpdf_latin1('E-mail: ') . cpdf_t($pjDados['inq_pj_email'] ?? null), 0, 0, 'L');
    $pdf->Cell(95, 6, cpdf_latin1('Telefones: ') . cpdf_t_telefone($pjDados['inq_pj_telefone'] ?? null), 0, 1, 'L');
    $pdf->Ln(3);

    // Pessoa politicamente exposta
    $polExposta = strtolower(trim((string) ($cotacao['pol_exposta'] ?? '')));
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $pdf->Cell(70, 6, cpdf_latin1('É uma pessoa politicamente exposta:'), 0, 0, 'L');
    $pdf->SetX(90);
    cpdf_checkbox_label($pdf, $polExposta === 'não' || $polExposta === 'nao', 'Não');
    $pdf->SetX(112);
    cpdf_checkbox_label($pdf, $polExposta === 'sim', 'Sim, Função: ' . cpdf_v($cotacao['pol_exposta_funcao'] ?? null));
    $pdf->Ln(8);

    // Proprietário do imóvel
    cpdf_secao_barra($pdf, 'PROPRIETÁRIO DO IMÓVEL', 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $pdf->Cell(190, 6, cpdf_latin1('Nome ') . cpdf_t($cotacao['prop_nome'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(63, 6, cpdf_latin1('CPF: ') . cpdf_t_cpf_cnpj($cotacao['prop_cpf'] ?? null), 0, 0, 'L');
    $pdf->Cell(63, 6, cpdf_latin1('Data Nascimento: ') . cpdf_latin1(cpdf_data($cotacao['prop_data_nasc'] ?? null)), 0, 0, 'L');
    $pdf->Cell(64, 6, cpdf_latin1('Estado Civil: ') . cpdf_t($cotacao['prop_estado_civil'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(63, 6, cpdf_latin1('RG: ') . cpdf_t($cotacao['prop_rg'] ?? null), 0, 0, 'L');
    $pdf->Cell(63, 6, cpdf_latin1('Data Expedição: ') . cpdf_latin1(cpdf_data($cotacao['prop_rg_expedicao'] ?? null)), 0, 0, 'L');
    $pdf->Cell(64, 6, cpdf_latin1('Órgão Emissor: ') . cpdf_t($cotacao['prop_orgao_emissor'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $telsProp = trim(cpdf_v(cpdf_mascara_telefone($cotacao['prop_telefone1'] ?? null)) . (!empty($cotacao['prop_telefone2']) ? ' ou ' . cpdf_mascara_telefone($cotacao['prop_telefone2']) : ''));
    $pdf->Cell(95, 6, cpdf_latin1('Profissão: ') . cpdf_t($cotacao['prop_profissao'] ?? null), 0, 0, 'L');
    $pdf->Cell(95, 6, cpdf_latin1('Telefones: ') . cpdf_latin1($telsProp), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(190, 6, cpdf_latin1('E-mail: ') . cpdf_t($cotacao['prop_email'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(95, 6, cpdf_latin1('CNPJ: ') . cpdf_t_cpf_cnpj($cotacao['prop_cnpj'] ?? null), 0, 0, 'L');
    $pdf->Cell(95, 6, cpdf_latin1('Nome do Sócio: ') . cpdf_t($cotacao['prop_nome_socio'] ?? null), 0, 1, 'L');
    $pdf->Ln(4);

    cpdf_rodape_endereco($pdf);

    // Página de assinatura
    $pdf->AddPage();
    $pdf->Ln(10);
    $yAss = $pdf->GetY();
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Line(15, $yAss, 90, $yAss);
    $pdf->Line(115, $yAss, 190, $yAss);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY(15, $yAss + 1.5);
    $pdf->Cell(75, 5, cpdf_latin1('Assinatura (Carimbo ou Nome da Imobiliária)'), 0, 0, 'C');
    $pdf->SetXY(115, $yAss + 1.5);
    $pdf->Cell(75, 5, cpdf_latin1('Nome legível do(a) atendente de locação'), 0, 0, 'C');

    $pdf->Ln(16);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetX(10);
    $cidadeExtenso = cpdf_v($cotacao['imovel_cidade'] ?? null);
    $pdf->Cell(190, 6, cpdf_latin1($cidadeExtenso . ', ' . cpdf_data_extenso() . '.'), 0, 1, 'C');

    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetX(10);
    $pdf->MultiCell(190, 4.5, cpdf_latin1('OBS.: Favor após efetuar o contrato do título, enviar o CONTRATO DE LOCAÇÃO. Além disso, assinar o título conforme seus respectivos documentos pessoais, sendo que, facilita o resgate, sinistro ou qualquer outra solicitação a corretora.'), 0, 'J');

    cpdf_rodape_endereco($pdf);

    return $pdf;
}

/** Linha "Rótulo: [ ]Não [ ]Sim" (ou variação com texto extra após o Sim). */
function cpdf_sim_nao_linha(FPDF $pdf, string $rotulo, $valor, float $xNao, float $xSim, ?string $extraSim = null): void
{
    $v = strtolower(trim((string) ($valor ?? '')));
    $ehSim = $v === 'sim';
    $ehNao = $v === 'não' || $v === 'nao';
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($xNao - $pdf->GetX(), 6, cpdf_latin1($rotulo), 0, 0, 'L');
    $pdf->SetX($xNao);
    cpdf_checkbox_label($pdf, $ehNao, 'Não');
    $pdf->SetX($xSim);
    cpdf_checkbox_label($pdf, $ehSim, $ehSim && $extraSim ? 'Sim, ' . $extraSim : 'Sim');
}

/**
 * Linha de cobertura da lista "Favor informar as coberturas e valores para
 * contratação": checkbox marcado + valor quando contratada, checkbox vazio
 * (sem repetir "Não preenchido" em toda linha) quando não.
 */
function cpdf_cobertura_linha(FPDF $pdf, float $x, float $y, $valor, string $label, float $altura): void
{
    $marcado = !empty($valor) && (float) $valor > 0;
    cpdf_checkbox($pdf, $x, $y + 0.9, $marcado, 3);
    $pdf->SetXY($x + 4.5, $y);
    $pdf->SetFont('Arial', $marcado ? 'B' : '', 8.5);
    $texto = $marcado ? ('R$ ' . number_format((float) $valor, 2, ',', '.') . '  ' . $label) : $label;
    $pdf->Cell(90, $altura, cpdf_latin1($texto), 0, 0, 'L');
}

/**
 * Monta e retorna o PDF pronto (ainda não enviado) pra cotação de seguro
 * condominial — segue a mesma estrutura do formulário físico (dados do
 * condomínio, coberturas com valores, dados do síndico).
 */
function cotacao_condominial_montar_pdf(array $cotacao): FPDF
{
    $protocolo = (string) ($cotacao['protocolo'] ?? '');

    $pdf = new FPDF();
    $pdf->SetTitle('Cotação ' . $protocolo . ' - Seguro Condomínio', true);
    $pdf->SetAuthor('Mesquita Prime & Sami', true);
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);

    // Cabeçalho: código do template + logo/título centralizados
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetXY(160, 8);
    $pdf->Cell(40, 5, 'SC-1.0.2024', 0, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);

    $logo = BASE_PATH . '/public/assets/img/mesquita-prime-e-sami-corretora-de-seguros-color.png';
    if (is_file($logo)) {
        $pdf->Image($logo, 82.5, 12, 45);
    }
    $pdf->SetTextColor(...CPDF_MARCA);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetXY(10, 40);
    $pdf->Cell(190, 8, cpdf_latin1('SEGURO CONDOMÍNIO'), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);

    // Tipo de condomínio
    $pdf->Ln(4);
    $tipoLower = strtolower(trim((string) ($cotacao['tipo_condominio'] ?? '')));
    $pdf->SetX(10);
    cpdf_checkbox_label($pdf, $tipoLower === 'residencial', 'Residencial');
    $pdf->SetX(55);
    cpdf_checkbox_label($pdf, $tipoLower === 'comercial', 'Comercial');
    $pdf->SetX(100);
    cpdf_checkbox_label($pdf, str_contains($tipoLower, 'escritório') || str_contains($tipoLower, 'escritorio'), 'Escritório/Consultório');
    $pdf->SetX(155);
    cpdf_checkbox_label($pdf, str_contains($tipoLower, 'comércio no térreo') || str_contains($tipoLower, 'comercio no terreo'), 'Com Comércio no Térreo');
    $pdf->Ln(10);

    // Dados do condomínio
    cpdf_secao_barra($pdf, 'DADOS DO CONDOMÍNIO', 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $pdf->Cell(130, 6, cpdf_latin1('Nome: ') . cpdf_t($cotacao['imovel_nome'] ?? null), 0, 0, 'L');
    $pdf->Cell(60, 6, cpdf_latin1('CNPJ: ') . cpdf_t_cpf_cnpj($cotacao['imovel_cnpj'] ?? null), 0, 1, 'L');

    $pdf->SetX(10);
    $pdf->Cell(130, 6, cpdf_latin1('Endereço: ') . cpdf_t($cotacao['imovel_endereco'] ?? null), 0, 0, 'L');
    $pdf->Cell(60, 6, cpdf_latin1('Número: ') . cpdf_t($cotacao['imovel_numero'] ?? null), 0, 1, 'L');

    $pdf->SetX(10);
    $pdf->Cell(63, 6, cpdf_latin1('Complemento: ') . cpdf_t($cotacao['imovel_complemento'] ?? null), 0, 0, 'L');
    $pdf->Cell(63, 6, cpdf_latin1('Bairro: ') . cpdf_t($cotacao['imovel_bairro'] ?? null), 0, 0, 'L');
    $pdf->Cell(64, 6, cpdf_latin1('CEP: ') . cpdf_t_cep($cotacao['imovel_cep'] ?? null), 0, 1, 'L');

    $pdf->SetX(10);
    $cidadeUf = trim(cpdf_v($cotacao['imovel_cidade'] ?? null) . (!empty($cotacao['imovel_uf']) ? '/' . $cotacao['imovel_uf'] : ''));
    $pdf->Cell(130, 6, cpdf_latin1('Cidade/UF: ') . cpdf_latin1($cidadeUf), 0, 0, 'L');
    $pdf->Cell(60, 6, cpdf_latin1('Ano de Construção: ') . cpdf_t($cotacao['imovel_ano_construcao'] ?? null), 0, 1, 'L');

    $pdf->SetX(10);
    $pdf->Cell(48, 6, cpdf_latin1('Blocos: ') . cpdf_t($cotacao['imovel_blocos'] ?? null), 0, 0, 'L');
    $pdf->Cell(48, 6, cpdf_latin1('Andares: ') . cpdf_t($cotacao['imovel_andares'] ?? null), 0, 0, 'L');
    $pdf->Cell(47, 6, cpdf_latin1('Elevadores: ') . cpdf_t($cotacao['imovel_elevadores'] ?? null), 0, 0, 'L');
    $areaTxt = !empty($cotacao['imovel_area_m2']) ? $cotacao['imovel_area_m2'] . ' m²' : 'Não preenchido';
    $pdf->Cell(47, 6, cpdf_latin1('Área: ' . $areaTxt), 0, 1, 'L');

    $pdf->SetX(10);
    cpdf_sim_nao_linha($pdf, 'Alarme:', $cotacao['tem_alarme'] ?? null, 30, 55);
    $pdf->SetX(100);
    $qtdFunc = !empty($cotacao['qtd_funcionarios']) ? $cotacao['qtd_funcionarios'] . ' vidas' : null;
    cpdf_sim_nao_linha($pdf, 'Funcionários Registrados:', $cotacao['tem_funcionarios'] ?? null, 148, 168, $qtdFunc ?? 'Não preenchido');
    $pdf->Ln(7);

    $pdf->SetX(10);
    cpdf_sim_nao_linha($pdf, 'Câmeras:', $cotacao['tem_cameras'] ?? null, 30, 55);
    $pdf->SetX(100);
    cpdf_sim_nao_linha($pdf, 'Central telefônica:', $cotacao['tem_central_telefonica'] ?? null, 148, 168);
    $pdf->Ln(7);

    $pdf->SetX(10);
    cpdf_sim_nao_linha($pdf, 'Seguro:', $cotacao['tem_seguro_atual'] ?? null, 30, 55, 'na Seguradora: ' . cpdf_v($cotacao['seguro_atual_seguradora'] ?? null));
    $pdf->SetX(144);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(24, 6, cpdf_latin1('Classe Bônus:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(22, 6, cpdf_t($cotacao['seguro_atual_classe_bonus'] ?? null), 0, 1, 'L');
    $pdf->Ln(3);

    // Coberturas e valores
    cpdf_secao_barra($pdf, 'Favor informar as coberturas e valores para contratação', 'C', 6);
    $coberturaColunaEsquerda = [
        'cob_incendio' => 'Incêndio/Raio/Explos./Fumaça',
        'cob_vazamento' => 'Vazamento Tub. Água e Esgoto',
        'cob_roubo_cond' => 'Roubo bens Condomínio',
        'cob_perda_aluguel' => 'Perda/Pagamento aluguel',
        'cob_roubo_condominios' => 'Roubo bens dos Condôminos',
        'cob_incendio_condominios' => 'Incêndio bens dos Condôminos',
        'cob_equipamentos_eletronicos' => 'Equipamentos Eletrônicos',
        'cob_impacto_veiculos' => 'Impacto de Veículos',
        'cob_quebra_vidros' => 'Quebra de vidros',
        'cob_rc_garagista_compreensiva' => 'RC Garagista Compreensiva',
        'cob_rc_garagista_exclusiva' => 'RC Garagista Exclusiva',
        'cob_vida_grupo' => 'Vida em Grupo Funcionários',
        'cob_chuveiros' => 'Chuveiros / Sprinkler',
    ];
    $coberturaColunaDireita = [
        'cob_danos_eletricos' => 'Danos elétricos',
        'cob_rc_sindico' => 'RC Síndico',
        'cob_rc_condominio' => 'RC Condomínio',
        'cob_rc_danos_morais' => 'RC Danos Morais',
        'cob_rc_empregador' => 'RC Empregador',
        'cob_rc_portoes' => 'RC Portões',
        'cob_vendaval' => 'Vendaval',
        'cob_tumulto_greve' => 'Tumulto e Greve',
        'cob_despesas_fixas' => 'Despesas fixas',
        'cob_lucros_cessantes' => 'Lucros cessantes',
        'cob_desmoronamento' => 'Desmoronamento',
        'cob_alagamento' => 'Alagamento',
        'cob_placas_solares' => 'Placas Solares',
    ];
    $yColunas = $pdf->GetY();
    $linhaAltura = 5.3;
    foreach (array_values($coberturaColunaEsquerda) as $i => $label) {
        $chave = array_keys($coberturaColunaEsquerda)[$i];
        cpdf_cobertura_linha($pdf, 10, $yColunas + $i * $linhaAltura, $cotacao[$chave] ?? null, $label, $linhaAltura);
    }
    foreach (array_values($coberturaColunaDireita) as $i => $label) {
        $chave = array_keys($coberturaColunaDireita)[$i];
        cpdf_cobertura_linha($pdf, 105, $yColunas + $i * $linhaAltura, $cotacao[$chave] ?? null, $label, $linhaAltura);
    }
    $pdf->SetY($yColunas + count($coberturaColunaEsquerda) * $linhaAltura + 4);

    // Dados do síndico
    cpdf_secao_barra($pdf, 'DADOS DO SÍNDICO', 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX(10);
    $pdf->Cell(190, 6, cpdf_latin1('Nome: ') . cpdf_t($cotacao['sindico_nome'] ?? null), 0, 1, 'L');
    $pdf->SetX(10);
    $pdf->Cell(95, 6, cpdf_latin1('Telefone: ') . cpdf_t_telefone($cotacao['sindico_telefone'] ?? null), 0, 0, 'L');
    $pdf->Cell(95, 6, cpdf_latin1('e-mail: ') . cpdf_t($cotacao['sindico_email'] ?? null), 0, 1, 'L');
    $pdf->Ln(4);

    cpdf_rodape_endereco($pdf);

    return $pdf;
}
