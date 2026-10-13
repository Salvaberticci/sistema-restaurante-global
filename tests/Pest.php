<?php

use App\Domain\Shared\Support\FechaFiscal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Boot de Pest. Las pruebas usan MySQL: SQLite está prohibido por AGENTS.md §4
 * trampa 2, porque los ENUM se modifican con SQL crudo y SQLite no los entiende.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit/Feature');

/**
 * Fecha fiscal congelada para los tests. Todo cálculo de vigencia recibe una
 * FechaFiscal explícita justamente para que los tests sean deterministas: si
 * alguien metiera un `now()` adentro, el test fallaría en diciembre.
 */
function fecha(string $iso = '2026-10-12'): FechaFiscal
{
    return FechaFiscal::de($iso);
}
