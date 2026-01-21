# Análisis y Diseño: Módulo de Listados Operacionales

## 📋 Resumen Ejecutivo

Diseño de un módulo para gestionar **listados operacionales** en empresas de confección textil, integrado al sistema Soft Olamtex existente. El módulo captura tiempos reales de confección de prendas muestra, genera un catálogo reutilizable de operaciones y produce listados operacionales para productos productivos.

### 🔑 Decisión de Diseño Principal: Tabla Única

⚠️ **IMPORTANTE**: Este diseño usa **una sola tabla** (`product_operation_lists`) para servir tanto a fichas de muestra como fichas productivas. La diferenciación se hace mediante el campo `type` en `ProductSheet` ('muestra' o 'productivo').

**Ventajas de este enfoque:**
- Evita duplicación de tablas similares
- Simplifica la arquitectura (2 modelos en lugar de 3)
- Facilita el flujo muestra → productivo (ambos en la misma tabla)
- Usa campos condicionales: `optimal_time` siempre presente, `production_time`/`efficiency_factor` solo para productivos, `operator_name` solo para muestras
- Single source of truth para todos los listados operacionales

**Modelos finales:**
1. **Operation**: Catálogo de operaciones reutilizables con `standard_time` (promedio histórico)
2. **ProductOperationList**: Tabla universal con campos condicionales según tipo de ficha

---

## 🎯 Objetivo del Módulo

Capturar operaciones de confección con todos sus datos en una sola tabla flexible:
1. **Tiempos óptimos (SAM)** - Operario experto en muestra
2. **Factores de eficiencia** - Operario promedio vs experto
3. **Suplementos de máquina** - Pausas, cambios, etc.
4. **Tiempos productivos** - SAM × operario × máquina
5. **Costos y productividad** - Calculados con tarifas fijas configurables
6. **Agrupación por secciones** - Delantero, trasero, pretina, etc.
7. **Totales dinámicos** - Sin campos en ProductSheet, todo calculado on-demand
8. **Trazabilidad completa** - Operario experto, máquina, notas, auditoría

### ⚡ Ventajas del Diseño

1. **Una sola tabla** → Sin duplicación de lógica
2. **Datos completos siempre** → No hay NULLs innecesarios
3. **Flexible** → Mismo formato para muestra, productivo, y reutilización
4. **Historial** → SoftDeletes + auditoría en cada cambio
5. **Trazable** → Quién ejecutó (operario), qué máquina, cuándo (timestamps)
6. **Evoluciona** → actual_time se completa en producción, se calcula variance

### 📝 Ejemplo de Registro

| Ficha | Sección | Operación | Máquina | SAM | Prod | Costo | Operario |
|-------|---------|----------|---------|-----|------|-------|----------|
| MUEST | Delantero | Cerrar tiro | MF5H | 0.17 | 0.21 | $353 | Juan |
| PROD1 | Delantero | Cerrar tiro | MF5H | 0.17 | 0.21 | $353 | - |

**Mismo dato, copiado perfectamente. Agrupado por sección.**

### 🔄 Flujo Optimizado

1. **Crear MUESTRA**
   - Registra operación + máquina + optimal_time (10 min)
   - Sistema calcula: 10 × 1.25 × 1.12 = 14 min (production_time)
   - Guarda: óptimo, factores, production_time **todo junto**

2. **Crear PRODUCTIVO**
   - Copia EXACTAMENTE el registro anterior
   - No hay transformaciones: mismo optimal_time, mismos factores, mismo production_time
   - Reutilización perfecta con 0 duplicación

3. **En Producción**
   - Puede registrar actual_time real (ej: 14.5 min)
   - Sistema calcula variance automática
   - Auditoría registra cambios

---

## 🏗️ Arquitectura Propuesta

### Dominio de Negocio
**ProductSheet** (Fichas Técnicas) - Extensión del módulo existente

### Modelos Necesarios

#### 1. **Machine** (Catálogo de Máquinas)
```php
// app/Models/ProductSheet/Machine.php
- id
- code (string, unique) // Código único (ej: "MF01" para Fileteadora)
- name (string) // Nombre (ej: "Fileteadora")
- supplement_factor (decimal) // Suplemento (ej: 1.08 = +8% por pausas/cambios)
- is_active (boolean, default: true)
- created_by (foreignId → users.id)
- timestamps
- softDeletes
```

**Ejemplo de máquinas:**
| Código | Nombre | Suplemento | Razón |
|--------|--------|-----------|-------|
| MF01 | Fileteadora | 1.08 | Pausas mínimas |
| MRC02 | Recubridora | 1.12 | Cambios de hilo |
| MCO01 | Cosedora | 1.10 | Ajustes estándar |
| MBR03 | Bordadora | 1.15 | Setup frecuente |
| MCP01 | Cortadora | 1.05 | Muy automática |

**Reglas:**
- Implementa `HasAuditLabel` para auditoría
- `supplement_factor`: 1.0 = sin suplemento, 1.10 = +10%, etc.
- Categorías: costura, corte, estampado, etc.

---

#### 2. **Operation** (Catálogo de Operaciones Reutilizables)
```php
// app/Models/ProductSheet/Operation.php
- id
- code (string, unique) // Código único (ej: "COS-001")
- name (string) // Nombre (ej: "Coser manga derecha")
- description (text, nullable) // Descripción detallada
- category (string) // Categoría: costura, corte, terminado, etc.
- standard_time (decimal, nullable) // ⚠️ Tiempo estándar HISTÓRICO (promedio de optimal_time de todas las fichas)
- usage_count (integer, default: 0) // ⚠️ Contador de veces que se ha usado (para métricas)
- is_active (boolean, default: true) // Estado activo/inactivo
- created_by (foreignId → users.id) // Usuario que creó la operación
- timestamps
- softDeletes
```

