# Propuesta de mejora: parametrización y catálogos de Producción

> **Versión:** 2.0 — Plan técnico detallado con migración segura  
> **Actualizado:** 2026-04-11  
> **Regla:** nada se rompe en cada paso; cada fase es desplegable independientemente.

---

## PARTE I — DIAGNÓSTICO

### 1. Hallazgos validados en el código actual

#### 1.1 `operation_sections` sí es estructural

Se usa como dato relacional en FK, validaciones `exists`, relaciones Eloquent y cálculos por tipo de sección (`confeccion` / `presentacion`).

**Conclusión:** debe permanecer como tabla relacional, con seeder fijo.

#### 1.2 Ya existe un módulo genérico para parámetros

El flujo actual es:

```
settings (tabla) ← SettingService.getCatalogOptions(key)
                 ← ParameterController (CRUD genérico en /parametros)
                 ← ProductSheetController.gatherCatalogs() → props Inertia
                 ← Creación inline desde dialogs via addCatalogOption()
```

Archivos clave:

- Backend: `ParameterController`, `ParameterService`, `SettingService`
- Frontend: `MasterData/Parameters/Index.vue`, `ProductSheetDialog.vue`
- Rutas: `/parametros` (CRUD), `product-sheets/parameters` (lectura), `product-sheets/parameters/options` (creación inline)

#### 1.3 Hay catálogos de negocio importantes viviendo en `settings`

Se guardan como arrays JSON en `settings` tipo `parameter`:

| Catálogo | Quién lo consume | Tipo de uso |
|---|---|---|
| `sections` | ProductSheet, OP, Ficha técnica | ID guardado en `product_sheets.section_id` |
| `groups` | ProductSheet, OP, Ficha técnica | ID guardado en `product_sheets.group_id` |
| `brands` | ProductSheet, OP, Ficha técnica | ID guardado en `product_sheets.brand_id` |
| `worlds` | ProductSheet, OP, Ficha técnica | ID guardado en `product_sheets.world_id` |
| `fabric_bases` | ProductSheet, Listado operacional, SAM | ID + factor afecta cálculo de costo |
| `yarn_bases` | ProductSheet, OP | ID guardado en `product_sheets.yarn_base_id` |
| `destinations` | ProductionOrder | ID guardado en `production_orders.destination_id` |
| `operation_cost` | Listado operacional, Ficha técnica | Valor escalar, afecta costo directo |
| `yarn_cost` | Listado operacional, Ficha técnica | Valor escalar, afecta costo directo |
| `cutting_cost` | Ficha técnica | Valor escalar, afecta costo directo |
| `minute_cost` | Ficha técnica | Valor escalar, afecta costo directo |
| `operation_actions` | Base operaciones, selects | Solo alimenta opciones de formulario |
| `operation_garment_parts` | Base operaciones, selects | Solo alimenta opciones de formulario |
| `operation_types` | Base operaciones, selects | Solo alimenta opciones de formulario |

#### 1.4 Cómo fluye hoy la resolución de catálogos

```
                    ┌─────────────────────────────┐
                    │   settings (tabla MySQL)     │
                    │   kind = 'parameter'         │
                    │   key = 'sections'           │
                    │   value = [{id:1,name:...}]  │
                    └──────────┬──────────────────┘
                               │
                    SettingService.getCatalogOptions('sections')
                               │
              ┌────────────────┼────────────────┐
              │                │                │
   ProductSheetController  ProductionOrder   TechnicalDataSheet
   .gatherCatalogs()       Controller.show() Controller.basicInfo()
              │                │                │
     Inertia::render(      ->keyBy('id')     ->keyBy('id')
       props: {               resolve          resolve
         sections,            names by         names by
         groups, ...          ID lookup        ID lookup
       }
     )
              │
     Frontend: ProductSheetDialog.vue
     - defineProps<{sections: OptionParams[]}>
     - creación inline → POST store-option
     - SettingService.addCatalogOption()
```

