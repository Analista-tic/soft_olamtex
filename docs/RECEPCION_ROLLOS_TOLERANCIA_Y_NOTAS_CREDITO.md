# Recepción de telas: tolerancia por rollo, rollos restringidos, nota crédito y retención de pago

**Última actualización:** 2026-09-27
**Para qué sirve:** documenta el ciclo de vida de un rollo en la recepción de una factura de tela cuando no cumple la tolerancia: cómo se aprueba o rechaza rollo a rollo, cómo queda restringido, cómo se cubre con una nota crédito, cómo se da de baja y cómo se retiene el pago. Incluye las reglas exactas, los archivos donde vive cada una, los permisos y los casos borde.

Complementa a [REGLAS_ESTADOS_COMPRAS.md](REGLAS_ESTADOS_COMPRAS.md) (reglas por estado de los documentos de compra).

> **Migraciones y permiso nuevos:** este flujo necesita `2026_09_27_000002_add_tolerance_fields_to_fabric_rolls_table` y `2026_09_27_000003_create_fabric_roll_credit_note_links_table` (`php artisan migrate`) y el permiso `agregar rollos recepcion aprobada` (`php artisan db:seed --class=RolesAndPermissionsSeeder`).

---

## 1. Resumen del flujo

```
Registro de rollos (Recepción)
        │
        ▼
 ¿La línea de la factura queda fuera de ±30 m?
   no ──► rollos: none (sin acción)
   sí ──► rollos: pending_review
        │
        ▼
 Rollo a rollo, con la factura aún "pendiente de recepción":
   • Aprobar diferencia ──► approved_with_difference (entra normal al aprobar la factura)
   • Rechazar (motivo obligatorio) ──► rejected  ── queda RESTRINGIDO
        │
        ▼
 Aprobar la recepción de la FACTURA (solo si no queda ningún pending_review)
   • rollos aprobados ──► entran al inventario disponible
   • rollos rechazados ──► NO entran; se ven en Kardex "Restringidos"
   • la factura se contabiliza (asiento) automáticamente
        │
        ▼
 Una persona crea la NOTA CRÉDITO de la factura y marca qué rollos rechazados cubre
        │
        ▼
 Mientras la nota no esté aprobada: se retiene del pago el monto de esos rollos
        │
        ▼
 Nota crédito aprobada ──► los rollos se DAN DE BAJA (resolved_at, written_off_at)
                           y el monto sale de la retención (el saldo baja por la nota)
```

## 2. Estados

### 2.1 `fabric_rolls.tolerance_status` (nuevo)

| Valor | Significado | Cómo se llega |
|---|---|---|
| `none` | Sin observación de tolerancia. | Por defecto; también cuando la línea vuelve a estar dentro de ±30 m. |
| `pending_review` | La línea de factura está fuera de tolerancia y este rollo espera decisión. | Automático (ver 3.1). |
| `approved_with_difference` | Se aceptó el rollo pese a la diferencia. | Acción "Aprobar diferencia". |
| `rejected` | Se rechazó el rollo. Queda restringido hasta que una nota crédito aprobada lo cubra. | Acción "Rechazar". |

Columnas asociadas: `tolerance_reason` (motivo del rechazo, o notas al aprobar), `resolved_at` (cuándo lo resolvió una nota aprobada) y `written_off_at` (cuándo se dio de baja).

### 2.2 Relación con `approval_status` (no cambia de significado)

`approval_status` sigue siendo el estado de aprobación de recepción del rollo (`pending_approval` → `approved`, o `not_approved`). Un rollo **rechazado** queda con `approval_status = not_approved`; como todas las consultas de inventario disponible filtran `approval_status = approved`, **el rollo rechazado queda automáticamente fuera del stock disponible** y `approveReception` (que solo actualiza los `pending_approval`) nunca lo aprueba ni le crea movimiento de inventario.

### 2.3 "Restringido"

Un rollo está **restringido** cuando `tolerance_status = rejected` y `resolved_at IS NULL`. No es un campo: se deriva de `fabric_rolls`. No existe columna de stock restringido en `warehouse_items`, así que ningún lector de `quantity` (Kardex general, reportes, traslados, solicitudes) cambia.

