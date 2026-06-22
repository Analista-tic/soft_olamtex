# Módulo de Gastos

**Última actualización:** 2026-06-12
**Propósito:** Explicar qué hace el módulo de Gastos, cómo funciona, qué datos maneja y cómo se conecta con el resto del sistema.

---

## 1. ¿Qué hace este módulo?

El módulo de Gastos gestiona **todo lo relacionado con las compras que hace la empresa**: materiales, insumos, servicios y cualquier pago a proveedores.

En términos simples:
1. Un colaborador hace una **solicitud de compra** cuando necesita algo.
2. La solicitud se aprueba y se convierte en una **orden de compra** al proveedor.
3. El proveedor envía la mercancía junto con una **factura de compra**.
4. Se registra la recepción y el pago al proveedor.
5. Para gastos menores (sin factura formal), se usa un **comprobante de egreso**.

---

## 2. ¿Quién usa este módulo?

| Rol | ¿Para qué lo usa? |
|-----|-------------------|
| Colaborador / Área solicitante | Crear solicitudes de compra |
| Jefe / Aprobador | Aprobar o rechazar solicitudes |
| Comprador (buyer) | Gestionar órdenes de compra con proveedores |
| Contabilidad | Registrar facturas de compra y pagos |
| Gerencia | Revisar gastos, órdenes pendientes y estado de pagos |

---

## 3. Pantallas principales

| Pantalla | Ruta en el código | ¿Qué hace? |
|----------|-------------------|------------|
| Solicitudes de compra | `resources/js/pages/Expenses/PurchaseRequests/` | Crear y aprobar solicitudes internas |
| Órdenes de compra | `resources/js/pages/Expenses/PurchaseOrders/` | Órdenes enviadas al proveedor |
| Facturas de compra | `resources/js/pages/Expenses/PurchaseInvoices/` | Facturas recibidas de proveedores |
| Recepción | `resources/js/pages/Expenses/Reception/` | Registro de recepción de mercancía |
| Comprobantes de egreso | `resources/js/pages/Expenses/ExpenseVouchers/` | Pagos directos sin orden formal |

---

## 4. Flujo principal (paso a paso)

```
[1] SOLICITUD DE COMPRA (purchase_request)
    → Un colaborador pide que se compre algo
    → Estados: Borrador → Pendiente de aprobación → Aprobada / Rechazada / Cancelada → Procesada
        ↓ (si se aprueba)
[2] ORDEN DE COMPRA (purchase_order)
    → Se formaliza el pedido al proveedor
    → Estados: Pendiente → Aprobada / Rechazada → Facturada parcial → Facturada
        ↓ (cuando llega la mercancía con factura)
[3] FACTURA DE COMPRA (purchase_invoice)
    → Se registra la factura del proveedor
    → Estados: Pendiente de recepción → Recibida / Rechazada
        ↓
[4] PAGO AL PROVEEDOR
    → Se registra el pago

─── FLUJO ALTERNATIVO (gastos menores) ───

[A] COMPROBANTE DE EGRESO (expense_voucher)
    → Para pagos directos sin proceso formal de compra
    → Se puede anular si hubo error
```

---

## 5. Dato importante: documentos de compra también usan una tabla central

> Al igual que en Ingresos, las **órdenes de compra y facturas de compra** comparten la tabla `purchase_documents`, diferenciadas por el campo `document_type`.

| Valor de `document_type` | ¿Qué es? |
|--------------------------|----------|
| `purchase_order` | Orden de compra al proveedor |
| `purchase_invoice` | Factura de compra recibida |
| `purchase_credit_note` | Nota crédito de compra |
| `purchase_debit_note` | Nota débito de compra |

Las **solicitudes de compra** tienen su propia tabla (`purchase_requests`) porque tienen un flujo de aprobación diferente.

---

## 6. Piezas clave del módulo

### 6.1 Solicitud de compra (`purchase_requests`)
Es el punto de entrada cuando alguien dentro de la empresa necesita comprar algo.

Datos principales:
- Número de solicitud
- Quién la solicita (`requester_user_id`)
- A quién se le solicita comprar (`solicited_to`) — ej: "Gerencia", "Compras"
- Quién es el comprador asignado (`buyer_user_id`)
- Proveedor sugerido, fecha, monto estimado
- Lista de ítems solicitados (guardada como JSON — nombre, cantidad, imagen de referencia)
- Estado de aprobación con fechas y usuarios responsables
- Razón de rechazo si aplica

### 6.2 Orden de compra (`purchase_documents` con tipo `purchase_order`)
Formaliza el pedido al proveedor una vez aprobada la solicitud.

Datos principales:
- Número de orden, proveedor (`contact_id`)
- Número de orden del proveedor (`supplier_order_number`)
- Estado de aprobación (puede requerir aprobación interna adicional)
- Comprador responsable (`buyer_user_id`)
- Fecha de entrega esperada
- Si hubo diferencias en la recepción (`has_difference`, `difference_status`)
- Retenciones aplicadas

