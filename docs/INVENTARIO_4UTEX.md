# Módulo de Inventario 4UTEX – Documento consolidado

Documento maestro único que consolida el análisis, las mejoras, la arquitectura y el plan de implementación del módulo de inventario.

---

# Parte 1 – Contexto y flujo actual

## 1.1 Flujo actual

1. **InventoryMovementService::createMovement** – punto central para crear movimientos
2. **Fuentes de movimientos**: Facturas aprobadas (PurchaseDocumentInventoryObserver), Ajustes (InventoryAdjustmentService), Traslados (WarehouseTransferService), Solicitudes (InventoryRequestService)
3. **Almacenamiento**: `warehouse_items` (cantidad, weighted_average_price), `inventory_movements` (historial)

## 1.2 Reglas de cálculo (correctas)

- **Entradas**: Nuevo PP = (StockAnterior × PPAnterior + Cantidad × PrecioCompra) / StockTotal
- **Salidas**: PP no cambia, valorización = cantidad × PP vigente
- **Devolución venta**: Entrada al PP vigente
- **Devolución compra**: Salida al PP vigente
- **Traslado**: Salida en origen (PP no cambia), Entrada en destino al PP del origen

## 1.3 Problemas detectados

| # | Problema | Impacto |
|---|----------|---------|
| 1 | `items.weighted_average_price` es global pero el inventario es por bodega | PP incorrecto al consultar por bodega |
| 2 | Dualidad: `ItemInventoryService` + `item_inventories` vs `InventoryMovementService` + `inventory_movements` | Reportes incoherentes con el libro de movimientos |
| 3 | Sin locking en `createMovement` | Condiciones de carrera, stock negativo o PP incoherente |
| 4 | FabricRoll y otros modifican `warehouse_items` directo | Rompe la regla de fuente única |
| 5 | Código muerto: `InventoryAdjustmentController::applyStockMovement` | Confusión y mantenimiento |

## 1.4 Correcciones ya aplicadas

- **getWeightedPrice**: Si se recibe `warehouseId`, no usar `items.weighted_average_price` y tomar el PP del último movimiento del ítem en esa bodega.
- **updateItemWeightedPrice**: Mantener el caché solo para consultas sin bodega; migrar PP a `warehouse_items`.

## 1.5 Checklist de flujos

| Flujo | Estado | Implementación |
|-------|--------|----------------|
| Aprobación factura compra | ✓ | PurchaseDocumentInventoryObserver |
| Ajuste inventario | ✓ | InventoryAdjustmentService → createMovement |
| Devolución venta | ✓ | createSaleReturn |
| Devolución compra | ✓ | createPurchaseReturn |
| Traslado entre bodegas | ✓ | createTransfer |
| Consumo producción | ✓ | FabricRollService + production_roll_consumptions; pasar production_order_id desde OP |
| FabricRoll / ItemService | ✓ | syncWarehouseLengthForItem eliminado; stock solo vía movimientos |

**Regla central:** Todo cambio de stock pasa por `InventoryMovementService::createMovement`. No manipular `warehouse_items` fuera del servicio.

---

# Parte 2 – Principios arquitectónicos

## 2.1 `inventory_movements` es la única fuente de verdad

- Nadie modifica stock directo.
- Nadie actualiza `warehouse_items.quantity` manualmente.
- Todo pasa por: `InventoryMovementService::createMovement()`

## 2.2 `warehouse_items` es estado derivado optimizado

No es fuente primaria. Es caché consistente con movimientos.

- `quantity`
- `weighted_average_price`

## 2.3 Movimientos son inmutables

- Nunca se editan.
- Si hay error: `reverseMovement()` crea un movimiento contrapartida.
- Auditable. Limpio. Profesional.

---

# Parte 3 – Estructura final de base de datos

## 3.1 Tabla `items`

| Acción | Columna | Motivo |
|--------|---------|--------|
| Eliminar | `weighted_average_price` | ✓ Hecho. El PP vive en `warehouse_items` |
| Agregar | `costing_method` | Future ready: weighted_average \| fifo \| lifo |
| Mantener | `negative_movement` | Permite stock negativo sí/no |

