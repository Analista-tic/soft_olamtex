# Módulo de Ingresos

**Última actualización:** 2026-06-12
**Propósito:** Explicar qué hace el módulo de Ingresos, cómo funciona, qué datos maneja y cómo se conecta con el resto del sistema.

---

## 1. ¿Qué hace este módulo?

El módulo de Ingresos gestiona **todo lo relacionado con las ventas de la empresa**: desde que se le presenta una propuesta de precio a un cliente, hasta que el cliente paga.

En términos simples:
1. Se crea una **cotización** para ofrecerle precio a un cliente.
2. El cliente acepta y se convierte en una **orden de venta**.
3. Se genera una **factura de venta** para cobrar.
4. El cliente paga y se registra un **recibo de caja**.
5. Si hay ajustes al valor facturado, se crean **notas crédito o débito**.

---

## 2. ¿Quién usa este módulo?

| Rol | ¿Para qué lo usa? |
|-----|-------------------|
| Vendedor / Comercial | Crear cotizaciones y órdenes de venta |
| Facturación | Emitir facturas de venta |
| Cartera / Tesorería | Registrar pagos y recibos de caja |
| Contabilidad | Revisar notas crédito/débito y estado de pagos |
| Gerencia | Consultar cartera, ventas y estado de clientes |

---

## 3. Pantallas principales

| Pantalla | Ruta en el código | ¿Qué hace? |
|----------|-------------------|------------|
| Cotizaciones | `resources/js/pages/Income/Quotes/` | Crear y gestionar cotizaciones a clientes |
| Órdenes de venta | `resources/js/pages/Income/SalesOrders/` | Órdenes confirmadas por el cliente |
| Facturas de venta | `resources/js/pages/Income/SalesInvoices/` | Facturación a clientes |
| Recibos de caja | `resources/js/pages/Income/CashReceipts/` | Registro de pagos recibidos |
| Notas crédito/débito | `resources/js/pages/Income/CreditDebitNotes/` | Ajustes sobre facturas emitidas |

---

## 4. Flujo principal (paso a paso)

```
[1] COTIZACIÓN (quote)
    → Se le presenta precio al cliente
    → Estados: Borrador → Enviada → Aceptada / Rechazada / Vencida
        ↓ (si el cliente acepta)
[2] ORDEN DE VENTA (sales_order)
    → Compromiso formal de compra
    → Estados: Borrador → Confirmada → En proceso → Facturada / Cancelada
        ↓
[3] FACTURA DE VENTA (sales_invoice)
    → Documento de cobro oficial
    → Estados: Borrador → Emitida → Pago parcial → Pagada / Anulada
        ↓
[4] RECIBO DE CAJA (cash_receipt)
    → Registro del pago del cliente
        ↓ (si hay ajuste de valor)
[5] NOTA CRÉDITO / DÉBITO
    → Nota crédito: reduce el valor de la factura
    → Nota débito: aumenta el valor de la factura
    → Estados: Borrador → Emitida → Anulada
```

---

## 5. Dato importante: todos usan la misma tabla

> **Diseño clave del sistema:** Cotizaciones, órdenes de venta, facturas, recibos de caja y notas crédito/débito **se guardan todas en la misma tabla** (`sale_documents`). Lo que las diferencia es el campo `document_type`.

| Valor de `document_type` | ¿Qué es? |
|--------------------------|----------|
| `quote` | Cotización |
| `sales_order` | Orden de venta |
| `sales_invoice` | Factura de venta |
| `cash_receipt` | Recibo de caja |
| `sale_credit_note` | Nota crédito |
| `sale_debit_note` | Nota débito |

Esto significa que cuando vayas a buscar una cotización en código, verás un filtro como `where('document_type', 'quote')`.

---

## 6. Piezas clave del módulo

### 6.1 Documento de venta (`sale_documents`)
Es la tabla central. Todos los documentos del módulo viven aquí.

Datos principales:
- Número de documento (formato: `PREFIJO-0001`)
- Tipo de documento (`document_type`)
- Cliente (`contact_id` → viene de Datos Maestros)
- Fecha del documento, fecha de vencimiento, fecha de entrega
- Estado (`status`) — varía según el tipo de documento
- Estado de pago: pendiente, parcial, pagado
- Monto pagado acumulado
- Condiciones de pago y días de plazo
- Número de orden del cliente (referencia interna del cliente)
- Retenciones aplicadas

