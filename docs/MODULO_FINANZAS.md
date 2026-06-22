# Módulo de Finanzas

**Última actualización:** 2026-06-12
**Propósito:** Explicar qué hace el módulo de Finanzas, cómo funciona, qué datos maneja y cómo se conecta con el resto del sistema.

---

## 1. ¿Qué hace este módulo?

El módulo de Finanzas es el **soporte contable y financiero** del sistema. No gestiona ventas ni compras directamente — eso lo hacen Ingresos y Gastos. Finanzas se encarga de:

- Organizar el **plan de cuentas contables** (el árbol de cuentas).
- Registrar las **cuentas bancarias** de la empresa.
- Gestionar **impuestos y retenciones** que se aplican a documentos.
- Registrar **recibos de caja** (pagos recibidos de clientes).
- Emitir **notas crédito/débito** de tipo financiero.
- Generar **asientos contables** (libro diario).
- Consultar el estado de **tesorería** (flujo de caja).

---

## 2. ¿Quién usa este módulo?

| Rol | ¿Para qué lo usa? |
|-----|-------------------|
| Contador | Gestionar plan de cuentas, asientos y retenciones |
| Tesorero / Cartera | Registrar pagos y consultar flujo de caja |
| Administrador del sistema | Configurar impuestos y cuentas bancarias |
| Gerencia | Consultar estado financiero y saldos |

---

## 3. Pantallas principales

| Pantalla | Ruta en el código | ¿Qué hace? |
|----------|-------------------|------------|
| Plan de cuentas | `resources/js/pages/Finance/AccountChart/` | Árbol jerárquico de cuentas contables |
| Cuentas bancarias | `resources/js/pages/Finance/Banks/` | Cuentas de banco, caja y tarjetas de la empresa |
| Impuestos | `resources/js/pages/Finance/Taxes/` | Configurar tasas de IVA y otros impuestos |
| Retenciones | `resources/js/pages/Finance/Retention/` | Tipos de retención (fuente, ICA, IVA) |
| Recibos de caja | `resources/js/pages/Finance/CashReceipts/` | Pagos recibidos de clientes |
| Notas crédito/débito | `resources/js/pages/Finance/CreditDebitNotes/` | Ajustes financieros sobre documentos |

---

## 4. Piezas clave del módulo

### 4.1 Plan de cuentas (`accounting_accounts`)
Es el **mapa de todas las cuentas contables** de la empresa, organizado en jerarquía (árbol).

Tipos de cuenta:
| Tipo | Significado |
|------|-------------|
| `asset` | Activo (lo que tiene la empresa) |
| `liability` | Pasivo (lo que debe la empresa) |
| `equity` | Patrimonio |
| `income` | Ingreso |
| `cost` | Costo |
| `expense` | Gasto |

Cuentas especiales (usadas automáticamente por el sistema):
| Tipo especial | ¿Para qué se usa? |
|---------------|-------------------|
| `accounts_receivable` | Cuentas por cobrar (clientes) |
| `accounts_payable` | Cuentas por pagar (proveedores) |
| `cash` | Caja |
| `bank` | Banco |

> La jerarquía funciona así: una cuenta puede tener una cuenta padre (`parent_id`). Las cuentas del nivel más bajo son las que **permiten movimientos** (`allows_entries = true`). Las cuentas de nivel superior son solo agrupadores.

### 4.2 Cuentas bancarias (`bank_accounts`)
Registra las cuentas reales de dinero de la empresa.

Tipos:
| Tipo | Significado |
|------|-------------|
| `bank` | Cuenta bancaria |
| `cash` | Caja (efectivo físico) |
| `credit_card` | Tarjeta de crédito |

Datos: nombre, banco, número de cuenta, moneda, saldo inicial, fecha de saldo inicial, cuenta contable asociada, si está activa.

> Cada cuenta bancaria está **vinculada a una cuenta contable** del plan de cuentas — así los movimientos bancarios quedan reflejados en la contabilidad.

### 4.3 Impuestos (`taxes`)
Define las tasas de impuesto (principalmente IVA) que se pueden aplicar a ítems y documentos.

Ejemplo: IVA 19%, IVA 5%, Excluido de IVA.

### 4.4 Retenciones (`retentions`)
Define los tipos de retención que se aplican al momento de pagar a un proveedor o recibir un pago de un cliente.

Ejemplos comunes en Colombia:
- Retención en la fuente (Retefuente)
- Retención de ICA
- Retención de IVA (Reteiva)

Las retenciones se guardan en los documentos de venta y compra como un campo JSON (`withholdings`).

### 4.5 Asientos contables (`journal_entries` y `journal_entry_lines`)
Son el **registro contable formal** de cada transacción. Cada asiento tiene líneas de débito y crédito que deben cuadrar (suma de débitos = suma de créditos).

