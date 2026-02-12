# Análisis y Diseño: Módulo de Listados Operacionales

## 📋 Resumen Ejecutivo

Diseño de un módulo para gestionar **listados operacionales** en empresas de confección textil con **50-120 operaciones por producto**, integrado al sistema Soft Olamtex existente. El módulo captura operaciones de confección, las relaciona con productos y máquinas, y genera reportes de tiempos, costos y productividad.

### 🔑 Decisión de Diseño Principal: Arquitectura en 3 Niveles

✅ **IMPORTANTE**: Este diseño está optimizado para **MUCHAS operaciones** (50-120 por producto):

**Nivel 1 – Catálogo (Operation)**
- Estándares de ingeniería sin cambios frecuentes
- Reutilizable en múltiples productos

**Nivel 2 – Ruta del Producto (ProductSheetOperation)**
- **LA TABLA CENTRAL**: 50-120 operaciones por producto
- Define la "receta" completa del producto
- Editable (factores pueden ajustarse para futuras órdenes)
- Versionada automáticamente (próximo paso)

**Nivel 3 – Orden de Producción (ProductionOrderOperation)**
- **COPIA CONGELADA** de la ruta en momento de crear orden
- Inmutable para auditoría histórica exacta
- Datos precompilados para facturación rápida

**Ventajas de este enfoque:**
- ✅ Escalable: 100 operaciones × 1 orden = 100 inserts (rápido)
- ✅ Aislamiento: cambios en ruta futura NO afectan órdenes pasadas
- ✅ Auditoría: historiales exactos sin búsquedas dinámicas
- ✅ Versionado: fácil agregar versiones de ProductSheet después
- ✅ Índices: recuperación O(log n) con (product_sheet_id, sequence)

**Modelos principales:**
1. **Operation**: Catálogo de operaciones estándar (sin cambios frecuentes)
2. **ProductSheetOperation**: Ruta del producto con 50-120 operaciones (tabla central)
3. **ProductSheetOperationHistory**: Versionado de cambios en factores de la ruta
4. **ProductionOrderOperation**: Snapshot congelado para cada orden de producción

---

## 🎯 Objetivo del Módulo

Gestionar **operaciones masivas de confección** (50-120 por producto) con arquitectura en 3 niveles:

### Nivel 1: Catálogo (Operation)
- Ingeniería estándar inmutable
- Sam_base tomado con cronómetro en muestra
- Reutilizable en múltiples productos

### Nivel 2: Ruta de Producto (ProductSheetOperation)
- Define TODAS las operaciones del producto
- Secuencia ordenada (10, 20, 30... para inserciones)
- Snapshots editables de máquina, tela, factores operario
- Versionada automáticamente si hay cambios (próximo paso)

### Nivel 3: Orden de Producción (ProductionOrderOperation)
- Copia congelada de la ruta en momento de crear orden
- Todas las operaciones precompiladas
- Inmutable para auditoría histórica exacta
- Rápida disponibilidad para facturación

### ⚡ Ventajas del Diseño para Manufactura Industrial

1. **Escalable** → 100 operaciones sin problemas de rendimiento
2. **Aislamiento** → Cambios en ruta NO afectan órdenes pasadas (snapshot protege)
3. **Precisión histórica** → Órdenes congeladas con valores exactos para auditoría
4. **Integridad de datos** → Snapshots en 2 niveles (doble congelamiento)
5. **Trazabilidad** → ProductSheetOperationHistory registra quién cambió qué y por qué
6. **Versionabilidad** → Arquitectura lista para versiones de ProductSheet
7. **Reportes exactos** → Datos precompilados sin joins dinámicos

### 📝 Ejemplo de Flujo Completo

**PASO 1: TOMAR SAM EN PRODUCTSHEET (SABANA)**
```
PRODUCT SHEET MUESTRA (Sabana REF-001):
- reference: "REF-001"
- type: "muestra"

OPERACION REGISTRADA EN SABANA REF-001:
- operacion_id: [nueva operación]
- seccion: DELANTERO
- accion: CERRAR
- parte: TIRO
- product_sheet_id_reference: 1 (referencia a REF-001, dónde se tomó)
- maquina_id: 1 (MF01 usada para tomar el tiempo)
- sam_base: 0.25 minutos (cronómetro)
- factor_operario: [vacío, se usa 1.0 default para toma de muestra]
```

**PASO 2: CREAR CATÁLOGO DE OPERACIONES**
```
OPERATION (Catálogo global):
- código: OP-DEL-001
- sección: DELANTERO
- acción: CERRAR
- parte: TIRO
- ubicación: DELANTERO
- medida: 0.25 mts
- sam_base: 0.25 minutos
- product_sheet_reference: "REF-001" (dónde fue tomada)
- activo: true
- descripción: "Cerrar tiro delantero, tomado en muestra REF-001"
```

**PASO 3: REUTILIZAR EN OTRO PRODUCTSHEET CON FACTORES**
```
PRODUCT SHEET PRODUCTIVO (Sabana REF-002):
- reference: "REF-002"
- type: "productivo"

PRODUCTO OPERACION (copia con factores):
- producto_sheet_id: 2 (REF-002)
- operacion_id: 1 (referencia a OP-DEL-001)
- maquina_id: 1 (MF01)
- base_tela_id: 1
- sam_base: 0.25 (copiado origialmente)
- factor_operario: 1.25 (añadido, + experiencia operario promedio)
- suplemento_maquina: 1.08 (copiado de máquina actual)
- factor_tela: 1.05 (copiado de tela actual)
- sam_total: 0.25 × 1.08 × 1.05 × 1.25 = 0.354 minutos
```

### 🔄 Flujo Completo: Arquitectura de 3 Niveles

#### Flujo Simplificado
```
NIVEL 1: Operation (Catálogo)
         ↓
NIVEL 2: ProductSheet → ProductSheetOperation (Ruta del producto, 50-120 operaciones)
         ↓ (copiar snapshot al crear orden)
NIVEL 3: ProductionOrder → ProductionOrderOperation (Congelado para facturación)
```

#### Paso a Paso

