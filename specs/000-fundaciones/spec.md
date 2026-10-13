# Spec 000 — Fundaciones

**Estado:** 🟡 Aprobada · **Fase:** 0 · **Bloquea a:** todas las demás specs

---

## 1. Contexto

El repositorio está vacío. Hay que construir la base técnica sobre la que van a vivir los
trece módulos del sistema: un restaurante en Venezuela con POS, cocina, reservas,
inventario y facturación fiscal.

Esta spec no describe una funcionalidad de negocio. Describe las **decisiones de
cimentación** que, si se hacen mal, obligan a refactorizar el resto del sistema.

Dos restricciones del ambiente condicionan casi todo:

- **El servidor es cPanel compartido con LiteSpeed** que aloja 17 proyectos más, y el
  deploy vive en un **subdirectorio**: `/sistema-restaurante-global`. El docroot no se
  puede cambiar por SSH, así que apunta a la raíz del proyecto, no a `public/`.
- **El repositorio es privado**, así que el servidor no puede hacer `git clone`. El
  despliegue es `tar` + `scp` + `composer install`.

Además, el emisor fiscal (impresora fiscal autorizada o facturación electrónica) **no está
decidido**. El cliente puede ser contribuyente especial o no. Por eso la emisión fiscal se
diseña como un **driver intercambiable desde el día 1**, no como un módulo atado a un
hardware concreto. Definir esta interfaz en la Fase 0 y no en la Fase 3 es la diferencia
entre refactorizar el POS o no refactorizarlo.

---

## 2. Alcance

- Estructura de directorios del dominio (`app/Domain/<Modulo>/`).
- Value object `Money` y `Decimal` (invariante I2).
- Zona horaria `America/Caracas` y concepto de "fecha fiscal" (invariante I4).
- Configuración de base de datos y `defaultStringLength(191)` (trampa 1).
- Auditoría base: modelo, servicio y contrato de registro (invariante I6).
- Interfaz `FiscalDocumentDriver` + driver **simulado** funcional.
- Configuración del subdirectorio: Vite, `ASSET_URL`, `SESSION_PATH` (trampas 6 y 7).
- Estructura de pruebas con Pest sobre MySQL.
- Verificación de que login + Filament + Livewire funcionan en el subdirectorio real.

## 3. No-alcance

- Ninguna regla de negocio de restaurante.
- Ninguna pantalla de operación (POS, cocina, reservas).
- El driver de impresora fiscal real y el de facturación electrónica (van en spec 070).
- El seeding de datos de demostración (spec 120).

---

## 4. Requisitos

### FR-01 — Estructura del dominio

```
app/Domain/<Modulo>/
├── Enums/
├── Models/
├── Services/
├── Policies/
├── Dtos/
└── Contracts/       ← interfaces (puertos)
```

Módulos previstos: `Shared`, `Iam`, `Catalogos`, `TasaCambio`, `Menus`, `Mesas`, `Ordenes`,
`Cocina`, `Pos`, `Facturacion`, `Reservas`, `Inventario`, `Ventas`, `Auditoria`.

> Cada módulo declara su carpeta en `app/Domain/<Modulo>/module.php`. No hay
> auto-descubrimiento: es explícito y auditable.

### FR-02 — Value object `Money`

```php
final readonly class Money
{
    private function __construct(
        private int $monto,          // enteros en la unidad menor, nunca float
        private string $moneda,      // ISO-4217: USD, VES
    ) {}

    public static function deUSD(int $centavos): self;
    public static function deBolivares(int|Decimal $monto): self;

    public function sumar(self $otro): self;
    public function restar(self $otro): self;
    public function multiplicarPor(Decimal $factor): self;
    public function porcentaje(Decimal $p): self;

    public function esIgualA(self $otro): bool;
    public function esMayorQue(self $otro): bool;
    public function esMenorQue(self $otro): bool;

    public function aDecimal(): Decimal;        // para la BD
    public function formateado(): string;      // "1.234,56 USD"
}
```

- Mezclar monedas distintas lanza `MonedaIncompatibleException`.
- **Prohibido** implementar `JsonSerializable` sobre `Money` sin convertir antes: en la BD
  y en las respuestas HTTP viaja `Decimal` o string.
- `Decimal` es un wrapper de `Brick\Math\BigDecimal`. Se elige `brick/math` porque PHP no
  tiene decimal nativo y `float` no sirve para dinero (invariante I2).

