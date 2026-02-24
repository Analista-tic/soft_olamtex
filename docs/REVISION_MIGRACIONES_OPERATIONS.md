# Revisión de Migraciones - Módulo de Operations

**Fecha:** 23 de febrero de 2026  
**Estado:** Análisis Completo

---

## 🔍 Hallazgos Principales

### ❌ PROBLEMA: Migraciones Consolidadas (Deben Separarse)

**Archivo:** `2026_01_06_141918_create_product_operation_lists_table.php`

Este archivo contiene **4 tablas en una sola migración**:

```php
1. machines                 ← Máquinas de producción
2. operation_sections       ← Secciones de operaciones
3. operations              ← Catálogo maestro de operaciones
4. product_operation_lists ← Operaciones de ProductSheet (template)
```

**Impacto:**
- Difícil de mantener y versionar
- Si una tabla falla, todas fallan
- Imposible hacer rollback selectivo
- Rompe el patrón de 1 tabla = 1 migración

**Solución:** Crear 4 migraciones separadas:

```
2026_01_06_141918_create_machines_table.php
2026_01_06_141919_create_operation_sections_table.php
2026_01_06_141920_create_operations_table.php
2026_01_06_141921_create_product_operation_lists_table.php
```

---

## 🎯 Migraciones de Operations Identificadas

### 1️⃣ Machines (Máquinas de Producción)

**Archivo Actual:** Dentro de `2026_01_06_141918_create_product_operation_lists_table.php`

**Estructura:**
```sql
- id (PK)
- code (UNIQUE)
- name (UNIQUE)
- supplement_factor (DECIMAL 8,4) = 1.0 por defecto
- is_active (BOOLEAN)
- timestamps + softDeletes
```

**Estado:** ✅ Bien estructurada, necesita extraer a archivo propio

---

### 2️⃣ Operation Sections (Secciones de Operaciones)

**Archivo Actual:** Dentro de `2026_01_06_141918_create_product_operation_lists_table.php`

**Estructura:**
```sql
- id (PK)
- name
- type (¿qué valores? - SIN DOCUMENTAR)
- is_active (BOOLEAN)
- timestamps + softDeletes
```

**⚠️ Problemas:**
- Campo `type` sin documentación de valores válidos
- Debería tener constraint ENUM o validación explícita

**Recomendación:** Documentar valores: `['confeccion', 'presentacion']` (per copilot-instructions.md)

---

### 3️⃣ Operations (Catálogo Maestro)

**Archivo Actual:** Dentro de `2026_01_06_141918_create_product_operation_lists_table.php`

**Estructura:**
```sql
- id (PK)
- codigo (VARCHAR 50, UNIQUE) - AUTO-GENERADO EN BACKEND
- action (VARCHAR)
- garment_part (VARCHAR)
- section_id (FK → operation_sections)
- location (VARCHAR 100) nullable
- measure (DECIMAL 8,4) nullable
- quantity (INT) nullable
- description (TEXT) nullable
- sam_base (DECIMAL 8,4) nullable
- product_sheet_id_reference (FK → product_sheets) nullable
- machine_id_reference (FK → machines) nullable
- is_active (BOOLEAN)
- UNIQUE(['action', 'garment_part'])
- timestamps + softDeletes
```

**✅ Estado:** Bien estructurada, operación no-editable (código auto-generado)

---

### 4️⃣ Product Operation Lists (Operaciones de ProductSheet)

**Archivo Actual:** Dentro de `2026_01_06_141918_create_product_operation_lists_table.php`

**Nombre en BD:** `product_operation_lists`  
**Modelo:** `ProductSheetOperation` (alias confuso)

**Estructura:**
```sql
- id (PK)
- product_sheet_id (FK, CASCADE)
- operation_id (FK, RESTRICT)
- machine_id (FK, RESTRICT)
- operation_section_id (FK, RESTRICT)
- sequence (INT) = 10
- fabric_base_id (nullable)
- quantity (INT) = 1
- sam_base, sam, operator_efficiency_factor, machine_supplement_factor, fabric_base_factor
- sam_total_unitario, sam_total_operacion
- optimal_time, production_time, actual_time, variance (ALL NULLABLE)
- notes (TEXT) nullable
- is_active (BOOLEAN)
- timestamps + softDeletes
```