**Fase 1: Construcción de ProductSheet (Ruta base)**
```
1. Usuario abre ProductSheet (ej: REF-002, tipo "productivo")
2. Para CADA operación que lleve el producto:
   - Selecciona si existe en catálogo (Operation) o actualiza
   - Define sequence (10, 20, 30... permite inserciones)
   - Selecciona máquina (parámetro operacional.maquinas)
   - Selecciona base de tela (parámetro operacional.fabric_bases)
   - Asigna factor_operario (ej: 1.25)
   - Sistema guarda snapshot y calcula sam_total_unitario
3. Repite hasta 50-120 operaciones
4. ProductSheet queda con ruta completa lista para usar
```

**Fase 2: Crear Orden de Producción (Copiar snapshot)**
```
1. Usuario abre Crear ProductionOrder
2. Selecciona:
   - Producto: ProductSheet REF-002
   - Cantidad lote: 500 unidades
3. Sistema automáticamente:
   - Trae TODAS las ProductSheetOperation de REF-002 (en orden sequence)
   - Crea ProductionOrderOperation para CADA una (100 inserts si hay 100 ops)
   - Calcula sam_total_lote = sam_total_operacion × cantidad_lote
4. ProductionOrder queda congelada e independiente
```

**Fase 3: Editar Factores del ProductSheet (Futuras órdenes)**
```
1. Usuario modifica factor_operario en ProductSheetOperation
   (ej: 1.25 → 1.35 en "Cerrar hombros")
2. Sistema registra en ProductSheetOperationHistory:
   - factor_operario_anterior: 1.25
   - factor_operario_nuevo: 1.35
   - sam_total_unitario_anterior: 0.354
   - sam_total_unitario_nuevo: 0.388
   - motivo: "Ajuste por eficiencia"
   - usuario_id: ID del usuario
3. IMPORTANTE: Órdenes anteriores NO se afectan (congeladas)
4. Próximas órdenes usarán el nuevo factor 1.35
```

**Fase 4: Auditoría Histórica Congelada**
```
ProductionOrder (2024-11-15):
├─ seq 10: sam_total_lote = (0.25 × 1.08 × 1.05 × 1.25) × 500 = 176.5 min
├─ seq 20: sam_total_lote = (0.18 × 1.10 × 1.05 × 1.20) × 500 = 119.3 min
└─ Total lote: 27,160 minutos (exacto para factura)

ProductionOrder (2025-02-11, DESPUÉS de cambiar factor a 1.35):
├─ seq 10: sam_total_lote = (0.25 × 1.08 × 1.05 × 1.35) × 500 = 191.2 min ← diferente
├─ seq 20: sam_total_lote = (0.18 × 1.10 × 1.05 × 1.20) × 500 = 119.3 min
└─ Total lote: 29,320 minutos (nuevo, facturable correctamente)
```

---

## 🏗️ Arquitectura Propuesta: Modelo Jerárquico en 3 Niveles

### Concepto Correcto (para 50-120 operaciones por producto)

La arquitectura está diseñada en **3 niveles independientes** para manejar masivamente sin problemas de rendimiento:

```
Nivel 1: Operation (Catálogo estándar)
         ↓ referencias
Nivel 2: ProductSheet → ProductSheetOperation (Ruta base del producto)
         ↓ copia (snapshot) al crear orden
Nivel 3: ProductionOrder → ProductionOrderOperation (Congelado para facturación/costos)
```

#### Nivel 1 - Catálogo (Ingeniería estándar)
**Operation**: Define qué se hace y tiempos estándar, independiente del producto.

#### Nivel 2 - Ruta del producto (Operaciones base del producto)
**ProductSheetOperation**: Lista todos los pasos del producto con factores base.
- Es la "receta" con **muchas operaciones** (50-120)
- Define orden de proceso (`sequence`)
- Guarda snapshots de factores

#### Nivel 3 - Orden de producción (Copia congelada)
**ProductionOrderOperation**: Copia de la ruta en momento de crear orden.
- Snapshot completo para costos y auditoría
- NO depende del ProductSheet después de creada
- Permite facturas y reportes históricos exactos

---

### Modelos Necesarios

#### 1. **Operation** (Catálogo de Operaciones - Generado desde ProductSheets)
```php
// app/Models/ProductSheet/Operation.php
- id
- codigo (string, unique) // Ej: "OP-DEL-001" (autogenerado)
- seccion (enum) // DELANTERO / TRASERO / ENSAMBLE / TERMINADO
- accion (string) // CERRAR, PRENDER, ASENTAR, FILETEAR, etc.
- parte (string) // BOLSILLO, PRETINA, TIRO, MANGA, etc.
- ubicacion (string) // DELANTERO, TRASERO, PRETINA, ENTREPIERNA
- medida (decimal, nullable) // Ej: 0.25 mts
- cantidad (string, nullable) // Ej: "20 cm"
- descripcion (text) // Autogenerada o editable
- sam_base (decimal) // Tiempo base en minutos (ej: 0.25) - TOMADO CON CRONÓMETRO EN SABANA
- product_sheet_reference (string, nullable) // Referencia a SABANA donde se tomó el tiempo (ej: "REF-001")
- product_sheet_id_reference (foreignId, nullable) // ID de ProductSheet donde fue tomada
- maquina_id_reference (foreignId, nullable) // Máquina usada cuando se tomó el tiempo
- activo (boolean, default: true)
- created_by (foreignId → users.id)
- timestamps
- softDeletes
```

**Relaciones:**
- `hasMany(ProductOperation::class)` - Asignaciones a otros ProductSheets
- `belongsTo(ProductSheet::class, 'product_sheet_id_reference')` - SABANA donde se tomó
- Referencia a máquina se hace por código en parámetro operacional.maquinas

**Reglas:**
- Implementa `HasAuditLabel` para auditoría
- Código único generado automáticamente
- **Sam_base = tiempo REAL tomado con cronómetro por operario experto EN SABANA**
- `product_sheet_reference` guarda la SABANA de origen para trazabilidad histórica
- Descripción puede ser autogenerada o editada manualmente
- Una operación NO se edita en el catálogo; si cambia sam_base, se crea nueva operación
- `maquina_id_reference` es el ID de la máquina. En ProductOperation se usa `maquina_cod` para referenciar el parámetro

---

#### 2. **PARÁMETRO: Machine** (Catálogo de Máquinas)
```php
// settings tabla con kind='parameter'
// key: 'operacional.maquinas'
// Estructura JSON en value:
[
  {
    "cod": "MF01",
    "nombre": "Fileteadora",
    "suplemento": 1.08
  },
  {
    "cod": "MRC02",
    "nombre": "Recubridora",
    "suplemento": 1.12
  },
  {
    "cod": "MCO01",
    "nombre": "Cosedora",
    "suplemento": 1.10
  }
]
```

