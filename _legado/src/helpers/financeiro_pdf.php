<?php
/**
 * Relatório financeiro em PDF (/admin/financeiro/pdf) — FPDF (mesma lib do
 * PDF da cotação, src/lib/fpdf), A4 paisagem, cores da marca:
 *   cabeçalho (título, período, filtros) → resumo (5 números) → por
 *   seguradora → contratações (tabela com total) → rodapé com página.
 * Dados de src/helpers/api/financeiro.php (mesmos filtros da tela).
 */

require_once BASE_PATH . '/src/lib/fpdf/fpdf.php';

class FinanceiroPdf extends FPDF
{
    public const MARCA   = [122, 0, 0];   // vinho da marca
    public const TEXTO   = [31, 41, 55];
    public const SUTIL   = [107, 114, 128];
    public const LINHA   = [229, 231, 235];
    public const FUNDO   = [243, 244, 246];

    public string $subtitulo = '';

    /** UTF-8 → Windows-1252 (fontes core da FPDF). */
    public static function t($texto): string
    {
        return mb_convert_encoding((string) ($texto ?? ''), 'Windows-1252', 'UTF-8');
    }

    /** Corta o texto com "…" pra caber na largura (mm). */
    public function caber(string $texto, float $largura): string
    {
        $t = self::t($texto);
        if ($this->GetStringWidth($t) <= $largura - 2) {
            return $t;
        }
        while ($t !== '' && $this->GetStringWidth($t . '...') > $largura - 2) {
            $t = substr($t, 0, -1);
        }
        return rtrim($t) . '...';
    }

    public function Header(): void
    {
        // Faixa da marca.
        $this->SetFillColor(...self::MARCA);
        $this->Rect(0, 0, $this->GetPageWidth(), 4, 'F');

        $this->SetXY(12, 9);
        $this->SetTextColor(...self::MARCA);
        $this->SetFont('Helvetica', 'B', 15);
        $this->Cell(0, 7, self::t('Relatório financeiro'), 0, 1);
        $this->SetX(12);
        $this->SetTextColor(...self::SUTIL);
        $this->SetFont('Helvetica', '', 8.5);
        $this->Cell(0, 5, self::t($this->subtitulo), 0, 1);

        $this->SetXY(-82, 9);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(...self::TEXTO);
        $this->Cell(70, 5, self::t('Mesquita Prime & Sami Corretora'), 0, 2, 'R');
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...self::SUTIL);
        $this->Cell(70, 5, self::t('Gerado em ' . date('d/m/Y \à\s H:i')), 0, 0, 'R');

