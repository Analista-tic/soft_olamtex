# Depuración de campos de auditoría redundantes

**Fecha:** 10 feb 2026  
**Objetivo:** Eliminar `created_by`/`updated_by` en tablas donde la auditoría está centralizada en `audit_logs`.

---

## 1. Análisis

### Tablas con campos redundantes

| Tabla | Campos | Observado por AuditObserver | Acción |
|-------|--------|----------------------------|--------|
| `machines` | `created_by` | No | Añadir observer, luego eliminar columna |
| `operation_sections` | `created_by` | No | Añadir observer, luego eliminar columna |
| `operations` | `created_by` | Sí | Eliminar columna |
| `product_operation_lists` | `created_by`, `updated_by` | Sí | Eliminar columnas |

### Auditoría centralizada

- **audit_logs** (AuditLogService): registra `user_id`, `action`, `old_values`, `new_values`, `auditable_type`, `auditable_id` para cada create/update/delete.
- Los modelos con AuditObserver ya tienen trazabilidad completa; `created_by`/`updated_by` duplican información.

### Bases de tela (`fabric_bases`)

- `fabric_bases` es un catálogo en la tabla `parameters` (SettingService), no una tabla dedicada.
- No tiene columna `created_by`; no requiere cambios.

---

## 2. Plan de migración

1. Registrar `Machine` y `OperationSection` en `AuditObserver` (para no perder trazabilidad).
2. Añadir `auditIgnore = ['created_by', 'updated_by']` en modelos que lo usen.
3. Crear migración que elimine las columnas.
4. Actualizar modelos: quitar de `fillable`, relaciones `createdBy`/`updatedBy`.
5. Actualizar controllers/servicios: dejar de asignar `created_by`/`updated_by`.

---

## 3. Cambios aplicados (10 feb 2026)

| Archivo | Cambio |
|---------|--------|
| `AppServiceProvider` | Añadidos `Machine::observe`, `OperationSection::observe` |
| `database/migrations/2026_01_06_141918_create_product_operation_lists_table.php` | Eliminados `created_by`/`updated_by` de la creación de tablas |
| `Machine`, `OperationSection`, `Operation`, `ProductSheetOperation` | Quitados `created_by`/`updated_by` de fillable y relaciones |
| `MachineController`, `SectionController` | Quitada asignación de `created_by` |
| `ProductOperationListController` | Quitadas asignaciones de `created_by`, `updated_by` |
| `OperationService` | Quitado `$data['created_by']` en findOrCreate |
| `ProductOperationListService` | Quitados de replicate en copyToSheet |
| `ProductOperationListResource` | Quitado `created_by` del resource |

**Nota:** Si la BD ya existe con esas columnas, hacer `php artisan migrate:fresh` (desarrollo) o crear migración separada para eliminarlas en producción.