**Estructura en Setting:**
- `key`: 'operacional.maquinas'
- `kind`: 'parameter'
- `label`: 'Máquinas de Confección'
- `field_type`: 'select'
- `value`: JSON array de máquinas
- `options`: Auto-generado para formularios

**Reglas:**
- Parámetro estático gestionado desde Configuration/Settings
- Suplemento: 1.0 = sin suplemento, 1.10 = +10%, etc.
- Se referencia en ProductOperation por `maquina_cod`
- El equipo de IT gestiona los cambios en este parámetro

---

#### 3. **PARÁMETRO: FabricBase** (Catálogo de Bases de Tela)
```php
// settings tabla con kind='parameter'
// key: 'operacional.fabric_bases'
// Estructura JSON en value:
[
  {
    "cod": "FB-ALGODON",
    "nombre": "Algodón 100%",
    "factor": 1.05
  },
  {
    "cod": "FB-POLIESTER",
    "nombre": "Poliéster 65%",
    "factor": 1.08
  },
  {
    "cod": "FB-MEZCLA",
    "nombre": "Mezcla Algodón-Poliéster",
    "factor": 1.06
  }
]
```

**Estructura en Setting:**
- `key`: 'operacional.fabric_bases'
- `kind`: 'parameter'
- `label`: 'Bases de Tela'
- `field_type`: 'select'
- `value`: JSON array de bases de tela
- `options`: Auto-generado para formularios

**Reglas:**
- Parámetro estático gestionado desde Configuration/Settings
- Factor afecta el tiempo según tipo de tela
- Se referencia en ProductOperation por `fabric_base_cod`
- El equipo de IT gestiona los cambios en este parámetro

---

#### 4. **ProductSheetOperation** (Ruta base del producto - TABLA CENTRAL)
```php
// app/Models/ProductSheet/ProductSheetOperation.php
// Tabla: product_sheet_operations
// Esta es la tabla CRÍTICA para manejar muchas operaciones

- id
- product_sheet_id (foreignId) // Referencia a ProductSheet
- sequence (integer) // Orden de proceso (10, 20, 30..., permite inserciones)
- operation_id (foreignId) // Referencia a Operation (NO editable después)
- maquina_id (foreignId) // ID de máquina de parámetro operacional.maquinas
- base_tela_id (foreignId) // ID de tela de parámetro operacional.fabric_bases
- cantidad (integer, default: 1) // Ej: mangas = 2 unidades por operación

// SNAPSHOTS DE VALORES EN MOMENTO DE ASIGNACIÓN
- sam_base (decimal) // Copiado de operation.sam_base
- suplemento_maquina (decimal) // Copiado de máquina (snapshot momento asignación)
- factor_tela (decimal) // Copiado de tela (snapshot momento asignación)
- factor_operario (decimal) // Asignado por usuario (ej: 1.25)

// CÁLCULOS PRECOMPILADOS (para rendimiento)
- sam_total_unitario (decimal) // = sam_base × suplemento_maquina × factor_tela × factor_operario
- sam_total_operacion (decimal) // = sam_total_unitario × cantidad

// TRAZABILIDAD
- creado_por (foreignId → users.id)
- editado_por (foreignId → users.id, nullable)
- timestamps
- softDeletes

// ÍNDICES CRÍTICOS (para muchos registros)
index(['product_sheet_id'])
index(['product_sheet_id', 'sequence'])  // Clave para recuperar en orden
```

**Relaciones:**
- `belongsTo(ProductSheet::class)`
- `belongsTo(Operation::class)`
- `hasMany(ProductionOrderOperation::class)` - Copia congelada
- `hasMany(ProductSheetOperationHistory::class)` - Historial de ediciones

**Métodos Auxiliares:**
- `getMachine()` - Obtiene objeto máquina de parámetro operacional.maquinas
- `getFabricBase()` - Obtiene objeto tela de parámetro operacional.fabric_bases
- `calculateSamTotalUnitario()` - Recalcula sam_total_unitario
- `calculateSamTotalOperacion()` - Recalcula sam_total_operacion

**Reglas Críticas:**
- `operation_id` es inmutable (no se puede cambiar después)
- Sam_base, suplemento, factor_tela, factor_operario SÍ son editables
- Cuando se edita cualquier factor, se registra automáticamente en ProductSheetOperationHistory
- `sequence` define orden (usar saltos de 10 para poder insertar después: 10, 20, 30...)
- Cambios en catálogos (Operation, machine, tela) NO afectan ProductSheetOperation (snapshot protege)
- Los cambios NO afectan ProductionOrderOperation ya creadas (están congeladas)

---

#### 5. **ProductSheetOperationHistory** (Versionado de cambios en ruta del producto)
```php
// app/Models/ProductSheet/ProductSheetOperationHistory.php
// Tabla: product_sheet_operation_history
// Registra SOLO cambios en factores de ProductSheetOperation

- id
- product_sheet_operation_id (foreignId) // Referencia a ProductSheetOperation
- sam_base_anterior (decimal, nullable)
- sam_base_nuevo (decimal, nullable)
- suplemento_maquina_anterior (decimal, nullable)
- suplemento_maquina_nuevo (decimal, nullable)
- factor_tela_anterior (decimal, nullable)
- factor_tela_nuevo (decimal, nullable)
- factor_operario_anterior (decimal, nullable)
- factor_operario_nuevo (decimal, nullable)
- sam_total_unitario_anterior (decimal, nullable)
- sam_total_unitario_nuevo (decimal, nullable)
- sam_total_operacion_anterior (decimal, nullable)
- sam_total_operacion_nuevo (decimal, nullable)
- motivo (text) // Razón del cambio (ej: "Máquina defectuosa, se ajustó factor")
- usuario_id (foreignId → users.id)
- created_at
```

**Relaciones:**
- `belongsTo(ProductSheetOperation::class)`
- `belongsTo(User::class, 'usuario_id')`

**Reglas:**
- Se registra AUTOMÁTICAMENTE al editar ProductSheetOperation
- SOLO se registra si hubo cambios (no en creación inicial)
- Motivo es obligatorio en UI
- NO se registra ediciones en catálogos (Operation, máquina, tela)
- Implementa `HasAuditLabel` para auditoría adicional