**Problema central:** los IDs (1, 2, 3...) son auto-incrementales generados dentro del JSON, no son IDs reales de base de datos. Si alguien edita el parámetro manualmente y reordena, los IDs guardados en `product_sheets` apuntan a datos incorrectos.

---

## PARTE II — REGLA DE DECISIÓN

### Nivel A — Catálogo fijo del sistema
- Dominio cerrado, casi nunca cambia.
- Tabla relacional + seeder. Sin CRUD o solo soporte.
- Ejemplo: `operation_sections`, `months`.

### Nivel B — Catálogo parametrizable liviano
- Solo alimenta selects/filtros.
- Sin FK directa crítica. No afecta trazabilidad.
- Puede vivir en `settings`, administrado desde Parámetros.
- Ejemplo: `thread_brands`, `priorities`, `calibre`.

### Nivel C — Catálogo estructural de negocio
- Su ID se guarda en tablas transaccionales.
- Participa en cálculos, costos, reportes.
- Necesita tabla relacional, modelo Eloquent, CRUD dedicado.
- Ejemplo: `sections`, `brands`, `fabric_bases`, `destinations`.

### Clasificación final

| Catálogo | Nivel | Acción |
|---|---|---|
| `operation_sections` | A | ya resuelto |
| `months` | A | dejar en helper, no migrar |
| `operation_actions` | C | migrado a tabla relacional |
| `operation_garment_parts` | C | migrado a tabla relacional |
| `operation_types` | B | mantener en `settings` |
| `process_types` | B | mantener en `settings` |
| `thread_brands` | B | mantener en `settings` |
| `priorities` | B | mantener en `settings` |
| `calibre` | B | mantener en `settings` |
| `puntada_por_pulgada` | B | mantener en `settings` |
| `sections` | C | migrar a tabla |
| `groups` | C | migrar a tabla |
| `brands` | C | migrar a tabla |
| `worlds` | C | migrar a tabla |
| `fabric_bases` | C | migrar a tabla |
| `yarn_bases` | C | migrar a tabla |
| `destinations` | C | migrar a tabla |
| `operation_cost` | C | migrar a tabla de costos |
| `yarn_cost` | C | migrar a tabla de costos |
| `cutting_cost` | C | migrar a tabla de costos |
| `minute_cost` | C | migrar a tabla de costos |

---

## PARTE III — PLAN TÉCNICO DETALLADO (sin romper nada)

### Principio rector de cada paso

> Cada catálogo se migra con **doble fuente temporal**:
> 1. Se crea la tabla nueva y se llena con los datos que ya existen en `settings`.
> 2. Se crea un servicio "puente" que lee **primero de la tabla nueva** y si no hay datos, **cae a `settings`** (fallback).
> 3. Los controladores consumen el servicio puente en vez de `SettingService` directamente.
> 4. Se valida que todo funciona igual.
> 5. Solo después se elimina la lectura de `settings` y se retira el parámetro viejo.

Esto garantiza que **nunca se rompe funcionalidad existente** en ningún punto intermedio.

---

### FASE 0 — Preparación (no toca funcionalidad)

**Objetivo:** crear la infraestructura base sin cambiar ningún flujo.

#### Paso 0.1 — Crear servicio puente `CatalogService`

Archivo: `app/Services/Common/CatalogService.php`

```php
class CatalogService
{
    public function __construct(
        protected SettingService $settingService,
    ) {}

    /**
     * Obtiene opciones de un catálogo.
     * Prioridad: tabla relacional → settings fallback.
     */
    public function getOptions(string $catalog): Collection
    {
        // Fase 0: solo delega a settings (comportamiento idéntico al actual)
        return $this->settingService->getCatalogOptions($catalog);
    }

    public function getOptionById(string $catalog, int|string $id): ?array
    {
        return $this->settingService->getCatalogOptionById($catalog, $id);
    }

    public function addOption(string $catalog, string $name): array
    {
        return $this->settingService->addCatalogOption($catalog, $name);
    }

    public function getNumeric(string $key, float $default = 0.0): float
    {
        return $this->settingService->getNumericParameter($key, $default);
    }
}
```