## 3. Reglas

### 3.1 Tolerancia por rollo (regla derivada de la tolerancia por línea)
1. La tolerancia existente es **por línea de factura**: `TOLERANCE_QUANTITY_METERS = 30` (`FabricRollService`), comparando lo facturado con la suma de metros de los rollos de esa línea, por faltante **o** por exceso.
2. Cuando una línea queda fuera de ±30 m, **todos** sus rollos con `tolerance_status = none` pasan a `pending_review`. Si la línea vuelve a estar en tolerancia, los `pending_review` vuelven a `none`. Se recalcula tras crear, editar (línea anterior y nueva) y eliminar rollos (`FabricRollService::syncToleranceStatusForLine`).
3. El recálculo **nunca** modifica `approved_with_difference` ni `rejected` (las decisiones tomadas se respetan).
4. Solo aplica a facturas en `pending_receipt` y a líneas de tela. Los ítems generales (bodega) no tienen este control.
5. Para el cálculo, "recibido" **incluye los rollos rechazados** (es lo que se entregó físicamente). La cobertura del valor rechazado la da la nota crédito, no la diferencia de línea.

### 3.2 Aprobar o rechazar un rollo
6. Solo se decide mientras la factura está **pendiente de recepción** y el rollo aún no fue aprobado (`pending_approval`).
7. **Aprobar diferencia** (`approveRollDifference`): solo desde `pending_review`. Notas opcionales.
8. **Rechazar** (`rejectRoll`): desde `none`, `pending_review` o `approved_with_difference` (también sirve para un defecto de calidad en un rollo dentro de tolerancia). **El motivo es obligatorio** (se recorta con `trim`; vacío = 422 en la clave `rejection_reason`). Un rollo ya rechazado no se puede rechazar de nuevo.
9. Rechazar no crea movimiento de inventario.
10. Cada acción escribe un evento en `purchase_document_reception_events` (`roll_rejected`, `roll_difference_approved`) con el motivo, el usuario y la fecha.

### 3.3 Orden obligatorio: rollo a rollo y luego la factura
11. `approveReception` responde **422** ("Hay N rollos pendientes de aprobar o rechazar antes de aprobar la recepción completa.", clave `rolls_pending_review`) mientras exista un rollo `pending_review`. En pantalla el botón "Aprobar recepción" se deshabilita con el contador "N rollos por resolver".
12. **Convive con la aprobación de diferencia de la factura** (flujo anterior, `reportReceptionDifference` + aprobación con motivo y evidencia): si una línea de tela está fuera de tolerancia se exigen **ambas** cosas: la diferencia de factura aprobada y todos los rollos resueltos. El chequeo de factura se evalúa primero.

### 3.4 Agregar rollos después de aprobar
13. Con la factura **aprobada** no se pueden agregar más rollos. La única excepción es el permiso **`agregar rollos recepcion aprobada`**, que solo tienen `admin` y `superadmin`. El permiso anterior `ajustar telas rollos` **ya no basta**. Aun con el permiso: el **motivo es obligatorio** (`post_approval_reason`, queda como evento `roll_added_after_approval`) y el rollo **no puede sacar la línea de ±30 mts**; solo se admite si la deja dentro o si acerca lo recibido a lo facturado (`ensureRollAdditionAfterApprovalAllowed`). Una entrega adicional real va en una factura nueva.
14. Con la factura rechazada, o con diferencia rechazada, nunca se pueden agregar rollos.
15. En pantalla el botón "Agregar/Registrar rollos" se oculta tras aprobar salvo con ese permiso.