        $this->SetDrawColor(...self::LINHA);
        $this->Line(12, 24, $this->GetPageWidth() - 12, 24);
        $this->SetY(28);
    }

    public function Footer(): void
    {
        $this->SetY(-11);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...self::SUTIL);
        $this->Cell(0, 5, self::t('Comissão: Pottencial informada por ela na cotação; Too pelo percentual configurado, sobre o prêmio líquido.'), 0, 0, 'L');
        $this->Cell(0, 5, self::t('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    /** Título de seção com barrinha da marca. */
    public function secao(string $titulo): void
    {
        $this->Ln(2);
        $this->SetFillColor(...self::MARCA);
        $this->Rect(12, $this->GetY() + 1, 1.2, 4.5, 'F');
        $this->SetX(15);
        $this->SetFont('Helvetica', 'B', 10.5);
        $this->SetTextColor(...self::TEXTO);
        $this->Cell(0, 6.5, self::t($titulo), 0, 1);
        $this->Ln(1);
    }
}

/**
 * Gera o PDF e devolve o conteúdo.
 *
 * @param array $filtros api_financeiro_filtros()
 * @param array $linhas  api_financeiro_linhas()
 * @param array $totais  api_financeiro_totais()
 */
function financeiro_pdf(array $filtros, array $linhas, array $totais): string
{
    $reais = static fn($v): string => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $pct   = static fn($v): string => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',') . '%';
    $data  = static fn($iso): string => $iso ? date('d/m/Y', strtotime((string) $iso)) : '—';

    // Subtítulo: período + filtros aplicados.
    $partes = [$filtros['de'] || $filtros['ate']
        ? 'Contratações de ' . ($filtros['de'] ? $data($filtros['de']) : 'início') . ' a ' . ($filtros['ate'] ? $data($filtros['ate']) : 'hoje')
        : 'Todas as contratações'];
    if ($filtros['seguradora'] && $linhas) $partes[] = 'Seguradora: ' . $linhas[0]['seguradora'];
    if ($filtros['imobiliaria'] && $linhas) $partes[] = 'Imobiliária: ' . $linhas[0]['imobiliaria'];
    if ($filtros['situacao']) $partes[] = 'Situação: ' . API_FINANCEIRO_SITUACOES[$filtros['situacao']];
    if ($filtros['busca'] !== '') $partes[] = 'Busca: "' . $filtros['busca'] . '"';

    $pdf = new FinanceiroPdf('L', 'mm', 'A4');
    $pdf->subtitulo = 'Contratações pela API das seguradoras · ' . implode(' · ', $partes);
    $pdf->SetTitle(FinanceiroPdf::t('Relatório financeiro'));
    $pdf->SetAuthor('MPS');
    $pdf->SetMargins(12, 28, 12);
    $pdf->SetAutoPageBreak(true, 16);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $largura = $pdf->GetPageWidth() - 24;

    // ── Resumo: 5 caixas ──
    $caixas = [
        ['Contratações', (string) $totais['qtd'], ''],
        ['Prêmio total', $reais($totais['premio']), 'IOF ' . $reais($totais['iof'])],
        ['Prêmio líquido', $reais($totais['liquido']), 'Base da comissão'],
        ['Comissão', $reais($totais['comissao']), $totais['liquido'] > 0 ? 'Média ' . $pct($totais['comissao'] / $totais['liquido'] * 100) . ' do líquido' : ''],
        ['Apólices a enviar', (string) $totais['a_enviar'], $totais['em_emissao'] . ' em emissão'],
    ];
    $gap = 3;
    $w   = ($largura - $gap * 4) / 5;
    $y   = $pdf->GetY();
    foreach ($caixas as $i => [$rotulo, $valor, $detalhe]) {
        $x = 12 + $i * ($w + $gap);
        $pdf->SetDrawColor(...FinanceiroPdf::LINHA);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $w, 20, 'DF');
        $pdf->SetFillColor(...FinanceiroPdf::MARCA);
        $pdf->Rect($x, $y, 1, 20, 'F');
        $pdf->SetXY($x + 4, $y + 2.5);
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(...FinanceiroPdf::SUTIL);
        $pdf->Cell($w - 6, 4, FinanceiroPdf::t(mb_strtoupper($rotulo)), 0, 2);
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->SetTextColor(...FinanceiroPdf::TEXTO);
        $pdf->Cell($w - 6, 7, FinanceiroPdf::t($valor), 0, 2);
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(...FinanceiroPdf::SUTIL);
        $pdf->Cell($w - 6, 4, $pdf->caber($detalhe, $w - 6), 0, 2);
    }
    $pdf->SetY($y + 24);

    // Cabeçalho de tabela (fundo da marca, texto branco).
    $cabecalho = static function (array $cols) use ($pdf): void {
        $pdf->SetFillColor(...FinanceiroPdf::MARCA);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        foreach ($cols as [$rotulo, $w, $alinha]) {
            $pdf->Cell($w, 7, FinanceiroPdf::t($rotulo), 0, 0, $alinha, true);
        }
        $pdf->Ln();
    };
    $linha = static function (array $cols, array $valores, bool $zebra, bool $negrito = false) use ($pdf): void {
        $pdf->SetFillColor(...($negrito ? FinanceiroPdf::FUNDO : ($zebra ? [249, 250, 251] : [255, 255, 255])));
        $pdf->SetTextColor(...FinanceiroPdf::TEXTO);
        $pdf->SetFont('Helvetica', $negrito ? 'B' : '', 7.5);
        foreach ($cols as $i => [, $w, $alinha]) {
            $pdf->Cell($w, 6.5, $pdf->caber((string) ($valores[$i] ?? ''), $w), 0, 0, $alinha, true);
        }
        $pdf->Ln();
        $pdf->SetDrawColor(...FinanceiroPdf::LINHA);
        $pdf->Line(12, $pdf->GetY(), 12 + array_sum(array_column($cols, 1)), $pdf->GetY());
    };

    // ── Por seguradora ──
    if ($totais['por_seguradora']) {
        $pdf->secao('Por seguradora');
        $cols = [['Seguradora', 70, 'L'], ['Contratações', 35, 'C'], ['Prêmio', 45, 'R'], ['Prêmio líquido', 45, 'R'], ['Comissão', 45, 'R'], ['Participação', $largura - 240, 'R']];
        $cabecalho($cols);
        $i = 0;
        foreach ($totais['por_seguradora'] as $nome => $s) {
            $linha($cols, [
                $nome, (string) $s['qtd'], $reais($s['premio']), $reais($s['liquido']), $reais($s['comissao']),
                $totais['premio'] > 0 ? $pct($s['premio'] / $totais['premio'] * 100) : '—',
            ], (bool) ($i++ % 2));
        }
        $pdf->Ln(4);
    }

    // ── Contratações ──
    $pdf->secao('Contratações');
    if (!$linhas) {
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...FinanceiroPdf::SUTIL);
        $pdf->Cell(0, 8, FinanceiroPdf::t('Nenhuma contratação no período.'), 0, 1);
    } else {
        $cols = [
            ['Data', 17, 'C'], ['Protocolo', 25, 'L'], ['Imobiliária', 27, 'L'], ['Pretendente', 27, 'L'],
            ['Seguradora', 20, 'L'], ['Apólice', 42, 'L'], ['Prêmio', 21, 'R'], ['Pagamento', 16, 'L'],
            ['Líquido', 21, 'R'], ['Comissão', 26, 'R'], ['Situação', $largura - 242, 'C'],
        ];
        $cabecalho($cols);
        foreach ($linhas as $i => $l) {
            if ($pdf->GetY() > $pdf->GetPageHeight() - 24) {
                $pdf->AddPage();
                $cabecalho($cols);
            }
            $linha($cols, [
                $data($l['contratado_em']), $l['protocolo'], $l['imobiliaria'], $l['pretendente'], $l['seguradora'],
                $l['apolice'] ?: 'Em emissão', $reais($l['premio_total']),
                $l['forma'] . ($l['parcelas'] > 1 ? ' · ' . $l['parcelas'] . 'x' : ''),
                $reais($l['premio_liquido']), $reais($l['comissao_valor']) . ' (' . $pct($l['comissao_pct']) . ')',
                ['em_emissao' => 'Em emissão', 'a_enviar' => 'A enviar', 'enviada' => 'Enviada', 'cancelada' => 'Cancelada'][$l['situacao']],
            ], (bool) ($i % 2));
        }
        $linha($cols, ['', 'Total', $totais['qtd'] . ($totais['qtd'] === 1 ? ' contratação' : ' contratações'), '', '', '',
            $reais($totais['premio']), '', $reais($totais['liquido']), $reais($totais['comissao']), ''], false, true);
    }

    return $pdf->Output('S');
}
