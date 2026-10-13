<?php

namespace App\Domain\Mesas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zona o salón. Sirve para lo mismo en el salón de mesas y en el salón de
 * eventos: son espacios de una sucursal con una capacidad.
 *
 * Sin borrado lógico, igual que la sucursal: un salón con reservas encima no se
 * borra, se desactiva.
 */
class Zona extends Model
{
    protected $table = 'zonas';

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