### 3.5 Nota crédito enlazada a rollos
16. La nota crédito sigue siendo un documento contra la **factura** (`parent_document_id`). Lo nuevo es el enlace opcional de la nota con los rollos rechazados que cubre (tabla `fabric_roll_credit_note_links`).
17. Una persona crea la nota manualmente. Al crear una nota crédito de compra, si la factura padre tiene rollos restringidos, el diálogo ofrece una lista con casillas (número de rollo, metros, motivo y monto) y envía `fabric_roll_ids`. Los rollos disponibles se consultan con `GET /notas-credito-debito/rejected-rolls?parent_document_id=&note_id=`.
18. Validaciones del enlace (`FabricRollCreditNoteService::linkRolls`, error en la clave `fabric_roll_ids`):
    - la nota debe ser **crédito** (no débito);
    - la factura padre debe ser de **tela**;
    - los rollos deben pertenecer a **esa** factura;
    - deben estar `rejected` y sin resolver;
    - un rollo solo puede estar en **una nota activa** a la vez (los enlaces de notas eliminadas no cuentan).
19. Semántica de `fabric_roll_ids` en crear/editar: si **no viene**, los enlaces quedan como están; si viene `[]` o `null`, se desenlazan todos; si viene una lista, los enlaces quedan exactamente esa lista. Cambiar la nota a débito sin enviar el campo desenlaza sus rollos.
20. Cada enlace nuevo escribe el evento `roll_linked_to_note`.
21. Eliminar una nota (solo posible si no está `approved`) borra sus enlaces y los rollos vuelven a quedar sin nota.

### 3.6 Baja al aprobar la nota
22. Cuando la nota pasa a `status = 'approved'` (al editarla, o si nace aprobada) cada rollo enlazado sin `resolved_at` recibe `resolved_at = now` y `written_off_at = now`, y se escribe `roll_written_off`. **No hay movimiento de inventario que revertir**: el rollo nunca entró al stock disponible.
23. Una nota aprobada no se puede editar ni eliminar (regla existente).
24. Un rollo dado de baja deja de aparecer como restringido y no cuenta en la retención.

### 3.7 Retención de pago (parcial por monto)
25. **Monto retenido** de una factura = suma, por cada rollo restringido de esa factura, de:

    `metros × (price_per_meter, o el precio de la línea si es nulo) × (1 + total_tax / total de la línea)`

    (si el total de la línea es 0 se usa `tax / 100`). Es el mismo criterio de `total + total_tax` con el que se calculan las notas.
    *Ejemplo:* línea de 500 m a $100 con 19 % de IVA; cada rollo de 100 m rechazado retiene $11.900; con dos rollos rechazados se retienen $23.800.
26. `PurchasePaymentService::getInvoicePaymentSummary` agrega `disputed_amount`, `disputed_rolls_count` y `payable_balance = max(0, current_balance − disputed_amount)`.
27. `registerInvoicePayment` usa `payable_balance` como tope: se puede pagar la parte no disputada. Si se excede, 422: "El pago supera el saldo pagable; $23,800.00 están retenidos por 2 rollos rechazados sin nota crédito aprobada." (en singular: "1 rollo rechazado"). Sin disputa, los mensajes son los de siempre.
28. Al aprobarse la nota, el monto sale de la retención y del saldo a la vez (la nota aprobada reduce el saldo), sin doble conteo.
29. Todos los caminos de pago (comprobantes de egreso, `PurchasePaymentController`, tesorería) pasan por `registerInvoicePayment`. Cuentas por pagar y las filas del comprobante de egreso muestran "Retenido $X (N rollos) · Pagable $Y" y usan `payable_balance` como tope sugerido.

### 3.8 Contabilidad
30. **La factura de tela se contabiliza al aprobar la recepción.** Antes solo se contabilizaba la factura "recibida" por otro flujo, y las de tela nunca tenían asiento. Ahora `approveReception` llama a `postPurchaseInvoice` (idempotente) al confirmar la recepción.
31. **La falta de configuración contable NO bloquea la recepción.** Si no se puede contabilizar (cuentas o impuestos sin configurar), la aprobación y el inventario quedan como están, se registra un `Log::warning` y la respuesta trae `accounting_warning`, que la pantalla de Recepción muestra como advertencia.
32. Se contabiliza la **factura completa**, incluidos los rollos rechazados. La nota crédito es la que reduce el valor.
33. **Nota crédito parcial = asiento proporcional.** `AccountingPostingService::postCreditNote`, dentro de una transacción:
    - Si la nota ya tiene asiento vigente, lo devuelve (idempotente).
    - `ratio = total de la nota / total del asiento de la factura`. Si la nota equivale al total (±0,01) se usa la reversión total de siempre.
    - Si es parcial, se crea un asiento nuevo con las líneas de la factura invertidas (debe ↔ haber) y escaladas por `ratio`, calculado en centavos enteros. El residuo de redondeo se suma a la línea de mayor monto de cada lado para que débitos = créditos = monto de la nota. La factura **no** se marca como revertida.
    - El acumulado de notas crédito de una factura no puede superar su total (si lo supera: `ValidationException`, y no queda nota ni asiento).
    - Editar el monto de una nota parcial revierte su asiento y publica uno nuevo; eliminarla lo anula.
