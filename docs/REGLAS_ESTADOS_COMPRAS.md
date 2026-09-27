# Reglas de estado del módulo de Compras

**Última actualización:** 2026-09-26
**Para qué sirve:** define qué se puede hacer con cada documento de compra según su estado, dónde se impone la regla y cómo mantenerla sincronizada entre backend y frontend.

Flujo: **Solicitud → Orden → Factura → Recepción**. La recepción no tiene modelo propio: es una vista sobre la factura (`status` y `approval_status`).

## 1. Dónde vive cada regla (fuente única)

| Capa | Archivo | Qué contiene |
|---|---|---|
| Backend | `app/Models/Expenses/PurchaseDocument.php` | `canEditOrder()`, `canEditInvoice()`, `canDeleteInvoice()` y los mensajes `ORDER_LOCKED_MESSAGE` / `INVOICE_LOCKED_MESSAGE` |
| Backend | `app/Enums/PurchaseRequestStatus.php` | `canEdit()`, `canApprove()`, `canReject()`, `canCancel()`, `canSendToApproval()` |
| Frontend | `resources/js/domain/Expenses/rules.ts` | Las mismas reglas como funciones puras (`canEditRequest`, `canEditOrder`, `canEditInvoice`, `canCreateInvoiceNote`, …) |
| Frontend | `resources/js/domain/Expenses/statuses.ts` | Etiqueta y color de cada estado (`getPurchaseRequestStatus`, `getPurchaseOrderStatus`, `getPurchaseInvoiceStatus`) |

**Regla de mantenimiento:** si cambias una condición en el backend, cambia la función equivalente de `rules.ts` en el mismo cambio (y viceversa). El frontend solo oculta opciones; el backend es quien realmente rechaza.

Además del estado, cada acción exige un **permiso** (`authStore.can(...)` en el frontend, middleware `permission:` en `routes/expenses.php` y `routes/textil.php`). Una opción se muestra solo si el usuario tiene el permiso **y** el estado lo permite. Si no tiene el permiso, la opción no se muestra (no se deshabilita).

## 2. Solicitud de compra

Estados: `draft` (borrador), `pending`, `approved`, `processed`, `rejected`, `cancelled`.

| Acción | draft | pending | approved | processed | rejected | cancelled |
|---|---|---|---|---|---|---|
| Editar | sí | no | no | no | no | no |
| Eliminar | sí | no | no | no | no | no |
| Enviar a aprobación | sí | no | no | no | no | no |
| Aprobar / Rechazar | no | sí | no | no | no | no |
| Cancelar | sí | sí | no | no | no | no |
| Convertir a orden | no | no | sí | no | no | no |

- `processed` se asigna solo cuando todas las órdenes no rechazadas están facturadas, y vuelve a `approved` si se elimina la última orden o se reabre la facturación.
- `PUT` de una solicitud responde **422** si no está en borrador y **ignora el campo `status`**. El estado solo cambia por las acciones (enviar, aprobar, rechazar, cancelar). Al crear, `status` sí se admite (`draft` o `pending`).
- Desde el diálogo, "enviar a aprobación" en una solicitud existente guarda el borrador y luego llama a `POST /gastos/solicitudes-compra/{id}/send-to-approval`.

## 3. Orden de compra

Estados: `pending`, `approved`, `partially_invoiced`, `invoiced`, `rejected`. No existe "cancelada"; cerrar una orden manualmente la pasa a `invoiced` con `closure_reason`.

| Acción | pending | approved | partially_invoiced | invoiced | rejected |
|---|---|---|---|---|---|
| Editar | sí | no | no | no | no |
| Eliminar | sí | no | no | no | no |
| Aprobar / Rechazar | sí | no | no | no | no |
| Crear factura desde la orden | no | sí | sí | no | no |
| Cerrar (motivo obligatorio) | no | no | sí | no | no |

- Editar y eliminar responden **422** con mensaje en español fuera de `pending`; la página `/ordenes-compra/{id}/editar` responde **403**.
- Aunque la orden esté `pending`, si ya tiene facturas el formulario queda de solo lectura (no se pueden cambiar ítems ni proveedor).
- Aprobar y rechazar comparten el permiso `aprobar orden compra`. Cerrar y enviar por correo usan `editar ordenes compra`.

