<?php

namespace App\Domain\Ordenes\Models;

use App\Domain\Catalogos\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ítem de la orden.
 *
 * Copia el nombre y el precio del producto al momento del pedido. Es la misma
 * razón por la que un recibo no muestra el precio de hoy: es la evidencia de lo
 * que se cobró.
 */
class OrdenItem extends Model
{
    protected $table = 'orden_items';

    protected $fillable = [
        'orden_id', 'producto_id', 'producto_nombre', 'producto_codigo',
        'cantidad', 'precio_unitario', 'importe', 'alicuota_codigo',
        'notas', 'estado', 'cortado_motivo', 'cortado_por',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'importe' => 'decimal:4',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function fueCortado(): bool
    {
        return $this->estado === 'cortado';
    }
}
