<?php

namespace App\Domain\Catalogos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Categoría del menú. Sin borrado lógico: una categoría que ya se vendió no se
 * borra, se desactiva. Borrarla dejaría facturas apuntando a una categoría
 * fantasma.
 */
class Categoria extends Model
{
    protected $table = 'categorias';

    protected $fillable = [
        'nombre', 'slug', 'descripcion', 'icono', 'orden', 'mostrar_menu_digital', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'mostrar_menu_digital' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // El slug se genera solo para no tener que acordarse de escribirlo.
        static::saving(function (self $categoria) {
            if (blank($categoria->slug)) {
                $categoria->slug = Str::slug($categoria->nombre);
            }
        });
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