---

#### 6. **ProductionOrderOperation** (Copia congelada para orden de producción)
```php
// app/Models/Expenses/ProductionOrderOperation.php
// Tabla: production_order_operations
// SNAPSHOT congelado en momento de crear la orden

- id
- production_order_id (foreignId) // Referencia a ProductionOrder
- sequence (integer) // Copiado de ProductSheetOperation
- operation_id (foreignId) // Referencia a Operation (para trazabilidad)
- maquina_id (foreignId) // ID de máquina (snapshot)
- cantidad (integer) // Copiado de ProductSheetOperation

// SNAPSHOTS CONGELADOS (NO SE EDITAN)
- sam_base (decimal) // Congelado de ProductSheetOperation
- suplemento_maquina (decimal) // Congelado de ProductSheetOperation
- factor_tela (decimal) // Congelado de ProductSheetOperation
- factor_operario (decimal) // Congelado de ProductSheetOperation
- sam_total_unitario (decimal) // Congelado de ProductSheetOperation
- sam_total_operacion (decimal) // Congelado de ProductSheetOperation
- sam_total_lote (decimal) // = sam_total_operacion × cantidad_lote (para factura)

// AVANCE DE PRODUCCIÓN
- cantidad_progresada (integer, default: 0) // Cantidad ejecutada
- estado (enum) // PENDIENTE / EN_PROCESO / COMPLETADA
- fechas
- created_at
```

**Relaciones:**
- `belongsTo(ProductionOrder::class)`
- `belongsTo(Operation::class)`

**Reglas Críticas:**
- Es un SNAPSHOT CONGELADO creado al momento de ProductionOrder
- NO SE EDITA después de creada (permite auditoría histórica exacta)
- Todos los valores están precompilados para rapidez
- NO depende de ProductSheet después de creada
- Permite generar facturas exactas años después

---

## 📊 Estructura en 3 Niveles (para muchas operaciones)

### Modelos (Tablas en BD):
1. **Operation** - Catálogo de operaciones estándar (sin cambios)
2. **ProductSheetOperation** - Ruta base del producto (TABLA CENTRAL con 50-120 operaciones)
3. **ProductSheetOperationHistory** - Versionado de cambios en ruta
4. **ProductionOrderOperation** - Copia congelada para orden de producción (nuevo nivel)

### Parámetros (Settings con kind='parameter'):
1. **operacional.maquinas** - Catálogo de máquinas (JSON)
2. **operacional.fabric_bases** - Catálogo de bases de tela (JSON)

**Ventajas del diseño jerárquico:**
- ✅ **Rendimiento**: 100 operaciones × 1 orden = 100 inserts (muy liviano)
- ✅ **Independencia**: Cambios en catálogos NO afectan órdenes existentes (snapshot protege)
- ✅ **Integridad histórica**: Órdenes congeladas para auditoría exacta años después
- ✅ **Auditabilidad**: ProductSheetOperationHistory registra cambios en ruta
- ✅ **Escalabilidad**: Índices en `(product_sheet_id, sequence)` para recuperación rápida
- ✅ **Versionado seguro**: Cambios en ProductSheet NO rompen ProductionOrder

### Cálculo de SAM Total

```
SAM Total = SAM Base × Suplemento Máquina × Factor Tela × Factor Operario

Ejemplo:
- SAM Base: 0.25 minutos (tomado en ProductSheet con cronómetro)
- Suplemento Máquina: 1.08 (parámetro operacional.maquinas)
- Factor Tela: 1.05 (parámetro operacional.fabric_bases)
- Factor Operario: 1.25 (asignado en ProductOperation)
- SAM Total: 0.25 × 1.08 × 1.05 × 1.25 = 0.354 minutos
```

---

## 🔧 Componentes Técnicos

### Backend (Laravel)

#### Migraciones
```php
// database/migrations/YYYY_MM_DD_create_operations_table.php
// database/migrations/YYYY_MM_DD_create_product_operations_table.php
// database/migrations/YYYY_MM_DD_create_product_operation_history_table.php

// ⚠️ NOTA: Machine y FabricBase NO tienen migraciones, son Setting parámetros
```

#### Parámetros en Settings
El equipo de IT debe crear dos parámetros en Configuration/Settings:
```
1. key: 'operacional.maquinas'
   kind: 'parameter'
   field_type: 'select'
   value: JSON con máquinas y suplementos

2. key: 'operacional.fabric_bases'
   kind: 'parameter'
   field_type: 'select'
   value: JSON con bases de tela y factores
```

#### Servicios
```php
// app/Services/ProductSheet/OperationService.php
- create(array $data): Operation
- update(Operation $operation, array $data): Operation
- getBySection(string $section): Collection
- searchByName(string $name): Collection
- getMostUsed(int $limit = 10): Collection

// app/Services/ProductSheet/ProductOperationService.php
// CRUD
- create(int $productSheetId, array $data): ProductOperation
- update(ProductOperation $productOperation, array $data): ProductOperation
- delete(ProductOperation $productOperation): void
- reorder(int $productSheetId, array $order): void
- getByProductSheet(int $productSheetId): Collection

// CÁLCULOS
- calculateSamTotal(float $samBase, float $suplementoMaquina, float $factorTela, float $factorOperario): float
- getTotalSamBySection(int $productSheetId, string $section): float
- getTotalSamByProduct(int $productSheetId): float

// HISTORIAL
- recordChange(int $productOperationId, User $user, string $motivo, array $before, array $after): ProductOperationHistory

// PARÁMETROS (Acceso a Settings)
- getMachines(): array (obtiene parámetro operacional.maquinas)
- getFabricBases(): array (obtiene parámetro operacional.fabric_bases)
- getMachineByCode(string $code): ?array
- getFabricBaseByCode(string $code): ?array
```

#### Controllers
```php
// app/Http/Controllers/ProductSheet/OperationController.php
- index(): InertiaResponse
- getData(Request $request): JsonResponse
- store(OperationStoreRequest $request): JsonResponse
- update(OperationUpdateRequest $request, Operation $operation): JsonResponse
- destroy(Operation $operation): JsonResponse

// app/Http/Controllers/ProductSheet/ProductOperationController.php
- store(ProductOperationStoreRequest $request): JsonResponse
- update(ProductOperationUpdateRequest $request, ProductOperation $productOperation): JsonResponse
- destroy(ProductOperation $productOperation): JsonResponse
- reorder(ReorderProductOperationsRequest $request): JsonResponse
- getMachinesAndFabricBases(): JsonResponse (para selects en formulario)

// ⚠️ NOTA: NO hay MachineController ni FabricBaseController (son Settings)
```

