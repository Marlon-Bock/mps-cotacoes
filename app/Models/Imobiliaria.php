<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Imobiliaria extends Model
{
    protected $table = 'imobiliarias';

    public $timestamps = false;

    protected $guarded = ['id'];
}
