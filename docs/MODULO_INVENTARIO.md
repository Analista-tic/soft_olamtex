# Módulo de Inventario

**Última actualización:** 2026-06-12
**Propósito:** Explicar qué hace el módulo de Inventario, cómo funciona, qué datos maneja y cómo se conecta con el resto del sistema.

---

## 1. ¿Qué hace este módulo?

El módulo de Inventario controla **qué productos hay en las bodegas, en qué cantidad, y todo lo que entra o sale**.

En términos simples:
- Sabe cuántas unidades de cada producto hay en cada bodega en tiempo real.
- Registra cada entrada y salida como un movimiento trazable.
- Permite corregir el stock cuando hay errores o diferencias físicas.
- Permite mover productos de una bodega a otra.
- Permite que áreas internas soliciten materiales de bodega.

---

## 2. ¿Quién usa este módulo?

| Rol | ¿Para qué lo usa? |
|-----|-------------------|
| Bodeguero / Almacenista | Ver stock, registrar entradas/salidas y traslados |
| Área de producción | Solicitar insumos y materiales |
| Contabilidad | Revisar valorización y ajustes de inventario |
| Gerencia | Consultar niveles de stock y movimientos |

---

## 3. Pantallas principales

| Pantalla | Ruta en el código | ¿Qué hace? |
|----------|-------------------|------------|
| Bodegas | `resources/js/pages/Inventory/Warehouse/` | Crear y administrar bodegas |
| Ubicaciones | `resources/js/pages/Inventory/WarehouseLocation/` | Ubicaciones físicas dentro de una bodega (estante, zona, etc.) |
| Stock por ítem | `resources/js/pages/Inventory/ItemInventory/` | Ver cuánto hay de cada producto en cada bodega |
| Movimientos | `resources/js/pages/Inventory/Movements/` | Historial completo de entradas y salidas |
| Ajustes | `resources/js/pages/Inventory/InventoryAdjustment/` | Correcciones manuales de stock |
| Solicitudes | `resources/js/pages/Inventory/Requests/` | Solicitudes internas de materiales desde bodega |

---

## 4. Cómo funciona el stock (concepto clave)

> El sistema **no guarda el stock directamente** como un número fijo. En cambio, registra cada movimiento (entrada o salida) y el stock actual es el resultado acumulado de todos esos movimientos.

Sin embargo, para no recalcular siempre desde cero, se guarda el stock actual en la tabla `warehouse_items` como un campo `quantity`. Cada vez que hay un movimiento, ese campo se actualiza.

```
BODEGA A — Ítem "Botón negro"
  → Entrada por factura de compra:   +500 unidades
  → Salida por solicitud interna:    -100 unidades
  → Ajuste manual:                   -  5 unidades
  ─────────────────────────────────────────────────
  Stock actual en warehouse_items:   395 unidades
```

---

## 5. Flujos principales

### 5.1 Flujo de entrada de mercancía
```
Factura de compra aprobada (módulo Gastos)
        ↓
Movimiento de ENTRADA generado automáticamente
        ↓
Stock aumenta en warehouse_items
        ↓
Queda registrado en inventory_movements
```

### 5.2 Flujo de salida de mercancía
```
Factura de venta / Solicitud interna / Control de lotes
        ↓
Movimiento de SALIDA generado automáticamente
        ↓
Stock disminuye en warehouse_items
        ↓
Queda registrado en inventory_movements
```

### 5.3 Traslado entre bodegas
```
Se crea un traslado (WarehouseTransfer)
        ↓
Salida de la bodega origen
        ↓
Entrada en la bodega destino
        ↓
El stock total no cambia, solo cambia de bodega
```

### 5.4 Ajuste manual
```
Se detecta diferencia física (conteo vs sistema)
        ↓
Se crea un ajuste (entrada o salida)
        ↓
Se corrige el stock con justificación
        ↓
Queda registrado en inventory_adjustments + inventory_movements
```

### 5.5 Solicitud interna de materiales
```
Un área solicita materiales de bodega
        ↓
Bodega revisa disponibilidad
        ↓
Entrega o rechaza la solicitud
        ↓
Si entrega: genera movimiento de salida
```

---

## 6. Piezas clave del módulo

