# Sistema Restaurante Global

Sistema de gestión para restaurante en Venezuela: punto de venta y caja, comandas
de cocina, menú digital, reservas de eventos y cursos, inventario con recetas,
estadísticas de ventas y facturación fiscal preparada para el SENIAT.

**Ambiente de demostración comercial.** Los datos que se muestran son de un
restaurante ficticio.

---

## Empezar

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

Las pruebas usan **MySQL**, no SQLite: `phpunit.xml` apunta a
`sistema_restaurante_global_test`.

```bash
./vendor/bin/pest
./vendor/bin/pint
./vendor/bin/phpstan analyse
```

---

## Cómo está organizado

El proyecto sigue **Spec-Driven Development**. Antes de tocar un módulo, lee su
spec en [`specs/`](specs/README.md) y las reglas del repo en
[`AGENTS.md`](AGENTS.md).

```
app/Domain/<Modulo>/
├── Contracts/   interfaces (puertos)
├── Dtos/        objetos de transferencia
├── Enums/
├── Exceptions/
├── Models/
├── Services/    lógica de dominio
├── Support/     value objects y helpers
└── Values/      value objects
```

### Dónde va cada cosa

| Pantalla | Tecnología |
|---|---|
| Roles, catálogos, inventario, reportes, parámetros, auditoría | **Filament 3** |
| Login demo, panel del mesero (tablet), **pantalla de cocina**, POS, cobro, reservas | **Livewire 3 + Blade + Alpine** |

El POS y la pantalla de cocina **no** son recursos de Filament. Se construyen en
Livewire porque necesitan respuesta inmediata y pantallas propias.

---

## Las ocho invariantes

Están resumidas en [`AGENTS.md`](AGENTS.md#3-las-8-invariantes-del-dominio) y hay
tests que las vigilan. Las que más se rompen en la práctica:

1. **Ninguna tasa hardcodeada.** Las alícuotas de IVA y los porcentajes de IGTF
   salen de tablas con vigencia. Hay un test arquitectónico que falla si aparece
   un `0.16` en el dominio.
2. **El dinero nunca es `float`.** Se usa el value object `Money`, que guarda
   enteros en centavos y calcula con decimal exacto.
3. **Los documentos fiscales son append-only.** Una anulación es un documento
   nuevo que referencia al original, nunca un `UPDATE`.
4. **`America/Caracas`.** El reloj del dominio vive solo en `FechaFiscal`.
5. **La emisión fiscal es idempotente.** `idempotency_key` única: un reintento
   nunca duplica una factura.
6. **Todo cambio sensible queda auditado**, en la misma transacción del cambio.
7. **Filament es backoffice, Livewire es operación.**
8. **Una reserva sin pago completo no se confirma.**

---

## Facturación

El emisor fiscal es un **driver intercambiable**. El restaurante emite a través
de una impresora fiscal autorizada o de un proveedor de facturación electrónica
certificado; el software no emite el documento por sí solo.

```env
FISCAL_DRIVER=simulated   # simulated | spooler | electronic
```

`SimulatedFiscalDriver` mantiene memoria fiscal de verdad: correlativo por serie,
serial, contador Z, turno acumulado y deduplicación por clave de idempotencia.
Por eso el demo puede mostrar reportes X y Z creíbles y, a la vez, valida el
contrato de la interfaz antes de que exista el hardware.

Cambiar de emisor es cambiar esa línea del `.env`. El código de negocio no se
toca. Ver [`specs/000-fundaciones/spec.md`](specs/000-fundaciones/spec.md) FR-06.

---

## Despliegue

Ambiente de pruebas: `demo.salvanovasolutions.online/sistema-restaurante-global`
(cPanel compartido, LiteSpeed, subdirectorio).

```powershell
.\deploy.ps1
```

El script empaqueta, sube por `scp`, instala, migra y **verifica con `curl`**.
Falla si la raíz da 405, si falta un asset o si un secreto deja de estar
bloqueado.

Toda la documentación, incluidas **las cinco trampas de este servidor** que ya
costaron tiempo en otro proyecto, está en
[`docs/DESPLIEGUE_SERVIDOR.md`](docs/DESPLIEGUE_SERVIDOR.md).

---

## Estado

Las specs y su avance están en [`specs/README.md`](specs/README.md).