// app/Http/Controllers/ProductSheet/ProductOperationController.php
- index(ProductSheet $productSheet): InertiaResponse
- getData(ProductSheet $productSheet, Request $request): JsonResponse
- store(ProductSheet $productSheet, ProductOperationStoreRequest $request): JsonResponse
- update(ProductOperation $productOperation, ProductOperationUpdateRequest $request): JsonResponse
- destroy(ProductOperation $productOperation): JsonResponse
- reorder(ProductSheet $productSheet, Request $request): JsonResponse
- history(ProductOperation $productOperation): JsonResponse
```

#### Policies
```php
// app/Policies/ProductSheet/OperationPolicy.php
// app/Policies/ProductSheet/MachinePolicy.php
// app/Policies/ProductSheet/FabricBasePolicy.php
// app/Policies/ProductSheet/ProductOperationPolicy.php
```

#### Requests
```php
// app/Http/Requests/ProductSheet/OperationStoreRequest.php
// app/Http/Requests/ProductSheet/OperationUpdateRequest.php
// app/Http/Requests/ProductSheet/MachineStoreRequest.php
// app/Http/Requests/ProductSheet/MachineUpdateRequest.php
// app/Http/Requests/ProductSheet/FabricBaseStoreRequest.php
// app/Http/Requests/ProductSheet/FabricBaseUpdateRequest.php
// app/Http/Requests/ProductSheet/ProductOperationStoreRequest.php
// app/Http/Requests/ProductSheet/ProductOperationUpdateRequest.php
```

#### Resources
```php
// app/Http/Resources/OperationResource.php
// app/Http/Resources/MachineResource.php
// app/Http/Resources/FabricBaseResource.php
// app/Http/Resources/ProductOperationResource.php
// app/Http/Resources/ProductOperationHistoryResource.php
```

---

### Frontend (Vue 3 + TypeScript)

#### Páginas
```typescript
// resources/js/pages/ProductSheet/Operations/Index.vue
// Catálogo de operaciones: búsqueda, filtros por sección, CRUD

// resources/js/pages/ProductSheet/Machines/Index.vue
// Catálogo de máquinas: búsqueda, CRUD, gestión de suplementos

// resources/js/pages/ProductSheet/FabricBases/Index.vue
// Catálogo de bases de tela: búsqueda, CRUD, gestión de factores

// resources/js/pages/ProductSheet/ProductOperations/Index.vue
// Listado de operaciones asignadas a un producto específico
```

#### Componentes
```typescript
// resources/js/components/ProductSheet/OperationSelector.vue
// Selector de operación con autocompletado, muestra: código, sección, sam_base

// resources/js/components/ProductSheet/MachineSelector.vue
// Selector de máquina, muestra: código, nombre, suplemento_actual

// resources/js/components/ProductSheet/FabricBaseSelector.vue
// Selector de base de tela, muestra: código, nombre, factor

// resources/js/components/ProductSheet/FactorOperarioSelector.vue
// Selector de factor de operario con presets (1.15, 1.20, 1.25, 1.30, 1.35)

// resources/js/components/ProductSheet/SamCalculator.vue
// Widget que muestra desglose del cálculo de SAM Total en tiempo real

// resources/js/components/ProductSheet/ProductOperationTable.vue
// Tabla editable de operaciones asignadas con drag-and-drop para reordenar

// resources/js/components/ProductSheet/ProductOperationHistoryDialog.vue
// Diálogo que muestra historial de cambios para una operación
```

#### Tipos
```typescript
// resources/js/types/product-operations.d.ts
export interface Operation {
    id: number;
    codigo: string;
    seccion: 'DELANTERO' | 'TRASERO' | 'ENSAMBLE' | 'TERMINADO';
    accion: string;
    parte: string;
    ubicacion: string;
    medida?: number;
    cantidad?: string;
    descripcion: string;
    sam_base: number;
    referencia?: string;
    activo: boolean;
}

export interface Machine {
    id: number;
    cod_maquina: string;
    nombre: string;
    suplemento: number;
    es_activa: boolean;
}

export interface FabricBase {
    id: number;
    codigo: string;
    nombre: string;
    factor_base: number;
    es_activa: boolean;
}

export interface ProductSheetOperation {
    id: number;
    product_sheet_id: number;
    sequence: number; // Orden (10, 20, 30...)
    operation_id: number;
    maquina_id: number;
    base_tela_id: number;
    cantidad: number;
    sam_base: number;
    suplemento_maquina: number;
    factor_tela: number;
    factor_operario: number;
    sam_total_unitario: number; // sam_base × suplemento × factor_tela × factor_operario
    sam_total_operacion: number; // sam_total_unitario × cantidad
}

export interface ProductionOrderOperation {
    id: number;
    production_order_id: number;
    sequence: number; // Copiado de ProductSheetOperation
    operation_id: number;
    maquina_id: number;
    cantidad: number;
    // SNAPSHOT CONGELADO (no se edita)
    sam_base: number;
    suplemento_maquina: number;
    factor_tela: number;
    factor_operario: number;
    sam_total_unitario: number;
    sam_total_operacion: number;
    sam_total_lote: number; // sam_total_operacion × cantidad_lote
    cantidad_progresada: number;
    estado: 'PENDIENTE' | 'EN_PROCESO' | 'COMPLETADA';
}

export interface ProductSheetOperationHistory {
    id: number;
    product_sheet_operation_id: number;
    sam_base_anterior?: number;
    sam_base_nuevo?: number;
    suplemento_maquina_anterior?: number;
    suplemento_maquina_nuevo?: number;
    factor_tela_anterior?: number;
    factor_tela_nuevo?: number;
    factor_operario_anterior?: number;
    factor_operario_nuevo?: number;
    sam_total_unitario_anterior?: number;
    sam_total_unitario_nuevo?: number;
    sam_total_operacion_anterior?: number;
    sam_total_operacion_nuevo?: number;
    motivo: string;
    usuario_id: number;
    created_at: string;
}
```

---

## 🔐 Permisos y Seguridad

### Permisos Spatie (nuevos)
```php
// database/seeders/RoleAndPermissionSeeder.php
// Catálogo de Operaciones
'ver catálogo operaciones',
'crear catálogo operaciones',
'editar catálogo operaciones',
'eliminar catálogo operaciones',

