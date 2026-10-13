<?php

namespace App\Domain\Catalogos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estación de cocina. Define dónde se prepara cada producto y cuánto tarda antes
 * de que su ticket se ponga ámbar o rojo.
 *
 * Los tiempos son datos, no constantes: un café sale en dos minutos y una
 * bandeja grande en veinte, y con un solo umbral el semáforo de la cocina
 * sirve para una cosa y no para la otra.
 */
class EstacionCocina extends Model
{
    protected $table = 'estaciones_cocina';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'icono',
        'tiempo_verde_min', 'tiempo_ambar_min', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'tiempo_verde_min' => 'integer',
            'tiempo_ambar_min' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'estacion_cocina_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }

    /**
     * Nivel del semáforo según los minutos que lleva el ticket.
     * `verde` · `ambar` · `rojo`
     */
    public function nivel(int $minutos): string
    {
        return match (true) {
            $minutos >= $this->tiempo_ambar_min => 'rojo',
            $minutos >= $this->tiempo_verde_min => 'ambar',
            default => 'verde',
        };
    }
}