**⚠️ Problemas:**
- Demasiados campos calculados (optimal_time, actual_time, variance)
- Mixtura de datos template + ejecución
- Nombre confuso: "product_operation_lists" vs "ProductSheetOperation"

**Recomendación:** Este archivo debe renombrarse a `product_sheet_operations` (per optimization doc)

---

### ⚠️ REDUNDANTE: Product Sheet Operation History

**Archivo:** `2026_02_10_000004_create_product_sheet_operation_history_table.php`

**Estructura:**
```sql
- id (PK)
- product_sheet_operation_id (FK → product_operation_lists)
- sam_base_anterior, sam_base_nuevo
- machine_supplement_factor_anterior, machine_supplement_factor_nuevo
- fabric_base_factor_anterior, fabric_base_factor_nuevo
- operator_efficiency_factor_anterior, operator_efficiency_factor_nuevo
- sam_total_unitario_anterior, sam_total_unitario_nuevo
- sam_total_operacion_anterior, sam_total_operacion_nuevo
- motivo (TEXT) nullable
- usuario_id (FK → users)
- timestamps
```

**❌ ESTADO:** REDUNDANTE - debe consolidarse a `AuditLog` centralizado

**Alternativa:** Dejar que `AuditObserver` registre cambios automáticamente en `audit_logs` con `old_values` y `new_values` en JSON

**Migración de Rolback:** Migración que consolide registros a `AuditLog` y luego drop tabla

---

### 🔴 CRÍTICO: Production Order Operations (Listado Operacional Snapshot)

**Archivo:** `2026_02_10_000005_create_production_order_operations_table.php`

**Tabla:** `production_order_operations`  
**Modelo:** `ProductionOrderOperation`

**Estructura:**
```sql
- id (PK)
- programmer_id (FK → programmers, CASCADE)
- product_sheet_operation_id (FK → product_operation_lists, nullOnDelete) ← SNAPSHOT SOURCE
- sequence (INT)
- operation_id (FK → operations, RESTRICT)
- machine_id (FK → machines, SET NULL)
- quantity (INT) = 1
- sam_base, machine_supplement_factor, fabric_base_factor, operator_efficiency_factor (ALL DECIMAL)
- sam_total_unitario, sam_total_operacion, sam_total_lote (CALCULATED, PERSISTED)
- quantity_progressed (INT) = 0 ← SOLO ESTO DEBE CAMBIAR
- status (ENUM['PENDIENTE', 'EN_PROCESO', 'COMPLETADA']) = 'PENDIENTE'
- progressed_at (TIMESTAMP) nullable
- timestamps + softDeletes
```

**✅ EXCELENTE ESTRUCTURA para Listado Operacional Snapshot**

**Mejoras recomendadas (per optimization doc):**
- [ ] Agregar `HasAuditLabel` trait (audit_logs registran todos EXCEPTO quantity_progressed)
- [ ] Cambiar ENUM a `EstatusOperacionOrden` con más estados:
  ```php
  ['PENDIENTE', 'EN_EJECUCION', 'PAUSADA', 'COMPLETADA', 'CANCELADA']
  ```
- [ ] Agregar columna `motivo_progreso` (TEXT) para capturar razón de actualización
- [ ] Índice en `(programmer_id, sequence)` ✅ ya existe

---

### ⚠️ REDUNDANTE: Operation Audit Logs

**Archivo:** `2026_02_19_093350_create_operation_audit_logs_table.php`

**Estructura:**
```sql
- id (PK)
- operation_id (FK → operations, CASCADE)
- field_changed (VARCHAR 50)
- old_value (TEXT) nullable
- new_value (TEXT) nullable
- motivo (TEXT) nullable
- changed_by (FK → users)
- timestamps
```

