<?php
// Uso: php token.php imob|admin  → imprime JWT de teste (banco db_mpsapis)
require '/u01/home/marlon/www/dev-cotacoes/src/bootstrap.php';
if (env('DB_TEST') !== 'db_mpsapis') exit("banco errado\n");
if (($argv[1] ?? '') === 'admin') {
    $u = db_fetch_one('SELECT * FROM usuario WHERE id = 7');
    $p = ['uid' => 7, 'nome' => $u['username'], 'email' => $u['email'], 'role' => 'admin', 'imob_admin' => false, 'mps_admin' => true, 'imobiliaria_id' => null];
} else {
    $u = db_fetch_one('SELECT * FROM users WHERE id = 19');
    $p = ['uid' => 19, 'nome' => $u['username'], 'email' => $u['email'], 'role' => 'imobiliaria', 'imob_admin' => true, 'mps_admin' => false, 'imobiliaria_id' => (int) $u['imobiliaria_id']];
    foreach (['perm_cotar','perm_suporte','perm_aceitar','perm_fianca','perm_auto','perm_condo','perm_titulo','perm_incendio','perm_residencial','perm_comercial'] as $k) $p[$k] = $u[$k] ?? 'N';
}
$p['iat'] = time(); $p['exp'] = time() + 3600;
echo jwt_encode($p, env('JWT_SECRET'));