### FR-03 — Fecha fiscal

```php
final class FechaFiscal
{
    public static function hoy(): CarbonImmutable;   // en America/Caracas
    public static function de(CarbonInterface $f): self;
    public function es(Local $fechaOperacion): bool; // ¿vigente a esta fecha?
}
```

Todo el cálculo de vigencias (IVA, IGTF, tasas) recibe esta fecha, **nunca** `now()` suelto
dentro de un servicio. Así los tests son deterministas.

### FR-04 — Base de datos

- `Schema::defaultStringLength(191)` en `AppServiceProvider::boot()`.
- **MySQL únicamente.** El `phpunit.xml` apunta a la BD de pruebas MySQL. SQLite queda
  prohibido por la trampa 2.
- Convenciones de nombres: tablas en español y `snake_case` plural
  (`ordenes`, `productos`, `documentos_fiscales`).
- **Una migración por concern**, con fecha: `2026_10_12_000100_create_mesas.php`.
- ⚠️ **Nunca editar una migración ya aplicada en el servidor.** Se agrega una nueva.

### FR-05 — Auditoría base

```php
interface RegistraAuditoria
{
    public function registrar(
        string $accion,          // 'precio.actualizado'
        string $entidad,         // App\Domain\Catalogos\Models\Producto::class
        int|string $entidadId,
        array $antes,
        array $despues,
        ?int $usuarioId = null,
        ?string $ip = null,
    ): Auditoria;
}
```

- Se escribe dentro de la misma transacción que el cambio. Si el cambio falla, no hay
  registro huérfano.
- Registro automático por modelo con `Observers\AuditaCambios`, opt-in por modelo.
- **Nunca** se edita ni se borra una entrada de auditoría.

### FR-06 — Interfaz `FiscalDocumentDriver`

Esta es la decisión de arquitectura más importante de la spec.

```php
interface FiscalDocumentDriver
{
    public function nombre(): string;

    public function capacidades(): DriverCapabilities;

    /** Abre el documento en el emisor y devuelve una sesión abierta. */
    public function abrir(DocumentoFiscalContexto $ctx): SesionFiscal;

    /** Registra una o más líneas. Puede agrupar si el emisor lo permite. */
    public function registrarLineas(SesionFiscal $s, LineasDocumento $lineas): void;

    /** Registra pagos. Permite pago mixto: la sesión conserva el desglose. */
    public function registrarPagos(SesionFiscal $s, PagosDocumento $pagos): void;

    /** Cierra y emite. Es idempotente: reintentar devuelve el mismo resultado. */
    public function cerrar(DocumentoFiscalContexto $ctx): ResultadoFiscal;

    /** Emite nota de crédito o débito referenciando el documento original. */
    public function emitirNota(NotaFiscalContexto $ctx): ResultadoFiscal;

    /** Reporte X: lectura de caja, no altera la memoria fiscal. */
    public function reporteX(ReporteXCContexto $ctx): ResultadoReporte;

    /** Reporte Z: cierre fiscal diario, graba en memoria fiscal y resetea. */
    public function reporteZ(ReporteZCContexto $ctx): ResultadoReporte;

    /** Estado del emisor: en línea, último correlativo, contadores de memoria. */
    public function estado(): EstadoDriver;
}
```

**Contratos:**

- `ResultadoFiscal`: `documentoId`, `numeroFiscal`, `serial`, `contador`, `hash`,
  `emitidoEn`, `crudo` (respuesta cruda del emisor, para auditoría).
- `DriverCapabilities`: `soportaPagoMixto`, `soportaPropinaEnDocumento`,
  `soportaDescuentoEnLinea`, `maximoLineas`, `requiereAperturaPrevia`.
- Todo **monto** que cruza la frontera del driver viaja como `Money`, nunca como `float`.
- Todo **documento** lleva su `idempotencyKey`. El driver la usa para deduplicar.

**Drivers:**

| Driver | Uso | Estado en esta spec |
|---|---|---|
| `SimulatedFiscalDriver` | Demo y pruebas. Correlativo, serial, X/Z y NC reales y verificados. | ✅ se implementa |
| `SpoolerFileFiscalDriver` | Impresora fiscal HKA/Dascom/Bixolon: escribe el `.txt` que el spooler local consume. | ⬜ spec 070 |
| `ElectronicInvoiceDriver` | Facturación electrónica vía proveedor certificado. | ⬜ spec 070 |

