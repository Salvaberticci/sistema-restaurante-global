<?php

namespace App\Domain\Mesas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sucursal extends Model
{
    use SoftDeletes;

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
