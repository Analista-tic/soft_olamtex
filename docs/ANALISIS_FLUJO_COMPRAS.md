# Análisis del Flujo de Compras - Estado Actual vs Requerimientos

## 📊 Resumen Ejecutivo

Este documento compara el estado actual de implementación del flujo de compras con los requerimientos funcionales especificados.

---

## 1. Solicitud de Compra (PurchaseRequest)

### ✅ Estado Actual

**Estados implementados:**
- `pending` - Pendiente
- `processed` - Procesada

**Campos en modelo:**
- `status` (enum)
- `processed_at` (timestamp, nullable)
- `processed_by_user_id` (foreign key, nullable)

**Métodos disponibles:**
- `isPending()`
- `isProcessed()`
- `markAsProcessed()`

### ❌ Requerimientos Faltantes

**Estados requeridos:**
- ❌ `draft` - Borrador (no implementado)
- ✅ `pending` - Pendiente (implementado)
- ❌ `approved` - Aprobada (no implementado)
- ❌ `rejected` - Rechazada (no implementado)
- ❌ `cancelled` - Cancelada (opcional, no implementado)

**Campos faltantes:**
- ❌ `approved_at` (timestamp)
- ❌ `approved_by_user_id` (foreign key)
- ❌ `rejected_at` (timestamp)
- ❌ `rejected_by_user_id` (foreign key)
- ❌ `rejection_reason` (text)

**Métodos faltantes:**
- ❌ `canEdit()` - Solo `draft` y `pending` son editables
- ❌ `canApprove()` - Solo `pending` puede aprobarse
- ❌ `canReject()` - Solo `pending` puede rechazarse
- ❌ `approve()` - Transición `pending` → `approved`
- ❌ `reject()` - Transición `pending` → `rejected`
- ❌ `sendToApproval()` - Transición `draft` → `pending`

**Flujo actual:**
```
Usuario crea → pending
(No hay draft ni aprobación)
```

**Flujo requerido:**
```
Usuario crea → draft
Usuario envía → pending
Aprobador aprueba → approved
Aprobador rechaza → rejected
Desde approved → se crea Orden
```

---

## 2. Orden de Compra (PurchaseDocument type=order)

### ✅ Estado Actual

**Estados implementados:**
- `pending` - Pendiente
- `approved` - Aprobada
- `sent` - Enviada
- `invoiced` - Facturada
- `rejected` - Rechazada

**Campos en modelo:**
- `status` (enum)
- `approved_at` (timestamp, nullable)
- `approved_by_user_id` (foreign key, nullable)
- `rejected_at` (timestamp, nullable)
- `rejected_by_user_id` (foreign key, nullable)
- `rejection_reason` (text, nullable)

**Métodos en enum:**
- `canEdit()` - Solo `pending`
- `canApprove()` - Solo `pending`
- `canReject()` - Solo `pending`
- `canInvoice()` - Solo `approved`

### ❌ Requerimientos Faltantes

**Estados requeridos:**
- ❌ `draft` - Borrador (no implementado)
- ✅ `pending` - Pendiente (implementado)
- ✅ `approved` - Aprobada (implementado)
- ✅ `sent` - Enviada (implementado)
- ✅ `rejected` - Rechazada (implementado)
- ❌ `cancelled` - Cancelada (no implementado)

**Flujo actual:**
```
Se crea desde solicitud → pending (directamente)
Se aprueba → approved
Se envía → sent
Se factura → invoiced
```

**Flujo requerido:**
```
Se crea desde solicitud approved → draft
Se envía a aprobación → pending
Aprobador aprueba → approved
Aprobador rechaza → rejected
Se envía al proveedor → sent
Desde approved o sent → se crea Factura
```

**Reglas faltantes:**
- ❌ `draft` debe ser editable
- ❌ `approved` debe congelar proveedor y precios (validación faltante)
- ❌ No se debe eliminar, solo `cancelled` (soft delete ya implementado, pero falta estado)

---

## 3. Factura de Compra (PurchaseDocument type=invoice)

### ✅ Estado Actual

**Estados implementados:**
- `pending_receipt` - Pendiente de recepción
- `received` - Recibida
- `rejected` - Rechazada

