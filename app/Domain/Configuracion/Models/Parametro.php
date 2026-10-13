<?php

namespace App\Domain\Configuracion\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Parámetro de configuración.
 *
 * El valor se guarda como JSON porque no todos los parámetros son escalares: hay
 * booleanos, listas y estructuras como las alícuotas con su vigencia. El cast a
 * `array` es lo que permite leerlos tipados desde el dominio.
 *
 * @property int $id
 * @property string $clave
 * @property array<string, mixed>|null $valor
 * @property string|null $descripcion
 * @property string $grupo
 */
class Parametro extends Model
{
    protected $table = 'parametros';

    protected $fillable = ['clave', 'valor', 'descripcion', 'grupo', 'usuario_id'];

    protected function casts(): array
    {
        return [
            'valor' => 'array',
        ];
    }
}
