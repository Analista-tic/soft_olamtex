# Numeración de documentos y protección contra duplicados

**Última actualización:** 2026-09-28
**Para qué sirve:** garantiza que los números que identifican los documentos (facturas, órdenes, solicitudes, movimientos…) nunca se repitan, aunque dos personas trabajen a la vez o se envíe un formulario dos veces.

## Regla de oro

**El consecutivo lo asigna el servidor al guardar, nunca al abrir el formulario.** El formulario muestra "Se asigna al guardar" (o "Automatico"). Un número "sugerido" en pantalla lo veían igual dos usuarios a la vez y, además, podía no coincidir con el que finalmente se guardaba.

## Cómo se asigna (`App\Support\DocumentSequence`)

- Tabla `document_sequences`: una fila por serie (`purchase_documents:purchase_invoice:FAC`, `inventory_movements:MOV-2026`…).
- `DocumentSequence::next($serie, fn () => $maximoExistente)` bloquea la fila de la serie, toma `max(contador, máximo real de la tabla) + 1` y guarda el nuevo valor. Dos peticiones simultáneas se turnan: nunca reciben el mismo número.
- Se llama **dentro de la transacción que crea el documento**: si esa transacción falla, el número no se consume.
- El máximo real se lee con el prefijo **exacto** (`FAC-0007`, no `FAC-T-0003`) y **incluyendo registros eliminados**: un número usado no se reutiliza. El contador se autocorrige si hay importaciones o inserciones manuales.

| Serie | Formato | Dónde se asigna |
|---|---|---|
| Solicitud de compra | `SOL-###` | `PurchaseRequestService::generateRequestNumber` |
| Orden / factura / factura de tela | `ORD-####`, `FAC-####`, `FAC-T-####` | `PurchaseDocumentService::generateDocumentNumber` |
| Nota crédito/débito de compra | prefijo por tipo | `CreditDebitNoteService::generateNoteDocumentNumber` |
| Solicitud interna / insumo | `SINT-###`, `SOL-INS-AAAA-####` | `InventoryRequestService`, `SupplyRequestService` |
| Traslado / ajuste / movimiento | `TR-###`, `OL-###`, `MOV-AAAA-###` | `WarehouseTransferService`, `InventoryAdjustmentService`, `InventoryMovementService` |

Ventas, rollos, asientos y comprobantes de egreso ya usaban contadores propios atómicos.

## Garantía final en base de datos

Aunque algo falle en la aplicación, la BD rechaza el duplicado (el usuario ve un 422 claro, ver `bootstrap/app.php`):

- `purchase_documents`: único `(document_type, document_number)`.
- `purchase_documents.supplier_invoice_key`: columna virtual `proveedor|número de factura del proveedor` **solo para facturas vivas** → el mismo proveedor no puede registrar dos veces el mismo número de factura; al eliminar la factura el número queda libre.
- `inventory_requests`, `warehouse_transfers`, `sale_documents`: único por número (por tipo en ventas).
- `purchase_requests.request_number`, `inventory_adjustments.document_number`, `fabric_rolls.roll_number`, `production_orders.op_code`, asientos y comprobantes: ya eran únicos.

Además, `ValidatesPurchaseInvoiceRequest::ensureSupplierInvoiceNumberIsUnique` da el mensaje amigable antes de llegar a la BD (ignora mayúsculas/espacios y la propia factura al editar).

## Doble clic / reintento

`App\Http\Middleware\PreventDuplicateSubmission` (configuración en `config/duplicate_submission.php`): si el mismo usuario envía una alta idéntica (`POST` a una ruta `*.store` sin parámetros) mientras la anterior se procesa o en los 4 s siguientes a guardarse, responde **409** y no crea otro registro. Si la primera falla (validación, error), el bloqueo se libera y se puede reintentar. En tests está apagado salvo que se active (`duplicate_submission.in_tests`).

Ítems (`ItemService::create`): la validación de duplicado por nombre se repite dentro de un bloqueo por nombre, para que dos usuarios no creen el mismo ítem a la vez.

## Cómo agregar una serie nueva

1. Generar el número con `DocumentSequence::next()` dentro de la transacción de creación (no leer `max(id)` ni "el último por id").
2. No exponer un endpoint que "adivine" el siguiente número para el formulario.
3. Agregar índice único en una migración (si ya hay repetidos, corregirlos primero y con aprobación).
4. Probarlo (ver `tests/Feature/DocumentNumberingTest.php` y los tests de numeración en `PurchaseFlowStateTest`).

## Pendientes conocidos

- `supplier_order_number` (número de orden del proveedor) puede repetirse: hoy hay un `#1` en 3 órdenes del mismo proveedor. Es un dato libre; no se impuso unicidad (decisión de negocio).
- Facturas creadas antes de esta corrección con un número de proveedor repetido quedaron marcadas `(REPETIDA-<id>)` (ver factura 22); hay que corregir el número real a mano mientras esté pendiente de recepción.
- `items.reference` no tiene índice único (los ítems se protegen por nombre).
