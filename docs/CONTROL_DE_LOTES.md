# Control de lotes

Documentación del módulo Control de lotes para seguimiento de órdenes de producción por proceso.

## Resumen

Interfaz con tabs por proceso donde se listan las órdenes de producción (Programmer) según su estado actual. Cada orden puede cambiar de proceso mediante un diálogo de confirmación.

## Requerimientos capturados

| # | Requerimiento | Decisión |
|---|---------------|----------|
| 1 | Determinar proceso actual de cada orden | Campo `current_process` en `programmers` |
| 2 | Lista de procesos para tabs | I&D, Logística, Desarrollo de Muestras, Muestras, Costos, Confección, Estampando, Bordado, Sublimado, Corte, Tejido, Lavandería, Bodega |
| 3 | Órdenes sin proceso asignado | Asignar "I&D" por defecto al crear |
| 4 | Cambio de proceso | Selector + diálogo de confirmación (sin datos adicionales por ahora) |
| 5 | Columnas de la tabla | Referencia, Temporada, Prioridad, Cant. Programadas, Destino, Sección, Grupo, Fecha, Lote |
| 6 | Ubicación en sidebar | Grupo propio "Control de lotes" |
| 7 | Carga de datos | Todo de una vez, filtrado en frontend |
| 8 | Colores de tabs | Paleta suave asignada automáticamente |

## Archivos creados o modificados

### Backend

| Archivo | Acción |
|---------|--------|
| `config/product-sheet.php` | **Creado** — Lista `batch_control_processes` |
| `database/migrations/2026_02_24_000001_add_current_process_to_programmers_table.php` | **Creado** — Campo `current_process` |
| `database/migrations/2026_02_24_000002_set_default_current_process_on_programmers.php` | **Creado** — Migración de datos existentes |
| `app/Http/Controllers/ProductSheet/BatchControlController.php` | **Creado** — index, getData, updateProcess |
| `app/Http/Requests/ProductSheet/UpdateProgrammerProcessRequest.php` | **Creado** — Validación de current_process |
| `app/Models/ProductSheet/Programmer.php` | **Modificado** — `current_process` en fillable |
| `app/Services/ProductSheet/ProgrammerService.php` | **Modificado** — `defaultCurrentProcess()`, asignación al crear |
| `app/Http/Controllers/ProductSheet/ProgrammerController.php` | **Modificado** — `current_process` en store manual |
| `routes/web.php` | **Modificado** — Rutas control-de-lotes |

### Frontend

| Archivo | Acción |
|---------|--------|
| `resources/js/pages/BatchControl/Index.vue` | **Creado** — Página con tabs y tabla |
| `resources/js/components/AppSidebar.vue` | **Modificado** — Sección "Control de lotes" |

## Rutas

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/control-de-lotes` | `control-de-lotes.index` | Página Inertia |
| GET | `/control-de-lotes/data` | `control-de-lotes.data` | JSON con todas las órdenes |
| PATCH | `/control-de-lotes/{programmer}/proceso` | `control-de-lotes.update-process` | Actualizar current_process |

**Payload PATCH:** `{ "current_process": "Corte" }`

## Estructura de datos

### Respuesta GET /control-de-lotes/data

```json
{
  "data": [
    {
      "id": 1,
      "op_code": "MU-0001",
      "current_process": "I&D",
      "referencia": "MUESTRA001",
      "temporada": "ENERO",
      "prioridad": "A",
      "cant_programadas": 100,
      "destino": "OLAMTEX",
      "seccion": "Sección A",
      "grupo": "Grupo 1",
      "fecha": "2026-03-15"
    }
  ]
}
```

### Configuración (config/product-sheet.php)

```php
'batch_control_processes' => [
    'I&D',
    'Logística',
    'Desarrollo de Muestras',
    'Muestras',
    'Costos',
    'Confección',
    'Estampando',
    'Bordado',
    'Sublimado',
    'Corte',
    'Tejido',
    'Lavandería',
    'Bodega',
],
```

## Pendientes / mejoras futuras

- [ ] **Permisos:** Definir permiso(s) para acceder al módulo (ej. `ver control de lotes`, `editar proceso orden`)
- [ ] **Datos por cambio de estado:** Si se requiere capturar información adicional al cambiar proceso (fecha, responsable, observaciones)
- [ ] **Paginación:** Si el volumen de órdenes crece, considerar paginación por tab
- [ ] **Filtros:** Búsqueda por referencia, temporada, destino dentro de cada tab
- [ ] **Colores personalizables:** Permitir configurar colores por proceso en parámetros
- [ ] **Historial de cambios:** Registrar quién y cuándo cambió el proceso de cada orden
- [ ] **Validación de flujo:** Restringir cambios a procesos "siguientes" según flujo definido

## Relación con otros módulos

- **Programmer** (órdenes de producción): modelo principal, tiene `current_process`
- **ProductSheet** (sábana de productos): aporta referencia, sección, grupo
- **SettingService:** catálogos destino, sección, grupo
- **ProductionOrderController:** órdenes de producción (vista detalle)

## Notas técnicas

- El campo `current_process` es `string(80)` nullable; la migración de datos asigna "I&D" a registros existentes con null
- Las órdenes nuevas (auto o manual) reciben `current_process = 'I&D'` por defecto
- Los valores de `current_process` deben coincidir exactamente con los de `config/product-sheet.php`