**Relaciones:**
- `belongsTo(User::class, 'created_by')`
- `hasMany(ProductOperationList::class)` - Uso en listados operacionales (muestras y productivos)

**Reglas:**
- Código único generado automáticamente si no se proporciona
- Implementa `HasAuditLabel` para auditoría
- Categorías predefinidas en `config/operations.php`
- **`standard_time` es el promedio histórico** de todos los `optimal_time` de fichas donde se usó
- **Se actualiza automáticamente** cada vez que se registra en una nueva ficha
- **Permite reutilización**: Nuevas fichas pueden usar operaciones con tiempos de referencia

---

#### 2. **ProductSheet** (Extensión - Distinguir Muestra vs Productivo)
```php
// Agregar campo a tabla existente: product_sheets
- type (enum: 'muestra', 'productivo') // ⚠️ NUEVO: Tipo de ficha
```

**Reglas:**
- Fichas tipo **'muestra'**: Se registran tiempos óptimos (operario experto)
- Fichas tipo **'productivo'**: Se usan tiempos productivos (con factor de eficiencia)
- Una muestra puede convertirse en base para múltiples productos productivos

---

#### 3. **ProductOperationList** (Registro Universal de Operaciones)
```php
// app/Models/ProductSheet/ProductOperationList.php
// Una sola tabla almacena TODOS los datos siempre

- id
- product_sheet_id (foreignId) // Ficha: muestra o productivo
- operation_id (foreignId) // Operación del catálogo
- machine_id (foreignId) // Máquina utilizada
- sequence_order (integer) // Orden de ejecución
- section (enum) // 🆕 Sección: delantero, trasero, pretina, ensamble, terminacion, presentacion

// DATOS DE LA OPERACIÓN (SIEMPRE PRESENTES)
- optimal_time (decimal) // Tiempo óptimo (operario experto, referencia)
- sam (decimal) // 🆕 Standard Allowed Minutes (= optimal_time, alias para reportes)
- operator_efficiency_factor (decimal) // Factor operario (default: 1.25)
- machine_supplement_factor (decimal) // Factor máquina (copiado de Machine)
- production_time (decimal) // = optimal × 1.25 × machine_supplement (SAM 100%)

// 🆕 COSTOS Y PRODUCTIVIDAD SE CALCULAN DINÁMICAMENTE (no se almacenan)
// unit_cost = production_time × config('operations.labor_cost_per_minute')
// units_per_hour = 60 / production_time (UND 100%)

// TRAZABILIDAD Y CONTEXTO
- operator_name (string, nullable) // Operario experto que ejecutó (para muestras)
- actual_time (decimal, nullable) // Tiempo real registrado en producción
- variance (decimal, nullable) // Desviación: (actual - production) / production * 100
- notes (text, nullable) // Observaciones, cambios, problemas

// METADATOS
- is_active (boolean, default: true) // Activo en el listado
- created_by (foreignId → users.id)
- timestamps, softDeletes
```

**Secciones del producto (enum):**
- `delantero` - Operaciones del frente
- `trasero` - Operaciones de la parte trasera
- `pretina` - Operaciones de cintura/pretina
- `ensamble` - Unión de partes
- `terminacion` - Acabados finales
- `presentacion` - Empaque, etiquetado, stickers

**El truco: NO hay campos condicionales NULL.**
- Muestra: almacena optimal_time + operario + máquina (cálculos incluidos)
- Productivo: copia exactamente los mismos datos (reutiliza todo)
- En producción: puede completar actual_time, variance y costos

---

## 📊 Estructura de Datos Optimizada

**Tabla única, datos completos:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| section | enum | Sección: delantero, trasero, pretina, ensamble, terminacion, presentacion |
| optimal_time | decimal | Tiempo operario experto (SAM - Standard Allowed Minutes) |
| operator_efficiency_factor | decimal | Factor operario (1.25 default, configurable) |
| machine_supplement_factor | decimal | Factor máquina (copiado de catálogo) |
| production_time | decimal | **Guardado**: optimal × 1.25 × suplemento (SAM 100%) |
| actual_time | decimal | **Opcional**: Tiempo real en producción |
| variance | decimal | **Calculado**: Desviación % (actual vs production) |
| operator_name | string | Quién ejecutó en muestra (trazabilidad) |

**🆕 Cálculos Dinámicos (no almacenados):**

| Campo Virtual | Fórmula | Ejemplo |
|---------------|---------|----------|
| unit_cost | production_time × tarifa_confeccion | 0.21 × $1,680 = $353 |
| units_per_hour | 60 / production_time | 60 / 0.21 = 285 UND |
| sam_display | optimal_time (alias) | 0.17 min |

**Beneficios:**
- ✅ Agrupación por secciones (como en imagen)
- ✅ SAM estándar de industria textil
- ✅ Costos por operación y totales
- ✅ Productividad (unidades/hora)
- ✅ Copia perfecta de muestra a productivo
- ✅ Historial completo (SoftDeletes)
- ✅ Datos listos para reportes tipo Excel

---

## 🔧 Componentes Técnicos

### Backend (Laravel)

#### Migraciones
```php
// database/migrations/YYYY_MM_DD_create_operations_table.php
// database/migrations/YYYY_MM_DD_create_product_operation_lists_table.php // ⚠️ TABLA ÚNICA
// database/migrations/YYYY_MM_DD_add_type_to_product_sheets_table.php // Agregar campo 'type' (muestra/productivo)
```