// Ruta del Producto (ProductSheetOperation) - CENTRAL
'ver ruta producto',
'crear operación en ruta',
'editar operación en ruta',
'eliminar operación en ruta',
'reordenar operaciones ruta',
'ver historial ruta',

// Orden de Producción
'ver órdenes producción',
'crear orden producción',
'editar progreso orden',
'ver operaciones orden',

// TOTAL: 16 permisos
```

### Roles sugeridos
- **Supervisor de Planta**: Crear/editar producto-operaciones, ver catálogos
- **Jefe de Producción**: Todos los permisos
- **Operario**: Ver listados operacionales (solo lectura)
- **Administrador**: Control total

---

## 📝 Rutas

```php
// routes/web.php
Route::prefix('diseno')->name('diseno.')->middleware(['auth', 'verified'])->group(function () {
    // Catálogo de Operaciones (solo lectura en producción)
    Route::resource('operaciones', OperationController::class)->only(['index', 'show']);
    Route::get('operaciones/data', [OperationController::class, 'getData'])->name('operaciones.data');
    
    // Ruta del Producto (ProductSheetOperation) - CENTRAL para muchas operaciones
    Route::prefix('productos/{productSheet}')->name('productos.')->group(function () {
        Route::resource('operaciones', ProductSheetOperationController::class)->shallow();
        Route::get('operaciones/data', [ProductSheetOperationController::class, 'getData'])->name('operaciones.data');
        Route::post('operaciones/reorder', [ProductSheetOperationController::class, 'reorder'])->name('operaciones.reorder');
        Route::get('operaciones/{productSheetOperation}/history', [ProductSheetOperationController::class, 'history'])->name('operaciones.history');
    });
    
    // Órdenes de Producción
    Route::resource('ordenes-produccion', ProductionOrderController::class);
    Route::get('ordenes-produccion/{productionOrder}/operaciones', [ProductionOrderController::class, 'operaciones'])->name('ordenes-produccion.operaciones');
});
```

---

## 🎯 Casos de Uso Principales

### Caso 1: Construir Ruta de Producto (ProductSheetOperation) - 50-120 operaciones
```
1. Usuario abre/crea ProductSheet (ej: REF-002, tipo "productivo")
2. Usuario navega a sección "Listado Operacional"
3. Para CADA operación que lleve el producto:
   - Define sequence (10, 20, 30 permite inserciones entre operaciones)
   - Busca operación en catálogo (Operation)
   - Selecciona máquina a usar (parámetro operacional.maquinas)
   - Selecciona base de tela (parámetro operacional.fabric_bases)
   - Asigna factor_operario (ej: 1.25 para operario promedio)
   - Sistema calcula SNAPSHOT:
     * sam_base: 0.25 (copiado de Operation)
     * suplemento_maquina: 1.08 (copiado de máquina en este momento)
     * factor_tela: 1.05 (copiado de tela en este momento)
     * factor_operario: 1.25 (asignado)
     * sam_total_unitario: 0.25 × 1.08 × 1.05 × 1.25 = 0.354
   - Sistema guarda ProductSheetOperation con snapshot
4. Repetir hasta tener 50-120 operaciones
5. ProductSheet queda con ruta completa y lista para producción
6. Se registra auditoría: "ProductSheet REF-002 creado con 85 operaciones"
```

### Caso 2: Crear Orden de Producción (Copiar snapshot a ProductionOrderOperation)
```
1. Usuario navega a Crear ProductionOrder
2. Usuario selecciona:
   - Producto: ProductSheet REF-002
   - Cantidad lote: 500 unidades
3. Sistema automáticamente:
   a) Busca todas las ProductSheetOperation de REF-002 ordenadas por sequence
   b) Para CADA ProductSheetOperation:
      - Crea ProductionOrderOperation con COPIA CONGELADA:
        * sequence, operation_id, maquina_id, cantidad
        * sam_base, suplemento_maquina, factor_tela, factor_operario
        * sam_total_unitario, sam_total_operacion
        * sam_total_lote: sam_total_operacion × 500
      - Inserta en tabla (100 inserts si hay 100 operaciones, es rápido)
4. ProductionOrder queda lista con 85 operaciones congeladas
5. Los valores están precompilados para:
   - Facturación: sam_total_lote exacto
   - Reportes: datos históricos exactos
   - Auditoría: nunca camblantes
6. Se registra auditoría: "ProductionOrder #2024-512 creada de REF-002 con 85 operaciones"
```

### Caso 3: Editar Factores en ProductSheet (Afecta futuras órdenes SOLAMENTE)
```
1. Usuario está viendo listado operacional de ProductSheet REF-002
2. Usuario modifica factor_operario en operación "Cerrar hombros"
   - Anterior: 1.25
   - Nuevo: 1.35
   - Motivo: "Operario más experimentado asignado"
3. Sistema detecta cambio en ProductSheetOperation
4. Sistema guarda cambio en ProductSheetOperation
5. Sistema REGISTRA AUTOMÁTICAMENTE en ProductSheetOperationHistory:
   - product_sheet_operation_id: 1 (Cerrar hombros)
   - factor_operario_anterior: 1.25
   - factor_operario_nuevo: 1.35
   - sam_total_unitario_anterior: 0.354
   - sam_total_unitario_nuevo: 0.388
   - motivo: "Operario más experimentado asignado"
   - usuario_id: ID del usuario
   - created_at: fecha/hora actual
6. IMPORTANTE: Órdenes de producción anteriores NO se afectan:
   - ProductionOrder creada el 2024-11-15: se factura con sam 0.354
   - ProductionOrder creada el 2025-02-11: usa nuevo sam 0.388
