# AGENTS.md — Sistema Restaurante Global

Reglas del repositorio. **Léelas antes de escribir una línea de código.**
Valen para las personas y para la IA que trabaje en este repo.

---

## 1. Qué es este proyecto

Sistema web de gestión para restaurante en **Venezuela**: POS/caja, comandas de cocina,
menú digital, reservas de eventos y cursos, inventario, estadísticas y facturación
fiscal preparada para las obligaciones del SENIAT.

Es un **ambiente de demostración comercial** para mostrar a un cliente interesado.
Prioridades del código, en este orden:

1. **Que no se caiga** frente al cliente. Nada de pantallas blancas ni errores 500.
2. **Que sea creíble** en los números: IVA, IGTF, margen, tasas.
3. **Que sea mantenible**: la arquitectura real está detrás de la demo, no al revés.

---

## 2. Spec-Driven Development (obligatorio)

```
specs/
├── README.md              ← índice, estado, dependencias entre specs
├── 000-fundaciones/
├── 010-iam-roles/
├── 020-catalogos/
├── 030-tasa-cambio/
├── 040-menu-digital/
├── 050-mesas-ordenes/
├── 055-comandas-cocina/
├── 060-pos-cajero/
├── 070-facturacion-fiscal/
├── 080-reservaciones/
├── 090-inventario-bom/
├── 100-ventas-estadisticas/
├── 110-auditoria/
└── 120-demo/
```

**Reglas:**

- **Ningún módulo se empieza sin `spec.md` aprobado.**
- Cada spec tiene: Contexto · Alcance · No-alcance · FR-xx · Invariantes ·
  Criterios Given/When/Then · Casos borde · Modelo de datos · Contratos ·
  Casos de prueba (Pest) · Dependencias · Preguntas abiertas.
- Se implementa en **vertical slices**: una tarea = un test que falla + el código que lo hace pasar.
- Al terminar, la spec se marca `✅` en `specs/README.md`.
- **No se inventa comportamiento.** Si algo no está en la spec, primero se actualiza la spec.

---

## 3. Las 8 invariantes del dominio

Estas reglas no se negocian. Si una implementación las contradice, la implementación está mal.

### I1 — Cero tasas hardcodeadas
Ninguna alícuota de IVA, IGTF ni porcentaje impositivo se escribe en el código.
Todo sale de tablas con **vigencia** (`vigencia_desde` / `vigencia_hasta`) consultadas por
la fecha de la operación. Prohibido escribir `0.16`, `'17'`, `* 0.17` o similar.

```php
// PROHIBIDO
$iva = $base * 0.16;

// OBLIGATORIO
$iva = $this->impuestos->alicuotaVigente(TaxCode::IVA, $fechaOperacion);
```

### I2 — El dinero nunca es `float`
`decimal(18,4)` en base de datos. Value object `Money` en el dominio. Presentación a
2 decimales. **Prohibido** `float`, `double`, sumas de `0.1` o comparaciones `==` de importes.

### I3 — Los documentos fiscales son append-only
Ningún `UPDATE` ni `DELETE` sobre `documentos_fiscales` ni sobre notas crédito/débito.
Una anulación es **un documento nuevo** que referencia al original (`documento_padre_id`).

### I4 — La fecha fiscal manda
`APP_TIMEZONE = America/Caracas`. Toda fecha operativa (corte, cierre, emisión, tasa) usa
la fecha del negocio, no la del reloj del servidor.

### I5 — La emisión fiscal es idempotente
`idempotency_key` única por documento. Un reintento de red o de cola **jamás** puede
duplicar una factura. `queued` → `issued` solo avanza hacia adelante.

### I6 — Todo cambio sensible queda auditado
Precio, tasa de cambio, descuento, propina, anulación, cambio de rol, emisión de reporte Z.
Actor (`usuario_id`), acción, entidad, `before`, `after`, IP y fecha. Sin excepción.

### I7 — Filament es backoffice, Livewire es operación

| Pantalla | Tecnología |
|---|---|
| Roles, catálogos, inventario, reportes, parámetros, auditoría | **Filament 3** |
| Login demo, panel del mesero (tablet), **pantalla de cocina**, POS, cobro, reservas | **Livewire 3 + Blade + Alpine** |

Prohibido construir el POS o la pantalla de cocina como recursos de Filament.

### I8 — Una reserva sin pago completo no se confirma
Eventos y cursos se pagan **100% por adelantado**. La reserva no puede pasar a
`confirmada` mientras `estado_pago != pagado`. El pago pasa por el pipeline fiscal.

---

## 4. Las 5 trampas del servidor de pruebas