#### Servicios
```php
// app/Services/ProductSheet/MachineService.php
- create(array $data): Machine
- update(Machine $machine, array $data): Machine
- findOrCreateByName(string $name, ?string $category): Machine
- getMostUsed(int $limit = 10): Collection
- getByCategory(string $category): Collection

// app/Services/ProductSheet/OperationService.php
- create(array $data): Operation
- update(Operation $operation, array $data): Operation
- findOrCreateByName(string $name, ?string $category): Operation
- deduplicateOperations(): void
- calculateStandardTime(int $operationId): float // Promedio ponderado (EWMA) de optimal_time por recencia
- updateStandardTime(int $operationId): void
- getMostUsed(int $limit = 10): Collection
- getSimilarOperations(int $productSheetId): Collection

// app/Services/ProductSheet/ProductOperationListService.php

// CRUD BÁSICO
- create(array $data): ProductOperationList
- update(ProductOperationList $listItem, array $data): ProductOperationList
- delete(ProductOperationList $listItem): void
- copyFromProduct(ProductSheet $source, ProductSheet $target): Collection
- getBySection(int $productSheetId, string $section): Collection
- paginate(array $filters): Paginator

// CÁLCULOS BASE
- calculateProductionTime(float $optimal, float $operator, float $machine): float
- calculateVariance(float $actual, float $production): float

// 🆕 CÁLCULOS DINÁMICOS (no almacenados, calculados on-demand)
- calculateUnitCost(float $productionTime): float // production_time × tarifa_config
- calculateUnitsPerHour(float $productionTime): int // 60 / production_time

// 🆕 TOTALES DINÁMICOS POR SECCIÓN
- getTotalSAMBySection(int $productSheetId, string $section): float
- getTotalCostBySection(int $productSheetId, string $section): float
- getTotalSAMConfeccion(int $productSheetId): float // delantero+trasero+pretina+ensamble+terminacion
- getTotalSAMPresentacion(int $productSheetId): float // presentacion
- getTotalCostConfeccion(int $productSheetId): float
- getTotalCostPresentacion(int $productSheetId): float
- getTotalCostHilo(int $productSheetId): float // tarifa_fija × cantidad_operaciones
- getTotalCostProduct(int $productSheetId): float // confeccion + hilo
```

#### Controllers
```php
// app/Http/Controllers/ProductSheet/MachineController.php
- index(): InertiaResponse
- getData(Request $request): JsonResponse
- store(MachineStoreRequest $request): JsonResponse
- update(MachineUpdateRequest $request, Machine $machine): JsonResponse
- destroy(Machine $machine): JsonResponse

// app/Http/Controllers/ProductSheet/OperationController.php
- index(): InertiaResponse
- getData(Request $request): JsonResponse
- store(OperationStoreRequest $request): JsonResponse
- update(OperationUpdateRequest $request, Operation $operation): JsonResponse
- destroy(Operation $operation): JsonResponse
- deduplicateOperations(): JsonResponse

// app/Http/Controllers/ProductSheet/ProductOperationListController.php
- index(): InertiaResponse // Listar operaciones de ficha
- getData(Request $request): JsonResponse // Paginado
- store(ProductOperationListStoreRequest $request): JsonResponse // Crear
- update(ProductOperationListUpdateRequest $request, ProductOperationList $item): JsonResponse // Actualizar
- destroy(ProductOperationList $item): JsonResponse // Eliminar (SoftDelete)
- copyFromProduct(Request $request): JsonResponse // Copiar listado de otra ficha
- reorder(Request $request): JsonResponse // Cambiar orden
```

#### Policies
```php
// app/Policies/ProductSheet/MachinePolicy.php
// app/Policies/ProductSheet/OperationPolicy.php
// app/Policies/ProductSheet/ProductOperationListPolicy.php // ⚠️ POLICY ÚNICA
```

#### Requests
```php
// app/Http/Requests/ProductSheet/MachineStoreRequest.php
// app/Http/Requests/ProductSheet/MachineUpdateRequest.php
// app/Http/Requests/ProductSheet/OperationStoreRequest.php
// app/Http/Requests/ProductSheet/OperationUpdateRequest.php
// app/Http/Requests/ProductSheet/ProductOperationListStoreRequest.php // ⚠️ REQUEST ÚNICO
// app/Http/Requests/ProductSheet/ProductOperationListUpdateRequest.php
```

#### Resources
```php
// app/Http/Resources/ProductSheet/MachineResource.php
// app/Http/Resources/ProductSheet/OperationResource.php
// app/Http/Resources/ProductSheet/ProductOperationListResource.php // ⚠️ RESOURCE ÚNICO
```

---

### Frontend (Vue 3 + TypeScript)

#### Páginas
```typescript
// resources/js/pages/ProductSheet/Machines/Index.vue
// Catálogo de máquinas con búsqueda, filtros por categoría, gestión de suplementos

// resources/js/pages/ProductSheet/Operations/Index.vue
// Catálogo de operaciones con búsqueda, filtros por categoría

// resources/js/pages/ProductSheet/OperationLists/Index.vue // ⚠️ PÁGINA ÚNICA
// Lista de fichas con sus listados operacionales (muestras y productivos)

// resources/js/pages/ProductSheet/OperationLists/Create.vue // ⚠️ FORMULARIO ÚNICO
// Formulario que se adapta según tipo de ficha:
//   - Si es MUESTRA: registrar operaciones con tiempos óptimos
//   - Si es PRODUCTIVO: copiar desde muestra o construir desde catálogo
```