### 6.2 Ítems del documento (`sale_document_items`)
Cada documento tiene una lista de productos/servicios vendidos.

Datos por ítem:
- Ítem o artículo (viene de Datos Maestros)
- Nombre del ítem (se guarda el nombre al momento de la venta)
- Cantidad, precio unitario, descuento
- Impuesto y total de impuesto
- Total de línea

### 6.3 Pagos (`sale_payments`)
Registra cada pago que hace el cliente contra una factura.

---

## 7. Estados por tipo de documento

### Cotización
| Estado | Significado |
|--------|-------------|
| `draft` | Borrador, aún no enviada |
| `sent` | Enviada al cliente |
| `accepted` | Cliente la aceptó |
| `rejected` | Cliente la rechazó |
| `expired` | Venció sin respuesta |
| `converted` | Se convirtió en orden de venta |

### Orden de venta
| Estado | Significado |
|--------|-------------|
| `draft` | Borrador |
| `confirmed` | Confirmada |
| `in_process` | En proceso de despacho/producción |
| `invoiced` | Facturada completamente |
| `cancelled` | Cancelada |

### Factura de venta
| Estado | Significado |
|--------|-------------|
| `draft` | Borrador |
| `issued` | Emitida (enviada al cliente) |
| `partial` | Pago parcial recibido |
| `paid` | Pagada completamente |
| `voided` | Anulada |

### Nota crédito / débito
| Estado | Significado |
|--------|-------------|
| `draft` | Borrador |
| `issued` | Emitida |
| `voided` | Anulada |

---

## 8. Reglas de negocio importantes (¡no romper!)

1. **Una cotización solo se puede convertir si está enviada o aceptada** — No se puede convertir un borrador directamente a orden de venta.
2. **Una cotización aceptada o convertida no se puede editar** — Solo se puede editar si está en borrador, enviada, rechazada o vencida.
3. **Una factura solo se puede editar si está en borrador** — Una vez emitida, es inmutable.
4. **Una factura solo se puede anular si está emitida o en pago parcial** — No se puede anular una factura ya pagada.
5. **El número de documento se genera automáticamente** con formato `PREFIJO-0001` — No se edita manualmente.
6. **Una nota crédito reduce el valor** de la factura a la que está vinculada; una nota débito lo aumenta.
7. **Los ítems guardan el nombre del producto en el momento de la venta** (`item_name`) — Así, si el producto cambia de nombre después, la factura queda con el nombre original.

---

## 9. Tablas de base de datos

| Tabla | ¿Qué guarda? |
|-------|-------------|
| `sale_documents` | Todos los documentos de venta (cotizaciones, órdenes, facturas, notas, recibos) |
| `sale_document_items` | Líneas de productos/servicios de cada documento |
| `sale_payments` | Pagos registrados contra facturas |

---

## 10. Conexiones con otros módulos

| Módulo | ¿Cómo se conecta con Ingresos? |
|--------|-------------------------------|
| **Datos Maestros** | Los clientes vienen de `contacts`; los productos de `items` |
| **Finanzas** | Los pagos registrados afectan la contabilidad (cuentas por cobrar) |
| **Inventario** | Las facturas de venta pueden generar movimientos de salida de inventario |
| **Configuración** | Los permisos controlan quién puede emitir, anular o ver documentos |

---

## 11. Archivos clave para un desarrollador

| Archivo | ¿Para qué? |
|---------|------------|
| `app/Models/Income/SaleDocument.php` | Modelo central — contiene todos los tipos de documentos y sus reglas |
| `app/Models/Income/SaleDocumentItem.php` | Modelo de líneas/ítems |
| `app/Models/Income/SalePayment.php` | Modelo de pagos |
| `app/Http/Controllers/Income/QuoteController.php` | Lógica de cotizaciones |
| `app/Http/Controllers/Income/SalesOrderController.php` | Lógica de órdenes de venta |
| `app/Http/Controllers/Income/SalesInvoiceController.php` | Lógica de facturas |
| `app/Http/Controllers/Income/CashReceiptController.php` | Lógica de recibos de caja |
| `app/Http/Controllers/Income/CreditDebitNoteController.php` | Lógica de notas crédito/débito |
| `resources/js/pages/Income/Quotes/Index.vue` | Pantalla de cotizaciones |
| `resources/js/pages/Income/SalesInvoices/Index.vue` | Pantalla de facturas |