## 3.2 Tabla `warehouse_items`

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint | PK |
| item_id | bigint | FK items |
| warehouse_id | bigint | FK warehouses |
| quantity | decimal(20,6) | Stock actual |
| weighted_average_price | decimal(20,6) | PP vigente por bodega |
| stock_min, stock_max | decimal(20,6) | Opcional – alertas |
| updated_at | timestamp | |

**Índice único:** `unique(item_id, warehouse_id)` – evita duplicados, suficiente para el volumen actual

## 3.3 Tabla `inventory_movements` (libro contable)

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint | PK |
| item_id, warehouse_id | bigint | FK |
| type | enum('entrada','salida') | Dirección |
| quantity | decimal(20,6) | Siempre positiva |
| unit_cost, total_cost | decimal(20,6) | Costo aplicado |
| **resulting_quantity** | decimal(20,6) | Stock después del movimiento |
| **resulting_weighted_price** | decimal(20,6) | PP vigente después |
| source_type, source_id | string, bigint | Origen (purchase_document, adjustment, transfer, production_order, …) |
| document_number, notes | string, text | Consecutivo y motivo |
| created_by_user_id | bigint | Usuario que registró |
| created_at | timestamp | |

**Sin soft delete:** El movimiento nunca se borra. Solo se revierte con `reverseMovement()`.

**Importante:** `resulting_quantity` y `resulting_weighted_price` evitan recalcular historia.

**Índices:**
- `index(item_id, warehouse_id, created_at)` – consultas por item/bodega e historial
- `index(source_type, source_id)` – trazabilidad por documento origen

## 3.4 Tabla `fabric_rolls`

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint | PK |
| item_id, warehouse_id | bigint | FK |
| meters_remaining, original_meters | decimal(20,6) | Metros |
| unit_cost | decimal(20,6) | Costo unitario |
| status | enum | active, consumed, scrapped |
| purchase_document_item_id | bigint nullable | Origen factura |

## 3.5 Tabla `production_roll_consumptions`

| Columna | Tipo | Descripción |
|---------|------|-------------|
| id | bigint | PK |
| production_order_id | bigint | FK |
| fabric_roll_id | bigint | FK |
| meters_consumed | decimal(20,6) | Metros consumidos |
| created_at | timestamp | |

---

# Parte 4 – Flujo correcto de movimientos

## 4.1 Entrada (compra / ajuste positivo / devolución venta)

1. Lock `warehouse_items` para (item_id, warehouse_id)
2. Leer stock actual y PP actual
3. Calcular:
   ```
   nuevo_total = (stock_actual × pp_actual) + (cantidad × costo_unitario)
   nuevo_stock = stock_actual + cantidad
   nuevo_pp = nuevo_stock == 0 ? 0 : nuevo_total / nuevo_stock
   ```
   *Manejo división por cero:* si `nuevo_stock == 0`, entonces `nuevo_pp = 0`. Sin lógica compleja.
4. Crear movimiento con `resulting_quantity`, `resulting_weighted_price`
5. Actualizar `warehouse_items`

## 4.2 Salida (venta / producción / ajuste negativo)

1. Lock `warehouse_items`
2. `unit_cost` = PP actual (o último conocido si stock negativo permitido)
3. `resulting_quantity` = stock_actual − cantidad
4. `resulting_weighted_price` = PP actual (no cambia)
5. Crear movimiento y actualizar `warehouse_items`

## 4.3 Traslado

1. Salida en origen con PP vigente
2. Entrada en destino con `unit_cost` = PP del origen

---

# Parte 5 – FabricRoll: separado pero consistente

**Decisión:** Rollos tienen tabla propia; inventario general vía movimientos.

### Al recibir rollo (factura aprobada)

- Crear/actualizar `fabric_rolls`
- Crear movimiento de entrada vía `createMovement`

### Al consumir rollo en producción

Flujo ya correcto. No se necesita lote contable adicional todavía:

1. Reducir `fabric_rolls.meters_remaining`
2. Crear `production_roll_consumptions`
3. Crear movimiento: type=salida, quantity=metros_consumed, unit_cost=roll.unit_cost, source_type=production_order

---

# Parte 6 – Stock negativo (comportamiento simple)

Regla clara:

- **Permitido** solo si `item.negative_movement = true`
- **Se usa** el PP vigente (último `resulting_weighted_price` conocido)
- **No se recalcula** historia
- **Cuando entre compra**, el PP se ajusta naturalmente
- Nada más sofisticado por ahora

---