**Impacto:** cero. Es un wrapper que se comporta exactamente igual.

#### Paso 0.2 — Reemplazar inyecciones progresivamente

En cada controlador que hoy usa `SettingService->getCatalogOptions()`, cambiar a `CatalogService->getOptions()`:

| Controlador | Método | Cambio |
|---|---|---|
| `ProductSheetController` | `gatherCatalogs()` | `CatalogService->getOptions('sections')` |
| `ProductionOrderController` | `show()` | `CatalogService->getOptions('sections')` |
| `TechnicalDataSheetController` | `basicInfo()` | `CatalogService->getOptions('sections')` |
| `BatchControlController` | `show()` | `CatalogService->getOptions('sections')` |
| `ProductOperationListController` | `indexView()`, `options()` | `CatalogService->getNumeric(...)` |

**Impacto:** cero funcional. Solo cambia la clase inyectada; el resultado es idéntico.

**Validación:** `php artisan test`, verificar que todos los módulos cargan igual.

---

### FASE 1 — Migrar catálogos comerciales (sections, groups, brands, worlds)

**Estos 4 catálogos tienen la misma estructura: `id`, `name`, `is_active`, `timestamps`.**

#### Paso 1.1 — Crear migraciones

```
database/migrations/2026_04_XX_000001_create_product_sections_table.php
database/migrations/2026_04_XX_000002_create_product_groups_table.php
database/migrations/2026_04_XX_000003_create_brands_table.php
database/migrations/2026_04_XX_000004_create_worlds_table.php
```

Estructura idéntica para los 4:

```php
Schema::create('product_sections', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
});
```

**Impacto:** solo crea tablas vacías. Ningún flujo las usa aún.

#### Paso 1.2 — Crear modelos Eloquent

```
app/Models/Commercial/ProductSection.php
app/Models/Commercial/ProductGroup.php
app/Models/Commercial/Brand.php
app/Models/Commercial/World.php
```

Modelo base (igual para los 4):

```php
class ProductSection extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeOrdered($q) { return $q->orderBy('name'); }
}
```

**Impacto:** cero. Los modelos existen pero nadie los consume.

#### Paso 1.3 — Crear seeder de migración de datos

```php
// database/seeders/MigrateCommercialCatalogsSeeder.php
class MigrateCommercialCatalogsSeeder extends Seeder
{
    public function run(): void
    {
        $this->migrateCatalog('sections', ProductSection::class);
        $this->migrateCatalog('groups', ProductGroup::class);
        $this->migrateCatalog('brands', Brand::class);
        $this->migrateCatalog('worlds', World::class);
    }

    private function migrateCatalog(string $settingsKey, string $modelClass): void
    {
        $setting = Setting::parameters()->where('key', $settingsKey)->first();
        if (!$setting || !is_array($setting->value)) return;

        foreach ($setting->value as $item) {
            if (!is_array($item) || empty($item['name'])) continue;

            $modelClass::firstOrCreate(
                ['name' => trim($item['name'])],
                ['is_active' => true]
            );
        }
    }
}
```

**Punto crucial:** este seeder crea registros con IDs auto-incrementales de MySQL.
Los IDs en `product_sheets.section_id` (que venían del JSON) pueden no coincidir.

#### Paso 1.4 — Crear migración de remapeo de IDs

```php
// database/migrations/2026_04_XX_000010_remap_product_sheet_catalog_ids.php
// Dentro de up():
// 1. Leer catálogo viejo de settings → mapear old_id → name
// 2. Leer tabla nueva → mapear name → new_id
// 3. UPDATE product_sheets SET section_id = new_id WHERE section_id = old_id

$this->remapColumn('sections', 'product_sections', 'product_sheets', 'section_id');
$this->remapColumn('groups', 'product_groups', 'product_sheets', 'group_id');
$this->remapColumn('brands', 'brands', 'product_sheets', 'brand_id');
$this->remapColumn('worlds', 'worlds', 'product_sheets', 'world_id');
```

