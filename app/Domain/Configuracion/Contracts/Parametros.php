<?php

namespace App\Domain\Configuracion\Contracts;

/**
 * Parámetros de configuración que no van en el `.env` porque se cambian desde
 * la interfaz mientras el sistema está corriendo, y por eso tienen que quedar
 * auditados.
 *
 * Aquí viven los porcentajes de IGTF por modalidad de pago, las alícuotas de IVA
 * vigentes y las políticas comerciales. Invariante I1: nada de esto se escribe
 * en el código.
 */
interface Parametros
{
    public function obtener(string $clave, mixed $porDefecto = null): mixed;

    public function entero(string $clave, int $porDefecto = 0): int;

    public function decimal(string $clave, string $porDefecto = '0'): string;

    public function booleano(string $clave, bool $porDefecto = false): bool;

    public function texto(string $clave, string $porDefecto = ''): string;

    /** @throws \App\Domain\Configuracion\Exceptions\ParametroNoDefinidoException */
    public function exigir(string $clave): mixed;

    public function definir(string $clave, mixed $valor, ?int $usuarioId = null): void;

    /** @return array<string, mixed> */
    public function todos(?string $grupo = null): array;
}
