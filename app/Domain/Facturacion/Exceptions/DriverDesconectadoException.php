<?php

namespace App\Domain\Facturacion\Exceptions;

/**
 * El emisor fiscal no respondió. El documento queda en `queued` y la cola lo
 * reintenta: el cajero nunca ve un error en pantalla, sigue vendiendo.
 * Invariante I5: el reintento no puede duplicar, por eso el documento guarda su
 * `idempotency_key`.
 */
final class DriverDesconectadoException extends \RuntimeException
{
    public static function simuladorCaido(): self
    {
        return new self(
            'El emisor fiscal no está disponible. El documento queda en cola y se reintentará automáticamente.'
        );
    }

    public static function emisorNoResponde(string $driver): self
    {
        return new self("El emisor fiscal [{$driver}] no respondió.");
    }
}
