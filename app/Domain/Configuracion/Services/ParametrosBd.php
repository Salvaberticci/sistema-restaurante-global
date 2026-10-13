<?php

namespace App\Domain\Configuracion\Services;

use App\Domain\Auditoria\Contracts\RegistraAuditoria;
use App\Domain\Configuracion\Contracts\Parametros;
use App\Domain\Configuracion\Exceptions\ParametroNoDefinidoException;
use App\Domain\Configuracion\Models\Parametro;
use App\Domain\Configuracion\Support\Valores;
use Illuminate\Support\Facades\Cache;

/**
 * Parámetros en base de datos con caché.
 *
 * El caché se limpia al definir, no al leer: un parámetro se lee MUCHAS veces
 * por venta y se escribe POCAS veces.
 */
final class ParametrosBd implements Parametros
{
    private const CACHE_KEY = 'parametros';

    private const TTL = 300;

    public function __construct(
        private readonly RegistraAuditoria $auditoria,
    ) {}

    public function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        $todos = $this->cargar();

        return array_key_exists($clave, $todos) ? $todos[$clave] : $porDefecto;
    }

    public function exigir(string $clave): mixed
    {
        $valor = $this->obtener($clave);

        if ($valor === null) {
            throw ParametroNoDefinidoException::para($clave);
        }

        return $valor;
    }

    public function entero(string $clave, int $porDefecto = 0): int
    {
        return Valores::entero($this->obtener($clave), $porDefecto);
    }

    public function decimal(string $clave, string $porDefecto = '0'): string
    {
        return (string) Valores::decimal($this->obtener($clave) ?? $porDefecto);
    }

    public function booleano(string $clave, bool $porDefecto = false): bool
    {
        return Valores::booleano($this->obtener($clave), $porDefecto);
    }

    public function texto(string $clave, string $porDefecto = ''): string
    {
        $valor = $this->obtener($clave);

        return is_scalar($valor) ? (string) $valor : $porDefecto;
    }

    public function definir(string $clave, mixed $valor, ?int $usuarioId = null): void
    {
        $anterior = Parametro::where('clave', $clave)->first();

        Parametro::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor, 'usuario_id' => $usuarioId],
        );

        Cache::forget(self::CACHE_KEY);

        if ($anterior !== null) {
            $this->auditoria->registrar(
                accion: 'parametro.actualizado',
                entidad: Parametro::class,
                entidadId: $anterior->id,
                antes: ['valor' => $anterior->valor],
                despues: ['valor' => $valor],
                usuarioId: $usuarioId,
            );
        }
    }

    public function todos(?string $grupo = null): array
    {
        $consulta = Parametro::query()->select(['clave', 'valor']);

        if ($grupo !== null) {
            $consulta->where('grupo', $grupo);
        }

        return $consulta->pluck('valor', 'clave')->all();
    }

    /** @return array<string, mixed> */
    private function cargar(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, fn () => Parametro::pluck('valor', 'clave')->all());
    }
}