# Parte 7 – Bloqueo de período (simple)

Solo validación mínima:

```php
if ($periodIsClosed) {
    throw new Exception("Periodo cerrado");
}
```

No se necesita sistema contable completo aún.

**Validación enum:** `inventory_movements.type` = `entrada` | `salida`. Los ajustes mapean a uno u otro según el signo.

---

# Parte 8 – Mejoras priorizadas

| Prioridad | Mejora | Impacto | Esfuerzo |
|----------|--------|---------|----------|
| Alta | Unificar fuentes de costo (eliminar dualidad ItemInventoryService) | Alto | Medio |
| Alta | PP por bodega en `warehouse_items` | Alto | Bajo |
| Alta | Locking pesimista en `createMovement` | Crítico | Bajo |
| Alta | Centralizar FabricRoll y flujos legacy | Alto | Medio |
| Media | Unificar `item_inventories` con movimientos | Medio | Medio |
| Media | Consumo producción vía movimientos | Alto | Medio |
| Media | Reconciliación stock vs movimientos | Alto | Medio |
| Baja | Eliminar código muerto | Bajo | Bajo |
| Baja | Métodos FIFO/LIFO (largo plazo) | Medio | Alto |

---

# Parte 9 – Orden de implementación

## FASE 1 – Seguridad crítica

| # | Tarea |
|---|-------|
| 1 | Eliminar `deleted_at` y `SoftDeletes` de `inventory_movements` (modelo y migración) |
| 2 | Eliminar `InventoryAdjustmentController::applyStockMovement` |
| 3 | Deprecar `InventoryStockService::applyStockMovement` |
| 4 | Forzar uso único de `InventoryMovementService::createMovement` |
| 5 | Locking pesimista: `lockForUpdate()` en lectura de warehouse_items |
| 6 | Movimientos inmutables; implementar `reverseMovement()` |

## FASE 2 – Estructura base

| # | Tarea |
|---|-------|
| 7 | Agregar `resulting_quantity`, `resulting_weighted_price` a inventory_movements |
| 8 | Agregar índices en inventory_movements: `(item_id, warehouse_id, created_at)` y `(source_type, source_id)` |
| 9 | Agregar `weighted_average_price` a warehouse_items; asegurar `unique(item_id, warehouse_id)` |
| 10 | Eliminar `items.weighted_average_price` ✓ |

## FASE 3 – FabricRoll

| # | Tarea |
|---|-------|
| 11 | Crear tabla `production_roll_consumptions` |
| 12 | Modificar consumo producción para crear consumo + movimiento |
| 13 | Consumo de rollo genera salida en inventario |

## FASE 4 – Bloqueo de período (simple) ✓

| # | Tarea |
|---|-------|
| 14 | Validación: si periodo cerrado → `throw new RuntimeException("... período cerrado")` |
| 14b | Tabla `inventory_period_locks`, comando `php artisan inventory:close-period {year} {month}` |

---

# Resultado final (modo "Correcto")

## ✔ Lo que tendrás

| # | Logro |
|---|-------|
| ✓ | PP por bodega correcto |
| ✓ | Movimientos inmutables |
| ✓ | Trazabilidad por rollo |
| ✓ | Stock negativo permitido con control |
| ✓ | Sin condiciones de carrera |
| ✓ | Libro consistente |
| ✓ | Preparado para crecer |

## ❌ Lo que NO incluye (y está bien para tu etapa)

| # | Excluido |
|---|----------|
| ✗ | Contabilidad automática compleja |
| ✗ | FIFO/LIFO |
| ✗ | Cálculos retroactivos |
| ✗ | Reconciliación automática avanzada |

---

# Honestamente

Para una empresa textil mediana real:

**Esto ya es suficiente.**

No necesitas más ahora.

---

# Próximo paso recomendado

Lo más importante no es agregar más arquitectura, sino **ejecutar**:

1. **Refactorizar** `InventoryMovementService` correctamente
2. **Migrar** columnas (resulting_*, weighted_average_price, índices)
3. **Forzar** que nadie toque `warehouse_items` fuera del servicio
4. **Pruebas manuales** con casos reales:
   - Compra
   - Venta
   - Stock negativo
   - Compra posterior (recalcular PP)
   - Traslado
   - Consumo rollo

**Eso es lo que realmente valida un ERP.**