### 6.1 Bodega (`warehouses`)
Lugar físico donde se guardan los productos.

Datos: nombre, observación.

Una bodega puede tener varias **ubicaciones** (estantes, zonas, posiciones).

### 6.2 Ubicación dentro de bodega (`warehouse_locations`)
División interna de una bodega. Por ejemplo: "Estante A", "Zona Frío", "Mezzanine".

Permite saber exactamente **dónde está físicamente** un ítem dentro de la bodega.

### 6.3 Stock por ítem en bodega (`warehouse_items`)
Registra cuánto hay de cada ítem en cada bodega.

Datos principales:
- Bodega e ítem (combinación única)
- Cantidad actual (`quantity`)
- Stock mínimo y máximo (para alertas)
- Ubicación dentro de la bodega (`location_id`)
- Precio promedio ponderado (`weighted_average_price`) — se recalcula con cada entrada
- Último costo unitario y costo total valorizado

> **Precio promedio ponderado**: cuando entra mercancía a diferente precio, el sistema recalcula el precio promedio considerando lo que ya había y lo que entra. Así se valoriza el inventario correctamente.

### 6.4 Movimiento de inventario (`inventory_movements`)
Es el **registro histórico de todo lo que ha pasado** con el stock. Nunca se borra.

Datos principales:
- Ítem y bodega afectados
- Tipo: `entrada` o `salida`
- Cantidad, precio unitario, costo total
- Stock anterior y stock nuevo (antes y después del movimiento)
- Precio promedio anterior y nuevo
- Origen del movimiento (`source_type` + `source_id`): puede ser una factura de compra, una factura de venta, un ajuste, un traslado, una solicitud, etc.
- Número de documento de referencia
- Usuario que lo creó

> Los movimientos de origen antiguo (antes del sistema) se muestran como "Migración". Los importados por Excel se muestran como "Importación".

#### Salidas de insumos a OP y exportación a Excel
Las salidas de insumos hacia producción se registran en **Control de Lotes → proceso Logística → "Salida Logística de insumos"** (`BatchControlController::processLogisticsSupplyExit`). Cada línea entregada crea un movimiento con:
- `type = salida`
- `source_type = ProductionOrder` y `source_id` = id de la OP
- `source_reference` = **código de la OP** (por ejemplo `MF-045`). Antes solo quedaba dentro de `notes`; la migración `2026_10_13_100000_backfill_source_reference_on_op_supply_exits` lo completó en los movimientos anteriores.
- `notes` = `Salida Logística OP <op_code> - <insumo>`

La pantalla **Movimientos de inventario** (`/movimientos-inventario`) tiene estos filtros:
- **Fecha:** un rango desde/hasta.
- **Origen:** la opción "Salidas de insumos a OP" deja solo las salidas de Control de Lotes. El consumo de rollos también usa la OP como origen; queda fuera porque su nota es distinta.
- **OP:** busca por código.
- **Buscar:** también encuentra el código legacy del ítem.

El botón **Exportar Excel** (`GET /movimientos-inventario/exportar-excel`) descarga lo que se ve con los filtros actuales, sin paginar. Lo genera `InventoryMovementExcelService`.

Columnas del Excel:
`Fecha · Documento · OP · Código referencia (items.reference) · Código legacy (items.legacy_code) · Ítem · Bodega · Tipo · Cantidad · Costo unitario · Costo total · Usuario · Notas`

Los códigos se escriben como texto para conservar los ceros a la izquierda. Si el sistema anterior necesita un layout fijo de importación, se agrega como otro formato en ese mismo servicio.

### 6.5 Ajuste de inventario (`inventory_adjustments`)
Corrección manual del stock cuando hay diferencias entre lo físico y lo que dice el sistema.

Tipos: `entrada` (hay más de lo esperado) o `salida` (hay menos de lo esperado).

Datos: número de documento, fecha, tipo, motivo del movimiento, observación, líneas de ítems ajustados.

### 6.6 Solicitud de inventario (`inventory_requests`)
Un área interna (ej: producción) pide materiales a bodega.

Datos principales:
- Número de solicitud, fecha
- Estado: `solicitado` → `entregado` / `rechazado`
- Ítems solicitados
- Usuario que solicita, usuario que aprueba o rechaza
- Razón de rechazo si aplica
- Imagen adjunta (evidencia o referencia del material)

