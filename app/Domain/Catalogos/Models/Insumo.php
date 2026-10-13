<?php

namespace App\Domain\Catalogos\Models;

use App\Domain\Mesas\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Insumo: lo que se usa para producir, no lo que se vende.
 *
 * Sin borrado lógico. Un insumo que aparece en recetas ya cobradas no se borra,
 * se desactiva: borrarlo rompería el costo histórico del plato.
 */
class Insumo extends Model
{
    protected $table = 'insumos';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'unidad_medida_id',
        'precio_compra', 'stock_inicial', 'stock_minimo',
        'merma_porcentaje', 'alicuota_codigo', 'proveedor', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_compra' => 'decimal:4',
            'stock_inicial' => 'decimal:4',
            'stock_minimo' => 'decimal:4',
            'merma_porcentaje' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    /**
     * Stock por debajo del mínimo. El kardex (spec 090) va a recalcular el
     * stock real; acá está el umbral para la alerta.
     */
    public function estaBajoMinimo(): bool
    {
        return (float) $this->stock_inicial <= (float) $this->stock_minimo
            && (float) $this->stock_minimo > 0;
    }
}