#### Componentes
```typescript
// resources/js/components/ProductSheet/MachineCatalogSelector.vue
// Selector de máquinas con autocompletado, muestra suplemento de cada máquina

// resources/js/components/ProductSheet/MachineSupplementBadge.vue
// Badge que muestra el suplemento de la máquina (ej: "+15% pausas")

// resources/js/components/ProductSheet/OperationCatalogSelector.vue
// Selector de operaciones con autocompletado

// resources/js/components/ProductSheet/EfficiencyFactorSelector.vue
// ⚠️ Selector de factor de eficiencia de OPERARIO con presets (1.15, 1.25, 1.35)

// resources/js/components/ProductSheet/ProductionTimeCalculator.vue
// ⚠️ Nuevo: Widget que muestra desglose del cálculo:
//    Tiempo óptimo × Factor operario × Suplemento máquina = Tiempo productivo

// resources/js/components/ProductSheet/OperationCatalogSelector.vue

// resources/js/components/ProductSheet/OperationTimeInput.vue
// Input especializado para captura de tiempos (hh:mm:ss o minutos)

// resources/js/components/ProductSheet/SampleOperationTable.vue
// Tabla editable de operaciones de muestra con drag-and-drop

// resources/js/components/ProductSheet/ProductOperationListTable.vue
// Tabla del listado operacional con ajustes de tiempos

// resources/js/components/ProductSheet/OperationDuplicateDetector.vue
// Widget que alerta sobre posibles duplicados
```

#### Tipos
```typescript
// resources/js/types/index.d.ts
export interface Machine {
    id: number;
    code: string;
    name: string;
    category: string;
    supplement_factor: number; // Factor de suplemento (pausas, descansos)
    description?: string;
    is_active: boolean;
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface Operation {
    id: number;
    code: string;
    name: string;
    description?: string;
    category: string;
    standard_time?: number; // Promedio histórico
    usage_count: number;
    is_active: boolean;
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface ProductSheet {
    id: number;
    reference: string;
    type: 'muestra' | 'productivo'; // ⚠️ NUEVO
    // ... otros campos existentes
}

export interface ProductOperationList {
    id: number;
    product_sheet_id: number;
    product_sheet?: ProductSheet;
    operation_id: number;
    operation?: Operation;
    machine_id: number;
    machine?: Machine;
    sequence_order: number;
    section: 'delantero' | 'trasero' | 'pretina' | 'ensamble' | 'terminacion' | 'presentacion';
    
    // TIEMPOS (siempre presentes)
    optimal_time: number; // SAM original
    operator_efficiency_factor: number; // 1.25 default
    machine_supplement_factor: number; // Del catálogo
    production_time: number; // SAM 100% calculado
    
    // TRAZABILIDAD
    operator_name?: string;
    actual_time?: number;
    variance?: number;
    notes?: string;
    
    // METADATOS
    is_active: boolean;
    created_by: number;
    created_at: string;
    updated_at: string;
    
    // 🆕 PROPIEDADES CALCULADAS (computed, no en DB)
    unit_cost?: number; // production_time × tarifa
    units_per_hour?: number; // 60 / production_time
}
```

---

## 🔐 Permisos y Seguridad

### Permisos Spatie (nuevos)
```php
// database/seeders/RoleAndPermissionSeeder.php
// Catálogo de Máquinas
'ver catálogo máquinas',
'crear catálogo máquinas',
'editar catálogo máquinas',
'eliminar catálogo máquinas',

// Catálogo de Operaciones
'ver catálogo operaciones',
'crear catálogo operaciones',
'editar catálogo operaciones',
'eliminar catálogo operaciones',

// Listados Operacionales (⚠️ UNIFICADO para muestras y productivos)
'ver listados operacionales',
'crear listados operacionales',
'editar listados operacionales',
'eliminar listados operacionales',
'generar listado desde muestra', // Acción especial para productivos

// ⚠️ TOTAL: 13 permisos
```

### Roles sugeridos
- **Supervisor de Planta**: Crear/editar listados operacionales, ver catálogo
- **Jefe de Producción**: Todos los permisos del módulo
- **Operario**: Ver listados operacionales (solo lectura)
- **Administrador**: Control total

---

## 📝 Rutas

