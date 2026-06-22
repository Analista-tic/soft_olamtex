# Módulo de Textil

**Última actualización:** 2026-06-12
**Propósito:** Explicar qué hace el módulo de Textil, cómo funciona y cómo se conecta con el resto del sistema.

---

## 1. ¿Qué hace este módulo?

El módulo de Textil gestiona los **rollos de tela** que entran a la empresa. Es específico para el negocio textil de confección.

A diferencia del inventario general (que maneja todo tipo de productos por cantidad), el módulo Textil maneja la tela a nivel de **rollo individual** — cada rollo tiene su propio número, ancho, metros, color, precio y estado.

En términos simples:
- Cuando llega una factura de compra de tela, los rollos se registran aquí.
- Cada rollo tiene sus propias características: cuántos metros tiene, cuánto mide de ancho, qué color es.
- Se puede ver cuántos metros quedan disponibles por rollo.
- Los rollos se asignan a órdenes de producción (fichas técnicas).
- Se puede corregir la cantidad de un rollo con ajustes.

---

## 2. ¿Quién usa este módulo?

| Rol | ¿Para qué lo usa? |
|-----|-------------------|
| Almacén / Bodega textil | Registrar rollos que llegan, ver disponibilidad |
| Jefe de producción | Asignar rollos a órdenes de producción |
| Compras | Ver qué telas hay disponibles antes de pedir más |
| Calidad | Aprobar o rechazar rollos recibidos |

---

## 3. Pantallas principales

| Pantalla | Ruta en el código | ¿Qué hace? |
|----------|-------------------|------------|
| Inventario de rollos | `resources/js/pages/Textil/FabricRollInventory/` | Ver todos los rollos, su disponibilidad y estado |
| Órdenes de rollos | `resources/js/pages/Textil/FabricRollOrders/` | Rollos vinculados a órdenes/facturas de compra |
| Vista principal | `resources/js/pages/Textil/Index.vue` | Panel general del módulo textil |

---

## 4. Flujo principal (paso a paso)

```
[1] Llega una factura de compra de tela (módulo Gastos)
        ↓
[2] Se registran los rollos recibidos en el módulo Textil
    (vinculados a la línea de la factura de compra)
        ↓
[3] El rollo queda con estado pendiente de aprobación
        ↓
[4] Se aprueba o rechaza el rollo (revisión de calidad)
        ↓
[5] Rollo aprobado → disponible para asignar a producción
        ↓
[6] Al asignar a una ficha técnica, se registra el consumo
    (metros usados por la orden de producción)
        ↓
[7] La cantidad disponible del rollo se reduce
    (disponible = metros totales - metros consumidos - salidas)
```

---

## 5. Piezas clave del módulo

### 5.1 Rollo de tela (`fabric_rolls`)
Representa un **rollo físico de tela** individual.

Datos principales:
- Número de rollo (`roll_number`) — identificador único del rollo físico
- Lote (`batch`) — lote de producción de la tela
- Ítem asociado (`item_id`) — el tipo de tela según el catálogo de ítems
- Línea de factura de compra (`purchase_document_item_id`) — de dónde vino el rollo
- Ubicación física (`location`) — dónde está guardado en bodega
- Metros totales (`quantity`) — metraje total del rollo
- Ancho del rollo (`roll_width`) — en centímetros o metros
- Precio por metro (`price_per_meter`) — heredado de la factura de compra
- Color interno (`internal_color_name`, `internal_color_code`) — referencia de color propio
- Salida parcial (`partial_output`) — metros que han salido de forma parcial
- Salida real (`actual_output`) — metros totales ya consumidos
- Fecha de entrada (`entry_date`)
- Estado de aprobación (`approval_status`)

**Cantidad disponible** = `quantity` - `actual_output`

### 5.2 Estados de aprobación del rollo

| Estado | Significado |
|--------|-------------|
| `pending_approval` | Recibido pero pendiente de revisión de calidad |
| `approved` | Aprobado, disponible para producción |
| `not_approved` | Rechazado, no se puede usar |

### 5.3 Ajuste de rollo (`fabric_roll_adjustments`)
Corrección manual de la cantidad de un rollo cuando hay una diferencia entre lo físico y lo registrado.

Datos: rollo afectado, tipo de ajuste, cantidad antes y después, motivo, referencia, usuario que hizo el ajuste.

> Cada ajuste queda vinculado a un movimiento de inventario para mantener la trazabilidad.

### 5.4 Consumo de rollo por producción (`production_roll_consumptions`)
Registra cuántos metros de un rollo se usaron para una orden de producción específica.

Este modelo está en el módulo de Producción pero es la "puerta de conexión" entre Textil y Producción.

---

## 6. Reglas de negocio importantes (¡no romper!)

1. **Un rollo rechazado (`not_approved`) no puede asignarse a producción** — solo los aprobados están disponibles.
2. **La cantidad disponible nunca puede ser negativa** — no se pueden consumir más metros de los que tiene el rollo.
3. **El precio por metro viene de la factura de compra** — pero puede editarse manualmente para rollos sin factura vinculada.
4. **Cada ajuste deja traza** en el historial de ajustes y genera un movimiento en inventario.
5. **Los rollos importados de datos anteriores** pueden tener `legacy_id` — sirve para rastrear el origen histórico.
6. **El lote (`batch`) agrupa rollos** que llegaron juntos o son de la misma producción del proveedor.

---

## 7. Tablas de base de datos

| Tabla | ¿Qué guarda? |
|-------|-------------|
| `fabric_rolls` | Cada rollo de tela individual con su estado y disponibilidad |
| `fabric_roll_adjustments` | Correcciones manuales de cantidad por rollo |
| `production_roll_consumptions` | Consumo de metros de un rollo por orden de producción (en módulo Producción) |
| `fabric_bases` | Tipos base de tela (compartido con sábanas de producto en Producción) |

---

## 8. Conexiones con otros módulos

| Módulo | ¿Cómo se conecta con Textil? |
|--------|------------------------------|
| **Gastos** | Los rollos se crean a partir de líneas de facturas de compra (`purchase_document_item_id`) |
| **Producción** | Los rollos se asignan a fichas técnicas; el consumo se registra en `production_roll_consumptions` |
| **Inventario** | Los ajustes de rollo generan movimientos de inventario para trazabilidad contable |
| **Datos Maestros** | El tipo de tela de cada rollo viene del catálogo de ítems (`item_id`) |

---

## 9. Archivos clave para un desarrollador

| Archivo | ¿Para qué? |
|---------|------------|
| `app/Models/Textil/FabricRoll.php` | Modelo del rollo — incluye cálculo de disponibilidad |
| `app/Models/Textil/FabricRollAdjustment.php` | Modelo de ajustes de rollo |
| `app/Models/Production/FabricBase.php` | Modelo de tipos base de tela |
| `app/Http/Controllers/Textil/FabricRollController.php` | Lógica de rollos de tela |
| `resources/js/pages/Textil/FabricRollInventory/` | Pantalla de inventario de rollos |