**Impacto:** actualiza IDs en `product_sheets` para que apunten a la tabla nueva.
**Seguridad:** se ejecuta en transacción; si falla, no cambia nada.

#### Paso 1.5 — Activar tabla nueva en `CatalogService`

```php
// En CatalogService->getOptions():
public function getOptions(string $catalog): Collection
{
    // Catálogos ya migrados → leer de tabla
    $model = $this->resolveModel($catalog);
    if ($model) {
        $records = $model::active()->ordered()->get(['id', 'name']);
        if ($records->isNotEmpty()) {
            return $records->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]);
        }
    }

    // Fallback → settings (para catálogos no migrados aún)
    return $this->settingService->getCatalogOptions($catalog);
}

private function resolveModel(string $catalog): ?string
{
    return match ($catalog) {
        'sections' => ProductSection::class,
        'groups'   => ProductGroup::class,
        'brands'   => Brand::class,
        'worlds'   => World::class,
        default    => null,
    };
}
```

**Impacto:** los controladores ya usan `CatalogService` (Fase 0).
Ahora leen de tabla relacional. Si la tabla está vacía, caen a `settings`.
**Nada se rompe** porque los datos son los mismos, solo cambia la fuente.

#### Paso 1.6 — Crear controlador CRUD para los 4 catálogos

```
app/Http/Controllers/Commercial/CatalogController.php
```

Operaciones: `index`, `data`, `store`, `update`, `destroy`, `toggleStatus`.
Rutas: bajo `/configuracion/comercial/...`

#### Paso 1.7 — Crear vista de administración

```
resources/js/pages/Configuration/Commercial/Index.vue
```

Una sola vista con tabs o acordeón para los 4 catálogos.
Misma experiencia que Parámetros pero tipada y controlada.

#### Paso 1.8 — Agregar al sidebar y navegación

En `AppSidebar.vue`, sección Producción o como nueva sección "Comercial":

```typescript
{ name: 'Catálogos Comerciales', href: '/configuracion/comercial' }
```

En `Configuration/Index.vue`, sección "Comercial":

```typescript
{ name: 'Secciones', href: '/configuracion/comercial?tab=secciones' },
{ name: 'Grupos', href: '/configuracion/comercial?tab=grupos' },
{ name: 'Marcas', href: '/configuracion/comercial?tab=marcas' },
{ name: 'Mundos', href: '/configuracion/comercial?tab=mundos' },
```

#### Paso 1.9 — Actualizar creación inline en ProductSheetDialog

Cambiar el `POST store-option` para que cree en la tabla relacional en vez de en `settings`:

```php
// ProductSheetController@storeParameterOption
public function storeParameterOption(Request $request): JsonResponse
{
    $key = $request->input('key');
    $name = $request->input('name');

    // Catálogos migrados → crear en tabla
    $model = $this->catalogService->resolveModel($key);
    if ($model) {
        $record = $model::firstOrCreate(['name' => trim($name)], ['is_active' => true]);
        return response()->json(['id' => $record->id, 'name' => $record->name]);
    }

    // No migrado → settings (fallback)
    $option = $this->settingService->addCatalogOption($key, $name);
    return response()->json($option);
}
```

**Impacto:** la creación inline desde el dialog funciona igual, pero persiste en tabla relacional.

#### Paso 1.10 — Validación completa

1. `php artisan migrate:fresh --seed`
2. Verificar que Sábana de Productos carga catálogos correctamente.
3. Verificar que Órdenes de Producción resuelve nombres.
4. Verificar que Ficha Técnica muestra datos.
5. Verificar que Control de Lotes funciona.
6. Verificar creación inline desde ProductSheetDialog.
7. `php artisan test`

**Solo si todo pasa → continuar a Fase 2.**

---

### FASE 2 — Migrar `fabric_bases` y `yarn_bases`

**Estos son especiales porque `fabric_bases` tiene campo `factor`.**

#### Paso 2.1 — Migración