Se elige en `config/facturacion.php` → `driver`. Cambiar de emisor **no toca código de
negocio**.

### FR-07 — Driver simulado

Para que el demo sea creíble, el simulador no devuelve basura: mantiene estado real.

- Correlativo por `(sucursal, serie, tipo)` con reinicio diario.
- Número fiscal `0001-00000123`, serie por sucursal.
- Serial del emisor, contador Z, hash determinista del documento.
- **Reportes X** devuelven el acumulado del turno sin alterar contadores.
- **Reporte Z** graba el acumulado, resetea el turno y deja registro de auditoría.
- **Notas de crédito** referencian el documento original y lo dejan en estado `anulado`.
- Configurable en `config/demo.php`: prefijo de serie, retardo artificial, tasa de fallo.

### FR-08 — Configuración del subdirectorio

```env
APP_URL=https://demo.salvanovasolutions.online/sistema-restaurante-global
ASSET_URL=https://demo.salvanovasolutions.online/sistema-restaurante-global
SESSION_PATH=/sistema-restaurante-global
SESSION_DOMAIN=null
APP_DEBUG=false
```

```js
// vite.config.js
export default defineConfig({
    base: process.env.ASSET_URL_PATH ?? '/sistema-restaurante-global/build/',
    plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true })],
})
```

- `config/app.php` lee `ASSET_URL` para que `asset()` y `url()` no generen rutas sin prefijo.
- Livewire: `config/livewire.php` → `asset_url` resuelto con `url('/livewire/livewire.js')`
  para no romper en el subdirectorio.
- Filament: `php artisan filament:assets` en el deploy, copiado a la raíz.

### FR-09 — Layout del docroot

El docroot es la raíz del proyecto, no `public/`. Consecuencias ya resueltas en la spec 070
de despliegue, pero **se verifican acá**:

- `index.php` parcheado a `__DIR__.'/vendor/autoload.php'`.
- `.htaccess` en la raíz del proyecto, negando `app/ vendor/ config/ database/ routes/
  resources/ storage/ tests/ .env composer.json artisan`.
