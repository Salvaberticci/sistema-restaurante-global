<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver del emisor fiscal
    |--------------------------------------------------------------------------
    |
    | El restaurante emite sus documentos fiscales a través de un emisor
    | autorizado, no el software directamente. Hay tres caminos posibles y
    | todavía no está decidido cuál aplica:
    |
    |   simulated  → emulador con memoria fiscal real, para el demo y las pruebas
    |   spooler    → impresora fiscal (HKA, Dascom, Bixolon) vía spooler local
    |   electronic → facturación electrónica con proveedor certificado
    |
    | Cambiar de emisor es cambiar esta línea. El código de negocio no se toca.
    | Ver specs/000-fundaciones/spec.md FR-06.
    |
    */

    'driver' => env('FISCAL_DRIVER', 'simulated'),

    'serial_simulado' => env('FISCAL_SERIAL', 'DEMO-SRG-0001'),

    /*
    | Series de numeración. En una máquina fiscal real las series se configuran
    | en el propio equipo y no se pueden cambiar desde el software.
    */

    'serie_factura' => env('FISCAL_SERIE_FACTURA', '0001'),
    'serie_nota_credito' => env('FISCAL_SERIE_NOTA_CREDITO', '0002'),
    'serie_nota_debito' => env('FISCAL_SERIE_NOTA_DEBITO', '0003'),

    /*
    |--------------------------------------------------------------------------
    | Redondeo
    |--------------------------------------------------------------------------
    |
    | El precio se fija en dólares a 2 decimales, pero la conversión a bolívares
    | deja residuo. Estas reglas dicen qué pasa con ese residuo: es la causa
    | número uno de descuadres de caja, así que no se deja al azar.
    |
    */

    'redondeo' => [
        'escala_presentacion' => 2,
        'escala_calculo' => 4,
        'metodo' => 'half_up',
        'politica_diferencia' => env('FISCAL_DIFERENCIA_REDONDEO', 'absorber'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tiempo de espera del emisor
    |--------------------------------------------------------------------------
    |
    | El POS no puede quedarse colgado esperando a la impresora. Si el emisor no
    | responde en estos segundos, el documento queda en cola y el cajero sigue
    | vendiendo.
    |
    */

    'timeout_segundos' => (int) env('FISCAL_TIMEOUT', 15),

];