```php
// routes/web.php
Route::prefix('diseno')->name('diseno.')->group(function () {
    // Catálogo de Máquinas
    Route::prefix('maquinas')->name('machines.')->group(function () {
        Route::get('/', [MachineController::class, 'index'])
            ->middleware('permission:ver catálogo máquinas')
            ->name('index');
        Route::get('/data', [MachineController::class, 'getData'])
            ->name('data');
        Route::post('/', [MachineController::class, 'store'])
            ->middleware('permission:crear catálogo máquinas')
            ->name('store');
        Route::put('/{machine}', [MachineController::class, 'update'])
            ->middleware('permission:editar catálogo máquinas')
            ->name('update');
        Route::delete('/{machine}', [MachineController::class, 'destroy'])
            ->middleware('permission:eliminar catálogo máquinas')
            ->name('destroy');
    });

    // Catálogo de Operaciones
    Route::prefix('operaciones')->name('operations.')->group(function () {
        Route::get('/', [OperationController::class, 'index'])
            ->middleware('permission:ver catálogo operaciones')
            ->name('index');
        Route::get('/data', [OperationController::class, 'getData'])
            ->name('data');
        Route::post('/', [OperationController::class, 'store'])
            ->middleware('permission:crear catálogo operaciones')
            ->name('store');
        Route::put('/{operation}', [OperationController::class, 'update'])
            ->middleware('permission:editar catálogo operaciones')
            ->name('update');
        Route::delete('/{operation}', [OperationController::class, 'destroy'])
            ->middleware('permission:eliminar catálogo operaciones')
            ->name('destroy');
        Route::post('/deduplicar', [OperationController::class, 'deduplicateOperations'])
            ->middleware('permission:editar catálogo operaciones')
            ->name('deduplicate');
    });

    // ⚠️ RUTAS UNIFICADAS para Listados Operacionales (muestras Y productivos)
    Route::prefix('listados-operacionales')->name('product-operation-lists.')->group(function () {
        Route::get('/', [ProductOperationListController::class, 'index'])
            ->middleware('permission:ver listados operacionales')
            ->name('index');
        Route::get('/data', [ProductOperationListController::class, 'getData'])
            ->name('data');
        Route::post('/', [ProductOperationListController::class, 'store'])
            ->middleware('permission:crear listados operacionales')
            ->name('store');
        Route::post('/batch', [ProductOperationListController::class, 'bulkStore'])
            ->middleware('permission:crear listados operacionales')
            ->name('bulk-store');
        Route::put('/{listItem}', [ProductOperationListController::class, 'update'])
            ->middleware('permission:editar listados operacionales')
            ->name('update');
        Route::delete('/{listItem}', [ProductOperationListController::class, 'destroy'])
            ->middleware('permission:eliminar listados operacionales')
            ->name('destroy');
        Route::post('/generar-desde-muestra', [ProductOperationListController::class, 'generateFromSample'])
            ->middleware('permission:generar listado desde muestra')
            ->name('generate-from-sample');
        Route::post('/copiar-desde-producto', [ProductOperationListController::class, 'copyFromProduct'])
            ->middleware('permission:crear listados operacionales')
            ->name('copy-from-product');
        Route::post('/generar-desde-catalogo', [ProductOperationListController::class, 'generateFromCatalog'])
            ->middleware('permission:crear listados operacionales')
            ->name('generate-from-catalog');
        Route::post('/reordenar', [ProductOperationListController::class, 'reorder'])
            ->middleware('permission:editar listados operacionales')
            ->name('reorder');
        Route::get('/sugerir', [ProductOperationListController::class, 'suggestOperation'])
            ->name('suggest-operation');
    });
});
```
⚠️ Banner informativo**: "Registre tiempos tomados por el operario MÁS HABILIDOSO"
- **Formulario de captura rápida**:
  - Selector de operación del catálogo (autocompletado)
  - Campo libre para operación nueva
  - Input de tiempo con badge "TIEMPO ÓPTIMO" (formato flexible: mm:ss o minutos decimales)
  - Nombre de operario experto (recomendado
## 💡 UI Simplificada

### Formulario de Creación
```
Sección:         [Delantero ▼]
Operación:        [Cerrar tiro delantero ▼]
Máquina:          [MF5H - Fileteadora 5 hilos ▼]
Tiempo óptimo:    [0.17] minutos (SAM)
Operario experto: [Juan García]
Notas:            [Sin problemas]

[Guardar]
```

### Tabla Agrupada por Sección
```
=== DELANTERO ===
# | Operación                    | Máq  | SAM  | Prod | Cost | UND/h |
--+------------------------------+------+------+------+------+-------+
1 | Cerrar tiro delantero        | MF5H | 0.17 | 0.21 | $353 |  300  |
2 | Filetear bolsillo parche X2  | MF3H | 0.11 | 0.14 | $545 |  450  |
  Subtotal Delantero: 0.28 min | Costo: $898

=== TRASERO ===
# | Operación                    | Máq   | SAM  | Prod | Cost | UND/h |
--+------------------------------+-------+------+------+------+-------+
3 | Planchar bolsillo trasero    | PLANC | 1.26 | 1.58 |  $48 |   48  |
4 | Marcar bolsillo trasero      | OFM   | 0.92 | 1.15 |  $65 |   65  |
  Subtotal Trasero: 2.18 min | Costo: $113

=== TOTALES ===
Confeccion:    10.87 SAM | $4,553
Presentacion:   1.86 SAM | $755
------------------------
TOTAL PRODUCTO: 12.73 SAM | $5,308
```

**Reportes automáticos:**
- Excel con formato como imagen
- PDF para producción
- Dashboard con métricas por sección

---

## 🚀 Plan de Implementación

### Fase 1: Base (Día 1)
- [ ] Migration: machines (code, name, supplement_factor)
- [ ] Migration: operations (standard_time, usage_count)
- [ ] Migration: product_operation_lists con TODOS los campos (optimal_time, operator_efficiency_factor, machine_supplement_factor, production_time, actual_time, variance, operator_name)
- [ ] Modelos: Machine, Operation, ProductOperationList
- [ ] Observers para auditoría

### Fase 2: Lógica (Día 2)
- [ ] MachineService + MachineController
- [ ] OperationService + OperationController
- [ ] ProductOperationListService:
  - create() → guarda todos los campos
  - copyFromProduct() → copia exacta del registro
  - calculateProductionTime() → optimal × 1.25 × supplement
  - calculateVariance() → (actual - production) / production
- [ ] ProductOperationListController
- [ ] FormRequests, Policies, Resources

### Fase 3: Frontend (Día 3)
- [ ] Página Máquinas (CRUD)
- [ ] Página Operaciones (CRUD)
- [ ] Página Operaciones por Ficha (muestra/productivo)
- [ ] Componentes: autocompletes, tabla, calculadora

### Fase 4+: Refinamiento
- [ ] Tests
- [ ] Reportes (eficiencia, desviaciones)
- [ ] Dashboard de métricas

---

## 🔍 Consideraciones Técnicas

### Prevención de Duplicados
```php
// En OperationService::findOrCreateByName()
use Illuminate\Support\Str;

public function findOrCreateByName(string $name, ?string $category): Operation
{
    // Normalizar nombre
    $normalized = Str::lower(trim($name));
    
    // Buscar por similitud (Levenshtein o similar_text)
    $existing = Operation::where('is_active', true)
        ->get()
        ->filter(function ($op) use ($normalized) {
            $similarity = similar_text(
                Str::lower($op->name), 
                $normalized, 
                $percent
            );
            return $percent >= 85; // 85% de similitud
        })
        ->first();
    
    if ($existing) {
        return $existing;
    }
    
    // Crear nueva operación
    return Operation::create([
        'name' => $name,
        'code' => $this->generateUniqueCode($category),
        'category' => $category,
        'created_by' => auth()->id(),
    ]);
}

private function generateUniqueCode(?string $category): string
{
    $prefix = $category ? strtoupper(substr($category, 0, 3)) : 'OP';
    $count = Operation::where('code', 'like', "{$prefix}-%")->count();
    return sprintf('%s-%03d', $prefix, $count + 1);
}
```

### Cálculo de Tiempo Productivo
```php
public function calculateProductionTime(
    float $optimalTime, 
    float $operatorEfficiency = 1.25,
    float $machineSupplement = 1.0
): float
{
    // Tiempo productivo = Tiempo óptimo × Factor operario × Suplemento máquina
    // Ejemplo: 10 min × 1.25 (operario) × 1.15 (máquina) = 14.375 min
    // Desglose:
    //   - Tiempo base (experto, ideal): 10.0 min
    //   - + Diferencia operario promedio: +2.5 min (25%)
    //   - + Suplemento máquina (pausas): +1.875 min (15% sobre 12.5)
    //   - Total realista: 14.375 min
    
    $productionTime = $optimalTime * $operatorEfficiency * $machineSupplement;
    return round($productionTime, 2);
}

// Obtener suplemento de máquina automáticamente
public function getMachineSupplementFactor(int $machineId): float
{
    $machine = Machine::findOrFail($machineId);
    return $machine->supplement_factor;
}
```

### Cálculo de Tiempo Estándar
```php
public function calculateStandardTime(int $operationId): float
{
  // EWMA (Exponential Weighted Moving Average) sobre últimos N registros
  $window = (int) config('operations.standard_time.window', 10); // N
  $alpha  = (float) config('operations.standard_time.alpha', 0.30); // 0<alpha<=1

  $values = ProductOperationList::whereHas('productSheet', function ($q) {
      $q->where('type', 'muestra');
    })
    ->where('operation_id', $operationId)
    ->latest()
    ->limit($window)
    ->pluck('optimal_time')
    ->toArray();

  if (empty($values)) {
    return 0.0;
  }

  // Procesar de más antiguo a más reciente para EWMA
  $values = array_reverse($values);
  $ewma = $values[0];
  foreach (array_slice($values, 1) as $x) {
    $ewma = $alpha * $x + (1 - $alpha) * $ewma;
  }
  return round($ewma, 2);
}
```

---

## 📦 Configuración

```php
// config/operations.php
return [
  // Factor FIJO de operario
  'operator_efficiency_factor' => 1.25,
  
  // 🆕 TARIFAS FIJAS (editables en admin)
  'labor_cost_per_minute' => 1680, // Tarifa confección por minuto (ej: $1,680 COP)
  'thread_cost_fixed' => 140, // Costo hilo fijo por producto (ej: $140 COP)
  
  // Secciones del producto
  'sections' => [
    'delantero' => 'Delantero',
    'trasero' => 'Trasero',
    'pretina' => 'Pretina',
    'ensamble' => 'Ensamble',
    'terminacion' => 'Terminación',
    'presentacion' => 'Presentación',
  ],
  
  // Secciones de confección (vs presentación)
  'confeccion_sections' => [
    'delantero', 'trasero', 'pretina', 'ensamble', 'terminacion'
  ],
  
  // 🆕 Máquinas iniciales (CRUD editable sin interfaz dedicada)
  'machines' => [
    ['code' => 'MPP', 'name' => 'Máquina Plana', 'supplement' => 1.10],
    ['code' => 'MF5H', 'name' => 'Fileteadora 5 Hilos', 'supplement' => 1.08],
    ['code' => 'MF3H', 'name' => 'Fileteadora 3 Hilos', 'supplement' => 1.08],
    ['code' => 'PLANC', 'name' => 'Plancha', 'supplement' => 1.05],
    ['code' => 'OFM', 'name' => 'Ojaladora/Filete/Marca', 'supplement' => 1.10],
    ['code' => 'MRC02', 'name' => 'Recubridora', 'supplement' => 1.12],
    // ... más por agregar vía CRUD
  ],
  
  // EWMA para tiempos históricos
  'standard_time' => [
    'method' => 'ewma',
    'alpha' => 0.30,
    'window' => 10,
  ],
];
```
- **Desviación por operario**:
  - Si se registra en producción, comparar rendimiento vs tiempo óptimo
  - Identificar operarios que alcanzan tiempos cercanos al óptimolitud
        'enabled' => true,
    ],
    
    'sample_types' => [
        'muestra' => 'Muestra',
        'productivo' => 'Productivo',
    ],
];
```

---

## 🎯 Casos de Uso Principales

### Caso 1: Registrar Operaciones de una Muestra Nueva
1. Usuario selecciona ProductSheet (muestra)
2. Usuario registra operaciones secuencialmente
3. Sistema sugiere operaciones del catálogo mientras escribe
4. Usuario puede crear operación nueva si no existe
5. Sistema registra en `sample_operation_logs`
6. Al finalizar, sistema sugiere añadir operaciones nuevas al catálogo

### Caso 2: Generar Listado Operacional para Producto Productivo
1. Usuario selecciona ProductSheet (producto productivo)
2. Usuario hace clic en "Generar desde Muestra"
3. Sistema muestra selector de muestras disponibles
4. Usuario selecciona muestra origen
5. Sistema copia operaciones con tiempos estimados
6. Usuario ajusta tiempos si es necesario
7. Sistema guarda en `product_operation_lists`

### Caso 3: Detectar y Fusionar Duplicados
1. Usuario hace clic en "Detectar Duplicados" en catálogo
2. Sistema ejecuta algoritmo de similitud
3. Sistema muestra pares de operaciones similares
4. Usuario confirma fusiones
5. Sistema:
   - Actualiza referencias en logs y listados
   - Marca operación duplicada como inactiva
   - Calcula nuevo tiempo estándar

---

## 📊 Métricas y Reportes Futuros (Fase 2)

- **Eficiencia de operaciones**: Comparar tiempos estimados vs reales
- **Top operaciones más usadas**: Por categoría
- **Análisis de desviaciones**: Productos con mayor diferencia muestra-producción
- **Operaciones cuellos de botella**: Mayor tiempo promedio
- **Historial de ajustes**: Auditoría de cambios en tiempos

---

## ✅ Checklist de Integración

- [ ] Crear 3 migraciones (machines, operations, product_operation_lists)
- [ ] Crear 3 modelos + observers
- [ ] Registrar 13 permisos
- [ ] Crear config/operations.php con máquinas y EWMA
- [ ] Crear 3 services, 3 controllers, 3 policies, 3 resources
- [ ] Crear rutas bajo `/diseno` prefix
- [ ] Frontend: 3 páginas + 5 componentes
- [ ] Tests + documentación

---

## 🛠️ Comandos Artisan Útiles (Futuros)
Tiempos poco realistas | ⚠️ Factor de eficiencia ajustable + validaciones |
| Confusión óptimo vs productivo | ⚠️ UI clara con badges y colores diferenciados |
| Factores inadecuados | ⚠️ Presets basados en experiencia + validación de rangos (1.0-2.0) |
| Sobrecarga en DB | Índices en columnas clave (product_sheet_id, operation_id) |
| Errores en captura manual | Validación estricta + tooltips + banner informativo |
| Retroalimentación no deseada | ⚠️ Flujo unidireccional: muestra → producción (sin vuelta)
php artisan operations:deduplicate

# Recalcular tiempos estándar de todas las operaciones
php artisan operations:recalculate-standard-times

# Exportar catálogo de operaciones a Excel
php artisan operations:export

# Importar operaciones desde plantilla
php artisan operations:import {file}
```

