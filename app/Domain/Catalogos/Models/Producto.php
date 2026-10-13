<?php

namespace App\Domain\Catalogos\Models;

use App\Domain\Mesas\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Producto del menú: lo que el mesero pide y el cajero cobra.
 *
 * El precio es de VENTA al público, con el impuesto incluido, como sale en la
 * carta. La alícuota se guarda como CÓDIGO y no como número: el valor sale de
 * la tabla de vigencias (invariante I1). Si alguien escribe `16` acá en vez de
 * `'IVA_G'`, el producto queda congelado con la tasa equivocada el día que
 * cambie la alícuota.
 */
class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'categoria_id', 'unidad_medida_id',
        'estacion_cocina_id', 'precio_venta', 'precio_costo', 'alicuota_codigo',
        'requiere_preparacion', 'es_bebida', 'es_insumo', 'imagen',
        'disponible', 'mostrar_menu_digital', 'orden',
    ];

    protected function casts(): array
    {
        return [
            // decimal, nunca float: invariante I2.
            'precio_venta' => 'decimal:4',
            'precio_costo' => 'decimal:4',
            'requiere_preparacion' => 'boolean',
            'es_bebida' => 'boolean',
            'es_insumo' => 'boolean',
            'disponible' => 'boolean',
            'mostrar_menu_digital' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    public function estacionCocina(): BelongsTo
    {
        return $this->belongsTo(EstacionCocina::class, 'estacion_cocina_id');
    }

    public function scopeDisponibles($query)
    {
        return $query->where('disponible', true);
    }

    /**
     * Margen sobre el precio de venta. Es `null` mientras no haya costo cargado,
     * no `0`: un margen de cero y un costo desconocido son cosas distintas y
     * confundirlas hace que el dueño crea que está regalando el plato.
     */
    public function margen(): ?float
    {
        if ($this->precio_costo === null) {
            return null;
        }

        $costo = (float) $this->precio_costo;
        $venta = (float) $this->precio_venta;

        if ($costo <= 0) {
            return null;
        }

        return round((($venta - $costo) / $venta) * 100, 1);
    }
}
