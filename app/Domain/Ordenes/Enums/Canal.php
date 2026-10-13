<?php

namespace App\Domain\Ordenes\Enums;

enum Canal: string
{
    case Mesa = 'mesa';
    case ParaLlevar = 'para_llevar';
    case Delivery = 'delivery';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Mesa => 'Comedor',
            self::ParaLlevar => 'Para llevar',
            self::Delivery => 'Delivery',
        };
    }

    /** Fracción de la cuenta que se sugiere como propina. */
    public function propinaSugerida(): string
    {
        return match ($this) {
            self::Mesa => '10',
            self::ParaLlevar, self::Delivery => '0',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Mesa => 'info',
            self::ParaLlevar => 'warning',
            self::Delivery => 'purple',
        };
    }
}