---

## 🎓 Convenciones de Código

### Nomenclatura
- **Modelos**: Singular (Operation, ProductOperationList) ⚠️ 2 modelos en total
- **Tablas**: Plural (operations, product_operation_lists)
- **Controllers**: Singular (OperationController, ProductOperationListController)
- **Services**: Singular (OperationService, ProductOperationListService)
### ⚠️ Diferenciadores Clave del Diseño

1. **Dos tipos de tiempos**: 
   - **Óptimo** (operario experto en muestra) → Referencia
   - **Productivo** (con factor de eficiencia) → Uso real en planta

2. **Flujo unidireccional**: Muestra → Producción (sin retroalimentación automática)

3. **Ajustes excepcionales**: Solo por inconformidades, con auditoría completa

4. **Factores configurables**: Presets para diferentes niveles de experiencia

La implementación priorizará **simplicidad**, **claridad en la diferenciación de tiempos** y **reutilización efectiva** del catálogo histórico.

---

## 📊 Diagrama Simple

```
Maquina
├─ MF01 (Fileteadora) → 1.08
├─ MRC02 (Recubridora) → 1.12
└─ MCO01 (Cosedora) → 1.10

Operation (Operaciones)
├─ Coser manga
├─ Hacer dobladillo
└─ Fileteado

ProductOperationList (Fichas muestra/productivas)
├─ Operación
├─ Máquina
├─ Tiempo óptimo (muestra: 10 min)
└─ Tiempo productivo (= 10 × 1.25 × 1.12 = 14 min)
```

