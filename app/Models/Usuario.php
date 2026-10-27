<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Usuário da MPS (tabela `usuario`) — guard 'admin'. A conta do painel de TV
 * também está aqui (coluna painel = 'S').
 */
class Usuario extends Authenticatable
{
    protected $table = 'usuario';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    /** A tabela não tem remember_token. */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
