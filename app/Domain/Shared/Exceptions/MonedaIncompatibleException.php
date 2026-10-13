<?php

namespace App\Domain\Shared\Exceptions;

final class MonedaIncompatibleException extends \DomainException
{
    public static function entre(string $izquierda, string $derecha): self
    {
        return new self(
            "No se pueden combinar importes en monedas distintas: {$izquierda} y {$derecha}. "
            .'Convertí a una moneda antes de operar.'
        );
    }
}