```php
Schema::create('fabric_bases', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->decimal('factor', 6, 3)->default(1.000)->comment('Factor que afecta SAM');
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
});

Schema::create('yarn_bases', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
});
```

#### Paso 2.2 — Seeder con datos de settings (incluye factor)

```php
private function migrateFabricBases(): void
{
    $setting = Setting::parameters()->where('key', 'fabric_bases')->first();
    if (!$setting || !is_array($setting->value)) return;

    foreach ($setting->value as $item) {
        FabricBase::firstOrCreate(
            ['name' => trim($item['name'])],
            [
                'factor' => (float) ($item['factor'] ?? 1.0),
                'is_active' => true,
            ]
        );
    }
}
```

#### Paso 2.3 — Remapear IDs en `product_sheets`

Mismo patrón que Fase 1.4 para `fabric_base_id` y `yarn_base_id`.

#### Paso 2.4 — Actualizar `CatalogService`

Agregar `fabric_bases` y `yarn_bases` al `resolveModel()`.

Para `getFabricBasesWithFactor()`, crear método dedicado:

```php
public function getFabricBasesWithFactor(): Collection
{
    $records = FabricBase::active()->ordered()->get();
    if ($records->isNotEmpty()) {
        return $records->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'factor' => $r->factor,
        ]);
    }

    // Fallback
    return $this->settingService->getFabricBasesWithFactor();
}
```

#### Paso 2.5 — Actualizar `ProductOperationListService.resolveFabricBaseFactor()`

```php
private function resolveFabricBaseFactor(int|string|null $fabricBaseId): float
{
    if (!$fabricBaseId) return 1.0;

    // Primero tabla relacional
    $record = FabricBase::find($fabricBaseId);
    if ($record) return $record->factor > 0 ? $record->factor : 1.0;

    // Fallback settings
    $option = $this->settingService->getCatalogOptionById('fabric_bases', $fabricBaseId);
    // ...existente...
}
```

#### Paso 2.6 — UI de administración

Agregar tabs "Bases de tela" y "Bases de hilaza" a la vista de Catálogos Comerciales.
`fabric_bases` necesita campo extra `factor` editable.

#### Paso 2.7 — Validación

Verificar específicamente:

- cálculos SAM en listado operacional
- costos en ficha técnica
- creación inline desde ProductSheetDialog

---

### FASE 3 — Migrar `destinations`

#### Paso 3.1 — Migración

```php
Schema::create('destinations', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
});
```

#### Paso 3.2 — Seeder + remapeo de `production_orders.destination_id`

Mismo patrón: leer settings → crear registros → remapear IDs.

#### Paso 3.3 — Actualizar `CatalogService`

Agregar `destinations` al `resolveModel()`.

#### Paso 3.4 — UI

Agregar a `Configuration/Index.vue` sección "Diseño" → "Destinos".
O crear vista `Configuration/Design/Index.vue` con tab Destinos.

---

### FASE 4 — Migrar costos base de producción

#### Paso 4.1 — Migración

