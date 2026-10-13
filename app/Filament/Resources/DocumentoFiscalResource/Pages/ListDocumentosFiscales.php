<?php

namespace App\Filament\Resources\DocumentoFiscalResource\Pages;

use App\Filament\Resources\DocumentoFiscalResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Listado de documentos fiscales.
 *
 * Sale en el grupo "Caja y facturación", junto al punto de venta y los reportes
 * X/Z, porque es la vista que un cajero revisa al final del turno.
 */
class ListDocumentosFiscales extends ListRecords
{
    protected static string $resource = DocumentoFiscalResource::class;

    protected static ?string $title = 'Documentos fiscales';

    protected function getHeaderActions(): array
    {
        // La emisión pasa por el driver fiscal con su clave de idempotencia, no
        // por un botón de "crear" (invariante I5). Nadie escribe documentos a mano.
        return [];
    }
}