**Campos en modelo:**
- `status` (enum)
- `rejected_at` (timestamp, nullable)
- `rejected_by_user_id` (foreign key, nullable)
- `rejection_reason` (text, nullable)

**Métodos en enum:**
- `canEdit()` - Solo `pending_receipt`
- `canReject()` - Solo `pending_receipt`

### ❌ Requerimientos Faltantes

**Estados requeridos:**
- ❌ `draft` - Borrador (no implementado)
- ✅ `pending_receipt` - Pendiente de recepción (implementado)
- ✅ `received` - Recibida (implementado)
- ❌ `accounted` - Contabilizada (no implementado)
- ✅ `rejected` - Rechazada (implementado)

**Campos faltantes:**
- ❌ `accounted_at` (timestamp)
- ❌ `accounted_by_user_id` (foreign key)

**Métodos faltantes:**
- ❌ `canReceive()` - Solo `pending_receipt` puede recibirse
- ❌ `receive()` - Transición `pending_receipt` → `received`
- ❌ `account()` - Transición `received` → `accounted` (solo contabilidad)

**Flujo actual:**
```
Se crea desde orden → pending_receipt (directamente)
Usuario recibe → received
Usuario rechaza → rejected
```

**Flujo requerido:**
```
Se crea desde orden approved → draft
Se registra recepción → pending_receipt
Usuario recibe → received
Usuario rechaza → rejected
Contabilidad registra → accounted (estado final)
```

**Reglas faltantes:**
- ❌ `accounted` es estado final (no se edita ni elimina)
- ❌ Correcciones → nota crédito/débito (módulo no implementado)

---

## 4. Trazabilidad

### ✅ Estado Actual

**Implementado:**
- ✅ Relación `parent_document_id` en `purchase_documents`
- ✅ Relación `purchase_request_id` en `purchase_documents` (para órdenes)
- ✅ Métodos `parent()` y `children()` en modelo
- ✅ Snapshot de datos en cada documento (items, precios, etc.)

### ❌ Mejoras Necesarias

**Faltante:**
- ❌ Historial de estados obligatorio (tabla `purchase_document_status_history`)
- ❌ Auditoría de cambios de estado (quién, cuándo, por qué)
- ❌ Validación de integridad: no se puede facturar sin orden aprobada

---

## 5. Reglas Transversales

### ✅ Estado Actual

**Implementado:**
- ✅ Métodos de validación en enums (`canApprove()`, `canEdit()`, etc.)
- ✅ Soft deletes para no eliminar físicamente
- ✅ Campos de auditoría (`created_by_user_id`, `approved_by_user_id`, etc.)

### ❌ Requerimientos Faltantes

**Faltante:**
- ❌ El usuario NO debe cambiar estados manualmente (validación en controladores)
- ❌ Estados cambian solo por acciones (botones/endpoints específicos)
- ❌ Flujo irreversible (validación de transiciones)

---

## 📋 Plan de Implementación Recomendado

### Fase 1: Completar Estados de PurchaseRequest

1. **Actualizar enum `PurchaseRequestStatus`:**
   - Agregar `DRAFT`, `APPROVED`, `REJECTED`, `CANCELLED`
   - Actualizar método `label()`

2. **Actualizar migración:**
   - Agregar campos: `approved_at`, `approved_by_user_id`, `rejected_at`, `rejected_by_user_id`, `rejection_reason`
   - Actualizar enum en base de datos

3. **Actualizar modelo `PurchaseRequest`:**
   - Agregar métodos: `canEdit()`, `canApprove()`, `canReject()`, `approve()`, `reject()`, `sendToApproval()`
   - Agregar relaciones: `approvedBy()`, `rejectedBy()`

4. **Actualizar servicios:**
   - `PurchaseRequestService::sendToApproval()`
   - `PurchaseRequestService::approve()`
   - `PurchaseRequestService::reject()`

5. **Actualizar controladores:**
   - Endpoints: `POST /purchase-requests/{id}/send-to-approval`
   - Endpoints: `POST /purchase-requests/{id}/approve`
   - Endpoints: `POST /purchase-requests/{id}/reject`

### Fase 2: Completar Estados de PurchaseOrder

1. **Actualizar enum `PurchaseOrderStatus`:**
   - Agregar `DRAFT`, `CANCELLED`
   - Actualizar métodos de validación

