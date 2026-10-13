<?php

use Tests\Support\Codigo;

/**
 * CA-03 / invariantes I1 y I4: cero tasas hardcodeadas y un solo reloj.
 *
 * Es la única forma de que las reglas no se desmonten a la tercera spec.
 * Alguien siempre escribe `$base * 0.16` "solo por esta vez", y sin un test que
 * lo detecte, esa línea llega a producción.
 *
 * Solo mira `app/Domain/`. Los tests sí pueden pasar porcentajes explícitos:
 * ahí el valor es el dato de la prueba, no una regla del negocio.
 */
it('no hay alicuotas ni porcentajes de impuesto escritos en el dominio', function () {
    // Literales que solo tienen sentido como tasa fiscal.
    $prohibidos = [
        '0.16', '0,16',          // IVA general
        '0.17', '0,17',          // IGTF
        '16 / 100', '17 / 100',
    ];

    // El patrón real del error es multiplicar o dividir por el literal, no
    // escribirlo suelto.
    $patrones = [
        'multiplica o divide por una tasa' => '/[\*\/\(]\s*0[.,](1[67])(?![\d.])/',
    ];

    expect(Codigo::infracciones($prohibidos, $patrones))->toBe([]);
});

it('el helper now() no aparece suelto dentro del dominio', function () {
    // `FechaFiscal::hoy()` sí vale, y está centralizado. El helper global
    // `now()` dentro de un servicio de cálculo hace que el resultado dependa del
    // reloj del servidor, que es lo que la invariante I4 prohíbe.
    $patrones = [
        'usa el helper now() del reloj global' => '/(?<![A-Za-z_>:])now\(\)/',
    ];

    expect(Codigo::infracciones([], $patrones))->toBe([]);
});

it('el reloj del dominio vive solo en FechaFiscal', function () {
    $conReloj = array_values(array_filter(
        Codigo::archivosDeDominio(),
        fn (array $archivo) => str_contains(
            Codigo::sinComentarios(file_get_contents($archivo['ruta'])),
            'CarbonImmutable::now',
        ),
    ));

    expect(array_column($conReloj, 'nombre'))->toBe(['Shared/Support/FechaFiscal.php']);
});

it('los documentos fiscales se guardan con escala 4 y no con float', function () {
    $columna = DB::selectOne(
        "SELECT DATA_TYPE, NUMERIC_SCALE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_fiscales'
           AND COLUMN_NAME = 'total'"
    );

    expect($columna->DATA_TYPE)->toBe('decimal')
        ->and((int) $columna->NUMERIC_SCALE)->toBe(4);
});

it('la idempotency key de los documentos fiscales es unica', function () {
    $indices = DB::select(
        "SELECT NON_UNIQUE
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_fiscales'
           AND COLUMN_NAME = 'idempotency_key'"
    );

    expect($indices)->not->toBeEmpty();

    foreach ($indices as $indice) {
        expect((int) $indice->NON_UNIQUE)->toBe(0);
    }
});

it('el largo de string por defecto es 191 para no reventar el indice', function () {
    // AGENTS.md §4 trampa 1: con utf8mb4 un varchar(255) son 1020 bytes y el
    // límite del índice del servidor es 1000.
    //
    // Se prueba el comportamiento y no una columna concreta, porque una columna
    // con largo explícito no pasa por el default y daría un falso verde.
    Schema::create('prueba_largo_default', function ($table) {
        $table->id();
        $table->string('sin_largo');
    });

    $columna = DB::selectOne(
        "SELECT CHARACTER_MAXIMUM_LENGTH AS largo
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prueba_largo_default'
           AND COLUMN_NAME = 'sin_largo'"
    );

    expect((int) $columna->largo)->toBe(191);

    Schema::dropIfExists('prueba_largo_default');
});