`demo.salvanovasolutions.online` es **cPanel compartido con LiteSpeed de HostGator** que
aloja **17 proyectos más**. Estas trampas ya costaron tiempo en otro proyecto de la misma
empresa. Están prohibidas.

| # | Trampa | Prophylaxis |
|---|---|---|
| **1** | `Specified key was too long` (error 1071) — `varchar(255)` con `utf8mb4` son 1020 bytes y el límite es 1000 | `Schema::defaultStringLength(191)` en `AppServiceProvider::boot()`. **Obligatorio desde el primer commit.** |
| **2** | Los ENUM se cambian con SQL crudo → el proyecto deja de correr en SQLite | **Solo MySQL.** Nunca SQLite, ni local ni en tests. Las pruebas usan MySQL. |
| **3** | `optimize:clear` se traba si `CACHE_STORE=database` y la tabla `cache` no existe (deadlock) | Siempre `migrate` **antes** de limpiar caché |
| **4** | 🔴 **`route:cache` rompe el sitio: `GET /` responde 405** y el panel queda inaccesible | **Nunca `route:cache`.** Siempre `route:clear`. **Y verificar con `curl` después de cada deploy** — por consola Laravel responde bien y el fallo solo se ve en el navegador |
| **5** | Los assets se suben a `public/build/` pero el docroot sirve `build/` → el HTML se actualiza y **el estilo no** | El deploy sincroniza `public/build` y `public/assets` a la raíz. **Con verificación automática que falla si no coinciden.** |

Añadidas para este proyecto (Vite + Filament + Livewire, que `isp-core` no tenía):

| # | Trampa | Prophylaxis |
|---|---|---|
| **6** | El navegador pide `/build/...` y `public_path()` apunta a la raíz del proyecto | `vite.config.js` con `base` prefijado + `ASSET_URL` con prefijo + copiar `build/` a la raíz |
| **7** | La cookie de sesión sin el path del subdirectorio rompe el CSRF de Livewire | `SESSION_PATH=/sistema-restaurante-global` |
| **8** | Filament sirve assets por ruta dinámica y falla en subdirectorios | `php artisan filament:assets` en el deploy + copiar a la raíz. **Probar en la Fase 0, no al final.** |

---

## 5. Convenciones de código

- **PHP 8.2**, Laravel 12, Filament 3, Livewire 3.
- Nombres en **español** para el dominio y la base de datos (`ordenes`, `mesas`,
  `documentos_fiscales`). Nombres en inglés para lo de Laravel (`HasFactory`,
  `SoftDeletes`, `casts()`).
- **Una migración por Concerns**, fechas en el nombre: `2026_10_12_000100_create_mesas.php`.
- ⚠️ **Nunca editar una migración ya aplicada en el servidor.** Se agrega una nueva.
- Enums siempre en `app/Enums/`, con casos y métodos, nunca cadenas sueltas.
- Services de dominio en `app/Domain/<Modulo>/Services/`, no en los controllers.
- Los controllers son delgados: validan, delegan, responden.
- Acciones massivas (Job) y no bucles largos dentro de la request (cola).
- **Comentarios solo donde expliquen un porqué.** Nunca comments que repitan el código.

### Comandos de verificación (obligatorios antes de dar algo por terminado)

```bash
./vendor/bin/pest              # pruebas
./vendor/bin/pint              # estilo
./vendor/bin/phpstan analyse   # análisis estático
php artisan route:list         # que las rutas carguen
```

---

## 6. Despliegue

- **El repo es privado** → el servidor **no puede** hacer `git clone`. El flujo es
  `tar` (local) → `scp` (puerto **21098**) → `tar -xzf` (servidor) → `composer install` →
  `migrate` → limpiar caché → **verificar**.
- **Nunca** subir `vendor/`, `.env`, `node_modules` ni `.git` en el tar.
- `route:cache` está prohibido (§4, trampa 4).
- `.env` del servidor en `600`. `storage/` y `bootstrap/cache/` en `775`.
- Detalle completo en `docs/DESPLIEGUE_SERVIDOR.md`. Script en `deploy.ps1`.

---

## 7. Secretos

- **Nunca** se versiona `.env`, contraseñas, llaves privadas ni tokens de API.
- El `.env.example` documenta las variables **con valores de ejemplo**, nunca reales.
- La contraseña de la base de datos se lee del `.env` del servidor por SSH, jamás del repo.
- Si un secreto se sube por error: se rota primero, se limpia el historial después.

---

## 8. Empezar a trabajar

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Antes de tocar un módulo: leer su `specs/NNN-nombre/spec.md` y este archivo.
