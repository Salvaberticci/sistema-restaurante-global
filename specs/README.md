# Specs — Sistema Restaurante Global

Índice de especificaciones. **Ningún módulo se implementa sin su spec aprobada.**

## Estados

| Estado | Significado |
|---|---|
| ⬜ | Especificada, pendiente de aprobación |
| 🟡 | Aprobada, en implementación |
| ✅ | Implementada y verificada |
| ⬜🔒 | Bloqueada por dependencias |

## Orden de ejecución

| # | Spec | Módulo | Depende de | Estado |
|---|---|---|---|---|
| 000 | [fundaciones](000-fundaciones/spec.md) | Base técnica, dominio, driver fiscal | — | 🟡 |
| 010 | [iam-roles](010-iam-roles/spec.md) | Roles y usuarios | 000 | 🟡 |
| 020 | [catalogos](020-catalogos/spec.md) | Productos, insumos, IVA, métodos de pago | 010 | ⬜ |
| 030 | [tasa-cambio](030-tasa-cambio/spec.md) | Tasa BCV y multi-moneda | 020 | ⬜ |
| 040 | [menu-digital](040-menu-digital/spec.md) | Menú editable | 020 | ⬜ |
| 050 | [mesas-ordenes](050-mesas-ordenes/spec.md) | Mesas, salón, comanda, cuenta | 020 | ⬜ |
| 055 | [comandas-cocina](055-comandas-cocina/spec.md) | **Pantalla de cocina (KDS)** | 050 | ⬜ |
| 060 | [pos-cajero](060-pos-cajero/spec.md) | POS y cobro con pago mixto | 050, 030 | ⬜ |
| 070 | [facturacion-fiscal](070-facturacion-fiscal/spec.md) | IVA, IGTF, X/Z, notas crédito | 060 | ⬜ |
| 080 | [reservaciones](080-reservaciones/spec.md) | Eventos y cursos (pago 100%) | 070 | ⬜ |
| 090 | [inventario-bom](090-inventario-bom/spec.md) | Recetas, kardex, merma | 020, 060 | ⬜ |
| 100 | [ventas-estadisticas](100-ventas-estadisticas/spec.md) | Reportes y utilidad real | 070, 090 | ⬜ |
| 110 | [auditoria](110-auditoria/spec.md) | Bitácora consolidada | 000 | ⬜ |
| 120 | [demo](120-demo/spec.md) | Datos de demostración, landing, login por rol | todos | ⬜ |

## Reglas de dependencias

- `000` bloquea a todas. No se empieza otro módulo sin que esté ✅.
- `070` (fiscal) va **después** de `060` (POS): se factura lo que el cajero cobró, no al revés.
- `055` (cocina) va **antes** de `070`: es parte del corazón de la demo y solo depende de
  la interfaz del driver, no del hardware.
- `100` necesita `090` porque la utilidad real sale del BOM, no del precio de venta.
- `120` se cierra al final, cuando todos los módulos tienen datos que sembrar.

## Reglas del ciclo de trabajo

1. Leer `AGENTS.md`.
2. Leer la spec del módulo.
3. Si falta un comportamiento: **actualizar la spec primero**, después el código.
4. Implementar en vertical slices: una tarea = un test que falla + el código que lo hace pasar.
5. Verificar con `pest`, `pint`, `phpstan` y `route:list`.
6. Marcar ✅ acá cuando el módulo está completo.

## Plantilla de una spec

```markdown
## Contexto
## Alcance
## No-alcance
## Requisitos (FR-xx)
## Invariantes
## Criterios de aceptación (Given / When / Then)
## Casos borde y errores
## Modelo de datos
## Contratos (DTOs, eventos)
## Casos de prueba (Pest)
## Dependencias
## Preguntas abiertas
```
