# Requerimiento de Implementación: Movimientos de Inventario y Costo Promedio Ponderado

## Objetivo

Asegurar que todos los movimientos de inventario relevantes sean registrados correctamente, con cálculo automático del costo promedio ponderado, trazabilidad completa y sin manipulación directa de stock/costo fuera del servicio centralizado.

---

## Alcance

- Todas las operaciones que afecten inventario: compras, ajustes, devoluciones, consumos, traslados.
- Kardex histórico y auditable.
- Stock y costo promedio actualizados tras cada movimiento.

---

## Requerimientos Funcionales

1. **Registrar movimiento de inventario** en cada uno de los siguientes casos:
   - Aprobación de factura de compra (entrada)
   - Ajuste de inventario (entrada/salida)
   - Devolución de venta (entrada)
   - Devolución de compra (salida)
   - Consumo de producción (salida)
   - Traslado entre bodegas (salida en origen, entrada en destino)

2. **Cálculo del costo promedio ponderado**:
   - Solo se recalcula en entradas con valor (compras, devoluciones de venta, ajustes positivos).
   - Las salidas usan el costo promedio vigente, no lo recalculan.

3. **Datos obligatorios en cada movimiento**:
   - Usuario responsable (`created_by_user_id`)
   - Motivo u observación (`notes`)
   - Precio unitario en entradas (`unit_price`)

4. **Prohibido manipular stock/costo promedio directamente**:
   - Todo debe pasar por `InventoryMovementService::createMovement`.

5. **Trazabilidad y auditoría**:
   - El kardex debe ser histórico, no editable.
   - No se permite eliminar movimientos (solo soft delete/auditoría).

6. **Pruebas y validaciones**:
   - Deben existir tests unitarios y de integración para cada flujo.
   - Validar que el stock y el costo promedio se actualizan correctamente.

---

## Requerimientos Técnicos

- Adaptar todos los controladores y servicios para usar el servicio centralizado de movimientos.
- Validar en frontend y backend que los datos obligatorios estén presentes.
- Implementar doble movimiento en traslados.
- Documentar el flujo y capacitar al equipo.

---

## Criterios de Aceptación

- Todos los movimientos relevantes quedan registrados en el kardex.
- El stock y el costo promedio se actualizan correctamente tras cada operación.
- No existen manipulaciones directas de stock/costo fuera del servicio.
- El usuario y motivo quedan registrados en cada movimiento.
- El sistema pasa los tests de integración para todos los flujos.

---

**Este requerimiento debe ser implementado y validado antes de pasar a producción.**