### 6.7 Traslado entre bodegas (`warehouse_transfers`)
Mueve stock de una bodega a otra.

Datos: número de traslado, fecha, bodega origen, bodega destino, ítems y cantidades.

### 6.8 Bloqueo de período (`inventory_period_locks`)
Cierra un período contable para que no se puedan hacer movimientos con fecha anterior al cierre. Protege la integridad del inventario ya cerrado.

---

## 7. Estados de la solicitud de inventario

| Estado | Significado |
|--------|-------------|
| `solicitado` | Enviada, esperando respuesta de bodega |
| `entregado` | Bodega entregó los materiales |
| `rechazado` | Bodega rechazó la solicitud con motivo |

---

## 8. Reglas de negocio importantes (¡no romper!)

1. **Los movimientos de inventario nunca se eliminan** — son el registro histórico y de auditoría. Solo se pueden anular creando un movimiento inverso.
2. **El stock no puede quedar negativo** — el sistema debe validar que haya suficiente antes de registrar una salida.
3. **El precio promedio ponderado se recalcula automáticamente** con cada entrada — no se edita manualmente.
4. **Un traslado genera dos movimientos**: una salida en la bodega origen y una entrada en la bodega destino.
5. **Un ajuste siempre debe tener motivo** — no se permite ajustar sin justificación.
6. **Si un período está bloqueado**, no se pueden registrar movimientos con fecha dentro de ese período.
7. **Cada movimiento está vinculado a su origen** (`source_type` + `source_id`) — siempre se puede rastrear qué documento lo generó.

---

## 9. Tablas de base de datos

| Tabla | ¿Qué guarda? |
|-------|-------------|
| `warehouses` | Bodegas físicas |
| `warehouse_locations` | Ubicaciones dentro de cada bodega |
| `warehouse_items` | Stock actual de cada ítem por bodega |
| `inventory_movements` | Historial completo de todas las entradas y salidas |
| `inventory_adjustments` | Cabeceras de ajustes manuales |
| `inventory_adjustment_lines` | Ítems de cada ajuste |
| `inventory_requests` | Solicitudes internas de materiales |
| `inventory_request_items` | Ítems de cada solicitud |
| `warehouse_transfers` | Traslados entre bodegas |
| `warehouse_transfer_items` | Ítems de cada traslado |
| `inventory_period_locks` | Períodos contables cerrados |

---

## 10. Conexiones con otros módulos

| Módulo | ¿Cómo se conecta con Inventario? |
|--------|----------------------------------|
| **Gastos** | Las facturas de compra aprobadas generan entradas de inventario automáticamente |
| **Ingresos** | Las facturas de venta generan salidas de inventario automáticamente |
| **Producción** | El control de lotes genera salidas de insumos; la recepción en bodega genera entradas de prendas terminadas |
| **Datos Maestros** | Los ítems (productos) vienen del catálogo de ítems |
| **Finanzas** | El valor del inventario afecta la contabilidad y la valorización de activos |

---

## 11. Archivos clave para un desarrollador

| Archivo | ¿Para qué? |
|---------|------------|
| `app/Models/Inventory/Warehouse.php` | Modelo de bodegas |
| `app/Models/Inventory/WarehouseItem.php` | Modelo de stock por ítem/bodega |
| `app/Models/Inventory/InventoryMovement.php` | Modelo de movimientos — contiene lógica de display y trazabilidad |
| `app/Models/Inventory/InventoryAdjustment.php` | Modelo de ajustes |
| `app/Models/Inventory/InventoryRequest.php` | Modelo de solicitudes internas |
| `app/Models/Inventory/WarehouseTransfer.php` | Modelo de traslados |
| `app/Http/Controllers/Inventory/WarehouseItemStockController.php` | Lógica de stock |
| `app/Http/Controllers/Inventory/InventoryMovementController.php` | Lógica de movimientos |
| `app/Http/Controllers/Inventory/InventoryAdjustmentController.php` | Lógica de ajustes |
| `resources/js/pages/Inventory/Movements/Index.vue` | Pantalla de movimientos |
| `resources/js/pages/Inventory/ItemInventory/Index.vue` | Pantalla de stock por ítem |
