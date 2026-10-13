<?php

namespace App\Domain\Mesas\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table = 'unidades_medida';

    protected $fillable = [
        'codigo', 'nombre', 'plural', 'decimales', 'permite_fraccion', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'decimales' => 'integer',
            'permite_fraccion' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
