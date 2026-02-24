# Revisión de cambios actuales

**Fecha:** 10 feb 2026 | **Objetivo:** Estado funcional vs pendiente del repositorio

> **Depuración runtime:** `REVISION_DEPURACION_Y_LIMPIEZA.md` | **Checklist completo:** `OPTIMIZACION_MODELOS_OPERACIONES.md`

---

## 1. Correcciones aplicadas

| # | Área | Cambio |
|---|------|--------|
| 1 | ProgrammerController | `ProductionOrderService` inyectado en constructor |
| 2 | Storage | Ruta `GET storage/{path}` dentro de `auth` + `verified` |
| 3 | Backend | Enum `EstatusOperacionOrden`; `ProductionOrderOperation` (HasAuditLabel, SoftDeletes, enum, FK, `updateProgressOnly`); `ProductionOrderService`; rutas GET/PATCH operaciones; migración; observers |
| 4 | Backend | Programmer (sin booted hooks); OperationSection (productSheetOperations); ProductSheetOperation (fabricBase); Operation (byNormalizedName) |
| 5 | Frontend | Composables `useProgrammer*`, DTOs, refactor Edit/Create/Index |

---

## 2. Estado funcional

| Área | Detalle |
|------|---------|
| Listado operacional (plantilla) | ProductOperationListController: options, index, store, storeBulk, update, destroy, reorder, history |
| Operation (catálogo) | store/update, syncOperationBase, scope byNormalizedName |
| Programador | Crear/editar; snapshot en `update()` |
| Snapshot (ProductionOrderOperation) | SoftDeletes, HasAuditLabel, enum status, FK ProductSheetOperation, `updateProgressOnly`, `isCompleted` |
| Listado operacional de orden | `GET /comercial/programador/{id}/operaciones` (read-only), `PATCH .../progreso` |
| ProductSheetOperation | Modelo base; alias `ProductOperationList` en uso; tabla `product_operation_lists` |

---

## 3. Pendiente / no verificado

| Ítem | Notas |
|------|-------|
| Snapshot en `store()` | Solo se genera en `update`; valorar llamar `createSnapshotForProgrammer` tras create |
| ListadoOperacionalProgrammer.vue | No implementado; DTOs y rutas listos |
| Tests ProductSheetOperationTest | Requieren .env.testing con BD |
| OptimizedImage, symlink storage | Depende del entorno |

---

## 4. Migración ProductOperationList → ProductSheetOperation

Plan detallado en `REVISION_DEPURACION_Y_LIMPIEZA.md` §4. Resumen: migrar imports (Controller, Service, Resource) → rutas → tabla opcional → eliminar alias.

---

## 5. Comandos útiles

```bash
php artisan storage:link
php artisan route:list --path=programador
vendor/bin/pest tests/Feature/ProductSheetOperationTest.php
```
