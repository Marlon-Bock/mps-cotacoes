<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Segunda etapa do login: dois fatores (regras em app/Legado/dois_fatores.php).
 *
 * Depois da senha, o AuthController chama iniciar(): a conta fica "pendente"
 * na sessão (df_pendente — ninguém está logado ainda) por DF_PENDENTE_MINUTOS.
 *   - ainda não configurou → etapa 'configurar': escolhe e-mail (informa o
 *     e-mail dos códigos) ou Google Authenticator (QR);
 *   - já configurou → etapa 'codigo' (por e-mail, enviado na hora, ou do app).
 * verificar() confere o código, grava a configuração (no primeiro login) e
 * conclui o login (AuthController::concluirLogin).
 *
 * A conta acessada (tabela/usuario_id) pode ser diferente da conta da
 * configuração (cfg_tabela/cfg_id — df_conta_config): o suporte da Sami usa o
 * Authenticator da conta MPS também na conta de imobiliária.
 */
class DoisFatoresController extends Controller
{
    /** Tentativas de código por login (somando os reenvios). */
    private const MAX_TENTATIVAS = 10;

    /**
     * Abre a etapa dos dois fatores pra conta que acertou a senha. Com a senha
     * do dia, o código é do Google Authenticator do suporte da Sami (quem tem a
     * senha), não da conta acessada.
     */
    public static function iniciar(Request $request, string $tabela, int $usuarioId, bool $senhaMestra = false)
    {
        if ($senhaMestra) {
            session(['df_pendente' => [
                'tabela'       => $tabela,
                'usuario_id'   => $usuarioId,
                'expira'       => time() + DF_PENDENTE_MINUTOS * 60,
                'tentativas'   => 0,
                'senha_mestra' => true,
                'configurando' => false,
                'metodo'       => 'totp',
                'email'        => null,
                'segredo'      => null,
            ]]);
            return response()->json(['dois_fatores' => ['etapa' => 'codigo', 'metodo' => 'totp', 'suporte' => true]]);
        }

        // O suporte da Sami usa a configuração da conta MPS em todas as contas dele.
        [$cfgTabela, $cfgId] = df_conta_config($tabela, $usuarioId);
        $config = df_config($cfgTabela, $cfgId);
        session(['df_pendente' => [
            'tabela'      => $tabela,
            'usuario_id'  => $usuarioId,
            'cfg_tabela'  => $cfgTabela,
            'cfg_id'      => $cfgId,
            'senha_mestra' => false,
            'expira'      => time() + DF_PENDENTE_MINUTOS * 60,
            'tentativas'  => 0,
            'configurando' => $config === null,
            'metodo'      => $config['metodo'] ?? null,
            'email'       => $config['email'] ?? null,
            'segredo'     => null,
        ]]);

        if ($config === null) {
            return response()->json(['dois_fatores' => [
                'etapa'   => 'configurar',
                'metodos' => df_metodos_permitidos($cfgTabela, $cfgId),
            ]]);
        }

        if ($config['metodo'] === 'totp') {
            return response()->json(['dois_fatores' => ['etapa' => 'codigo', 'metodo' => 'totp']]);
        }

        $envio = df_enviar_codigo($cfgTabela, $cfgId, (string) $config['email'], (string) $request->ip());
        return response()->json(['dois_fatores' => [
            'etapa'   => 'codigo',
            'metodo'  => 'email',
            'destino' => df_email_mascarado((string) $config['email']),
            'aviso'   => $envio['ok'] ? null : $envio['erro'],
        ]]);
    }

    /**
     * POST /api/auth/2fa/email — configurar por e-mail: o e-mail que vai
     * receber os códigos (informado agora, mesmo que já tenha um no cadastro).
     */
    public function configurarEmail(Request $request)
    {
        $pendente = self::pendente();
        if (!$pendente['configurando'] || !in_array('email', df_metodos_permitidos($pendente['cfg_tabela'], $pendente['cfg_id']), true)) {
            return response()->json(['message' => 'Essa conta já tem os dois fatores configurados.'], 422);
        }
        $email = mb_strtolower(trim((string) $request->input('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Informe um e-mail válido.', 'errors' => ['email' => 'Informe um e-mail válido.']], 422);
        }

        // Falhou o envio (provedor de e-mail instável): segue pra tela do código
        // com o aviso — "Reenviar código" tenta de novo pro mesmo e-mail.
        $envio = df_enviar_codigo($pendente['cfg_tabela'], $pendente['cfg_id'], $email, (string) $request->ip());
        self::atualizar(['metodo' => 'email', 'email' => $email, 'segredo' => null]);

        return response()->json(['dois_fatores' => [
            'etapa'   => 'codigo',
            'metodo'  => 'email',
            'destino' => df_email_mascarado($email),
            'aviso'   => $envio['ok'] ? null : $envio['erro'],
        ]]);
    }

    /** POST /api/auth/2fa/totp — configurar o Google Authenticator (só MPS): gera o segredo e o QR code. */
    public function configurarTotp()
    {
        $pendente = self::pendente();
        if (!$pendente['configurando'] || !in_array('totp', df_metodos_permitidos($pendente['cfg_tabela'], $pendente['cfg_id']), true)) {
            return response()->json(['message' => 'O Google Authenticator não está disponível para essa conta.'], 422);
        }

        // Nome no Authenticator: "Fulano (Imobiliária X)" — separa logins com o mesmo e-mail.
        $rotulo  = df_conta_rotulo($pendente['cfg_tabela'], $pendente['cfg_id']);
        $conta   = str_replace(":", "", "{$rotulo['nome']} ({$rotulo['onde']})");
        $segredo = df_totp_gerar_segredo();
        self::atualizar(['metodo' => 'totp', 'email' => null, 'segredo' => Crypt::encryptString($segredo)]);

        return response()->json(['dois_fatores' => [
            'etapa'   => 'codigo',
            'metodo'  => 'totp',
            'qr'      => df_qr_data_uri(df_totp_uri($segredo, $conta)),
            'segredo' => trim(chunk_split($segredo, 4, ' ')),
        ]]);
    }

