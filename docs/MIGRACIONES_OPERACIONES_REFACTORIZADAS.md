# ✅ Migraciones de Operations - Refactorización Completada

**Fecha:** 23 de febrero de 2026  
**Estado:** ✅ Migraciones Separadas Creadas

---

## 📋 Resumen de Cambios

### ✅ SEPARADAS: 4 Migraciones Independientes (Antes Consolidadas)

#### 1️⃣ `2026_01_06_141918_create_machines_table.php` 
**Antes:** Dentro de `create_product_operation_lists_table.php`  
**Ahora:** ✅ Migración independiente

Estructura:
```sql
- id, code (UNIQUE), name (UNIQUE)
- supplement_factor (DECIMAL 8,4)
- is_active, timestamps, softDeletes
- Índices: code, is_active
```

---

#### 2️⃣ `2026_01_06_141919_create_operation_sections_table.php`
**Antes:** Dentro de `create_product_operation_lists_table.php`  
**Ahora:** ✅ Migración independiente + **MEJORA: ENUM validado**

Mejoras:
- ✅ Campo `type` ahora tiene ENUM: `['confeccion', 'presentacion']`
- ✅ Unique constraint en `name`
- ✅ Comentarios descriptivos

```sql
- id, name (UNIQUE), type (ENUM)
- is_active, timestamps, softDeletes
```

---

#### 3️⃣ `2026_01_06_141920_create_operations_table.php`
**Antes:** Dentro de `create_product_operation_lists_table.php`  
**Ahora:** ✅ Migración independiente + **MEJORA: Comentarios explicativos**

Mejoras:
- ✅ Campo `codigo` marcado como "NO EDITABLE por usuario"
- ✅ Todos los campos tienen comentarios (action, garment_part, sam_base, etc.)
- ✅ UNIQUE [`action`, `garment_part`] para evitar duplicados
- ✅ FK renombradas con sufijo `_fk` para claridad

```sql
- id, codigo (UNIQUE, auto-generado)
- action, garment_part (UNIQUE juntos)
- section_id, location, measure, quantity, description, sam_base
- product_sheet_id_reference, machine_id_reference
- is_active, timestamps, softDeletes
```

---

#### 4️⃣ `2026_01_06_141921_create_product_operation_lists_table.php`
**Antes:** Consolidada  
**Ahora:** ✅ Migración independiente + **MEJORA: Preparada para renombrado futuro**

Mejoras:
- ✅ Comentarios en TODOS los campos explicando PAX vs temporal
- ✅ Campos de cálculo claramente separados (sam_base, sam_total_unitario, etc.)
- ✅ Índices optimizados: `(product_sheet_id, sequence)` para búsquedas rápidas
- ✅ Soft deletes para preservar histórico

```sql
- id, product_sheet_id (FK CASCADE), operation_id (FK), machine_id (FK)
- sequence, quantity, fabric_base_id
- sam_* fields: sam_base, sam_total_unitario, sam_total_operacion
- Tiempos: optimal_time, production_time, actual_time, variance
- notes, timestamps, softDeletes
```

---

### 🔴 **CRÍTICO MEJORADO:** Production Order Operations (Snapshot Congelado)

**Archivo:** `2026_02_10_000005_create_production_order_operations_table.php`

#### Mejoras Aplicadas:

✅ **Enum Status Expandido** (3 → 5 valores)
```php
// Antes: ['PENDIENTE', 'EN_PROCESO', 'COMPLETADA']
// Ahora:
['PENDIENTE', 'EN_EJECUCION', 'PAUSADA', 'COMPLETADA', 'CANCELADA']
```

✅ **Campo Nuevo: motivo_progreso**
```sql
- text column para auditoría de actualización de progreso
- Explica POR QUÉ se cambió el progreso
```

✅ **Comentarios Detallados por Campo**
- Cada campo marca si es EDITABLE o CONGELADO
- Documentación explícita de que es SNAPSHOT

✅ **FK Renombradas**
```php
- programmer_id → 'poo_programmer_fk'
- operation_id → 'poo_operation_fk'
- machine_id → 'poo_machine_fk'
- product_sheet_operation_id → 'poo_pol_fk'
```

✅ **Índices Mejorados**
```sql
- (programmer_id, sequence) ← ya existía
- status ← nuevo para filtrar por estado
- operation_id ← nuevo
- (programmer_id, status) ← nuevo para reportes rápidos
```

#### Estructura Final:

