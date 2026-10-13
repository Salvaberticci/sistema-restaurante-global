<?php

namespace App\Domain\Mesas\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Zona o salón del restaurante: el salón de mesas, el salón de eventos, la
 * barra. Es un espacio con una capacidad y, en el caso de las mesas, con un
 * número de turnaround para calcular cuántas veces se puede usar por noche.
 *
 * Sin borrado lógico: un salón con reservas encima no se borra, se desactiva.
 */
class Zona extends Model
{
    protected $table = 'zonas';

    protected $fillable = [
        'nombre', 'tipo', 'capacidad', 'mesas', 'turnover_minutos', 'activo',
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

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
