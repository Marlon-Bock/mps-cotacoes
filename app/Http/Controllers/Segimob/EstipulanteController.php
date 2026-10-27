<?php

namespace App\Http\Controllers\Segimob;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Cadastro da imobiliária com canal Segimob: lista das imobiliárias com
 * fiança na Segimob, pra escolher o código (estipulante). Lógica em
 * app/Legado/segimob/estipulante.php.
 */
class EstipulanteController extends Controller
{
    /** GET /api/admin/imobiliarias/estipulante?imobiliaria_id= */
    public function listar(Request $request)
    {
        require_admin();

        $r = segimob_imobiliarias_fianca(max(0, (int) $request->query('imobiliaria_id', 0)));
        if (!$r['ok']) {
            json_response(['success' => false, 'message' => $r['erro']], 424);
        }

        json_response(['success' => true, 'itens' => $r['itens']]);
    }
}
