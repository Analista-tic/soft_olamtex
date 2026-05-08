# Ficha Técnica: Análisis de BD y almacenamiento de datos

## 1. Revisión de infraestructura de base de datos

### 1.1 Tablas existentes (inventario)

| Dominio        | Tablas |
|----------------|--------|
| **Auth/System** | users, password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, permissions (+ roles, model_has_*, role_has_*) |
| **Config**     | settings, company_settings, taxes, retentions |
| **Master Data** | category_groups, category_sub_groups, items, contacts |
| **Compras**    | purchase_documents, purchase_document_items, purchase_requests |
| **Inventario** | warehouses, warehouse_items, warehouse_transfers, warehouse_transfer_items, item_inventories, inventory_movements, inventory_requests, inventory_request_items, inventory_adjustments, inventory_adjustment_lines, inventory_period_locks |
| **Producción** | product_sheets, product_sheet_operations, product_sheet_operation_history, programmers (production_orders), technical_sheets, technical_sheet_operations, **technical_sheet_processes**, operations, operation_sections, machines, production_roll_consumptions |
| **Textil**     | fabric_rolls |
| **Auditoría**  | audit_logs |

### 1.2 ¿Hay demasiadas tablas?

- **No.** El número de tablas es coherente con un sistema ERP/modular:
  - Cada agregado (Órdenes, Inventario, Compras, Ficha Técnica) tiene sus propias tablas.
  - Las tablas de la ficha técnica (technical_sheets, technical_sheet_operations, technical_sheet_processes) siguen el patrón **una tabla raíz + tablas hijas por concepto**.
- **Recomendación:** Mantener este modelo. Añadir tablas hijas bajo `technical_sheets` para Rollos, Hilos, Insumos y Materia Prima es la opción correcta: misma raíz (TechnicalSheet), datos vinculados por `technical_sheet_id`, consultas y backups simples.

### 1.3 Estado actual del almacenamiento por pestaña

| Pestaña        | Almacenamiento actual     | Persistencia | Comentario |
|----------------|---------------------------|--------------|------------|
| **Procesos**   | BD (`technical_sheet_processes`) | ✅ Fija      | Ya migrado; costos fijos. |
| **Materia prima** | **localStorage** (`tDS_rawMaterial_${opId}`) | ❌ Solo cliente | No hay API; se pierde al cambiar dispositivo/limpiar. |
| **Rollos**     | Archivo JSON (`production/op_{id}_rolls.json`) | ⚠️ Frágil    | Storage en disco; sin FK, sin auditoría. |
| **Hilos**      | Archivo JSON (`production/op_{id}_threads.json`) | ⚠️ Frágil    | Igual que rollos. |
| **Insumos**    | Archivo JSON (`production/op_{id}_supplies.json`) | ⚠️ Frágil    | Igual; además el Store tiene lógica de última compra. |
| **Info adicional** | Archivo JSON (`production/op_{id}_additional_info.json`) | ⚠️ Frágil | No revisado en detalle aquí. |

### 1.4 Conclusión de diseño

- **Una tabla por tipo de dato de ficha** (rolls, threads, supplies, raw_material) vinculada a `technical_sheets` es la opción más clara:
  - Permite FKs, índices, auditoría y backups consistentes.
  - Evita JSON gigantes en una sola columna y permite consultas por tipo.
- **No conviene** un solo JSON en `technical_sheets` para todo: difícil de consultar, versionar y auditar.
- **Estructura recomendada:**
  - `technical_sheets` (ya existe)
  - `technical_sheet_processes` (ya existe)
  - `technical_sheet_rolls` (params + líneas de rollo)
  - `technical_sheet_roll_lines` (una fila por rollo/línea)
  - `technical_sheet_threads` (params + líneas de hilo)
  - `technical_sheet_thread_lines`
  - `technical_sheet_supplies` (ítems de insumos)
  - `technical_sheet_raw_materials` (filas de materia prima)