34. Si la factura padre no tiene asiento al crear la nota, se intenta contabilizarla primero; si aún falla, el error indica el motivo.
35. **Notas débito de compra: no generan asiento automático** (no existe `postDebitNote` para compras; antes se llamaba por error al posteo de notas crédito).

## 4. Dónde se ve (Kardex e Inventario)

- **Kardex de telas** (`/kardex-telas/inventario-rollos`): pestañas **Disponibles / Restringidos**. "Restringidos" (`restricted=1`) lista los rollos restringidos con badge "Restringido · pendiente nota crédito" (o "Restringido · Nota N° X" si está enlazada), tooltip con el motivo y número de factura, sin acciones de consumo.
- **Inventario general** (`/inventario-item`): columna **Restringido** junto a Cantidad. `quantity` sigue siendo el disponible. Un ítem de tela cuyo único stock es restringido sigue visible; uno cuyos rollos ya se dieron de baja se oculta.
- **Recepción**: por rollo, badge de estado de tolerancia con tooltip del motivo y acciones "Aprobar diferencia" / "Rechazar" (cada una solo con su permiso); banner "N rollos por resolver" y banner "N rollos pendientes de nota crédito ($X)".
- **Facturas de tela**: badge "N rollos pendientes de nota crédito" con el monto en el tooltip.

## 5. Permisos

| Acción | Permiso |
|---|---|
| Aprobar la diferencia de un rollo | `aprobar recepcion tela` |
| Rechazar un rollo (motivo obligatorio) | `rechazar recepcion tela` |
| Aprobar la recepción completa | `aprobar recepcion tela` |
| Agregar rollos con la factura ya aprobada | `agregar rollos recepcion aprobada` (solo `admin` y `superadmin`) |
| Crear / editar la nota crédito y enlazar rollos | `crear notas credito debito` / `editar notas credito debito` |
| Registrar el pago | `crear comprobantes egreso` |

Las opciones sin permiso no se muestran (no se deshabilitan).

## 6. Casos borde

| Situación | Resultado |
|---|---|
| Se rechaza un rollo y luego se elimina otro de la misma línea | Se recalcula la línea; el rechazado se conserva. |
| Se intenta rechazar un rollo con la factura ya aprobada | Se rechaza la acción; para corregir hay que usar el permiso súper solo para **agregar** rollos. |
| Se intenta enlazar el mismo rollo a dos notas | El segundo enlace falla (`fabric_roll_ids`). |
| La nota se crea como débito con `fabric_roll_ids` | Error: solo las notas crédito cubren rollos. |
| La nota se elimina antes de aprobarse | Se borran los enlaces; vuelve a haber retención por esos rollos. |
| El pago excede el saldo pagable | 422 con el monto retenido y la cantidad de rollos. |
| La factura de tela ya estaba aprobada antes de este cambio y no tiene asiento | Se contabiliza sola la primera vez que se crea una nota crédito sobre ella. |
| Falla la contabilización al aprobar la recepción | La recepción queda aprobada; solo se muestra `accounting_warning`. |
| Una nota parcial + una nota total posterior | La total falla por saldo (el acumulado no puede superar la factura). |

## 7. Archivos clave

