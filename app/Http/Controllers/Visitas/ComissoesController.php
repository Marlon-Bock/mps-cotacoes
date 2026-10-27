<?php

namespace App\Http\Controllers\Visitas;

use App\Http\Controllers\Controller;

/**
 * Comissões por imobiliária. Era pages/admin/comissoes.php.
 */
class ComissoesController extends Controller
{
    /**
     * GET+POST /admin/comissoes
     * Configuração de percentuais de comissão por imobiliária e tipo de seguro.
     */
    public function pagina()
    {
        $current_user = require_mps_admin_ou_comercial();

        $imobiliarias = [];
        try {
            $imobiliarias = db_fetch_all(
                'SELECT i.id, i.nome,
                        MAX(CASE WHEN c.tipo_seguro = "fianca_residencial" THEN c.percentual END) AS pct_fianca_res,
                        MAX(CASE WHEN c.tipo_seguro = "fianca_comercial"   THEN c.percentual END) AS pct_fianca_com,
                        MAX(CASE WHEN c.tipo_seguro = "auto"               THEN c.percentual END) AS pct_auto
                 FROM imobiliarias i
                 LEFT JOIN comissoes_imobiliarias c ON c.imobiliaria_id = i.id
                 GROUP BY i.id ORDER BY i.nome ASC'
            );
        } catch (\Exception $e) {
            $imobiliarias = [];
        }

        $page_title      = 'Comissões';
        $page_heading    = 'Comissões';
        $page_subheading = 'Percentuais por imobiliária e tipo de seguro';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = [];

        return view('visitas.comissoes.pagina', get_defined_vars());
    }
}
