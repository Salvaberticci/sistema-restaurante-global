<?php

namespace App\Domain\Mesas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Zona o salón. Sirve para lo mismo en el salón de mesas y en el salón de
 * eventos: son espacios de una sucursal con una capacidad.
 */
class Zona extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sucursal_id', 'nombre', 'tipo', 'capacidad', 'mesas', 'turnover_minutos', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'capacidad' => 'integer',
            'mesas' => 'integer',
            'turnover_minutos' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