---

## 🔄 Flujo de Datos Completo

```
┌─────────────────┐
│ 1. Crear Muestra│ (ProductSheet type='muestra')
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────────────┐
│ 2. Registrar Operaciones en Muestra            │
│  - Buscar en catálogo (sugerencias)            │
│  - Crear nueva si no existe                    │
│  - Ingresar optimal_time (operario experto)    │
│  → Guarda en product_operation_lists con:      │
│    optimal_time, operator_name                 │
│    production_time=NULL, efficiency_factor=NULL│
└────────┬────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────┐
│ 3. Actualizar Catálogo Automáticamente         │
│  - standard_time = AVG(optimal_time) de todas  │
│    las fichas donde se usó                     │
│  - usage_count++                               │
└────────┬────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────┐
│ 4. Crear Productivo                            │
│  Opción A: Copiar desde Muestra                │
│    - Seleccionar ficha muestra                 │
│    - Aplicar efficiency_factor (default 1.25)  │
│    - production_time = optimal_time × factor   │
│  Opción B: Copiar desde otro Productivo        │
│    - Copia production_time y factor directos   │
│  Opción C: Construir desde Catálogo            │
│    - Usa standard_time × factor                │
│  → Guarda en product_operation_lists con:      │
│    optimal_time (ref), production_time,        │
│    efficiency_factor, operator_name=NULL       │
└────────┬────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────┐
│ 5. Uso y Ediciones en Producción              │
│  - Tiempos productivos disponibles para planta│
│  - Ediciones directas sin restricciones        │
│  - Auditoría automática de todos los cambios  │
└─────────────────────────────────────────────────┘
```