- Cabeceras de seguridad (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`).
- Después de cada extracción de tar hay que **copiar `public/build/` y `public/assets/` a
  la raíz**. Sin esto el HTML se actualiza y el estilo no.

### FR-10 — Verificación en el servidor real (Fase 0, no Fase 3)

Antes de construir un solo módulo, se sube un esqueleto y se comprueba con `curl` y
navegador:

| Comprobación | Resultado esperado |
|---|---|
| `GET /` | 302 a `/login` — **jamás 405** |
| `GET /login` | 200 con el formulario |
| CSS de Vite | 200 y el HTML lo referencia con el prefijo correcto |
| `livewire/livewire.js` | 200 |
| Panel de Filament | 200, con sus assets cargados |
| `GET /.env`, `/vendor/autoload.php`, `/storage/logs/laravel.log` | **403** |
| Cambiar `APP_DEBUG`, recargar | Sin pantalla de error |
| Sesión entre dos peticiones | Persiste (confirma `SESSION_PATH`) |

---

## 5. Invariantes

| # | Invariante |
|---|---|
| I1 | Ninguna tasa o alícuota en el código |
| I2 | El dinero nunca es `float` |
| I3 | Los documentos fiscales son append-only |
| I4 | `America/Caracas`; la fecha fiscal viene explícita |
| I5 | `idempotency_key` única; reintentar no duplica |
| I6 | Precio, tasa, descuento, anulación y cambio de rol quedan auditados |
| I7 | Filament es backoffice; Livewire es operación |
| I8 | Una reserva sin pago 100% no se confirma |

## 6. Criterios de aceptación

### CA-01 — `Money` no admite `float`
```gherkin
Dado un Money de 10.00 USD
Cuando se le resta 0.10 USD
Entonces el resultado es 9.90 USD exactamente
Y la comparación con `new Money(990, 'USD')` es verdadera
```

### CA-02 — `Money` rechaza mezclar monedas
```gherkin
Dado un Money de 10.00 USD
Cuando se le suma un Money de 5.00 VES
Entonces lanza MonedaIncompatibleException
```

### CA-03 — El cálculo fiscal usa vigencias, no constantes
```gherkin
Dado un producto con IVA del 16% vigente desde el 2016-01-01
Y una operación del 2026-10-12
Cuando se calcula el documento
Entonces el IVA sale de la tabla de vigencias
Y no hay ningún literal de alícuota en el código de cálculo
```

### CA-04 — La auditoría va en la misma transacción
```gherkin
Dado un producto con precio 10.00
Cuando la actualización del precio falla a mitad
Entonces no queda ninguna entrada de auditoría
Y el precio sigue en 10.00
```

### CA-05 — El driver fiscal es idempotente
```gherkin
Dado un documento en estado "queued" con idempotency_key "abc"
Cuando el driver emite dos veces con la misma clave
Entonces devuelve el mismo número fiscal
Y queda un solo documento fiscal en la base
```

### CA-06 — El Reporte X no altera la memoria fiscal
```gherkin
Dado un turno con 3 facturas emitidas
Cuando se emite un Reporte X
Entonces el acumulado es el de las 3
Y el contador de memoria fiscal sigue igual
Y al emitir el Reporte Z el total coincide
```

### CA-07 — El Reporte Z resetea y queda auditado
```gherkin
Dado un turno con facturas emitidas
Cuando se emite el Reporte Z
Entonces el turno queda en cero
Y existe una entrada de auditoría "reporte_z.emitido" con el actor y la fecha
```

### CA-08 — La nota de crédito no borra el original
```gherkin
Dado una factura 0001-00000123 emitida
Cuando se emite una nota de crédito
Entonces la factura original queda en estado "anulado"
Y sigue existiendo, con su número fiscal intacto
Y la nota la referencia con documento_padre_id
```

### CA-09 — Todo monto hacia el driver es `Money`
```gherkin
Dado un documento con líneas y pagos
Cuando se inspecciona la llamada al driver
Entonces ningún argumento monetario es float
Y todos son Money o Decimal
```

### CA-10 — El subdirectorio sirve todo correctamente
```gherkin
Dado el deploy en /sistema-restaurante-global
Cuando se pide GET / desde fuera
Entonces responde 302, no 405
Y los assets devuelven 200
Y /.env responde 403
```

---

## 7. Casos borde y errores

| Caso | Comportamiento esperado |
|---|---|
| `driver` en config es un valor desconocido | Error de arranque claro, no pantalla en blanco |
| El driver lanza una excepción de red | El documento queda en `queued`, se reintenta con backoff, nunca en duplicado |
| El emisor devuelve número fiscal duplicado | Se registra la inconsistencia y se escala: el cajero ve "documento pendiente" |
| `Decimal` recibe más decimales de los que caben en `decimal(18,4)` | Excepción de dominio en el constructor de `Money` |
| Tasa de cambio del día no disponible | Modo degradado: tasa manual auditada; si tampoco hay, el POS **no** permite cobrar en Bs |
| `filament:assets` no encuentra archivos | Falla el deploy en vez de continuar |
| El `tar` sobrescribió el `.htaccess` de la raíz | La verificación del deploy falla por cabeceras de seguridad ausentes |

---

## 8. Modelo de datos

Migraciones iniciales:

```
0001_01_01_000001_create_cache_table
0001_01_01_000002_create_jobs_table
2026_10_12_000100_create_sessions_table
2026_10_12_000200_create_sucursales_table
2026_10_12_000300_create_auditoria_table
2026_10_12_000400_create_permisos_roles_table      (spatie/laravel-permission)
2026_10_12_000500_create_parametros_table
2026_10_12_000600_create_documentos_fiscales_table
2026_10_12_000700_create_cierres_fiscales_table
```

`sucursales` — `id, nombre, codigo, direccion, telefono, rif, activo, timestamps`

`auditoria` —
`id, usuario_id, actor_nombre, accion, entidad, entidad_id, antes(json), despues(json),
ip, user_agent, contexto(json), created_at`
Índice `(entidad, entidad_id)` y `(accion, created_at)`. **Sin `updated_at`** (append-only).

`parametros` — `clave (unique), valor(json), descripcion, grupo, updated_at`
Sirve para los parámetros fiscales configurables desde Filament (I1).

`documentos_fiscales` — solo el esqueleto que necesita el driver simulado:
`id, tipo, estado, sucursal_id, serie, numero, idempotency_key (unique), documento_padre_id,
ordenes_origen(json), documento_payload(json), resultado_emisor(json), hash, fecha_fiscal,
timestamps`.
El resto de columnas fiscales llegan en la spec 070.

---

## 9. Contratos

### Eventos

| Evento | Descripción |
|---|---|
| `DocumentoFiscalEncolado` | El documento quedó `queued` y espera emisión |
| `DocumentoFiscalEmitido` | Emitido con éxito, trae `numeroFiscal` y `serial` |
| `DocumentoFiscalFallido` | La emisión falló de forma terminal |
| `ReporteFiscalEmitido` | X o Z emitido |
| `ParametroActualizado` | Se cambió un parámetro del sistema |

### Jobs

| Job | Función |
|---|---|
| `EmitirDocumentoFiscal` | Toma un documento `queued` y lo emite. Idempotente. `tries=5`, backoff exponencial |
| `RefrescarTasaCambio` | Consulta la tasa y la cachea |

### Cola sin daemon (cPanel compartido)

No se puede correr `queue:work --daemon`. El scheduler lo hace cada minuto:

```php
Schedule::command('queue:work --stop-when-empty --max-jobs=20 --tries=2')
    ->everyMinute()->withoutOverlapping();
```

Requiere el cron de cPanel configurado. Si no está, la cola acumula y el POS sigue
funcionando en modo `sync` (más lento, pero no se rompe).

### Servicios de arranque

| Servicio | Responsabilidad |
|---|---|
| `Parametros` | Lee/escribe `parametros` con caché, para I1 |
| `RegistraAuditoria` (impl) | FR-05 |
| `ConfiguracionFiscal` | Lee alícuotas y perfiles vigentes desde BD |
| `RelojFiscal` | Inyectable en tests: `FechaFiscal` de ahora |

---

## 10. Casos de prueba (Pest)

`tests/Feature/Fundaciones/`:

```php
it('calcula sin errores de punto flotante', ...)            // CA-01
it('rechaza mezclar monedas', ...)                          // CA-02
it('no duplica documento al reintentar la emisión', ...)    // CA-05
it('el reporte x no altera la memoria fiscal', ...)         // CA-06
it('el reporte z resetea y queda auditado', ...)            // CA-07
it('la nota de crédito no borra el original', ...)          // CA-08
it('ningún monto cruza el driver como float', ...)          // CA-09
it('la auditoría se revierte si la transacción falla', ...)  // CA-04
it('la fecha fiscal usa America/Caracas', ...)
it('no hay tasas hardcodeadas en app/Domain', ...)          // CA-03, test arquitectónico
```

`tests/Feature/Fundaciones/DespliegueTest.php` (solo contra el servidor real, se salta en local):
`GET /` no es 405 · `/.env` es 403 · los assets son 200.

**Test arquitectónico anti-hardcodeo:** recorre `app/Domain/**` y falla si encuentra
`0.16`, `0,16`, `16%` o `* 0.17` como literal numérico fuera de las tablas de vigencia.
Es la única forma de que la invariante I1 no se desmonte a la tercera spec.

---

## 11. Dependencias

| Dependencia | Para qué |
|---|---|
| `laravel/framework` 12 | Base |
| `filament/filament` 3 | Backoffice |
| `livewire/livewire` 3 | Operación |
| `spatie/laravel-permission` | Roles y permisos |
| `brick/math` | `Decimal` con precisión arbitraria |
| `pestphp/pest` + `pestphp/pest-plugin-laravel` | Pruebas |
| `larastan/larastan` | Análisis estático |

## 12. Preguntas abiertas

| # | Pregunta | Bloquea a | Estado |
|---|---|---|---|
| 1 | ¿Filament 3.3.x soporta Laravel 12? Si no, ¿Filament 4? | Estructura del admin | Resolver en el primer `composer require` |
| 2 | ¿La máquina fiscal del cliente es HKA, Dascom o Bixolon? ¿Spooler local o red? | Driver spooler (070) | Preguntar al cliente. **No bloquea la demo** |
| 3 | ¿El restaurante es contribuyente especial? | Driver real (070) | Preguntar al cliente. **No bloquea la demo** |
| 4 | ¿Qué fuente de tasa además del BCV? | Spec 030 | Configurable desde el día 1 |