### 6.3 Factura de compra (`purchase_documents` con tipo `purchase_invoice`)
Registra la factura que envía el proveedor.

Datos principales:
- Número de factura del proveedor (`supplier_invoice_number`)
- Vinculada a la orden de compra (`parent_document_id`)
- Estado: pendiente de recepción → recibida / rechazada
- Razón de rechazo e imagen de evidencia si aplica
- Pagos realizados

### 6.4 Comprobante de egreso (`finance_expense_vouchers`)
Para gastos menores o pagos directos que no pasan por el flujo de solicitud/orden.

Datos principales:
- Número de comprobante
- Contacto/proveedor al que se le pagó
- Fecha de pago, cuenta bancaria usada
- Método de pago, referencia
- Se puede anular con razón de anulación

---

## 7. Estados por tipo de documento

### Solicitud de compra
| Estado | Significado |
|--------|-------------|
| `draft` | Borrador, aún no enviada a aprobación |
| `pending` | Enviada, esperando aprobación |
| `approved` | Aprobada, lista para generar orden |
| `rejected` | Rechazada con motivo |
| `cancelled` | Cancelada por el solicitante |
| `processed` | Ya se generó la orden de compra |

### Orden de compra
| Estado | Significado |
|--------|-------------|
| `pending` | Pendiente de aprobación interna |
| `approved` | Aprobada, enviada al proveedor |
| `rejected` | Rechazada |
| `partially_invoiced` | Facturada parcialmente |
| `invoiced` | Completamente facturada |

### Factura de compra
| Estado | Significado |
|--------|-------------|
| `pending_receipt` | Pendiente de recepción física de la mercancía |
| `received` | Mercancía recibida conforme |
| `rejected` | Mercancía rechazada (con evidencia) |

---

## 8. Reglas de negocio importantes (¡no romper!)

1. **Solo las solicitudes aprobadas pueden generar órdenes de compra.**
2. **Una orden solo puede facturarse si está aprobada o parcialmente facturada.**
3. **Una factura rechazada debe tener razón de rechazo e imagen de evidencia.**
4. **Una orden de compra solo se puede editar si está en estado pendiente** — una vez aprobada, es inmutable.
5. **Los ítems de la solicitud se guardan como JSON** (no como filas en otra tabla) — son de libre escritura, sin validación contra el catálogo de ítems.
6. **El comprobante de egreso se puede anular** pero nunca eliminar — queda como registro histórico.
7. **Si hay diferencias en la recepción** de una factura, se activa un flujo de aprobación de diferencias antes de procesarla.

---

## 9. Tablas de base de datos

| Tabla | ¿Qué guarda? |
|-------|-------------|
| `purchase_requests` | Solicitudes de compra internas |
| `purchase_documents` | Órdenes de compra y facturas de compra |
| `purchase_document_items` | Líneas de productos de órdenes y facturas |
| `purchase_payments` | Pagos realizados a proveedores |
| `finance_expense_vouchers` | Comprobantes de egreso (gastos menores) |

---

## 10. Conexiones con otros módulos

| Módulo | ¿Cómo se conecta con Gastos? |
|--------|------------------------------|
| **Datos Maestros** | Los proveedores vienen de `contacts`; los ítems del catálogo de `items` |
| **Inventario** | Las facturas de compra recibidas generan entradas al inventario |
| **Textil** | Las facturas de tela tienen su propio flujo especial dentro de gastos |
| **Finanzas** | Los pagos a proveedores afectan cuentas bancarias y contabilidad |
| **Configuración** | Los permisos definen quién puede aprobar, rechazar o pagar |

---

## 11. Archivos clave para un desarrollador

| Archivo | ¿Para qué? |
|---------|------------|
| `app/Models/Expenses/PurchaseRequest.php` | Modelo de solicitudes — contiene toda la lógica de estados |
| `app/Models/Expenses/PurchaseDocument.php` | Modelo de órdenes y facturas |
| `app/Models/Expenses/ExpenseVoucher.php` | Modelo de comprobantes de egreso |
| `app/Http/Controllers/Expenses/PurchaseRequestController.php` | Lógica de solicitudes |
| `app/Http/Controllers/Expenses/PurchaseOrderController.php` | Lógica de órdenes de compra |
| `app/Http/Controllers/Expenses/PurchaseInvoiceController.php` | Lógica de facturas de compra |
| `app/Http/Controllers/Expenses/ExpenseVoucherController.php` | Lógica de comprobantes de egreso |
| `resources/js/pages/Expenses/PurchaseRequests/Index.vue` | Pantalla de solicitudes |
| `resources/js/pages/Expenses/PurchaseOrders/Index.vue` | Pantalla de órdenes |
