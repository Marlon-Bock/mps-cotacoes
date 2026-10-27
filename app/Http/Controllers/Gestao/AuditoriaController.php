<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Auditoria (só o suporte da Sami — require_suporte_sami): tudo o que foi
 * feito no sistema, por quem, quando, de onde e o que mudou no banco (antes e
 * depois por campo). Os registros são gravados por app/Legado/auditoria.php.
 * Filtros via AJAX (admin-auditoria.js + /api/admin/auditoria/listar).
 */
class AuditoriaController extends Controller
{
    private const POR_PAGINA = 50;

    /** Tipos de quem fez (filtro e selo). */
    public const TIPOS = [
        'admin'       => 'MPS',
        'imobiliaria' => 'Imobiliária',
        'embed'       => 'Embed (site da imobiliária)',
        'painel'      => 'Painel TV',
        'sistema'     => 'Sistema (automático)',
        'anonimo'     => 'Sem login',
    ];

    /** GET /admin/auditoria */
    public function index()
    {
        require_suporte_sami();

        $opcoes_tipo        = self::TIPOS;
        $opcoes_imobiliaria = array_column(db_fetch_all('SELECT id, nome FROM imobiliarias ORDER BY nome'), 'nome', 'id');

        $page_title      = 'Auditoria';
        $page_heading    = 'Auditoria';
        $page_subheading = 'Tudo o que foi feito no sistema — quem, quando, de onde e o que mudou';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css', 'admin-auditoria.css'];
        $extra_js        = ['admin-auditoria.js'];

        return view('gestao.auditoria.index', get_defined_vars());
    }

    /**
     * GET /api/admin/auditoria/listar
     * Filtros: de/ate (Y-m-d), quem (nome ou e-mail), tipo, imobiliaria (id),
     * tabela, registro (id do registro alterado), ip, busca (URL/ação),
     * so_alteracoes=1, senha_mestra=1, page.
     */
    public function listar(Request $request)
    {
        require_suporte_sami();

        $q     = static fn(string $k): string => trim((string) $request->query($k, ''));
        $data  = static fn(string $v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        $where = [];
        $tipos = '';
        $vals  = [];
        $add   = static function (string $cond, string $t, array $v) use (&$where, &$tipos, &$vals): void {
            $where[] = $cond;
            $tipos  .= $t;
            array_push($vals, ...$v);
        };

        if ($de = $data($q('de')))   $add('r.criado_em >= ?', 's', [$de . ' 00:00:00']);
        if ($ate = $data($q('ate'))) $add('r.criado_em <= ?', 's', [$ate . ' 23:59:59.999']);
        if (isset(self::TIPOS[$q('tipo')])) $add('r.ator_tipo = ?', 's', [$q('tipo')]);
        if ((int) $q('imobiliaria') > 0) $add('r.imobiliaria_id = ?', 'i', [(int) $q('imobiliaria')]);
        if ($q('quem') !== '') {
            $like = '%' . $q('quem') . '%';
            $add('(r.ator_nome LIKE ? OR r.ator_email LIKE ?)', 'ss', [$like, $like]);
        }
        if ($q('ip') !== '')    $add('r.ip LIKE ?', 's', [$q('ip') . '%']);
        if ($q('busca') !== '') {
            $like = '%' . $q('busca') . '%';
            $add('(r.url LIKE ? OR r.acao LIKE ? OR r.parametros LIKE ?)', 'sss', [$like, $like, $like]);
        }
        if ($q('so_alteracoes') === '1') $where[] = 'r.alteracoes > 0';
        if ($q('senha_mestra') === '1')  $where[] = 'r.via_senha_mestra = 1';
        if ($q('tabela') !== '' || $q('registro') !== '') {
            $cond = 'EXISTS (SELECT 1 FROM auditoria_alteracoes a WHERE a.requisicao_id = r.id';
            $t = '';
            $v = [];
            if ($q('tabela') !== '')   { $cond .= ' AND a.tabela = ?';      $t .= 's'; $v[] = $q('tabela'); }
            if ($q('registro') !== '') { $cond .= ' AND a.registro_id = ?'; $t .= 's'; $v[] = $q('registro'); }
            $add($cond . ')', $t, $v);
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $bind     = $tipos !== '' ? [$tipos, ...$vals] : [];
        $total    = (int) (db_fetch_one("SELECT COUNT(*) AS n FROM auditoria_requisicoes r{$sqlWhere}", $bind)['n'] ?? 0);
        $paginas  = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina   = min($paginas, max(1, (int) $q('page')));

        $registros = db_fetch_all(
            "SELECT r.*, i.nome AS imobiliaria_nome
               FROM auditoria_requisicoes r
               LEFT JOIN imobiliarias i ON i.id = r.imobiliaria_id
              {$sqlWhere}
              ORDER BY r.id DESC
              LIMIT " . self::POR_PAGINA . ' OFFSET ' . (($pagina - 1) * self::POR_PAGINA),
            $bind
        );

        // Alterações das requisições da página (de uma vez).
        $alteracoes = [];
        $ids = array_column(array_filter($registros, fn($r) => (int) $r['alteracoes'] > 0), 'id');
        if ($ids) {
            foreach (db_fetch_all(
                'SELECT * FROM auditoria_alteracoes WHERE requisicao_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id',
                [str_repeat('i', count($ids)), ...$ids]
            ) as $a) {
                $alteracoes[(int) $a['requisicao_id']][] = $a;
            }
        }

        json_response([
            'success' => true,
            'html'    => view('gestao.auditoria._linhas', [
                'registros'  => $registros,
                'alteracoes' => $alteracoes,
                'tipos'      => self::TIPOS,
            ])->render(),
            'total'   => $total,
            'pagina'  => $pagina,
            'paginas' => $paginas,
        ]);
    }
}
