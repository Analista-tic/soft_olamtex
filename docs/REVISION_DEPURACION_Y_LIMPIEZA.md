# Revisión: Depuración, documentación y código no usado

**Fecha:** 10 feb 2026  
**Contexto:** Post-optimización de modelos de operaciones (OPTIMIZACION_MODELOS_OPERACIONES.md)

> **Referencia de estado funcional:** Ver `REVISION_CAMBIOS_ACTUALES.md` para qué está implementado y qué puede fallar.

---

## 1. Qué se puede depurar (riesgos con evidencia runtime)

### A. Enum `status` en `production_order_operations` existentes

- **Hipótesis:** Registros antiguos tienen `status` como string (ej. `'PENDIENTE'`). El cast a `EstatusOperacionOrden` podría fallar si hubiera valores distintos.
- **Instrumentación sugerida:** En `ProductionOrderOperation::boot()` o al cargar una colección, logear `status` crudo y si el cast lanza excepción.
- **Evidencia a buscar:** Errores de tipo/cast al listar operaciones de una orden existente.

### B. Doble fetch en Programmers Index

- **Hipótesis:** `useProgrammerFilters` hace `fetchProgrammers()` en `onMounted`, y el `watch` también llama `scheduleFetch(1)` al montar (por reactividad inicial).
- **Instrumentación sugerida:** Logs en `fetchProgrammers` y `scheduleFetch` con timestamp; contar cuántas veces se llama en la carga inicial.
- **Evidencia a buscar:** 2+ llamadas HTTP a `/comercial/programmers` al cargar la página.

### C. Ruta `operacion` en `updateProgresoOperacion`

- **Hipótesis:** Laravel resuelve `{operacion}` por ID, pero el nombre del parámetro en la ruta es `operacion` y el modelo inyectado podría no coincidir.
- **Instrumentación sugerida:** Log en el controller con `$operacion->id` y `$operacion->programmer_id` al recibir el PATCH.
- **Evidencia a buscar:** 404 o binding incorrecto al actualizar progreso.

### D. `forceDelete` vs SoftDeletes en snapshot

- **Hipótesis:** `ProductionOrderService::createSnapshotForProgrammer` usa `forceDelete()` para borrar snapshots previos. Con SoftDeletes, `delete()` haría soft delete; `forceDelete()` borra permanentemente. Si hay auditoría que espera registros soft-deleted, podría haber inconsistencia.
- **Instrumentación sugerida:** Log antes/después del `forceDelete` con count de filas afectadas.
- **Evidencia a buscar:** Comportamiento correcto (reemplazo total del snapshot) vs pérdida de auditoría esperada.

### E. Composable `useProgrammerNormalization` con meses vacíos

- **Hipótesis:** Si `months` llega vacío o `undefined`, `normalizeSeasonValue`/`normalizeSeasonForApi` podrían devolver valores inesperados.
- **Instrumentación sugerida:** Log en `normalizeSeasonForApi` con `months.length` y valor de entrada cuando `monthOptions.value` está vacío.
- **Evidencia a buscar:** Temporadas mal normalizadas cuando `props.months` no se carga.

---

## 2. Estado de documentación

| Archivo | Estado |
|---------|--------|
| `REVISION_CAMBIOS_ACTUALES.md` | Actualizado (optimizaciones, estado funcional, plan migración) |
| `OPTIMIZACION_MODELOS_OPERACIONES.md` | Checklist Semanas 1–2 marcado; Semana 3 pendiente (auditoría + migración alias) |
| `FUTURO/ANALISIS_LISTADO_OPERACIONAL.md` | Estructura antigua; actualizar si se usa como guía |

---

## 3. Código no usado / redundante

### Backend