## 4. Factura de compra (normal y de tela)

Estados (`status`): `pending_receipt`, `received`, `rejected`. La recepción de tela deja `approved`, que el frontend trata como sinónimo de `received`. Segundo eje (`approval_status`): `pending`, `pending_difference_approval`, `approved`, `difference_rejected`.

| Acción | pending_receipt | received / approved | rejected | difference_rejected | pending_difference_approval |
|---|---|---|---|---|---|
| Editar | sí | no | no | no | no |
| Eliminar | sí | no | no | no | no |
| Notas crédito / débito | no | sí | no | no | según `status` |
| Aprobar / rechazar diferencia | solo con `approval_status = pending_difference_approval` y permiso `gestionar diferencias factura compra` | | | | |

- Se puede editar o eliminar solo si `status` es `pending_receipt` (o vacío/`pending` heredado) **y** `approval_status` no es `pending_difference_approval`, `difference_rejected` ni `approved`. Es el mismo helper para factura normal y de tela.
- Fuera de ese caso, editar y eliminar responden **422**.
- Al eliminar una factura se recalcula el estado de la orden y, si aplica, de la solicitud.

## 5. Recepción

Sobre la factura, en `Reception.vue` y `FabricRollController`:

| Acción | pendiente de recepción | aprobada / recibida | rechazada | diferencia rechazada |
|---|---|---|---|---|
| Registrar recepción y aprobar | sí (aprobar exige diferencias aprobadas o dentro de tolerancia) | no | no | no |
| Rechazar (motivo obligatorio) | sí | no | no | no |
| Añadir rollos | sí, con `crear telas rollos` | solo con `ajustar telas rollos` | no | no |
| Editar / borrar rollos | sí | no | no | no |

No existe reapertura de una recepción rechazada.

## 6. Permisos usados en las listas

| Documento | Permisos |
|---|---|
| Solicitud | `crear`, `editar`, `eliminar`, `aprobar`, `rechazar`, `cancelar solicitudes compra` (enviar a aprobación usa `editar`) |
| Orden | `crear`, `editar`, `eliminar ordenes compra`; `aprobar orden compra`; `crear facturas compra` (crear factura desde la orden) |
| Factura | `crear`, `editar`, `eliminar facturas compra`; `gestionar diferencias factura compra`; `crear notas credito debito`; `ver notas credito debito`; `ver recepcion telas` |
| Recepción | `aprobar recepcion tela`, `rechazar recepcion tela`, `crear telas rollos`, `ajustar telas rollos` |

"Imprimir" y "Ver" en órdenes y facturas no tienen permiso propio: la ruta solo exige el permiso de ver.

## 7. Cómo cambiar una regla

1. Modificar el helper del backend (`PurchaseDocument` o el enum) y ajustar el controlador si responde distinto.
2. Modificar la función equivalente en `rules.ts`.
3. Actualizar las tablas de este documento.
4. Cubrir el caso en `tests/Feature/Expenses/PurchaseDocumentEditLockTest.php` (o el test del flujo correspondiente).

## 8. Pendientes conocidos

- Enviar por correo (orden y factura) no valida el estado: se puede enviar una orden `pending`/`rejected` o una factura `rejected`/`pending_receipt`.
- Ninguna pantalla produce el estado `received`; la recepción real deja `approved`. La ruta `recibir` y `receiveInvoice` solo se alcanzan por API.
- `show`, `print`, `approveDifference` y `rejectDifference` de factura de tela no comprueban el alcance del usuario.
- Recepción: rechazar no queda bloqueado con `pending_difference_approval`, y el backend no impide registrar recepción o aprobar de nuevo sobre una factura `approved`.
- `convertOrderToInvoice` rechaza órdenes con cualquier factura previa y el frontend nunca lo llama (posible código muerto).
- Faltan reaperturas: solicitud rechazada/cancelada, orden cerrada y recepción rechazada.