| Capa | Archivo |
|---|---|
| Modelos | `app/Models/Textil/FabricRoll.php`, `FabricRollCreditNoteLink.php`, `app/Models/Expenses/PurchaseDocumentReceptionEvent.php` |
| Tolerancia y recepción | `app/Services/Textil/FabricRollService.php` (`syncToleranceStatusForLine`, `approveRollDifference`, `rejectRoll`, `approveReception`, `ensureInvoiceNotYetReceptionApproved`), `FabricRollController.php` |
| Enlace, baja y retención | `app/Services/Textil/FabricRollCreditNoteService.php` (`linkRolls`, `unlinkAll`, `resolveOnApproval`, `pendingRejectedRolls`, `disputedAmount`, `disputedSummaries`) |
| Notas | `app/Services/Finance/CreditDebitNoteService.php`, `CreditDebitNoteController.php` |
| Pagos | `app/Services/Expenses/PurchasePaymentService.php`, `PurchaseDocumentInvoiceService.php`, `TreasuryVoucherService.php` |
| Contabilidad | `app/Services/Finance/AccountingPostingService.php` (`postCreditNote`, `postPurchaseInvoice`, `postPurchaseInvoiceIfMissing`) |
| Inventario | `FabricRollService::inventoryWithFilters`, `app/Services/Inventory/ItemInventoryService.php` |
| Frontend | `resources/js/pages/Expenses/Reception/Reception.vue`, `Textil/FabricRollInventory/Index.vue`, `Inventory/ItemInventory/Index.vue`, `Finance/CreditDebitNotes/CreditDebitNoteDialog.vue`, `Expenses/ExpenseVouchers/ExpenseVoucherForm.vue`, `resources/js/domain/Expenses/rules.ts` |
| Pruebas | `tests/Feature/Textil/FabricRollToleranceReviewTest.php`, `FabricRollRestrictedInventoryTest.php`, `ReceptionPendingCreditNotePayloadTest.php`, `FabricReceptionAccountingTest.php`, `tests/Feature/Finance/CreditNoteRejectedRollsTest.php`, `CreditNotePartialPostingTest.php`, `CreditNoteFabricInvoiceAutoPostTest.php` |

## 8. Cómo cambiar una regla

1. Cambiar el servicio backend correspondiente (tolerancia en `FabricRollService`, enlace/retención en `FabricRollCreditNoteService`, asiento en `AccountingPostingService`).
2. Si afecta a la pantalla, cambiar la función equivalente en `resources/js/domain/Expenses/rules.ts`.
3. Actualizar este documento y [REGLAS_ESTADOS_COMPRAS.md](REGLAS_ESTADOS_COMPRAS.md).
4. Cubrir el caso en el test de la lista anterior más cercano.

## 9. Supuestos y pendientes (para validar)

**Con contabilidad**
- El asiento parcial escala **todas** las líneas de la factura, incluidas retenciones e IVA, en la misma proporción. Se asume que la retención se reversa proporcionalmente.
- La base de la proporción es el total bruto (subtotal + IVA) del asiento; tolerancia de ±0,01 para considerar una nota "total".
- El débito va a `inventory_account_id` si el ítem lleva inventario y a `cost_of_sale_account_id` si no. Hay que confirmar que los ítems de tela tengan la cuenta correcta; si no, la factura queda sin asiento y sale la advertencia.
- Las notas débito de compra siguen sin asiento automático.
- Las facturas de tela aprobadas antes de este cambio no tienen backfill: se contabilizan al crear una nota crédito sobre ellas. Si se quiere un backfill masivo existe como referencia el comando del worktree `contabilidad-compras` (`accounting:post-pending-invoices`), no fusionado.
- Si el posteo falla al aprobar la recepción no hay reintento manual dedicado; solo se corrige al crear una nota crédito.

**De negocio**
- La tolerancia por rollo se deriva de la de línea: no hay una tolerancia individual por rollo.
- Un rollo rechazado no se puede reabrir ni "liberar" a disponible; el único destino es la baja.
- Quien hoy corrige omisiones tras aprobar con `ajustar telas rollos` ya no puede; solo quien tenga el permiso súper puede agregar rollos, y sigue sin existir reapertura de una recepción aprobada o rechazada.
- `FabricRollService::available()` no filtra por `approval_status` (no lo usa nadie hoy); si se llegara a usar mostraría rollos rechazados.