Alternativa más compacta: **una tabla por pestaña con payload JSON** (ej. `technical_sheet_rolls` con `params` JSON y `lines` JSON). Menos tablas, pero se pierde normalización y consultas por campo. Para una ficha técnica por OP, el volumen es bajo; se puede elegir entre **tablas normalizadas** (mejor para reportes y auditoría) o **una tabla + JSON por pestaña** (menos migraciones).

---

## 2. Plan de almacenamiento (misma idea que Procesos)

### 2.1 Objetivo

- Que los datos de **Materia prima, Rollos, Hilos e Insumos** se persistan en BD asociados a la ficha técnica (`technical_sheet_id`), con la misma filosofía que Procesos: datos fijos por OP, sin depender de JSON en disco ni de localStorage.

### 2.2 Opción A: Tablas normalizadas (recomendada a largo plazo)

- **technical_sheet_roll_params**: una fila por OP (trazo, defaultExtra).
- **technical_sheet_roll_lines**: id, technical_sheet_id, consecutive, type, fabric_name, consumption_base, price_per_meter, etc.
- **technical_sheet_thread_params**: una fila por OP.
- **technical_sheet_thread_lines**: id, technical_sheet_id, thread_name, gauge, cost_per_garment, etc.
- **technical_sheet_supply_items**: id, technical_sheet_id, item_id, qty_per_unit, unit_cost, etc.
- **technical_sheet_raw_material_lines**: id, technical_sheet_id, tela_item_id, tela, ancho, prom_trazo, consumo, last_unit_price_cop, etc.

Ventajas: consultas SQL, reportes, integridad referencial. Desventaja: más migraciones y más código de mapeo.

### 2.3 Opción B: Una tabla por pestaña con JSON (rápida)

- **technical_sheet_rolls**: technical_sheet_id, params (JSON), lines (JSON), timestamps.
- **technical_sheet_threads**: technical_sheet_id, params (JSON), lines (JSON).
- **technical_sheet_supplies**: technical_sheet_id, items (JSON).
- **technical_sheet_raw_materials**: technical_sheet_id, rows (JSON).

Ventajas: pocas tablas, reutilizar estructura actual de los Stores. Desventajas: menos normalizado, consultas por campo más incómodas.

### 2.4 Recomendación

- **Corto plazo (esta tarea):** Opción B para **Rollos, Hilos, Insumos**: una tabla por pestaña con JSON, leer/escribir desde el servicio igual que ahora pero contra BD usando `technical_sheet_id`. Así se unifica todo bajo la ficha técnica y se deja de usar archivos en `storage/production/`.
- **Materia prima:** Hoy solo está en localStorage y no hay API. Opciones: (1) Añadir API + tabla `technical_sheet_raw_materials` (JSON o normalizada) y que el front llame al backend para cargar/guardar, o (2) dejar Materia prima para una segunda fase y solo documentar que debe migrarse.
- **Largo plazo:** Si hace falta reportear por rollo/hilo/insumo a nivel global, se pueden crear después tablas normalizadas y migrar desde el JSON.

---

## 3. Resumen ejecutivo

- **BD:** El número y diseño de tablas es razonable; no hay “exceso” de tablas.
- **Procesos:** Ya almacenados en BD con costos fijos.
- **Rollos, Hilos, Insumos:** Pasar de JSON en disco a tablas en BD (una por pestaña, con JSON o normalizado) vinculadas a `technical_sheets`.
- **Materia prima:** Tabla y API listas; falta que **RawMaterialTab.vue** use la API en lugar de localStorage.

### Implementación realizada (Rollos, Hilos, Insumos, Materia prima)

- **technical_sheet_rolls / threads / supplies:** Stores leen de BD primero, fallback a JSON en disco; al guardar persisten en BD y en archivo.
- **technical_sheet_raw_materials:** Store y API `GET/PUT ficha-tecnica/{opId}/materia-prima` creados. RawMaterialTab carga desde API (fallback a localStorage) y guarda vía PUT; manejo de errores 422 (validación) y estado de carga inicial.
- **Auditoría:** Modelos TechnicalSheetRoll, TechnicalSheetThread, TechnicalSheetSupply, TechnicalSheetRawMaterial registrados en AppServiceProvider para AuditObserver.