    /** POST /api/auth/2fa/reenviar — novo código por e-mail. */
    public function reenviar(Request $request)
    {
        $pendente = self::pendente();
        if ($pendente['metodo'] !== 'email' || empty($pendente['email'])) {
            return response()->json(['message' => 'Essa conta não usa código por e-mail.'], 422);
        }
        $envio = df_enviar_codigo($pendente['cfg_tabela'], $pendente['cfg_id'], $pendente['email'], (string) $request->ip());
        if (!$envio['ok']) {
            return response()->json(['message' => $envio['erro']], 422);
        }
        return response()->json(['success' => true, 'destino' => df_email_mascarado($pendente['email'])]);
    }

    /**
     * POST /api/auth/2fa/verificar — confere o código; no primeiro login grava
     * a configuração; certo, conclui o login.
     */
    public function verificar(Request $request)
    {
        $pendente = self::pendente();
        $codigo   = preg_replace('/\D/', '', (string) $request->input('codigo', ''));
        if (strlen($codigo) !== 6) {
            return response()->json(['message' => 'Informe o código de 6 dígitos.', 'errors' => ['codigo' => 'Informe o código de 6 dígitos.']], 422);
        }
        if ($pendente['metodo'] === null) {
            return response()->json(['message' => 'Escolha como receber o código.'], 422);
        }

        $tentativas = $pendente['tentativas'] + 1;
        self::atualizar(['tentativas' => $tentativas]);
        if ($tentativas > self::MAX_TENTATIVAS) {
            session()->forget('df_pendente');
            return response()->json(['message' => 'Muitas tentativas. Entre de novo com seu e-mail e senha.', 'reiniciar' => true], 429);
        }

        [$tabela, $id] = [$pendente['cfg_tabela'] ?? $pendente['tabela'], $pendente['cfg_id'] ?? $pendente['usuario_id']];
        $suporte = null;
        if ($pendente['senha_mestra']) {
            $r = df_verificar_totp_suporte($codigo);
            $suporte = $r['suporte'] ?? null;
        } elseif ($pendente['metodo'] === 'email') {
            $r = df_verificar_codigo($tabela, $id, $codigo);
            if ($r['ok'] && $pendente['configurando']) {
                df_salvar($tabela, $id, 'email', $r['email'], null);
            }
        } elseif ($pendente['configurando']) {
            $segredo = Crypt::decryptString((string) $pendente['segredo']);
            $passo   = df_totp_verificar($segredo, $codigo);
            $r = $passo !== null
                ? ['ok' => true]
                : ['ok' => false, 'erro' => 'Código incorreto. Confira o código que aparece no Google Authenticator.'];
            if ($r['ok']) {
                df_salvar($tabela, $id, 'totp', null, $segredo);
                db_execute('UPDATE dois_fatores SET totp_ultimo_passo = ? WHERE tabela_origem = ? AND usuario_id = ?', ['isi', $passo, $tabela, $id]);
            }
        } else {
            $r = df_verificar_totp_conta($tabela, $id, $codigo);
        }

        if (!$r['ok']) {
            self::registrarFalha($request, $pendente['tabela'], $pendente['usuario_id']);
            return response()->json(['message' => $r['erro'], 'errors' => ['codigo' => $r['erro']]], 422);
        }

        session()->forget('df_pendente');
        return app(AuthController::class)->concluirLogin($request, $pendente['tabela'], $pendente['usuario_id'], $pendente['senha_mestra'], $suporte);
    }

    /** POST /api/auth/2fa/cancelar — volta pro e-mail e senha. */
    public function cancelar()
    {
        session()->forget('df_pendente');
        return response()->json(['success' => true]);
    }

    /** Login pendente da sessão (acertou a senha há pouco) — ou responde 419 pra recomeçar. */
    private static function pendente(): array
    {
        $p = session('df_pendente');
        if (!is_array($p) || ($p['expira'] ?? 0) < time()) {
            session()->forget('df_pendente');
            json_response(['message' => 'O tempo para confirmar o acesso acabou. Entre de novo com seu e-mail e senha.', 'reiniciar' => true], 419);
        }
        return $p;
    }

    private static function atualizar(array $campos): void
    {
        session(['df_pendente' => array_merge((array) session('df_pendente', []), $campos)]);
    }

    /** Código errado conta como falha de login (entra no bloqueio por e-mail/IP). */
    private static function registrarFalha(Request $request, string $tabela, int $usuarioId): void
    {
        $email = (string) (db_fetch_one("SELECT email FROM {$tabela} WHERE id = ?", ['i', $usuarioId])['email'] ?? '');
        if ($email === '') return;
        db_execute(
            'INSERT INTO login_tentativas (email, tipo, ip, falhou, motivo, criado_em) VALUES (?, ?, ?, 1, ?, NOW())',
            ['ssss', $email, $tabela === 'usuario' ? 'admin' : 'user', (string) $request->ip(), 'codigo_2fa_incorreto']
        );
    }
}