> En este sistema, los asientos se generan automáticamente cuando se emiten facturas, se registran pagos, etc. No se crean manualmente en la mayoría de los casos.

### 4.6 Recibo de caja — módulo Finanzas (`finance_cash_receipts`)
Registro de un pago recibido de un cliente, vinculado a la cuenta bancaria donde ingresó el dinero.

> No confundir con el recibo de caja del módulo de **Ingresos** (que está en `sale_documents` con tipo `cash_receipt`). Este es el registro contable/financiero del mismo concepto.

---

## 5. Relaciones con el plan de cuentas

```
Plan de cuentas (árbol jerárquico)
    ├── 1. ACTIVO
    │   ├── 1.1 Activo corriente
    │   │   ├── 1.1.1 Caja ← cuenta especial: cash
    │   │   ├── 1.1.2 Bancos ← cuenta especial: bank
    │   │   └── 1.1.3 Cuentas por cobrar ← cuenta especial: accounts_receivable
    │   └── ...
    ├── 2. PASIVO
    │   └── 2.1 Cuentas por pagar ← cuenta especial: accounts_payable
    ├── 3. PATRIMONIO
    ├── 4. INGRESOS
    ├── 5. COSTOS
    └── 6. GASTOS
```

---

## 6. Reglas de negocio importantes (¡no romper!)

1. **Todo asiento debe cuadrar** — la suma de débitos debe ser igual a la suma de créditos. Si no cuadra, el sistema no lo acepta.
2. **Solo las cuentas con `allows_entries = true` pueden tener movimientos** — las cuentas agrupador no reciben débitos/créditos directos.
3. **Las cuentas especiales son únicas** — solo puede haber una cuenta con tipo especial `cash`, una con `bank`, etc. El sistema las busca automáticamente para asientos.
4. **Las cuentas bancarias inactivas no aparecen** en los formularios de pago — si se desactiva una cuenta, deja de estar disponible para nuevas transacciones.
5. **Los impuestos y retenciones se configuran aquí pero se usan en Ingresos y Gastos** — si se borra un impuesto en uso, puede dañar documentos existentes.
6. **Las retenciones se guardan como JSON en los documentos** — si se cambia la estructura de retenciones, documentos viejos pueden quedar inconsistentes.

---

## 7. Tablas de base de datos

| Tabla | ¿Qué guarda? |
|-------|-------------|
| `accounting_accounts` | Plan de cuentas jerárquico |
| `bank_accounts` | Cuentas bancarias, de caja y tarjetas |
| `taxes` | Tasas de impuesto (IVA, etc.) |
| `retentions` | Tipos de retención |
| `journal_entries` | Cabeceras de asientos contables |
| `journal_entry_lines` | Líneas de débito/crédito de cada asiento |
| `finance_cash_receipts` | Recibos de caja (registro financiero) |
| `finance_expense_vouchers` | Comprobantes de egreso (compartido con Gastos) |

---

## 8. Conexiones con otros módulos

| Módulo | ¿Cómo se conecta con Finanzas? |
|--------|-------------------------------|
| **Ingresos** | Las facturas de venta usan impuestos y retenciones de aquí; los pagos afectan cuentas bancarias |
| **Gastos** | Las facturas de compra aplican retenciones; los pagos a proveedores salen de cuentas bancarias |
| **Inventario** | La valorización del inventario se refleja en cuentas contables de activo |
| **Datos Maestros** | Los ítems del catálogo tienen una cuenta contable de ingreso y una de costo asignadas |
| **Configuración** | Los permisos controlan quién puede modificar el plan de cuentas |

---

## 9. Archivos clave para un desarrollador

| Archivo | ¿Para qué? |
|---------|------------|
| `app/Models/Finance/AccountingAccount.php` | Modelo del plan de cuentas — contiene tipos y lógica débito/crédito |
| `app/Models/Finance/BankAccount.php` | Modelo de cuentas bancarias |
| `app/Models/Finance/Tax.php` | Modelo de impuestos |
| `app/Models/Finance/Retention.php` | Modelo de retenciones |
| `app/Models/Finance/JournalEntry.php` | Modelo de asientos contables |
| `app/Models/Finance/JournalEntryLine.php` | Modelo de líneas de asiento |
| `app/Http/Controllers/Finance/AccountingAccountController.php` | Lógica del plan de cuentas |
| `app/Http/Controllers/Finance/BankAccountController.php` | Lógica de cuentas bancarias |
| `app/Http/Controllers/Finance/TaxController.php` | Lógica de impuestos |
| `app/Http/Controllers/Finance/TreasuryLookupController.php` | Lógica de consulta de tesorería |
| `resources/js/pages/Finance/AccountChart/Index.vue` | Pantalla del plan de cuentas |
