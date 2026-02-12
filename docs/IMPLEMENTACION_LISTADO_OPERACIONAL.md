# Implementación: Módulo de Listados Operacionales (3 Niveles)

**Estado:** Guía de implementación paso a paso  
**Fecha:** 11 de febrero de 2026  
**Objetivo:** Depurar y reconstruir el módulo existente según arquitectura de 3 niveles para 50-120 operaciones por producto

---

## 🚀 INICIO RÁPIDO (Para modelos de IA)

**Tiendo este documento como guía de implementación paso a paso**, sigue estos pasos en orden:

### Paso 1: Entender el estado actual (5 min)
→ Lee la sección **"Análisis del Estado Actual vs Objetivo"** para ver qué existe y qué falta

### Paso 2: Seguir la Fase 1 (Estructura de datos) (45 min)
→ Implementa TODOS los cambios en **sección "Fase 1: Migración y Modelos"**
- 1.1: Actualizar Operation
- 1.2: Agregar supplement_factor a Machine
- 1.3: Renombrar ProductOperationList
- 1.4: Crear ProductSheetOperationHistory
- 1.5: Crear ProductionOrderOperation

**Ejecuta después:**
```bash
php artisan migrate
```

### Paso 3: Seguir la Fase 2 (Services) (30 min)
→ Crea los 3 services en **sección "Fase 2: Services"**:
- 2.1: OperationService
- 2.2: ProductSheetOperationService (el más importante)
- 2.3: ProductionOrderService

### Paso 4: Seguir la Fase 3 (Controllers) (20 min)
→ Actualiza/crea controllers en **sección "Fase 3: Controllers"**:
- 3.1: ProductSheetOperationController

### Paso 5: Fase 4 (FormRequests y Tests) (15 min)
→ Crea los requests y tests en **sección "Fase 4"**

### Paso 6: Fase 5 (Interfaces Frontend shadcn/vue) (60 min)
→ Diseña e implementa las vistas en **sección "Fase 5: Interfaces Frontend"**
- Index, ListView, OperationFormDialog, HistoryDialog, SamSummaryCard
- Usar componentes shadcn/vue: Table, Dialog, Card, Form, Button, etc.

### Paso 7: Ejecutar y verificar (5 min)
→ Ve a **sección "Ejecución"** y ejecuta los comandos

**TIEMPO TOTAL ESTIMADO: 3-4 horas de desarrollo concentrado**

---

## 📑 Índice del Documento

