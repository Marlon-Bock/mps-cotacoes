<?php

namespace App\Http\Controllers\Visitas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Horário de atendimento por produto e bloqueios temporários.
 * Era pages/admin/horarios-atendimento.php + includes/acao/admin-horario-salvar.php
 * e admin-bloqueio-*.php.
 */
class HorariosController extends Controller
{
    /**
     * GET /admin/horarios
     * Configura o horário comercial por produto (horarios_atendimento) e os
     * bloqueios temporários (bloqueios_horario, ex: equipe em reunião). Cotações
     * enviadas fora do horário ou durante um bloqueio continuam sendo recebidas
     * normalmente — o sistema só avisa a imobiliária (ver horario_atendimento_verificar()).
     */
    public function pagina()
    {
        $current_user = require_admin();

        $tipos_seguro = [
            'todos'                => 'Todos os produtos (padrão)',
            'fianca'               => 'Seguro Fiança',
            'auto'                 => 'Seguro Auto',
            'titulo_capitalizacao' => 'Título de Capitalização',
            'condominial'          => 'Seguro Condominial',
            'incendio'             => 'Seguro Incêndio',
            'residencial'          => 'Seguro Residencial',
        ];

        $dias_semana_labels = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 0 => 'Dom'];

        $horarios = [];
        try {
            foreach (db_fetch_all('SELECT * FROM horarios_atendimento') as $h) {
                $horarios[$h['tipo_seguro']] = $h;
            }
        } catch (\Throwable $e) {}

        $bloqueios = [];
        try {
            $bloqueios = db_fetch_all('SELECT * FROM bloqueios_horario ORDER BY data_inicio DESC');
        } catch (\Throwable $e) {}

        // Era a função horarioFormatarDias() declarada na página.
        $horarioFormatarDias = static function (string $diasSemana, array $labels): string {
            $dias = array_map('intval', explode(',', $diasSemana));
            return implode(', ', array_map(fn($d) => $labels[$d] ?? (string) $d, $dias));
        };

        $page_title      = 'Horário de Atendimento';
        $page_heading    = 'Horário de Atendimento';
        $page_subheading = 'Horário comercial e bloqueios temporários por produto';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css', 'admin-horarios-atendimento.css'];
        $extra_js        = ['admin-horarios-atendimento.js'];
        $main_attrs      = ' data-csrf="' . e(csrf_token()) . '"';

        return view('visitas.horarios.pagina', get_defined_vars());
    }

    /**
     * POST /api/admin/horario-atendimento/salvar
     * Upsert do horário de atendimento de um produto (uq_tipo_seguro cuida do
     * "1 linha por produto" — INSERT ... ON DUPLICATE KEY UPDATE).
     */
    public function horarioSalvar(Request $request)
    {
        $current_user = require_admin();

        $tiposValidos = ['todos', 'fianca', 'auto', 'titulo_capitalizacao', 'condominial', 'incendio', 'residencial'];

        $tipoSeguro = trim((string) $request->input('tipo_seguro', ''));
        $horaInicio = trim((string) $request->input('hora_inicio', ''));
        $horaFim    = trim((string) $request->input('hora_fim', ''));
        $diasSemana = $request->input('dias_semana') ?? [];
        $ativo      = !empty($request->input('ativo')) ? 'S' : 'N';

        if (!in_array($tipoSeguro, $tiposValidos, true)) {
            json_response(['ok' => false, 'message' => 'Produto inválido.'], 422);
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $horaInicio) || !preg_match('/^\d{2}:\d{2}$/', $horaFim)) {
            json_response(['ok' => false, 'message' => 'Informe os horários de início e fim.'], 422);
        }
        if (!is_array($diasSemana) || !$diasSemana) {
            json_response(['ok' => false, 'message' => 'Selecione ao menos um dia da semana.'], 422);
        }

        $diasSemana = array_values(array_unique(array_map('intval', $diasSemana)));
        sort($diasSemana);
        $diasSemanaStr = implode(',', $diasSemana);

        db_execute(
            'INSERT INTO horarios_atendimento (tipo_seguro, hora_inicio, hora_fim, dias_semana, ativo)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE hora_inicio = VALUES(hora_inicio), hora_fim = VALUES(hora_fim),
                                      dias_semana = VALUES(dias_semana), ativo = VALUES(ativo)',
            ['sssss', $tipoSeguro, $horaInicio . ':00', $horaFim . ':00', $diasSemanaStr, $ativo]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/admin/bloqueio-horario/salvar
     * Cria (id vazio) ou atualiza (id presente) um bloqueio temporário.
     */
    public function bloqueioSalvar(Request $request)
    {
        $current_user = require_admin();

        $tiposValidos = ['fianca', 'auto', 'titulo_capitalizacao', 'condominial', 'incendio', 'residencial'];

        $id          = (int) $request->input('id', 0);
        $titulo      = trim((string) $request->input('titulo', ''));
        $mensagem    = trim((string) $request->input('mensagem', ''));
        $dataInicioD = trim((string) $request->input('data_inicio', ''));
        $horaInicio  = trim((string) $request->input('hora_inicio', ''));
        $dataFimD    = trim((string) $request->input('data_fim', ''));
        $horaFim     = trim((string) $request->input('hora_fim', ''));
        $produtos    = $request->input('tipo_seguro') ?? [];
        $ativo       = !empty($request->input('ativo')) ? 'S' : 'N';

        if ($titulo === '' || $mensagem === '') {
            json_response(['ok' => false, 'message' => 'Preencha título e mensagem.'], 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicioD) || !preg_match('/^\d{2}:\d{2}$/', $horaInicio)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFimD) || !preg_match('/^\d{2}:\d{2}$/', $horaFim)) {
            json_response(['ok' => false, 'message' => 'Informe o período do bloqueio.'], 422);
        }
        $dataInicio = "{$dataInicioD} {$horaInicio}:00";
        $dataFim    = "{$dataFimD} {$horaFim}:00";
        if (strtotime($dataFim) <= strtotime($dataInicio)) {
            json_response(['ok' => false, 'message' => 'O fim do bloqueio precisa ser depois do início.'], 422);
        }
        if (!is_array($produtos) || !$produtos) {
            json_response(['ok' => false, 'message' => 'Selecione ao menos um produto afetado.'], 422);
        }
        $produtos = array_values(array_intersect($produtos, $tiposValidos));
        if (!$produtos) {
            json_response(['ok' => false, 'message' => 'Produto inválido.'], 422);
        }
        $produtosStr = implode(',', $produtos);

        if ($id > 0) {
            db_execute(
                'UPDATE bloqueios_horario
                    SET titulo = ?, mensagem = ?, data_inicio = ?, data_fim = ?, tipo_seguro = ?, ativo = ?
                  WHERE id = ?',
                ['ssssssi', $titulo, $mensagem, $dataInicio, $dataFim, $produtosStr, $ativo, $id]
            );
        } else {
            db_execute(
                'INSERT INTO bloqueios_horario (titulo, mensagem, data_inicio, data_fim, tipo_seguro, ativo, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                ['ssssssi', $titulo, $mensagem, $dataInicio, $dataFim, $produtosStr, $ativo, (int) ($current_user->uid ?? 0)]
            );
        }

        return response()->json(['ok' => true]);
    }

    /** POST /api/admin/bloqueio-horario/excluir */
    public function bloqueioExcluir(Request $request)
    {
        $current_user = require_admin();

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            json_response(['ok' => false, 'message' => 'Bloqueio inválido.'], 422);
        }

        db_execute('DELETE FROM bloqueios_horario WHERE id = ?', ['i', $id]);

        return response()->json(['ok' => true]);
    }
}