| Ubicación | Detalle | Recomendación |
|-----------|---------|---------------|
| `Operation::getNormalizedNameAttribute()` | El accessor existe pero `normalized_name` ya no está en `$appends`. Se usa internamente en `scopeByNormalizedName` (antes con whereRaw, ahora con where directo). El accessor no se usa en serialización. | **Mantener** el accessor si algún código lo usa; si no, se puede eliminar. Actualmente `byNormalizedName` no lo usa, usa `strtoupper(trim(...))` directamente. |
| `ProductOperationList` (alias) | Usado en: `ProductOperationListController`, `ProductOperationListService`, `ProductOperationListResource`, `UpdateProductOperationListRequest`, `StoreProductOperationListRequest`. | Según OPTIMIZACION_MODELOS_OPERACIONES, eliminar en Semana 3. Requiere migrar todas las referencias a `ProductSheetOperation` y luego borrar el alias. |
| `OperationSection::productSheetOperations()` | Nueva relación. La antigua `productOperationLists()` fue reemplazada. ¿Algún código usa `productOperationLists`? | Buscar referencias a `productOperationLists`; si no hay, no hay código huérfano. |

### Frontend

| Ubicación | Detalle | Recomendación |
|-----------|---------|---------------|
| `domain/product-sheet/dtos.ts` | Define `ProductionOrderOperationReadDTO`, `ProgressUpdateDTO`, funciones `dtoFromProgrammer`, `dtoToProgrammerPayload`. | `Edit.vue` y `Create.vue` no usan estos DTOs; usan el composable y el modelo directo. Los DTOs quedan listos para `ListadoOperacionalProgrammer.vue` cuando exista. |
| `useProgrammerForm.ts` | Exporta `getDialogProductSheet(productSheets, productSheetId)` pero en Edit.vue se usa `getDialogProductSheet(productSheetId)` local que usa `props.productSheets`. | Revisar si el helper del composable se usa; si no, es código preparatorio. |
| `hasAttemptedSave` en Create.vue | En el template original de Create.vue había `v-if="hasAttemptedSave && formErrors.length > 0"`. | Verificar si la variable existe en el componente actual; si se eliminó en el refactor, quitar la referencia. |

### Rutas / Configuración

| Ubicación | Detalle | Recomendación |
|-----------|---------|---------------|
| Rutas `programador/{programador}/operaciones` | La ruta PATCH usa `{operacion}`. Laravel inyecta `ProductionOrderOperation` por ID. La ruta debe incluir el ID de la operación. | Confirmar que la URL sea correcta (ej. `PATCH /comercial/programador/1/operaciones/5/progreso`) y que el binding funcione. |

---

## 4. Plan de migración: ProductOperationList → ProductSheetOperation

### Archivos que usan ProductOperationList (alias)

| Archivo | Cambio requerido |
|---------|------------------|
| `app/Http/Controllers/ProductSheet/ProductOperationListController.php` | `use ProductSheetOperation` en lugar de `ProductOperationList` |
| `app/Services/ProductSheet/ProductOperationListService.php` | Constructor: `ProductSheetOperation $model`; type hints en métodos |
| `app/Http/Resources/ProductSheet/ProductOperationListResource.php` | Opcional: renombrar a `ProductSheetOperationResource` y usar `ProductSheetOperation` |
| `app/Http/Requests/ProductSheet/StoreProductOperationListRequest.php` | No usa el modelo directamente; verificar referencias |
| `app/Http/Requests/ProductSheet/UpdateProductOperationListRequest.php` | Idem |
| `routes/web.php` | Verificar bindings de ruta (listItem → ProductSheetOperation) |

### Orden

1. Migrar imports y type hints (Controller, Service, Resource).
2. Actualizar rutas si hay model binding explícito.
3. Opcional: renombrar tabla `product_operation_lists` → `product_sheet_operations` (requiere migración con backup FK).
4. Eliminar `ProductOperationList.php` cuando no queden referencias.

El modelo `ProductSheetOperation` usa `$table = 'product_operation_lists'`. El alias puede eliminarse sin tocar la tabla.

---

## 5. Prioridades

1. **Depuración:** Validar en runtime los puntos A–E de este documento.
2. **Limpieza:** Eliminar `getNormalizedNameAttribute` si no se usa; ejecutar migración alias cuando se elimine `ProductOperationList`.

---

## 6. Comandos

```bash
php artisan route:list --path=programador
php artisan tinker --execute="echo json_encode(\App\Models\ProductSheet\ProductionOrderOperation::select('id','status')->limit(5)->get()->toArray());"
```