```php
Schema::create('production_cost_parameters', function (Blueprint $table) {
    $table->id();
    $table->decimal('operation_cost', 12, 4)->default(406.0);
    $table->decimal('yarn_cost', 12, 4)->default(0);
    $table->decimal('cutting_cost', 12, 4)->default(350.0);
    $table->decimal('minute_cost', 12, 4)->default(120.0);
    $table->date('effective_from')->nullable();
    $table->date('effective_to')->nullable();
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

#### Paso 4.2 — Seeder con datos actuales

```php
ProductionCostParameter::create([
    'operation_cost' => $settingService->getNumericParameter('operation_cost', 406),
    'yarn_cost'      => $settingService->getNumericParameter('yarn_cost', 0),
    'cutting_cost'   => $settingService->getNumericParameter('cutting_cost', 350),
    'minute_cost'    => $settingService->getNumericParameter('minute_cost', 120),
    'is_active'      => true,
]);
```

#### Paso 4.3 — Actualizar `CatalogService.getNumeric()`

```php
public function getNumeric(string $key, float $default = 0.0): float
{
    // Primero tabla relacional
    $current = ProductionCostParameter::where('is_active', true)
        ->orderByDesc('effective_from')
        ->first();

    if ($current) {
        return match ($key) {
            'operation_cost' => (float) $current->operation_cost,
            'yarn_cost'      => (float) $current->yarn_cost,
            'cutting_cost'   => (float) $current->cutting_cost,
            'minute_cost'    => (float) $current->minute_cost,
            default          => $default,
        };
    }

    // Fallback
    return $this->settingService->getNumericParameter($key, $default);
}
```

#### Paso 4.4 — UI

Vista `Configuration/Production/CostParameters.vue` con formulario simple.
Permite editar los 4 valores. Opcional: historial de cambios con vigencia.

---

### FASE 5 — Limpieza final

**Solo ejecutar cuando todas las fases anteriores estén validadas en producción.**

#### Paso 5.1 — Remover fallbacks de `CatalogService`

Eliminar toda lectura a `SettingService` para catálogos migrados.

#### Paso 5.2 — Eliminar parámetros viejos de settings

```php
Setting::parameters()
    ->whereIn('key', [
        'sections', 'groups', 'brands', 'worlds',
        'fabric_bases', 'yarn_bases', 'destinations',
        'operation_cost', 'yarn_cost', 'cutting_cost', 'minute_cost',
    ])
    ->delete();
