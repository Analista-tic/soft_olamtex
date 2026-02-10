# Checklist de Adecuación de Movimientos de Inventario y Costo Promedio Ponderado

## 1. Flujos que DEBEN generar movimientos de inventario

- [x] **Aprobación de factura de compra**
  - Genera movimiento de entrada con precio unitario (recalcula costo promedio)
  - Implementado: `PurchaseDocumentInventoryObserver` al actualizar factura con `approval_status = approved`.
- [x] **Ajuste de inventario**
  - Ajuste positivo: entrada con precio unitario (recalcula promedio)
  - Ajuste negativo: salida (usa costo promedio vigente)
  - Implementado: `InventoryAdjustmentService` usa `InventoryMovementService::createMovement` por línea.
- [x] **Devolución de venta**
  - Entrada con costo promedio vigente (recalcula promedio)
  - Implementado: `InventoryMovementService::createSaleReturn`.
- [x] **Devolución de compra**
  - Salida con costo promedio vigente (no recalcula promedio)
  - Implementado: `InventoryMovementService::createPurchaseReturn`.
- [ ] **Consumo de producción**
  - Salida (usa costo promedio vigente)
  - Pendiente: integrar con módulo de producción/rollos cuando corresponda.
- [x] **Traslado entre bodegas**
  - Salida en bodega origen, entrada en destino (ambos movimientos)
  - Implementado: `InventoryMovementService::createTransfer` y `POST /movimientos-inventario/transfer`.

## 2. Adaptaciones realizadas y pendientes

### a) Cobertura de orígenes
- [x] Ajustes de inventario y aprobación de factura usan `InventoryMovementService::createMovement`.
- [x] `InventoryStockService::applyStockMovement` deprecado; no usar para nuevos flujos.
- [ ] Auditar `FabricRollService` y `ItemService` (sync warehouse): aún modifican `warehouse_items` directo. Valorar migrar a movimientos.

### b) Validación de datos obligatorios
- [x] Entradas exigen `unit_price` en backend y en `InventoryMovementController::store`.
- [ ] Frontend: asegurar que formularios de movimientos/ajustes/facturas capturen y envíen `unit_price` en entradas.

### c) Responsable y motivo
- [x] Cada movimiento exige `notes` y `created_by_user_id` (por defecto usuario autenticado).
- [ ] Frontend: capturar y enviar siempre `notes` y usuario responsable donde aplique.

### d) Traslados
- [x] Doble movimiento: salida origen y entrada destino; ambos con costo promedio vigente.

### e) Auditoría y trazabilidad
- [x] Movimientos con `SoftDeletes`; no se eliminan físicamente.
- [x] `created_by_user_id` registrado en cada movimiento.

### f) Pruebas y validaciones
- [ ] Agregar tests unitarios y de integración por flujo.
- [ ] Validar actualización correcta de stock y costo promedio.

## 3. Flujo centralizado

**Regla:** Todo cambio de stock y costo promedio debe pasar por `InventoryMovementService::createMovement` (o helpers `createSaleReturn`, `createPurchaseReturn`, `createTransfer`). No manipular `warehouse_items` ni `items.weighted_average_price` fuera del servicio.

## 4. Recomendaciones adicionales

- Documentar el flujo para el equipo.
- Revisar y actualizar la documentación técnica y de usuario.
- Validar con usuarios clave los escenarios de negocio.

---

**Este checklist debe ser revisado y completado antes de ejecutar el despliegue del requerimiento.**