7. Próximas órdenes usarán automáticamente el nuevo factor 1.35
8. Se registra auditoría: "Factor editado en ProductSheetOperation #1"
```

### Caso 4: Ver Historial de Cambios (ProductSheetOperationHistory)
```
1. Usuario hace clic en ícono "Historial" en operación "Cerrar hombros"
2. Sistema abre diálogo mostrando timeline de cambios:
   Timeline:
   ┌─ Cambio 1 (2024-11-15 14:30) ────────────────────────────┐
   │ Usuario: Juan Pérez                                        │
   │ Motivo: "Operario más experimentado asignado"              │
   │ factor_operario: 1.25 → 1.35                               │
   │ sam_total_unitario: 0.354 → 0.388                          │
   └────────────────────────────────────────────────────────────┘
   
   ┌─ Cambio 2 (2024-12-03 09:15) ────────────────────────────┐
   │ Usuario: María García                                      │
   │ Motivo: "Ajuste por feedback supervisor"                  │
   │ factor_operario: 1.35 → 1.30                               │
   │ sam_total_unitario: 0.388 → 0.368                          │
   └────────────────────────────────────────────────────────────┘
3. Usuario puede ver exactamente qué cambió, quién, cuándo y por qué
4. Útil para auditar cambios en ingeniería
```

---

## 💡 UI Simplificada

### Formulario de Asignación de Operación
```
┌─ Asignar Operación a Producto ────────────────┐
│                                               │
│ Operación:     [Cerrar tiro delantero ▼]     │
│ Máquina:       [MF01 - Fileteadora ▼]        │
│ Base Tela:     [FB-ALGODON ▼]                │
│ Factor Operario: [1.25 ▼]                    │
│                                               │
│ SAM Base: 0.25 min                           │
│ × Suplemento Máquina: 1.08                   │
│ × Factor Tela: 1.05                          │
│ × Factor Operario: 1.25                      │
│ = SAM Total: 0.354 min                       │
│                                               │
│       [Asignar]  [Cancelar]                   │
└───────────────────────────────────────────────┘
```

### Tabla de Operaciones del Producto (ProductSheetOperation)
```
ProductSheet REF-002: Falda Clásica
Operaciones en ruta: 85 | SAM Total: 54.32 min | Última edición: 2025-02-11
┌─────────────────────────────────────────────────────────────────────────────┐
│ SECCIÓN DELANTERO                                               SAM: 12.45   │
├─────────────────────────────────────────────────────────────────────────────┤
│ # │ Operación                │ Sec │ Máq  │ Tela │ Factor │ SAM Unit │ Acciones
│ 1 │ Cerrar costados D       │ 10  │ MF01 │ 1.05 │ 1.25   │ 0.354    │ ✎ 🗑  
│ 2 │ Prender pretina D       │ 20  │ MRC  │ 1.08 │ 1.20   │ 0.285    │ ✎ 🗑  
│ 3 │ Filetear              │ 30  │ MF03 │ 1.05 │ 1.30   │ 0.368    │ ✎ 🗑  
├─────────────────────────────────────────────────────────────────────────────┤
│ SECCIÓN TRASERO                                                SAM: 18.22   │
├─────────────────────────────────────────────────────────────────────────────┤
│ 4 │ Cerrar costados T       │ 40  │ MF01 │ 1.05 │ 1.25   │ 0.354    │ ✎ 🗑  
│ 5 │ Prender pretina T       │ 50  │ MRC  │ 1.08 │ 1.20   │ 0.285    │ ✎ 🗑  
├─────────────────────────────────────────────────────────────────────────────┤
│                      TOTAL PRODUCTSHEET: 54.32 minutos/unidad                │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## ✅ Checklist de Implementación

- [ ] Crear 4 migraciones (operations, product_sheet_operations, product_sheet_operation_history, production_order_operations)
- [ ] Crear 4 modelos + observers (Operation, ProductSheetOperation, ProductSheetOperationHistory, ProductionOrderOperation)
- [ ] Registrar 16 permisos
- [ ] Crear 3 services (OperationService, ProductSheetOperationService, ProductionOrderService)
- [ ] Crear 3 controllers (OperationController, ProductSheetOperationController, ProductionOrderController)
- [ ] Crear 3 policies + 4 resources
- [ ] Crear rutas bajo `/diseno` prefix
- [ ] Frontend: 3 páginas + 5 componentes + tipos TypeScript
- [ ] Tests: casos de uso principales (construcción de ruta, copia a orden, edición de factores)
- [ ] Documentación completa de API
- [ ] Parámetros en Settings: `operacional.maquinas` y `operacional.fabric_bases`

---

## 🚀 Plan de Implementación (3 fases)

### Fase 1: Base de Datos y Modelos (Día 1)
- [ ] Migraciones: Operation, ProductSheetOperation, ProductSheetOperationHistory, ProductionOrderOperation
- [ ] Modelos con relaciones
- [ ] Observers para auditoría
- [ ] Parámetros creados en Settings

### Fase 2: Lógica y APIs (Día 2)
- [ ] Services: OperationService, ProductSheetOperationService, ProductionOrderService
- [ ] Controllers: 3 controllers con CRUD
- [ ] FormRequests para validación
- [ ] Policies y Resources
- [ ] Rutas bajo prefix `/diseno`
- [ ] Índices en `product_sheet_operations(product_sheet_id, sequence)`

### Fase 3: Frontend (Día 3)
- [ ] Página de ruta del producto (ProductSheetOperation)
- [ ] Página de órdenes de producción
- [ ] Componentes: selector de operación, tabla editable, historial
- [ ] Integración con Vue 3 + TypeScript

### Fase 4: Testing y Documentación (Día 4)
- [ ] Tests Pest para casos de uso principales
- [ ] Documentación API
- [ ] Manual de usuario
- [ ] Scripts de migración de datos (si aplica)

---

## 🔍 Arquitectura: 3 Niveles (Correcta para manufactura industrial)

| Aspecto | Diseño Anterior | Diseño Nuevo (3 Niveles) |
|---------|----------|-------|
| **Niveles** | 2 (Operación + Producto) | 3 (Catálogo + Ruta Producto + Orden Congelada) |
| **Tabla Central** | ProductOperation (relación) | ProductSheetOperation (ruta con 50-120 operaciones) |
| **Escalabilidad** | 20-30 operaciones | 50-120 operaciones (sin problemas de rendimiento) |
| **Snapshot** | Solo en relación | En 2 lugares: ruta + orden (doble congelamiento) |
| **Cambios en catálogos** | Afectan historiales | NO afectan: snapshot es inmutable |
| **Cambios en ruta del producto** | N/A | Afectan futuras órdenes, NO pasadas (ProductSheetOperationHistory) |
| **Versionado** | Todo se versionaba | Solo cambios en ProductSheetOperation (ProductSheetOperationHistory) |
| **Órdenes congeladas** | N/A | CRÍTICO para auditoría histórica exacta |
| **Integridad histórica** | Compleja | Perfecta (snapshots en 2 niveles + history) |
| **Índices críticos** | product_sheet_id | product_sheet_id + sequence (para recuperación ordenada rápida) |