```sql
-- CONGELADO (SNAPSHOT de ProductSheetOperation)
- programa_id (FK → programmers, CASCADE)
- product_sheet_operation_id (FK → product_operation_lists, nullOnDelete)
- sequence, operation_id, machine_id
- quantity, sam_base, machine_supplement_factor, fabric_base_factor, operator_efficiency_factor
- sam_total_unitario, sam_total_operacion, sam_total_lote

-- EDITABLE (PROGRESO)
- quantity_progressed ← ÚNICO CAMPO EDITABLE
- motivo_progreso ← AUDITORÍA de por qué se actualizó
- status ← Estado de ejecución
- progressed_at ← Timestamp de última actualización

-- AUDITORÍA
- timestamps, softDeletes
```

---

### 🧹 Consolidación de Auditoría (Nueva Migración)

**Archivo:** `2026_02_25_000000_consolidate_audit_logs.php`

**Acción:**
1. Copia datos de `operation_audit_logs` → `audit_logs` (auditable_type='Operation')
2. Copia datos de `product_sheet_operation_history` → `audit_logs` (auditable_type='ProductSheetOperation')
3. ✅ DROP `operation_audit_logs`
4. ✅ DROP `product_sheet_operation_history`

**Resultado:** Sistema centralizado único de auditoría

---

### 📝 Renombrado de Tabla (Nueva Migración)

**Archivo:** `2026_03_01_000000_rename_product_operation_lists_table.php`

**Acción:**
- Renombra: `product_operation_lists` → `product_sheet_operations`
- Actualiza automáticamente todas las FK

**Nota:** También requiere renombrado de modelo:
```
app/Models/ProductSheet/ProductOperationList.php 
↓
app/Models/ProductSheet/ProductSheetOperation.php
```

---

## 🔄 Orden de Ejecución Recomendado

```
1. 2026_01_06_141918_create_machines_table.php
2. 2026_01_06_141919_create_operation_sections_table.php
3. 2026_01_06_141920_create_operations_table.php
4. 2026_01_06_141921_create_product_operation_lists_table.php
5. 2026_02_10_000005_create_production_order_operations_table.php (MEJORADA)
6. (Existentes: 2026_02_10_000004 product_sheet_operation_history - DEPRECADA)
7. (Existentes: 2026_02_19_093350 operation_audit_logs - DEPRECADA)
8. 2026_02_25_000000_consolidate_audit_logs.php
9. 2026_03_01_000000_rename_product_operation_lists_table.php
```

---

## 📊 Tabla de Comparación

| Aspecto | Antes | Después | Beneficio |
|---------|-------|---------|-----------|
| Cantidad de archivos | 1 monolítico | 6 separados | ✅ Mantenibilidad |
| Rollback selectivo | ❌ Imposible | ✅ Por tabla | ✅ Seguridad |
| ENUM validado | ❌ String `type` | ✅ operation_sections.type | ✅ Integridad BD |
| Status ProductOrder | 3 valores | ✅ 5 valores | ✅ Flexibilidad |
| Auditoría motivo | ❌ No | ✅ motivo_progreso | ✅ Trazabilidad |
| Comentarios | Pocos | ✅ En cada campo | ✅ Documentación |
| Auditoría redundante | ❌ 2 tablas | ✅ 1 sistema | ✅ Limpieza |

---

## ✅ Checklist Final

- [x] Separar machines en archivo independiente
- [x] Separar operation_sections en archivo independiente
- [x] Separar operations en archivo independiente
- [x] Separar product_operation_lists en archivo independiente
- [x] Mejorar enum operation_sections.type a ENUM validado
- [x] Agregar comentarios a operations (explicar código auto-generado)
- [x] Mejorar production_order_operations con:
  - [x] Status extendido (5 valores)
  - [x] Nuevo campo motivo_progreso
  - [x] Comentarios CONGELADO/EDITABLE
  - [x] FK renombradas
  - [x] Índices mejorados
- [x] Crear migración de consolidación de auditoría
- [x] Crear migración de renombrado de tabla
- [x] Documentar orden de ejecución

---

## 🚀 Próximos Pasos

1. ✅ Ejecutar migraciones: `php artisan migrate`
2. ⏭️ Validar que operations tienen código auto-generado (backend)
3. ⏭️ Actualizar modelo ProductSheetOperation (remover alias)
4. ⏭️ Actualizar enum EstatusOperacionOrden en backend
5. ⏭️ Agregar trait HasAuditLabel a ProductionOrderOperation
6. ⏭️ Tests Pest para validar snapshots congelados
7. ⏭️ Actualizar copilot-instructions.md con decisiones

---

**Documento finalizado:** 23 de febrero de 2026  
**Estado:** ✅ Migraciones Refactorizadas y Listas para Deploy