1. [Análisis del Estado Actual](#-análisis-del-estado-actual-vs-objetivo) ← COMIENZA AQUÍ
2. [Plan de Implementación](#-plan-de-implementación-4-fases)
   - Fase 1: Migraciones y Modelos
   - Fase 2: Services
   - Fase 3: Controllers
   - Fase 4: FormRequests y Tests
   - Fase 5: Interfaces Frontend (shadcn/vue)
3. [Checklist de Implementación](#-checklist-de-implementación)
4. [Ejecución](#-ejecución)

---

## 📊 Análisis del Estado Actual vs Objetivo

### ✅ Lo que YA EXISTE

El proyecto tiene la base:

| Componente | Estado | Ubicación |
|-----------|--------|-----------|
| **ProductSheet** | ✅ Existe | `app/Models/ProductSheet/ProductSheet.php` |
| **Operation** | ✅ Existe (incompleto) | `app/Models/ProductSheet/Operation.php` |
| **Machine** | ✅ Existe | `app/Models/ProductSheet/Machine.php` |
| **FabricBase** | ✅ Existe | `app/Models/ProductSheet/FabricBase.php` |
| **ProductOperationList** | ✅ Existe (debe renombrarse) | `app/Models/ProductSheet/ProductOperationList.php` |
| **Controllers** | ✅ Existen | `app/Http/Controllers/ProductSheet/` |
| **Rutas** | ✅ Existen | `routes/web.php` (listados-operacionales) |

### ❌ Lo que FALTA o está INCOMPLETO

| Nivel | Componente | Falta | Impacto |
|-------|-----------|-------|--------|
| **Nivel 1** | Operation | `codigo`, `seccion`, `sam_base`, `medida`, `descripción` | NO se puede gestionar SAM base |
| **Nivel 2** | ProductOperationList | Renombrar a `ProductSheetOperation` | Confusión en nomenclatura |
| **Nivel 2** | ProductSheetOperation | Campos: `sequence`, `base_tela_id`, `cantidad`, `sam_base`, `suplemento_maquina`, `factor_tela`, `factor_operario`, `sam_total_unitario`, `sam_total_operacion` | Cálculos incorrectos, sin snapshots |
| **Nivel 2** | ProductSheetOperationHistory | ❌ No existe | Sin auditoría de cambios en factores |
| **Nivel 3** | ProductionOrderOperation | ❌ No existe | Sin órdenes congeladas |
| **Nivel 3** | ProductionOrder | ❌ Quizás existe en Expenses | Integración incompleta |
| **Services** | OperationService | ❌ No existe | Sin lógica de negocio centralizada |
| **Services** | ProductSheetOperationService | ❌ No existe | Sin CRUD ni servicios especializados |
| **Services** | ProductionOrderService | ❌ No existe | Sin sincronización de órdenes |

---

## 🎯 Plan de Implementación (4 Fases)

### Fase 1: Migración y Modelos (Estructura de datos)

#### 1.1 Actualizar Operation (Modelo base)

**Archivo:** `app/Models/ProductSheet/Operation.php`

**Cambios requeridos:**
```php
// Agregar campos a fillable
protected $fillable = [
    'codigo',              // NEW: "OP-DEL-001" (único)
    'action',              // EXISTENTE: "CERRAR", "PRENDER"
    'garment_part',        // EXISTENTE: "TIRO", "BOLSILLO"
    'section_id',          // NEW: DELANTERO, TRASERO, etc (FK a OperationSection)
    'location',            // NEW: "DELANTERO", "TRASERO", "PRETINA"
    'measure',             // NEW: 0.25 (cantidad en metros)
    'description',         // NEW: "Cerrar tiro delantero"
    'sam_base',            // NEW: 0.25 (tiempo cronometrado en MINUTOS)
    'product_sheet_id_reference',  // NEW: FK a ProductSheet (dónde se tomó)
    'machine_id_reference',        // NEW: FK a Machine (máquina usada al tomar)
    'is_active',           // EXISTENTE
    'created_by',          // EXISTENTE
];

// Agregar relaciones
public function section()
{
    return $this->belongsTo(OperationSection::class, 'section_id');
}

public function productSheetReference()
{
    return $this->belongsTo(ProductSheet::class, 'product_sheet_id_reference');
}

public function machineReference()
{
    return $this->belongsTo(Machine::class, 'machine_id_reference');
}

public function productSheetOperations()
{
    return $this->hasMany(ProductSheetOperation::class);
}
```

**Migración:** `database/migrations/YYYY_MM_DD_update_operations_table.php`
```php
public function up(): void
{
    Schema::table('operations', function (Blueprint $table) {
        $table->string('codigo')->unique()->nullable()->after('id');
        $table->unsignedBigInteger('section_id')->nullable()->after('action');
        $table->string('location')->nullable()->after('garment_part');
        $table->decimal('measure', 8, 4)->nullable()->after('location');
        $table->text('description')->nullable()->after('measure');
        $table->decimal('sam_base', 8, 4)->nullable()->after('description');
        $table->unsignedBigInteger('product_sheet_id_reference')->nullable();
        $table->unsignedBigInteger('machine_id_reference')->nullable();
        $table->foreign('section_id')->references('id')->on('operation_sections')->onDelete('set null');
        $table->foreign('product_sheet_id_reference')->references('id')->on('product_sheets')->onDelete('set null');
        $table->foreign('machine_id_reference')->references('id')->on('machines')->onDelete('set null');
    });
}
```

---

#### 1.2 Machine: Agregar campo `supplement_factor`

**Cambios requeridos:**

```php
// En app/Models/ProductSheet/Machine.php
protected $fillable = [
    'code',
    'name',
    'supplement_factor',    // NEW: 1.08 (snapshot de suplemento actual)
    'is_active',
    'created_by',
];

protected $casts = [
    // ...
    'supplement_factor' => 'float',
];

// Remover o deprecar el accessor getSupplementFactorAttribute
// (ahora usamos campo en BD en lugar de lógica dinámica)
```

**Migración:** `database/migrations/YYYY_MM_DD_update_machines_table.php`
```php
public function up(): void
{
    Schema::table('machines', function (Blueprint $table) {
        $table->decimal('supplement_factor', 8, 4)->default(1.0)->after('name');
    });
}
```

---

#### 1.3 Renombrar ProductOperationList → ProductSheetOperation

**Cambios:**

1. Renombrar archivo:
   - `ProductOperationList.php` → `ProductSheetOperation.php`
   
2. Actualizar contenidos (fusionar con cambios requeridos):

```php
// app/Models/ProductSheet/ProductSheetOperation.php
namespace App\Models\ProductSheet;

class ProductSheetOperation extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'product_operation_lists';  // Mantener nombre tabla por compatibilidad

    protected $fillable = [
        'product_sheet_id',
        'sequence',                 // NEW: 10, 20, 30... (permite inserciones)
        'operation_id',
        'machine_id',
        'fabric_base_id',           // NEW: FK a fabric_bases
        'quantity',                 // NEW: 1 (default), 2 para mangas, etc
        'sam_base',                 // NEW/ACTUALIZADO: copiado de Operation al asignar (snapshot)
        'machine_supplement_factor', // RENOMBRADO: suplemento_maquina
        'fabric_base_factor',       // NEW: factor_tela (snapshot)
        'operator_efficiency_factor',// RENOMBRADO: factor_operario (editable)
        'sam_total_unitario',       // NEW: sam_base × suplemento × factor_tela × factor_operario
        'sam_total_operacion',      // NEW/RENOMBRADO: (sam_total_unitario × quantity)
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'quantity' => 'integer',
        'sam_base' => 'float',
        'machine_supplement_factor' => 'float',
        'fabric_base_factor' => 'float',
        'operator_efficiency_factor' => 'float',
        'sam_total_unitario' => 'float',
        'sam_total_operacion' => 'float',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    };

    // Relaciones
    public function productSheet()
    {
        return $this->belongsTo(ProductSheet::class);
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function fabricBase()
    {
        return $this->belongsTo(FabricBase::class);
    }

    public function productionOrderOperations()
    {
        return $this->hasMany(ProductionOrderOperation::class);
    }

    public function history()
    {
        return $this->hasMany(ProductSheetOperationHistory::class, 'product_sheet_operation_id');
    }

    // Métodos de cálculo
    public function calculateSamTotalUnitario(): float
    {
        return $this->sam_base * 
               $this->machine_supplement_factor * 
               $this->fabric_base_factor * 
               $this->operator_efficiency_factor;
    }

    public function calculateSamTotalOperacion(): float
    {
        return $this->calculateSamTotalUnitario() * $this->quantity;
    }

    // Scopes
    public function scopeBySheet(Builder $query, int $sheetId): Builder
    {
        return $query->where('product_sheet_id', $sheetId)->orderBy('sequence');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
```

**Migración:** `database/migrations/YYYY_MM_DD_update_product_operation_lists_table.php`
```php
public function up(): void
{
    Schema::table('product_operation_lists', function (Blueprint $table) {
        // Agregar campos faltantes
        $table->integer('sequence')->nullable()->after('operation_id');
        $table->unsignedBigInteger('fabric_base_id')->nullable()->after('machine_id');
        $table->integer('quantity')->default(1)->after('fabric_base_id');
        $table->decimal('sam_base', 8, 4)->nullable()->after('quantity');
        $table->decimal('sam_total_unitario', 8, 4)->nullable()->after('sam');
        $table->decimal('sam_total_operacion', 8, 4)->nullable()->after('sam_total_unitario');
        $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
        
        // Renombrar columnas (si existen)
        if (Schema::hasColumn('product_operation_lists', 'machine_supplement_factor')) {
            // Ya existe, ok
        } else {
            $table->decimal('machine_supplement_factor', 8, 4)->default(1.0)->after('sam_base');
        }
        
        if (Schema::hasColumn('product_operation_lists', 'fabric_base_factor')) {
            // Ya existe, ok
        } else {
            $table->decimal('fabric_base_factor', 8, 4)->default(1.0)->after('machine_supplement_factor');
        }
        
        // Crear índices
        $table->index('sequence');
        $table->index(['product_sheet_id', 'sequence']);
        
        // Foreign keys
        $table->foreign('fabric_base_id')->references('id')->on('fabric_bases')->onDelete('set null');
        $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
    });
}
```

---

#### 1.4 Crear ProductSheetOperationHistory (Auditoría)

**Archivo:** `app/Models/ProductSheet/ProductSheetOperationHistory.php`

```php
<?php

namespace App\Models\ProductSheet;

use App\Models\Concerns\HasAuditLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSheetOperationHistory extends Model
{
    use HasFactory, HasAuditLabel;

    protected $table = 'product_sheet_operation_history';

    protected $fillable = [
        'product_sheet_operation_id',
        'sam_base_anterior',
        'sam_base_nuevo',
        'machine_supplement_factor_anterior',
        'machine_supplement_factor_nuevo',
        'fabric_base_factor_anterior',
        'fabric_base_factor_nuevo',
        'operator_efficiency_factor_anterior',
        'operator_efficiency_factor_nuevo',
        'sam_total_unitario_anterior',
        'sam_total_unitario_nuevo',
        'sam_total_operacion_anterior',
        'sam_total_operacion_nuevo',
        'motivo',
        'usuario_id',
    ];

    protected $casts = [
        'sam_base_anterior' => 'float',
        'sam_base_nuevo' => 'float',
        'machine_supplement_factor_anterior' => 'float',
        'machine_supplement_factor_nuevo' => 'float',
        'fabric_base_factor_anterior' => 'float',
        'fabric_base_factor_nuevo' => 'float',
        'operator_efficiency_factor_anterior' => 'float',
        'operator_efficiency_factor_nuevo' => 'float',
        'sam_total_unitario_anterior' => 'float',
        'sam_total_unitario_nuevo' => 'float',
        'sam_total_operacion_anterior' => 'float',
        'sam_total_operacion_nuevo' => 'float',
        'created_at' => 'datetime',
    ];

    public function productSheetOperation()
    {
        return $this->belongsTo(ProductSheetOperation::class, 'product_sheet_operation_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
```

**Migración:** `database/migrations/YYYY_MM_DD_create_product_sheet_operation_history_table.php`
```php
public function up(): void
{
    Schema::create('product_sheet_operation_history', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_sheet_operation_id');
        $table->decimal('sam_base_anterior', 8, 4)->nullable();
        $table->decimal('sam_base_nuevo', 8, 4)->nullable();
        $table->decimal('machine_supplement_factor_anterior', 8, 4)->nullable();
        $table->decimal('machine_supplement_factor_nuevo', 8, 4)->nullable();
        $table->decimal('fabric_base_factor_anterior', 8, 4)->nullable();
        $table->decimal('fabric_base_factor_nuevo', 8, 4)->nullable();
        $table->decimal('operator_efficiency_factor_anterior', 8, 4)->nullable();
        $table->decimal('operator_efficiency_factor_nuevo', 8, 4)->nullable();
        $table->decimal('sam_total_unitario_anterior', 8, 4)->nullable();
        $table->decimal('sam_total_unitario_nuevo', 8, 4)->nullable();
        $table->decimal('sam_total_operacion_anterior', 8, 4)->nullable();
        $table->decimal('sam_total_operacion_nuevo', 8, 4)->nullable();
        $table->text('motivo')->nullable();
        $table->unsignedBigInteger('usuario_id');
        $table->timestamps();
        
        $table->foreign('product_sheet_operation_id')->references('id')->on('product_operation_lists')->onDelete('cascade');
        $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('created_at');
    });
}
```

---

#### 1.5 Crear ProductionOrderOperation (Nivel 3 - Congelado)

**Archivo:** `app/Models/Expenses/ProductionOrderOperation.php`

```php
<?php

namespace App\Models\Expenses;

use App\Models\ProductSheet\Operation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOrderOperation extends Model
{
    use HasFactory;

    protected $table = 'production_order_operations';

    protected $fillable = [
        'production_order_id',
        'sequence',
        'operation_id',
        'machine_id',
        'quantity',
        'sam_base',
        'machine_supplement_factor',
        'fabric_base_factor',
        'operator_efficiency_factor',
        'sam_total_unitario',
        'sam_total_operacion',
        'sam_total_lote',
        'quantity_progressed',
        'status',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'quantity' => 'integer',
        'sam_base' => 'float',
        'machine_supplement_factor' => 'float',
        'fabric_base_factor' => 'float',
        'operator_efficiency_factor' => 'float',
        'sam_total_unitario' => 'float',
        'sam_total_operacion' => 'float',
        'sam_total_lote' => 'float',
        'quantity_progressed' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function scopeByOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('production_order_id', $orderId)->orderBy('sequence');
    }
}
```

**Migración:** `database/migrations/YYYY_MM_DD_create_production_order_operations_table.php`
```php
public function up(): void
{
    Schema::create('production_order_operations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('production_order_id');
        $table->integer('sequence');
        $table->unsignedBigInteger('operation_id');
        $table->unsignedBigInteger('machine_id')->nullable();
        $table->integer('quantity')->default(1);
        $table->decimal('sam_base', 8, 4);
        $table->decimal('machine_supplement_factor', 8, 4);
        $table->decimal('fabric_base_factor', 8, 4);
        $table->decimal('operator_efficiency_factor', 8, 4);
        $table->decimal('sam_total_unitario', 8, 4);
        $table->decimal('sam_total_operacion', 8, 4);
        $table->decimal('sam_total_lote', 10, 4)->comment('sam_total_operacion × cantidad_lote');
        $table->integer('quantity_progressed')->default(0);
        $table->enum('status', ['PENDIENTE', 'EN_PROCESO', 'COMPLETADA'])->default('PENDIENTE');
        $table->timestamps();
        
        $table->foreign('production_order_id')->references('id')->on('production_orders')->onDelete('cascade');
        $table->foreign('operation_id')->references('id')->on('operations')->onDelete('restrict');
        $table->index(['production_order_id', 'sequence']);
    });
}
```

---

### Fase 2: Services (Lógica de Negocio)

#### 2.1 OperationService

**Archivo:** `app/Services/ProductSheet/OperationService.php`

```php
<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\Operation;
use App\Models\ProductSheet\ProductSheet;
use Illuminate\Support\Collection;

class OperationService
{
    /**
     * Crear o actualizar operación a partir de ProductSheetOperation
     * Genera código automático: OP-<SECCIÓN>-<SECUENCIAL>
     */
    public function createFromProductSheetOperation(array $data, ProductSheet $sheet): Operation
    {
        $section = $data['section'] ?? 'GEN';  // Obtener sección
        $nextSequence = Operation::where('codigo', 'LIKE', "OP-{$section}-%")
            ->count() + 1;
        
        $operation = Operation::create([
            'codigo' => "OP-{$section}-" . str_pad($nextSequence, 3, '0', STR_PAD_LEFT),
            'action' => $data['action'] ?? '',
            'garment_part' => $data['garment_part'] ?? '',
            'section_id' => $data['section_id'] ?? null,
            'location' => $data['location'] ?? '',
            'measure' => $data['measure'] ?? null,
            'description' => $data['description'] ?? '',
            'sam_base' => $data['sam_base'] ?? 0,
            'product_sheet_id_reference' => $sheet->id,
            'machine_id_reference' => $data['machine_id_reference'] ?? null,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return $operation;
    }

    /**
     * Obtener operaciones por sección
     */
    public function getBySection(int $sectionId): Collection
    {
        return Operation::where('section_id', $sectionId)
            ->where('is_active', true)
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Buscar operaciones por nombre (action + garment_part)
     */
    public function search(string $query): Collection
    {
        return Operation::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('codigo', 'LIKE', "%{$query}%")
                  ->orWhereRaw('CONCAT(action, " ", garment_part) LIKE ?', ["%{$query}%"]);
            })
            ->limit(20)
            ->get();
    }
}
```

---

#### 2.2 ProductSheetOperationService (CRÍTICO)

**Archivo:** `app/Services/ProductSheet/ProductSheetOperationService.php`

```php
<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\Operation;
use App\Models\ProductSheet\ProductSheet;
use App\Models\ProductSheet\ProductSheetOperation;
use App\Models\ProductSheet\ProductSheetOperationHistory;
use App\Models\ProductSheet\FabricBase;
use App\Models\ProductSheet\Machine;
use Illuminate\Support\Collection;

class ProductSheetOperationService
{
    /**
     * Crear ProductSheetOperation con snapshot de valores
     * Fase: Usuario asigna operación a ProductSheet con factores
     */
    public function create(int $productSheetId, array $data): ProductSheetOperation
    {
        $operation = Operation::findOrFail($data['operation_id']);
        $machine = Machine::findOrFail($data['machine_id']);
        $fabricBase = FabricBase::findOrFail($data['fabric_base_id']);

        // Crear snapshot con valores ACTUALES (momento de asignación)
        $samBase = $data['sam_base'] ?? $operation->sam_base ?? 0;
        $machineSupplementFactor = $data['machine_supplement_factor'] ?? $machine->supplement_factor ?? 1.0;
        $fabricBaseFactor = $data['fabric_base_factor'] ?? $fabricBase->factor ?? 1.0;
        $operatorEfficiencyFactor = $data['operator_efficiency_factor'] ?? 1.0;
        $quantity = $data['quantity'] ?? 1;

        // Calcular SAM
        $samTotalUnitario = $samBase * $machineSupplementFactor * $fabricBaseFactor * $operatorEfficiencyFactor;
        $samTotalOperacion = $samTotalUnitario * $quantity;

        // Determinar sequence
        $nextSequence = ProductSheetOperation::where('product_sheet_id', $productSheetId)
            ->max('sequence') ?? 0;
        $sequence = $nextSequence + 10;  // Saltos de 10 para inserciones

        $pso = ProductSheetOperation::create([
            'product_sheet_id' => $productSheetId,
            'sequence' => $sequence,
            'operation_id' => $operation->id,
            'machine_id' => $machine->id,
            'fabric_base_id' => $fabricBase->id,
            'quantity' => $quantity,
            'sam_base' => $samBase,
            'machine_supplement_factor' => $machineSupplementFactor,
            'fabric_base_factor' => $fabricBaseFactor,
            'operator_efficiency_factor' => $operatorEfficiencyFactor,
            'sam_total_unitario' => $samTotalUnitario,
            'sam_total_operacion' => $samTotalOperacion,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return $pso;
    }

    /**
     * Actualizar ProductSheetOperation y registrar historial automáticamente
     */
    public function update(ProductSheetOperation $pso, array $data, string $motivo = ''): ProductSheetOperation
    {
        $before = $pso->getAttributes();

        // Permitir edición SOLO de factores (no de sam_base ni cantidades)
        if (isset($data['operator_efficiency_factor'])) {
            $pso->operator_efficiency_factor = $data['operator_efficiency_factor'];
        }
        if (isset($data['machine_supplement_factor'])) {
            $pso->machine_supplement_factor = $data['machine_supplement_factor'];
        }
        if (isset($data['fabric_base_factor'])) {
            $pso->fabric_base_factor = $data['fabric_base_factor'];
        }

        // Recalcular SAM
        $pso->sam_total_unitario = $pso->calculateSamTotalUnitario();
        $pso->sam_total_operacion = $pso->calculateSamTotalOperacion();

        $pso->updated_by = auth()->id();
        $pso->save();

        // Registrar en historial SOLO si hubo cambios
        $this->recordChange($pso, $before, $motivo);

        return $pso;
    }

    /**
     * Registrar cambios en ProductSheetOperationHistory
     */
    private function recordChange(ProductSheetOperation $pso, array $before, string $motivo = ''): void
    {
        $after = $pso->getAttributes();

        $changes = [];
        $fields = [
            'sam_base', 
            'machine_supplement_factor', 
            'fabric_base_factor', 
            'operator_efficiency_factor',
            'sam_total_unitario',
            'sam_total_operacion'
        ];

        foreach ($fields) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changes[$field] = [
                    'anterior' => $before[$field] ?? null,
                    'nuevo' => $after[$field] ?? null,
                ];
            }
        }

        if (!empty($changes)) {
            ProductSheetOperationHistory::create([
                'product_sheet_operation_id' => $pso->id,
                'sam_base_anterior' => $before['sam_base'] ?? null,
                'sam_base_nuevo' => $after['sam_base'] ?? null,
                'machine_supplement_factor_anterior' => $before['machine_supplement_factor'] ?? null,
                'machine_supplement_factor_nuevo' => $after['machine_supplement_factor'] ?? null,
                'fabric_base_factor_anterior' => $before['fabric_base_factor'] ?? null,
                'fabric_base_factor_nuevo' => $after['fabric_base_factor'] ?? null,
                'operator_efficiency_factor_anterior' => $before['operator_efficiency_factor'] ?? null,
                'operator_efficiency_factor_nuevo' => $after['operator_efficiency_factor'] ?? null,
                'sam_total_unitario_anterior' => $before['sam_total_unitario'] ?? null,
                'sam_total_unitario_nuevo' => $after['sam_total_unitario'] ?? null,
                'sam_total_operacion_anterior' => $before['sam_total_operacion'] ?? null,
                'sam_total_operacion_nuevo' => $after['sam_total_operacion'] ?? null,
                'motivo' => $motivo,
                'usuario_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Obtener todas las operaciones de un ProductSheet (ruta completa)
     */
    public function getByProductSheet(int $productSheetId): Collection
    {
        return ProductSheetOperation::bySheet($productSheetId)->get();
    }

    /**
     * Calcular SAM total del ProductSheet
     */
    public function getTotalSamByProductSheet(int $productSheetId): float
    {
        return ProductSheetOperation::where('product_sheet_id', $productSheetId)
            ->where('is_active', true)
            ->sum('sam_total_operacion');
    }

    /**
     * Reordenar operaciones (cambiar sequence)
     */
    public function reorder(int $productSheetId, array $order): void
    {
        // $order = [['id' => 1, 'sequence' => 10], ['id' => 2, 'sequence' => 20], ...]
        foreach ($order as $item) {
            ProductSheetOperation::where('id', $item['id'])
                ->where('product_sheet_id', $productSheetId)
                ->update(['sequence' => $item['sequence']]);
        }
    }

    /**
     * Eliminar ProductSheetOperation
     */
    public function delete(ProductSheetOperation $pso): void
    {
        $pso->delete();
    }
}
```

---

#### 2.3 ProductionOrderService

**Archivo:** `app/Services/Expenses/ProductionOrderService.php`

```php
<?php

namespace App\Services\Expenses;

use App\Models\Expenses\ProductionOrder;
use App\Models\Expenses\ProductionOrderOperation;
use App\Models\ProductSheet\ProductSheet;
use App\Models\ProductSheet\ProductSheetOperation;
use App\Services\ProductSheet\ProductSheetOperationService;

class ProductionOrderService
{
    protected ProductSheetOperationService $psService;

    public function __construct(ProductSheetOperationService $psService)
    {
        $this->psService = $psService;
    }

    /**
     * Crear ProductionOrder con copia congelada de ProductSheetOperation
     * FASE 3: Copiar snapshot de ruta a orden de producción
     */
    public function create(int $productSheetId, int $quantityLote, array $data = []): ProductionOrder
    {
        $productSheet = ProductSheet::findOrFail($productSheetId);

        // Crear orden de producción
        $order = ProductionOrder::create([
            'product_sheet_id' => $productSheetId,
            'quantity_lote' => $quantityLote,
            'status' => 'CREADA',
            'created_by' => auth()->id(),
        ]);

        // Obtener ruta completa del ProductSheet
        $operations = $this->psService->getByProductSheet($productSheetId);

        // Copiar CADA ProductSheetOperation a ProductionOrderOperation (snapshot)
        foreach ($operations as $psOp) {
            ProductionOrderOperation::create([
                'production_order_id' => $order->id,
                'sequence' => $psOp->sequence,
                'operation_id' => $psOp->operation_id,
                'machine_id' => $psOp->machine_id,
                'quantity' => $psOp->quantity,
                // SNAPSHOT CONGELADO (valores en momento de crear orden)
                'sam_base' => $psOp->sam_base,
                'machine_supplement_factor' => $psOp->machine_supplement_factor,
                'fabric_base_factor' => $psOp->fabric_base_factor,
                'operator_efficiency_factor' => $psOp->operator_efficiency_factor,
                'sam_total_unitario' => $psOp->sam_total_unitario,
                'sam_total_operacion' => $psOp->sam_total_operacion,
                'sam_total_lote' => $psOp->sam_total_operacion * $quantityLote,
                'status' => 'PENDIENTE',
            ]);
        }

        return $order;
    }

    /**
     * Obtener operaciones de una orden
     */
    public function getOperations(int $orderId): Collection
    {
        return ProductionOrderOperation::byOrder($orderId)->get();
    }

    /**
     * Calcular SAM total de la orden
     */
    public function getTotalSamLote(int $orderId): float
    {
        return ProductionOrderOperation::where('production_order_id', $orderId)
            ->sum('sam_total_lote');
    }
}
```

---

### Fase 3: Controllers (Actualizar y crear)

#### 3.1 ProductSheetOperationController (Renombrado de ProductOperationListController)

**Archivo:** `app/Http/Controllers/ProductSheet/ProductSheetOperationController.php`

```php
<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductSheet\ProductSheetOperationStoreRequest;
use App\Http\Requests\ProductSheet\ProductSheetOperationUpdateRequest;
use App\Models\ProductSheet\ProductSheet;
use App\Models\ProductSheet\ProductSheetOperation;
use App\Models\ProductSheet\ProductSheetOperationHistory;
use App\Services\ProductSheet\ProductSheetOperationService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductSheetOperationController extends Controller
{
    protected ProductSheetOperationService $service;

    public function __construct(ProductSheetOperationService $service)
    {
        $this->service = $service;
    }

    /**
     * Mostrar listado operacional de un ProductSheet
     */
    public function indexView(ProductSheet $productSheet): Response
    {
        return Inertia::render('ProductSheet/Operations/Index', [
            'productSheet' => $productSheet,
            'operations' => $this->service->getByProductSheet($productSheet->id),
            'totalSam' => $this->service->getTotalSamByProductSheet($productSheet->id),
        ]);
    }

    /**
     * Obtener operaciones en JSON (para DataTable)
     */
    public function index(ProductSheet $productSheet): JsonResponse
    {
        $operations = $this->service->getByProductSheet($productSheet->id);
        
        return response()->json([
            'data' => $operations,
            'total_sam' => $this->service->getTotalSamByProductSheet($productSheet->id),
        ]);
    }

    /**
     * Crear ProductSheetOperation
     */
    public function store(ProductSheet $productSheet, ProductSheetOperationStoreRequest $request): JsonResponse
    {
        $pso = $this->service->create($productSheet->id, $request->validated());

        return response()->json($pso, 201);
    }

    /**
     * Actualizar ProductSheetOperation
     */
    public function update(ProductSheetOperation $pso, ProductSheetOperationUpdateRequest $request): JsonResponse
    {
        $this->service->update($pso, $request->validated(), $request->input('motivo', ''));

        return response()->json($pso);
    }

    /**
     * Eliminar ProductSheetOperation
     */
    public function destroy(ProductSheetOperation $pso): JsonResponse
    {
        $this->service->delete($pso);

        return response()->json(['message' => 'Eliminada']);
    }

    /**
     * Reordenar operaciones
     */
    public function reorder(ProductSheet $productSheet, Request $request): JsonResponse
    {
        $this->service->reorder($productSheet->id, $request->input('order'));

        return response()->json(['message' => 'Reordenadas']);
    }

    /**
     * Obtener historial de cambios
     */
    public function history(ProductSheetOperation $pso): JsonResponse
    {
        $history = ProductSheetOperationHistory::where('product_sheet_operation_id', $pso->id)
            ->with('usuario')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($history);
    }
}
```

---

### Fase 4: FormRequests, Resources y Testing

#### 4.1 ProductSheetOperationStoreRequest

**Archivo:** `app/Http/Requests/ProductSheet/ProductSheetOperationStoreRequest.php`

```php
<?php

namespace App\Http\Requests\ProductSheet;

use Illuminate\Foundation\Http\FormRequest;

class ProductSheetOperationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('crear operación en ruta');
    }

    public function rules(): array
    {
        return [
            'operation_id' => 'required|exists:operations,id',
            'machine_id' => 'required|exists:machines,id',
            'fabric_base_id' => 'required|exists:fabric_bases,id',
            'quantity' => 'nullable|integer|min:1',
            'sam_base' => 'nullable|numeric|min:0',
            'machine_supplement_factor' => 'nullable|numeric|min:0.1|max:2.0',
            'fabric_base_factor' => 'nullable|numeric|min:0.1|max:2.0',
            'operator_efficiency_factor' => 'nullable|numeric|min:0.1|max:2.0',
        ];
    }
}
```

---

#### 4.2 Tests (Pest)

**Archivo:** `tests/Feature/ProductSheetOperationTest.php`

```php
<?php

test('crear product sheet operation con snapshot correcto', function () {
    $sheet = ProductSheet::factory()->create();
    $operation = Operation::factory()->create(['sam_base' => 0.25]);
    $machine = Machine::factory()->create(['supplement_factor' => 1.08]);
    $fabric = FabricBase::factory()->create(['factor' => 1.05]);

    $response = $this->postJson(route('diseno.productos.operaciones.store', $sheet), [
        'operation_id' => $operation->id,
        'machine_id' => $machine->id,
        'fabric_base_id' => $fabric->id,
        'operator_efficiency_factor' => 1.25,
    ]);

    $response->assertStatus(201);
    
    $pso = ProductSheetOperation::first();
    expect($pso->sam_total_unitario)->toEqual(0.354);  // 0.25 × 1.08 × 1.05 × 1.25
});

test('editar factor operario registra historial', function () {
    $pso = ProductSheetOperation::factory()->create([
        'operator_efficiency_factor' => 1.25,
        'sam_total_unitario' => 0.354,
    ]);

    $this->patchJson(route('diseno.productos.operaciones.update', $pso), [
        'operator_efficiency_factor' => 1.35,
        'motivo' => 'Operario más experimentado'
    ]);

    $history = ProductSheetOperationHistory::first();
    expect($history->operator_efficiency_factor_anterior)->toEqual(1.25);
    expect($history->operator_efficiency_factor_nuevo)->toEqual(1.35);
    expect($history->motivo)->toContain('experimentado');
});

test('crear orden copia snapshot congelado', function () {
    $sheet = ProductSheet::factory()->create();
    $pso = ProductSheetOperation::factory()->create([
        'product_sheet_id' => $sheet->id,
        'sam_total_unitario' => 0.354,
        'operator_efficiency_factor' => 1.25,
    ]);

    $this->actingAs($this->user)->postJson(route('diseno.ordenes-produccion.store'), [
        'product_sheet_id' => $sheet->id,
        'quantity_lote' => 500,
    ]);

    $orderOp = ProductionOrderOperation::first();
    expect($orderOp->sam_total_unitario)->toEqual(0.354);  // Congelado
    expect($orderOp->sam_total_lote)->toEqual(177);  // 0.354 × 500
});
```

---

## 📋 Checklist de Implementación

```
FASE 1: MIGRACIONES Y MODELOS
─────────────────────────────
[ ] Actualizar Operation (agregar campos)
[ ] Crear migración para Operation
[ ] Agregar supplement_factor a Machine
[ ] Crear migración para Machine
[ ] Renombrar ProductOperationList a ProductSheetOperation
[ ] Crear migración para ProductSheetOperation
[ ] Crear ProductSheetOperationHistory modelo
[ ] Crear migración para ProductSheetOperationHistory
[ ] Crear ProductionOrderOperation modelo
[ ] Crear migración para ProductionOrderOperation

FASE 2: SERVICES
────────────────
[ ] Crear OperationService
[ ] Crear ProductSheetOperationService (CRÍTICO)
[ ] Crear ProductionOrderService
[ ] Implementar métodos de cálculo
[ ] Implementar methods de auditoría

FASE 3: CONTROLLERS Y REQUESTS
──────────────────────────────
[ ] Actualizar ProductOperationListController (o renombrar)
[ ] Crear ProductSheetOperationController
[ ] Crear ProductionOrderController
[ ] Crear FormRequests
[ ] Crear Resources (JSON)

FASE 4: TESTING Y DOCUMENTACIÓN
────────────────────────────────
[ ] Tests para crear ProductSheetOperation
[ ] Tests para editar con historial
[ ] Tests para crear ProductionOrder (snapshot)
[ ] Tests para reordenar operaciones
[ ] Documentación API
[ ] Manual de usuario

FASE 5: INTERFACES SHADCN/VUE
─────────────────────────────
[ ] Index.vue (lista de listados operacionales)
[ ] ListView.vue (tabla de operaciones)
[ ] OperationFormDialog.vue
[ ] HistoryDialog.vue
[ ] SamSummaryCard.vue
[ ] Tipos TypeScript e integración con endpoints
```

---

## 🚀 Ejecución

### 1. Ejecutar migraciones
```bash
php artisan migrate
```

### 2. Registrar permisos
```bash
php artisan tinker
>>> Permission::create(['name' => 'crear operación en ruta']);
>>> Permission::create(['name' => 'editar operación en ruta']);
>>> Permission::create(['name' => 'eliminar operación en ruta']);
>>> Permission::create(['name' => 'reordenar operaciones ruta']);
>>> Permission::create(['name' => 'ver historial ruta']);
```

### 3. Tests
```bash
./vendor/bin/pest tests/Feature/ProductSheetOperationTest.php
```

### 4. Verificar en navegador
```
http://localhost:8000/diseno/listados-operacionales
```

---

### Fase 5: Interfaces Frontend (shadcn/vue + Vue 3 + TypeScript)

Objetivo: Diseñar e implementar las vistas del módulo Listados Operacionales usando **shadcn/vue** como librería de componentes, siguiendo los patrones del proyecto.

#### 5.1 Estructura de archivos

```
resources/js/
├── pages/
│   └── ProductSheet/
│       └── Operations/
│           ├── Index.vue              # Lista de listados operacionales (por ProductSheet)
│           ├── ListView.vue           # Vista detalle de un listado (tabla de operaciones)
│           ├── OperationFormDialog.vue  # Crear/editar operación en ruta
│           ├── OperationRowEditable.vue # Fila editable de operación (inline)
│           ├── HistoryDialog.vue      # Historial de cambios de una operación
│           └── SamSummaryCard.vue     # Tarjeta resumen SAM total
├── components/
│   └── ProductSheet/
│       └── OperationsTable.vue        # Tabla reutilizable con DataTable shadcn
```

#### 5.2 Componentes shadcn/vue a utilizar

| Componente | Uso |
|------------|-----|
| **Table** | Tabla de operaciones (Table, TableHeader, TableBody, TableRow, TableCell, TableHead) |
| **DataTable** | Si existe en el proyecto, para ordenamiento/filtrado/paginación |
| **Dialog** | Modales: crear/editar operación, historial, confirmaciones |
| **Card** | Resumen SAM total, cards de ProductSheet |
| **Button** | Acciones primarias y secundarias |
| **Input** | Campos numéricos (SAM, factores) |
| **Select** | Operación, máquina, base de tela, sección |
| **Badge** | Estados (activo, pendiente, completada) |
| **Tabs** | Pestañas: Ruta actual / Historial |
| **ScrollArea** | Tabla con 50–120 filas |
| **Skeleton** | Carga inicial |
| **AlertDialog** | Confirmar eliminar operación |
| **Tooltip** | Ayuda en factores (factor tela, factor operario) |
| **Form** (FormField, FormItem, FormLabel, FormControl) | Formularios con validación |

#### 5.3 Pantallas y wireframes

##### Pantalla 1: Index (Lista de listados operacionales)

- **Ruta:** `/produccion/listados-operacionales`
- **Layout:** AppLayout con breadcrumb: Sabana de Productos > Listados operacionales
- **Contenido:**
  - **FilterBar** (shadcn Input + Select): ProductSheet, búsqueda
  - **Card** por cada ProductSheet con listado: referencia, tipo, SAM total, nº operaciones
  - Botón **"Ver / Editar"** (Button) → ListView
  - Botón **"Nuevo listado"** (Button) → abre OperationFormDialog para crear
- **Componentes:** Card, Button, Badge, Input, Select, FilterBar

##### Pantalla 2: ListView (Vista detalle de un listado)

- **Ruta:** `/produccion/listados-operacionales/{productSheet}` (o modal/sheet)
- **Contenido:**
  - **SamSummaryCard:** SAM total del ProductSheet (destacado)
  - **Table** con columnas: Sec, Operación, Máquina, Base tela, Cant, SAM base, Suplemento, Factor tela, Factor operario, SAM unitario, SAM operación, Acciones
  - **ScrollArea** alrededor de la tabla (50–120 filas)
  - Por fila: Edit (Pencil), History (History), Delete (Trash)
  - Botón **"Agregar operación"** → OperationFormDialog
  - Botón **"Reordenar"** (drag-and-drop con GripVertical)
- **Componentes:** Table, ScrollArea, Card, Button, Badge, Tooltip

##### Pantalla 3: OperationFormDialog (Crear/editar operación en ruta)

- **Trigger:** Desde Index o ListView
- **Contenido:**
  - **Dialog** (shadcn) con Form
  - Campos: Operación (Select), Máquina (Select), Base tela (Select), Cantidad (Input número), SAM base (Input), Factor operario (Input)
  - SAM unitario y SAM operación: **solo lectura** (calculados)
  - Botones: Guardar, Cancelar
- **Componentes:** Dialog, Form (FormField, FormItem, FormLabel, FormControl), Input, Select, Button

##### Pantalla 4: OperationRowEditable (Edición inline)

- **Alternativa al Dialog:** edición directa en la fila de la tabla
- **Campos editables:** Factor operario, Factor tela, Suplemento máquina
- **Comportamiento:** al cambiar, recalcular SAM unitario/operación y enviar PATCH
- **Componentes:** Input (inline), Button (Check/Cancel)

##### Pantalla 5: HistoryDialog (Historial de cambios)

- **Trigger:** Botón History en cada fila
- **Contenido:**
  - **Dialog** con lista de cambios (sam_base, factores, motivo, usuario, fecha)
  - **Table** o lista con: Fecha, Usuario, Campo, Antes, Después, Motivo
- **Componentes:** Dialog, Table, Badge (por tipo de cambio)

##### Pantalla 6: SamSummaryCard (Resumen SAM)

- **Contenido:** Card con SAM total del ProductSheet, opcionalmente por sección
- **Estilo:** Destacado, tipografía grande para el total
- **Componentes:** Card, CardHeader, CardContent

#### 5.4 Especificaciones de componentes shadcn

```vue
<!-- Ejemplo: SamSummaryCard.vue -->
<template>
  <Card>
    <CardHeader>
      <CardTitle>SAM total</CardTitle>
    </CardHeader>
    <CardContent>
      <p class="text-3xl font-bold">{{ formatSam(totalSam) }} min</p>
    </CardContent>
  </Card>
</template>
```

```vue
<!-- Ejemplo: Tabla de operaciones con shadcn Table -->
<template>
  <ScrollArea class="h-[60vh]">
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Sec</TableHead>
          <TableHead>Operación</TableHead>
          <TableHead>Máquina</TableHead>
          <TableHead>Base tela</TableHead>
          <TableHead>Cant</TableHead>
          <TableHead>SAM base</TableHead>
          <TableHead>Factor op.</TableHead>
          <TableHead>SAM op.</TableHead>
          <TableHead></TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow v-for="op in operations" :key="op.id">
          <TableCell>{{ op.sequence }}</TableCell>
          <TableCell>{{ op.operation?.name }}</TableCell>
          <!-- ... -->
          <TableCell>
            <Button variant="ghost" size="icon" @click="edit(op)">
              <Pencil class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" @click="showHistory(op)">
              <History class="h-4 w-4" />
            </Button>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </ScrollArea>
</template>
```

#### 5.5 Checklist de implementación Frontend

```
FASE 5: INTERFACES SHADCN/VUE
─────────────────────────────
[ ] Crear ProductSheet/Operations/Index.vue
[ ] Crear ProductSheet/Operations/ListView.vue
[ ] Crear ProductSheet/Operations/OperationFormDialog.vue
[ ] Crear ProductSheet/Operations/OperationRowEditable.vue (opcional)
[ ] Crear ProductSheet/Operations/HistoryDialog.vue
[ ] Crear ProductSheet/Operations/SamSummaryCard.vue
[ ] Actualizar rutas Inertia para nuevas vistas
[ ] Tipos TypeScript para ProductSheetOperation, History
[ ] Integrar con endpoints /data, store, update, destroy, history
[ ] Validación de formularios (vee-validate o similar)
[ ] Estados de carga (Skeleton) y errores (Alert)
```

#### 5.6 Convenciones de estilo

- Usar `cn()` para combinar clases (utilidad del proyecto)
- Iconos: **Lucide** (Pencil, History, Trash, Plus, GripVertical)
- Colores: variantes de Tailwind y tokens de shadcn
- Números: formato con 2–4 decimales para SAM y factores
- Español: etiquetas, mensajes y validaciones en español

---

#### PRÓXIMOS PASOS DESPUÉS DE ESTO:

1. **Agregar ProductSheetVersion** (Fase 2 del análisis)
   - Para manejar versionado automático de rutas
   - Crítico antes de producción

2. **Implementar Fase 5 (Frontend shadcn/vue)**
   - Seguir la sección **Fase 5: Interfaces Frontend** de este documento

3. **Reportes y Analytics**
   - SAM total por sección
   - Comparativa antes/después cambios
   - Análisis de costos

---

**FIN DE DOCUMENTO DE IMPLEMENTACIÓN**