---

## 🔐 Reglas Críticas (Para muchas operaciones)

### Regla 1 – Nunca recalcular en masa
Si cambia:
- Operation (catálogo)
- Máquina (parámetro)
- Tela (parámetro)

**Afecta:**
- ProductSheetOperation futuras (al crear nuevas)
- **NO** órdenes existentes (están congeladas)

### Regla 2 – Snapshots dobles
```
ProductSheetOperation:
├─ Snapshot del catálogo (sam_base, suplemento, factor)
└─ Editables después: factor_operario

ProductionOrderOperation:
├─ Snapshot de ProductSheetOperation (TODOS los valores)
└─ Nunca se edita (congelado para auditoría)
```

### Regla 3 – Sequence obligatorio (para muchos registros)
```
ProductSheetOperation usa sequence (10, 20, 30):
├─ Permite insertar en medio: seq 15 entre 10 y 20
├─ Ordena recuperación rápida con índice (product_sheet_id, sequence)
└─ Crítico con 100+ operaciones
```

### Regla 4 – Sin joins en ProductionOrder
ProductionOrderOperation tiene:
- sam_base, suplemento, factor_tela, factor_operario
- sam_total_unitario, sam_total_operacion, sam_total_lote
- TODO precompilado

**Nunca** depende de Operation, máquina, tela después de creada.

---

## 🎓 Convenciones de Código

### Nomenclatura
- **Modelos**: Singular (Operation, ProductSheetOperation, ProductSheetOperationHistory, ProductionOrderOperation)
- **Tablas**: Plural (operations, product_sheet_operations, product_sheet_operation_history, production_order_operations)
- **Controllers**: Singular (OperationController, ProductSheetOperationController, ProductionOrderController)
- **Services**: Singular (OperationService, ProductSheetOperationService, ProductionOrderService)
- **Campos de fecha**: `created_at`, `updated_at` (timestamps)
- **Campos booleanos**: `activo` (en español)
- **Campos con snapshot**: sin prefijo (`sam_base`, NO `sam_base_snapshot`)

### Validación
- Dual: Frontend (Vee-Validate) + Backend (FormRequest)
- Mensajes en español
- Campos requeridos claramente marcados
- Validación de secuencia (no duplicados en mismo product_sheet_id)

### Auditoría
- Automática con observers en modelos principales
- Auditoría manual en ProductSheetOperationHistory (cambios en factores)
- Timestamps y usuario_id siempre presentes
- ProductionOrderOperation es INMUTABLE (no tiene observers de cambio)

### Índices Críticos
```php
// En ProductSheetOperation
$table->index(['product_sheet_id']);
$table->index(['product_sheet_id', 'sequence']); // ⭐ CRÍTICO para recuperación

// En ProductionOrderOperation
$table->index(['production_order_id']);
$table->index(['operation_id']); // Para reportes
```

---

## ⚠️ Punto Crítico Identificado: Versionado de ProductSheet (Próximo paso)

### El Problema
Si un ProductSheet se modifica (cambios en ruta, operaciones, secuencias), ¿qué pasa con:
- **Órdenes vigentes**: ¿deben seguir la ruta anterior o la nueva?
- **Costos históricos**: ¿cambian si la ruta cambió?
- **Auditoría**: ¿cómo saber qué ruta usó cada orden?

### La Solución: ProductSheet Versiones

**Opción recomendada:**
```
ProductSheet
├─ id: 1
├─ referencia: "REF-002"
├─ version_actual: 2
└─ estado: "activo"

ProductSheetVersion
├─ id: 1, product_sheet_id: 1, version: 1 ← ANTIGUA
├─ id: 2, product_sheet_id: 1, version: 2 ← ACTUAL

ProductSheetOperationVersion
├─ Linked a ProductSheetVersion específica

ProductionOrder
├─ product_sheet_version_id: 1 ← Referencia a versión usada
└─ (No puede cambiar, está congelada)
```

### Casos de Uso Que Resuelve
```
2024-11-15: Crea ProductionOrder con ProductSheet v1 (85 operaciones)
            ↓
2024-12-10: Usuario modifica ProductSheet REF-002:
            - Agrega 5 operaciones nuevas
            - Sistema crea ProductSheet v2 automáticamente
            ↓
2025-01-20: Crea ProductionOrder con ProductSheet v2 (90 operaciones)
            ↓
2025-02-11: Genera Factura para orden de noviembre:
            - Busca ProductSheetVersion v1 (85 operaciones)
            - SAM exacto histórico, auditable
```

### Implementación (Fase 2)

1. **Modelo ProductSheetVersion**
   - product_sheet_id (FK)
   - version (integer, auto-increment)
   - descripción (nullable)
   - activo (boolean)
   - created_at

2. **Actualizar ProductSheetOperation**
   - product_sheet_version_id (FK) EN LUGAR DE product_sheet_id
   - Así cada versión tiene sus propias operaciones

3. **Actualizar ProductionOrder**
   - product_sheet_version_id (FK) EN LUGAR DE product_sheet_id
   - Referencia a versión específica (nunca cambia)

4. **Lógica en Service**
   ```php
   // Al editar ProductSheet
   if ($hasChangesInOperations) {
       // Crear nueva versión
       $version = ProductSheetVersion::create([
           'product_sheet_id' => $productSheet->id,
           'version' => $productSheet->version_actual + 1,
           'descripción' => "Versión automática: cambios en operaciones"
       ]);
       
       // Vincular nuevas ProductSheetOperation a nueva versión
       // Las viejas quedan en versión anterior
   }
   ```

5. **Auditoría automática**
   - Cada versión tiene timestamp y usuario_id
   - ProductionOrder siempre referencia versión específica
   - Historiales son exactos y auditables

### ¿Cuándo implementar esto?
- **Fase 1 (Actual)**: Base de 3 niveles sin versiones (funciona para cambios puntuales)
- **Fase 2 (Próxima)**: Agregar ProductSheetVersion (crítico antes de ir a producción)
- **Fase 3+**: Considerar copias de snapshots de máquinas y telas por versión

