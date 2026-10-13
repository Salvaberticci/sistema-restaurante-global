<?php

namespace App\Domain\Configuracion\Models;

use Illuminate\Database\Eloquent\Model;

class Parametro extends Model
{
    protected $table = 'parametros';

    protected $fillable = ['clave', 'valor', 'descripcion', 'grupo', 'usuario_id'];

    protected function casts(): array
    {
        return [
            'valor' => 'array',
        ];
    }
}