---

## 🛠️ Comandos Artisan Útiles (Futuros)

```bash
# Recalcular standard_time de todas las operaciones
php artisan operations:recalculate-standard-times

# Detectar y listar operaciones duplicadas
php artisan operations:detect-duplicates

# Generar reporte de eficiencia (muestra vs productivo)
php artisan operations:efficiency-report {product_sheet_id}
```

---

### Comentarios
- Español claro y conciso
- PHPDoc completo en Services
- Comentarios inline solo cuando lógica no es obvia

### Validación
- Dual: Frontend (Vee-Validate + Zod) + Backend (FormRequest)
- Mensajes de error en español

---

## 🚨 Riesgos y Mitigaciones

| Riesgo | Mitigación |
|--------|-----------|
| Duplicados en catálogo | Algoritmo de similitud + revisión manual periódica |
| Pérdida de historial | SoftDeletes en todos los modelos |
| Inconsistencia de tiempos | Auditoría automática + alertas cuando desviación > 30% |
| Sobrecarga en DB | Índices en columnas clave (product_sheet_id, operation_id) |
| Errores en captura manual | Validación estricta + tooltips en UI |
| Confusión muestra vs productivo | Banner contextual + UI adaptativa según tipo |

---

## 📚 Referencias

- **Arquitectura del proyecto**: `.github/copilot-instructions.md`
- **Patrón Service Layer**: `app/Services/Expenses/PurchaseOrderService.php`
- **Patrón Observer**: `app/Observers/AuditObserver.php`
- **Patrón Policy**: `app/Policies/Expenses/PurchaseRequestPolicy.php`
- **Componentes Vue**: `resources/js/pages/ProductSheets/ProductSheetDialog.vue`

---

## � Conclusión: Diseño Optimizado

### ¿Por qué esta arquitectura es mejor?

1. **Una tabla, múltiples contextos**
   - Muestra y productivo usan EXACTAMENTE el mismo formato
   - Reutilización = copiar fila sin transformaciones
   - Menos bugs, menos lógica especial

2. **Datos completos siempre**
   - optimal_time (referencia histórica)
   - operator_efficiency_factor (1.25 default, configurable)
   - machine_supplement_factor (copiado del catálogo)
   - production_time (calculado)
   - actual_time (para producción real)
   - variance (desviación automática)
   - operator_name (quién ejecutó, trazabilidad)

3. **Sin campos NULL innecesarios**
   - Todos los campos tienen valor siempre
   - Simplifica queries, índices, caché
   - Facilita reportes y análisis

4. **Historial perfecto**
   - SoftDeletes preserva todas las versiones
   - Auditoría automática en cambios
   - Análisis de evolución de tiempos

5. **Escalable**
   - Fácil agregar nuevos campos (ej: machine_setup_time, operator_skill_level)
   - Mismo patrón para todos
   - Sin refactorización compleja

6. **Reportes tipo Excel** 🆕
   - Agrupación por secciones (DELANTERO, TRASERO, etc.)
   - Totales SAM por confección vs presentación
   - Costos desglosados por operación y sección
   - Exportación directa a formato como imagen

Esta estructura es **simple, eficiente, auditable, flexible y lista para reportes profesionales**.

---

---

## ⚠️ Aclaraciones Importantes

### 1. **Campos NO van en ProductSheet**
- ❌ NO agregar: `lote`, `cant_lote`, `tiempo_modulo`, `costo_total`
- ✅ Estos se **calculan dinámicamente** desde las operaciones
- ProductSheet solo almacena: referencia, nombre, tipo (muestra/productivo)

### 2. **Costos son Tarifas Fijas**
- **Costo confección**: `production_time × tarifa_por_minuto` (config)
- **Costo hilo**: Tarifa fija por producto (config)
- **NO se calculan por operación**, son globales configurables

### 3. **Máquinas Editables vía CRUD**
- Listado inicial en config solo para seeder
- CRUD básico (sin interfaz dedicada, solo select/modal)
- Campos: `code`, `name`, `supplement_factor`

### 4. **Fórmulas Confirmadas**
- **SAM 100%** = `production_time` (guardado en DB)
- **UND 100%** = `60 / production_time` (calculado dinámicamente)
- **Costo unitario** = `production_time × tarifa_config` (calculado dinámicamente)

### 5. **Totales se Calculan On-Demand**
```php
// En Service al generar reporte:
Tiempo Módulo = sum(production_time) de todas las operaciones
Costo Confección = Tiempo Módulo × tarifa_por_minuto
Costo Hilo = tarifa_fija (desde config)
Costo Total = Costo Confección + Costo Hilo
```

---

## 📊 Reportes Disponibles

### 1. Listado Operacional Completo (como imagen)
- Agrupado por secciones
- Columnas: Operación, Máquina, SAM, Costo, UND/hora
- Subtotales por sección
- Totales: Confección, Presentación, Total Producto

### 2. Análisis de Eficiencia
- Comparación SAM vs Tiempo Real
- Varianzas por operación
- Operarios más eficientes

### 3. Costos por Producto
- Desglose completo de costos operacionales
- Costo mano de obra vs materiales
- Márgenes y rentabilidad

### 4. Dashboard de Métricas
- Productividad por sección
- Tiempos promedio por tipo de operación
- Tendencias históricas (EWMA)
