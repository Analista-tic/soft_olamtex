# Plan de depuración: `operations` y `product_sheet_operations`

## Objetivo
- Detectar operaciones duplicadas en `operations`.
- Conservar **solo el primer registro canónico** por clave única definida.
- Reasignar referencias de `product_sheet_operations` y tablas dependientes al registro canónico.
- Eliminar duplicados remanentes sin perder trazabilidad operativa.

## Alcance (tablas impactadas)
1. `operations` (catálogo maestro)
2. `product_sheet_operations` (listado operacional por sábana)
3. `technical_sheet_operations` (usa `operation_id` y `product_sheet_operation_id`)
4. `product_sheet_operation_history` (historial por `product_sheet_operation_id`)

## Restricciones actuales detectadas
- `product_sheet_operations` tiene triggers que bloquean duplicados por:
  - (`product_sheet_id`, `operation_id`)
  - (`product_sheet_id`, `sequence`)
- `product_sheet_operations.operation_id` referencia `operations.id` (`restrictOnDelete`).
- `technical_sheet_operations.product_sheet_operation_id` referencia `product_sheet_operations.id`.

## Hallazgos de modelado (estado actual)
1. **Unicidad de `operations` desalineada con negocio**
  - En código, la firma de unicidad actual considera campos como máquina/factor/fuente.
  - En negocio, la unicidad correcta definida es:
    - `section_id` + `action` + `garment_part` + `measure` + `quantity` + `sam_base`.
2. **Snapshot técnico parcialmente mutable**
  - `technical_sheet_operations` sí guarda snapshot al crear OP.
  - Pero existe flujo que permite reemplazar completo el snapshot (edición de operaciones de ficha técnica), por lo que no es totalmente inmutable.
  - Esto es válido si se entiende como: edición local de la ficha técnica, sin tocar origen.
3. **`product_sheet_operation_history` no versiona ficha técnica**
  - Solo registra cambios de factores/SAM en `product_sheet_operations` (origen operativo).
  - No registra cambios de `technical_sheet_operations`.

## Mejoras aplicadas inmediatamente (16-05-2026)
1. **Firma de unicidad en servicio alineada a negocio**
   - Se actualizó `OperationService` para detectar duplicados con:
     - `section_id + action + garment_part + measure + quantity + sam_base`.
   - Se removió la dependencia de campos de referencia (máquina/fuente) para decidir unicidad.
2. **Índice de escalabilidad para firma de negocio**
   - Migración aplicada: `2026_05_16_113000_add_operations_business_signature_index`.
   - Índice compuesto en `operations`:
     - `(section_id, action, garment_part, measure, quantity, sam_base)`.

## Modelo objetivo acordado
1. **Origen (`product_sheet_operations`)**
  - Editable por negocio.
  - Cada cambio relevante deja traza en `product_sheet_operation_history`.
2. **Destino (`technical_sheet_operations`)**
  - Nace como snapshot del origen al crear OP/ficha técnica.
  - Puede editarse posteriormente SOLO dentro de la ficha técnica.
  - Cambios en origen no deben propagar retroactivamente a fichas técnicas ya creadas.
3. **Trazabilidad**
  - Si se edita snapshot técnico, se recomienda historial propio en tabla relacional dedicada (no JSON).

## Estrategia general (sin pérdida de datos)
### Fase 0 — Seguridad
1. Hacer backup completo de BD.
2. Ejecutar en ventana de mantenimiento (sin escrituras concurrentes).
3. Trabajar en transacciones por lote y registrar bitácora de cambios.

### Fase 1 — Definir clave de unicidad funcional
> Esta clave define qué se considera “misma operación”.

Definición de negocio (obligatoria):
- `section_id`
- `UPPER(TRIM(action))`
- `UPPER(TRIM(garment_part))`
- `COALESCE(measure, 0)`
- `COALESCE(quantity, 1)`
- `COALESCE(sam_base, 0)`

No forman parte de la unicidad:
- `machine_id_reference`
- `product_sheet_id_reference`
- metadatos de auditoría

### Fase 2 — Auditoría de duplicados (solo lectura)
1. Listar grupos duplicados en `operations` por clave funcional.
2. Elegir canónico por grupo: `MIN(id)` (primer registro único).
3. Generar tabla temporal/mapeo: `old_operation_id -> canonical_operation_id`.

### Fase 3 — Normalización previa en `product_sheet_operations`
Problema esperado:
- Al cambiar `operation_id` al canónico, pueden aparecer colisiones en la misma sábana (`product_sheet_id`, `operation_id`) bloqueadas por trigger.

Acción:
1. Detectar filas de `product_sheet_operations` que colisionarán tras el remapeo.
2. Conservar una fila “ganadora” por (`product_sheet_id`, `canonical_operation_id`) (regla sugerida: menor `sequence`, luego menor `id`).
3. Reapuntar dependencias de fila perdedora:
   - `technical_sheet_operations.product_sheet_operation_id` -> fila ganadora
4. Eliminar filas perdedoras en `product_sheet_operations`.

### Fase 4 — Reasignación de referencias a operación canónica
1. Actualizar `product_sheet_operations.operation_id` usando el mapeo.
2. Actualizar `technical_sheet_operations.operation_id` usando el mapeo.
3. Verificar que no queden referencias a IDs duplicados.

### Fase 5 — Eliminación de duplicados en `operations`
1. Borrar en `operations` todos los `old_operation_id` ya reemplazados.
2. Confirmar integridad referencial (sin huérfanos).

### Fase 6 — Validación post-proceso
1. No existen duplicados en `operations` por clave funcional.
2. No existen duplicados en `product_sheet_operations` por (`product_sheet_id`, `operation_id`).
3. No existen referencias rotas en `technical_sheet_operations`.
4. Muestreo funcional en UI: creación/edición de listado operacional.

## Plan de ejecución técnica (script recomendado)
Implementar un comando Laravel dedicado:
- `php artisan production:dedupe-operations --dry-run`
- `php artisan production:dedupe-operations --apply`

Comportamiento del comando:
1. Construye mapeo canónico.
2. Reporta conteos por tabla afectada.
3. En `--dry-run` no modifica datos.
4. En `--apply` ejecuta por transacción y genera reporte final en `storage/logs`.

## Entregables
1. Comando `production:dedupe-operations` con `dry-run/apply`.
2. Reporte CSV/JSON con:
   - grupos duplicados,
   - mapeo aplicado,
   - filas fusionadas en `product_sheet_operations`,
   - IDs eliminados.
3. Validación final documentada.

## Riesgos y mitigación
- Riesgo: colisiones por trigger durante remapeo.
  - Mitigación: fusionar primero filas conflictivas en `product_sheet_operations`.
- Riesgo: pérdida de trazabilidad de fila histórica.
  - Mitigación: remapear `technical_sheet_operations.product_sheet_operation_id` antes de borrar.
- Riesgo: criterio de unicidad incorrecto.
  - Mitigación: aprobar clave funcional con negocio antes de `--apply`.

## Checklist de aprobación antes de aplicar
- [ ] Clave de unicidad funcional aprobada por negocio.
- [ ] Backup confirmado.
- [ ] `--dry-run` revisado y validado.
- [ ] Ventana de mantenimiento aprobada.
- [ ] Responsable funcional disponible para validación post-proceso.
