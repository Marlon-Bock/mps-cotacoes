<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Redefinir a verificação em duas etapas (regras em app/Legado/dois_fatores.php):
 * a própria pessoa (Meu Perfil — trocar método ou e-mail) ou o admin da MPS
 * pra qualquer usuário (perdeu o celular, e-mail errado). Redefinida, a conta
 * configura de novo no próximo login.
 */
class DoisFatoresContaController extends Controller
{
    /** POST /api/perfil/2fa/redefinir — a própria conta. */
    public function redefinirPropria()
    {
        $user = require_auth();
        if (!in_array($user->role, ['admin', 'imobiliaria'], true)) {
            json_response(['success' => false, 'message' => 'Essa conta não usa verificação em duas etapas.'], 422);
        }
        df_redefinir(...df_conta_config($user->role === 'admin' ? 'usuario' : 'users', (int) $user->uid));
        json_response(['success' => true, 'message' => 'Pronto. No próximo login você escolhe de novo como receber o código.']);
    }

    /** POST /api/admin/2fa/redefinir — admin da MPS, pra um usuário da MPS ou de imobiliária. Body: { tabela, usuario_id } */
    public function redefinir(Request $request)
    {
        require_mps_admin();
        $tabela = (string) $request->input('tabela', '');
        $id     = (int) $request->input('usuario_id', 0);
        if (!in_array($tabela, ['usuario', 'users'], true) || $id <= 0
            || !db_fetch_one("SELECT id FROM {$tabela} WHERE id = ?", ['i', $id])) {
            json_response(['success' => false, 'message' => 'Usuário não encontrado.'], 404);
        }
        df_redefinir(...df_conta_config($tabela, $id));
        json_response(['success' => true, 'message' => 'Verificação redefinida. No próximo login a pessoa configura de novo.']);
    }
}
