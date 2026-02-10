# Vinculación Factura de Compra ↔ Orden de Compra

## Reglas de negocio

1. **Cuando la factura se crea desde una orden:** debe quedar vinculada vía `parent_document_id` y la orden debe actualizarse a `invoiced`.
2. **Facturas directas** (sin orden): `parent_document_id` = `NULL`; no se actualiza ninguna orden.
3. **Evitar facturas huérfanas:** si el usuario selecciona una orden en el formulario, la factura **siempre** debe guardar ese vínculo.

---

## Cambios realizados

### 1. Backend

- **`ValidatesPurchaseInvoiceRequest`**: se acepta `purchase_order_id` además de `parent_document_id`. Tras validar, se normaliza a `parent_document_id` y se elimina `purchase_order_id` del payload.
- **`PurchaseDocumentInvoiceService::createInvoice`**: usa `parent_document_id ?? purchase_order_id` para resolver la orden. Si hay `parent_document_id`:
  - Se valida que la orden exista, sea tipo orden y esté aprobada.
  - Se comprueba que no exista ya otra factura para esa orden.
  - Se crea la factura con `parent_document_id` y se actualiza la orden a `status = invoiced`, `invoiced_at = now()`.

### 2. Frontend

- **`PurchaseInvoiceDialog`** y **`PurchaseInvoiceDialogTelas`**:
  - Al enviar el formulario, se manda `parent_document_id` cuando hay orden seleccionada (`selectedPurchaseOrderId` o `purchase_order_id` / `parent_document_id` en edición).
  - Se deja de enviar `purchase_order_id`; el backend usa solo `parent_document_id`.
  - En `resetForm` (edición) se sincronizan `selectedPurchaseOrderId` y `parent_document_id` / `purchase_order_id` desde la factura cargada.

### 3. Flujo “Convertir a factura”

- `PurchaseDocumentOrderService::convertOrderToInvoice` ya envía `parent_document_id` y llama a `createInvoice`. No se modificó.

---

## Validaciones

| Regla | Dónde |
|-------|--------|
| `parent_document_id` y `purchase_order_id` deben ser IDs de documento tipo orden | `ValidatesPurchaseInvoiceRequest` |
| Orden existente y aprobada al crear factura vinculada | `PurchaseDocumentInvoiceService::createInvoice` |
| Una sola factura por orden | `PurchaseDocumentInvoiceService::createInvoice` |
| Actualización de orden a `invoiced` al crear factura vinculada | `PurchaseDocumentInvoiceService::createInvoice` (transacción) |

---

## Buenas prácticas

1. **Crear factura desde orden:** usar el selector de orden en el formulario o “Convertir a factura” desde la orden. Así siempre se envía `parent_document_id` y se actualiza la orden.
2. **Factura directa:** no elegir orden; `parent_document_id` queda `NULL`.
3. **Edición:** el vínculo se mantiene si la factura ya tenía `parent_document_id`. El update actual no permite cambiar `parent_document_id` (no está en `updateFillableKeys`).
4. **Eliminar factura:** si tenía `parent_document_id`, `deleteDocument` devuelve la orden a `pending` y limpia `invoiced_at`.
5. **API:** enviar `parent_document_id` (o `purchase_order_id`, que se normaliza) cuando corresponda. No depender solo de `purchase_order_id` en clientes nuevos.

---

## Cómo comprobar que todo funciona

1. Crear factura desde el diálogo **con** orden seleccionada → la factura tiene `parent_document_id` y la orden pasa a `invoiced`.
2. Crear factura **sin** orden → `parent_document_id` es `NULL`, ninguna orden cambia.
3. “Convertir a factura” desde una orden → factura vinculada y orden en `invoiced`.
4. Editar una factura vinculada → al guardar se sigue enviando `parent_document_id`; el vínculo se conserva (el backend no actualiza `parent_document_id` en update).
5. Eliminar una factura vinculada → la orden vuelve a `pending` y `invoiced_at` = `NULL`.
