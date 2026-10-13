<?php

namespace App\Domain\Mesas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sucursal. No tiene borrado lógico a propósito: una sucursal con órdenes,
 * facturas e inventario no se borra nunca, se desactiva con `activo`. Borrarla
 * dejaría documentos fiscales apuntando a la nada.
 */
class Sucursal extends Model
{
    /**
     * El pluralizador de Laravel es inglés: de `Sucursal` saca `sucursals`, no
     * `sucursales`. En este dominio los nombres de tabla son en español y se
     * declaran uno por uno. Ver AGENTS.md §5.
     */
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre', 'codigo', 'direccion', 'telefono', 'rif', 'ciudad', 'estado', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function zonas(): HasMany
    {
        return $this->hasMany(Zona::class);
    }
}