**❌ ESTADO:** REDUNDANTE - debe consolidarse a `AuditLog` centralizado

**Alternativa:** Usar `AuditObserver` que registra automáticamente en `audit_logs`

---

## 📋 Tabla Comparativa

| Tabla | Archivo | Estado | Acción |
|-------|---------|--------|--------|
| `machines` | Consolidada | ✅ Bien | **Extraer a archivo propio** |
| `operation_sections` | Consolidada | ⚠️ Sin validación tipo | **Extraer + ADD CONSTRAINT** |
| `operations` | Consolidada | ✅ Bien | **Extraer a archivo propio** |
| `product_operation_lists` | Consolidada | ⚠️ Mixto | **Extraer + Renombrar a `product_sheet_operations`** |
| `product_sheet_operation_history` | Separado | ❌ Redundante | **Consolidar a AuditLog + DROP** |
| `production_order_operations` | Separado | ✅ Crítico | **Mejorar status enum** |
| `operation_audit_logs` | Separado | ❌ Redundante | **Consolidar a AuditLog + DROP** |

---

## 🔧 Plan de Refactorización

### Paso 1: Crear Migraciones Separadas (Semana 1)

```bash
# Extraer de 2026_01_06_141918
2026_01_06_141918_create_machines_table.php
2026_01_06_141919_create_operation_sections_table.php
2026_01_06_141920_create_operations_table.php
2026_01_06_141921_create_product_operation_lists_table.php (preparar para renombrado)
```

### Paso 2: Crear Migración de Renombrado (Semana 2)

```bash
# Renombrar product_operation_lists → product_sheet_operations
2026_02_20_000000_rename_product_operation_lists_table.php
```

### Paso 3: Mejorar Enums (Semana 1)

```php
// 2026_02_10_000005_create_production_order_operations_table.php
$table->enum('status', [
    'PENDIENTE',
    'EN_EJECUCION',
    'PAUSADA',
    'COMPLETADA',
    'CANCELADA'
])->default('PENDIENTE');
```

### Paso 4: Consolidar Auditoría (Semana 3)

```bash
# Crear migración que consolida datos y elimina redundancia
2026_02_25_000000_consolidate_audit_logs.php
  - Copiar registros de operation_audit_logs → audit_logs
  - Copiar registros de product_sheet_operation_history → audit_logs
  - DROP TABLE operation_audit_logs
  - DROP TABLE product_sheet_operation_history
```

---

## ✅ Checklist de Migraciones

- [ ] Crear migración de machines separada
- [ ] Crear migración de operation_sections separada
- [ ] Crear migración de operations separada
- [ ] Crear migración de product_operation_lists separada
- [ ] Reconsiderar orden de dependencies (FK foreign keys deben crearse en orden correcto)
- [ ] Crear migración de renombrado: `product_operation_lists` → `product_sheet_operations`
- [ ] Actualizar model: `ProductOperationList` → `ProductSheetOperation` (alias removal)
- [ ] Actualizar enum status en production_order_operations con 5 estados
- [ ] Crear migración de consolidación de auditoría
- [ ] Documentar operación_sections.type enum values
- [ ] Validar todas las FK referencias y cascades

---

## 📝 Orden de Creación Recomendado

```
1. machines                          (sin FK)
2. operation_sections                (sin FK)
3. operations                        (FK: section_id, product_sheet_id_reference, machine_id_reference)
4. product_operation_lists           (FK: product_sheet_id, operation_id, machine_id, operation_section_id)
5. (existente) product_sheets
6. (existente) programmers
7. production_order_operations       (FK: programmer_id, operation_id, machine_id, product_sheet_operation_id)
8. product_sheet_operation_history   (DEPRECADO - migrar a AuditLog)
9. operation_audit_logs              (DEPRECADO - migrar a AuditLog)
10. rename product_operation_lists → product_sheet_operations
11. consolidate auditoría
```

---

**Documento finalizado:** 23 de febrero de 2026  
**Próximo paso:** Crear migraciones separadas y consolidar auditoría
