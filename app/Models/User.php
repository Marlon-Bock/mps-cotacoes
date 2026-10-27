<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Usuário da imobiliária (tabela `users`) — guard 'imobiliaria'.
 * Senha gravada com password_hash() (bcrypt), compatível com o Hash do Laravel.
 */
class User extends Authenticatable
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    /** A tabela não tem remember_token. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function imobiliaria()
    {
        return $this->belongsTo(Imobiliaria::class, 'imobiliaria_id');
    }
}