```

#### Paso 5.3 — Limpiar seeder

Remover esos catálogos de `SettingsFieldParametersSeeder`.

#### Paso 5.4 — Remover aliases de `SettingService`

Limpiar `CATALOG_KEY_ALIASES` para los catálogos migrados.

---

## PARTE IV — DÓNDE SE ADMINISTRA CADA COSA

### Vista unificada de configuración

```
Configuración (/configuracion)
├── Comercial (/configuracion/comercial)          ← NUEVO
│   ├── Secciones (tab)
│   ├── Grupos (tab)
│   ├── Marcas (tab)
│   ├── Mundos (tab)
│   ├── Bases de tela (tab, con campo factor)
│   └── Bases de hilaza (tab)
│
├── Diseño (/configuracion/diseno)                ← NUEVO
│   ├── Destinos (tab)
│   ├── Prioridades (tab, puede seguir en settings)
│   └── Temporadas (tab, futuro)
│
├── Producción (/configuracion/produccion)        ← NUEVO
│   ├── Costos base (formulario con vigencia)
│   └── Secciones operacionales (solo lectura)
│
├── Datos Maestros > Parámetros (/parametros)     ← EXISTENTE
│   ├── (sin acciones/partes de operación)
│   ├── Tipos de prenda
│   ├── Tipos de proceso
│   ├── Marcas de hilo
│   └── (otros catálogos livianos)
│
├── Accesos y Permisos                             ← EXISTENTE
├── Empresa                                        ← EXISTENTE
└── Notificaciones                                 ← EXISTENTE
```

### Experiencia de usuario propuesta

Cada vista de configuración por dominio tiene:

1. **Tabs o acordeón** para los sub-catálogos del dominio.
2. **Tabla editable** con columnas: Nombre, Estado (activo/inactivo), Acciones (editar, desactivar).
3. **Botón "Agregar"** para nuevas opciones.
4. **Sin paginación** (catálogos pequeños, < 100 registros).
5. **Misma experiencia visual** que Parámetros pero con campos tipados.

Para `fabric_bases` se agrega columna "Factor" editable.
Para `Costos base` es un formulario simple con los 4 valores.

---

## PARTE V — CHECKLIST POR FASE

### Fase 0 — Preparación
- [ ] Crear `CatalogService` como wrapper de `SettingService`
- [ ] Reemplazar inyecciones en controladores (uno por uno, con test)
- [ ] Verificar que todo funciona igual
- [ ] Commit

### Fase 1 — Catálogos comerciales
- [ ] Crear 4 migraciones (sections, groups, brands, worlds)
- [ ] Crear 4 modelos Eloquent
- [ ] Crear seeder de migración de datos
- [ ] Crear migración de remapeo de IDs en `product_sheets`
- [ ] Activar tabla nueva en `CatalogService.resolveModel()`
- [ ] Crear `CatalogController` con CRUD
- [ ] Crear rutas en `routes/settings.php`
- [ ] Crear vista `Configuration/Commercial/Index.vue`
- [ ] Actualizar creación inline en `ProductSheetController`
- [ ] Agregar links en sidebar y Configuration/Index.vue
- [ ] Registrar modelos en `AppServiceProvider` para auditoría
- [ ] Validar todos los módulos afectados
- [ ] Commit

### Fase 2 — Bases de tela e hilaza
- [ ] Crear 2 migraciones
- [ ] Crear 2 modelos (FabricBase con campo `factor`)
- [ ] Seeder con factor
- [ ] Remapear `product_sheets.fabric_base_id` y `yarn_base_id`
- [ ] Actualizar `CatalogService` y `resolveFabricBaseFactor()`
- [ ] Agregar tabs a vista Comercial
- [ ] Validar cálculos SAM y costos
- [ ] Commit

### Fase 3 — Destinos
- [ ] Crear migración
- [ ] Crear modelo
- [ ] Seeder + remapeo de `production_orders.destination_id`
- [ ] Actualizar `CatalogService`
- [ ] Crear vista Diseño
- [ ] Validar OP y ficha técnica
- [ ] Commit

### Fase 4 — Costos base
- [ ] Crear migración con vigencia
- [ ] Crear modelo `ProductionCostParameter`
- [ ] Seeder con valores actuales
- [ ] Actualizar `CatalogService.getNumeric()`
- [ ] Crear vista Producción > Costos
- [ ] Validar listado operacional y ficha técnica
- [ ] Commit

### Fase 5 — Limpieza
- [ ] Remover fallbacks de `CatalogService`
- [ ] Eliminar parámetros viejos de `settings`
- [ ] Limpiar seeder
- [ ] Limpiar aliases de `SettingService`
- [ ] Test completo
- [ ] Commit

---

## PARTE VI — CRITERIO RECTOR

Si un dato:

- se guarda por ID en tablas de negocio,
- afecta costos, tiempos, reportes o filtros críticos,
- o gobierna reglas funcionales,

→ **tabla relacional propia.**

Si solo llena selects auxiliares y no compromete trazabilidad → **settings** está bien.

Si es cerrado e inmutable → **seeder fijo**, sin módulo CRUD.

---

## PARTE VII — RIESGOS Y MITIGACIÓN

| Riesgo | Mitigación |
|---|---|
| IDs viejos (JSON) no coinciden con IDs nuevos (tabla) | Migración de remapeo ejecutada en transacción |
| Módulo funcional se rompe al cambiar fuente | Fallback en CatalogService lee settings si tabla vacía |
| Creación inline desde dialog deja de funcionar | Se actualiza `storeParameterOption` con detección de catálogo migrado |
| Seeder falla con datos existentes | Usa `firstOrCreate` para idempotencia |
| Usuarios editan parámetro viejo en `/parametros` después de migrar | Fase 5 elimina registros; durante transición, advertir en UI |
| Factor de fabric_bases se pierde en migración | Seeder lee `item['factor']` explícitamente |

---

## PARTE VIII — ORDEN DE EJECUCIÓN RECOMENDADO

```
Semana 1: Fase 0 (CatalogService wrapper)
Semana 2: Fase 1 (sections, groups, brands, worlds + UI)
Semana 3: Fase 2 (fabric_bases, yarn_bases)
Semana 4: Fase 3 (destinations)
Semana 5: Fase 4 (costos base)
Semana 6: Fase 5 (limpieza)
```

Cada semana termina con commit independiente y validación completa.
Si hay urgencias, se puede pausar en cualquier fase sin romper nada gracias al fallback.