2. **Actualizar migración:**
   - Agregar estado `cancelled` al enum

3. **Actualizar modelo:**
   - Agregar método `cancel()`
   - Validar que `approved` congela proveedor y precios

4. **Actualizar servicios:**
   - Validar que solo desde `approved` o `sent` se puede facturar
   - Validar que `approved` no permite editar proveedor/precios

### Fase 3: Completar Estados de PurchaseInvoice

1. **Actualizar enum `PurchaseInvoiceStatus`:**
   - Agregar `DRAFT`, `ACCOUNTED`
   - Actualizar métodos de validación

2. **Actualizar migración:**
   - Agregar campos: `accounted_at`, `accounted_by_user_id`
   - Agregar estado `accounted` al enum

3. **Actualizar modelo:**
   - Agregar métodos: `canReceive()`, `receive()`, `account()`
   - Agregar relación: `accountedBy()`

4. **Actualizar servicios:**
   - `PurchaseDocumentInvoiceService::receive()`
   - `PurchaseDocumentInvoiceService::account()` (solo para rol contabilidad)

### Fase 4: Historial de Estados

1. **Crear migración `create_purchase_document_status_history_table`:**
   - `purchase_document_id` (foreign key)
   - `status_from` (string)
   - `status_to` (string)
   - `changed_by_user_id` (foreign key)
   - `reason` (text, nullable)
   - `created_at` (timestamp)

2. **Crear modelo `PurchaseDocumentStatusHistory`**

3. **Actualizar servicios:**
   - Registrar cada cambio de estado en el historial

### Fase 5: Validaciones y Reglas de Negocio

1. **Validar transiciones de estado:**
   - Crear clase `StateTransitionValidator`
   - Validar que solo transiciones permitidas se ejecuten

2. **Validar integridad:**
   - No facturar sin orden aprobada
   - No crear orden sin solicitud aprobada
   - No editar documentos en estados finales

3. **Actualizar controladores:**
   - Remover capacidad de cambiar `status` directamente
   - Solo permitir cambios mediante acciones específicas

---

## 🎯 Prioridades

### Alta Prioridad
1. ✅ Completar estados de PurchaseRequest (draft, approved, rejected)
2. ✅ Agregar estado `draft` a PurchaseOrder
3. ✅ Agregar estado `accounted` a PurchaseInvoice
4. ✅ Validar que estados solo cambien por acciones

### Media Prioridad
5. ✅ Historial de estados
6. ✅ Validación de transiciones
7. ✅ Congelar proveedor/precios en órdenes aprobadas

### Baja Prioridad
8. ✅ Estado `cancelled` (opcional)
9. ✅ Módulo de notas crédito/débito

---

## 📝 Notas Técnicas

### Consideraciones

1. **Migración de datos:**
   - Los registros existentes con `status = 'pending'` deben mantenerse
   - Los registros con `status = 'processed'` pueden mapearse a `approved`

2. **Compatibilidad:**
   - Mantener métodos existentes para no romper código
   - Agregar nuevos métodos sin deprecar los antiguos inicialmente

3. **Frontend:**
   - Actualizar componentes Vue para mostrar nuevos estados
   - Agregar botones de acción según `can*()` methods
   - Deshabilitar edición según `canEdit()`

---

## ✅ Checklist de Implementación

### PurchaseRequest
- [ ] Agregar estados: draft, approved, rejected, cancelled
- [ ] Agregar campos de aprobación/rechazo
- [ ] Implementar métodos: canEdit, canApprove, canReject, approve, reject, sendToApproval
- [ ] Actualizar servicios y controladores
- [ ] Actualizar frontend

### PurchaseOrder
- [ ] Agregar estado: draft, cancelled
- [ ] Validar congelamiento de proveedor/precios en approved
- [ ] Actualizar servicios y controladores
- [ ] Actualizar frontend

### PurchaseInvoice
- [ ] Agregar estados: draft, accounted
- [ ] Agregar campos de contabilización
- [ ] Implementar métodos: canReceive, receive, account
- [ ] Actualizar servicios y controladores
- [ ] Actualizar frontend

### General
- [ ] Crear historial de estados
- [ ] Validar transiciones
- [ ] Actualizar documentación
- [ ] Tests unitarios
- [ ] Tests de integración
