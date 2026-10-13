<?php

namespace App\Domain\Catalogos\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Método de pago.
 *
 * `genera_igtf` y `es_divisa` no son lo mismo: el IGTF aplica a pagos en
 * divisa, pero cada modalidad tiene su propia alícuota y hay modalidades en
 * divisa exentas. Por eso el perfil se referencia por código y no se deduce de
 * `es_divisa`.
 */
class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $fillable = [
        'codigo', 'nombre', 'icono', 'es_divisa', 'es_efectivo',
        'requiere_referencia', 'genera_igtf', 'igtf_perfil_codigo', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_divisa' => 'boolean',
            'es_efectivo' => 'boolean',
            'requiere_referencia' => 'boolean',
            'genera_igtf' => 'boolean',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }
}
