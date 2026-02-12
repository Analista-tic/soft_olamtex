# Pruebas de funcionamiento: Módulo Listados Operacionales

## Inicio rápido

1. Iniciar el servidor: `php artisan serve`
2. Abrir: **http://127.0.0.1:8000/login**
3. Iniciar sesión: `admin@gmail.com` / `12345`
4. Ir a: **http://127.0.0.1:8000/produccion/listados-operacionales**

---

## Cómo acceder al módulo

### 1. Requisitos previos
- Usuario autenticado en el sistema
- Permisos necesarios: `ver listados operacionales` o `ver sabana productos`
- Para crear/editar/eliminar: `crear listados operacionales`, `editar listados operacionales`, `eliminar listados operacionales`

### 2. Ruta de acceso directa
```
http://localhost:8000/produccion/listados-operacionales
```

### 3. Acceso desde el menú de configuración
1. Ir a **Configuración** (o página de inicio de la app)
2. Buscar la sección **Producción**
3. Clic en **Listado operacional**

### 4. Usuarios de prueba (según RolesAndPermissionsSeeder)
| Usuario        | Email             | Permisos del módulo      |
|----------------|-------------------|--------------------------|
| Admin          | admin@gmail.com   | Ver, crear, editar, eliminar |
| General        | general@gmail.com | Solo ver                 |

---

## Escenarios de prueba

### Prueba 1: Cargar la vista principal
1. Iniciar sesión con `admin@gmail.com` / `12345`
2. Navegar a `/produccion/listados-operacionales`
3. **Esperado:** Se muestra la página con título "Listados operacionales", FilterBar (Buscar, Lote), botón "Nueva operación", Cards de resumen (si hay datos) y tabla de lotes.

### Prueba 2: Crear nueva operación (lote con operaciones)
1. Clic en **Nueva operación**
2. Seleccionar un **Lote** del listado
3. Agregar filas con: Sección, Operación, Máquina, Tiempo óptimo
4. Clic en **Guardar**
5. **Esperado:** Mensaje de éxito, el lote aparece en la tabla principal con SAM y costo calculados.

### Prueba 3: Ver detalle (ListView)
1. En la tabla, clic en el icono **Ojo** (Ver) de un lote
2. **Esperado:** Se abre un dialog con la tabla de operaciones del lote, SAM total, costos por sección (Confección, Presentación) y botón de historial por operación.

### Prueba 4: Historial de cambios
1. Con el dialog de detalle abierto, clic en el icono **Historial** (History) de cualquier operación
2. **Esperado:** Se abre un segundo dialog con la tabla de cambios (fecha, usuario, campo, antes, después, motivo).

### Prueba 5: Editar operaciones
1. Clic en el icono **Lápiz** (Editar) de un lote
2. Modificar operaciones y guardar
3. **Esperado:** Operaciones actualizadas; el historial debe registrar los cambios si se modifican factores.

### Prueba 6: Eliminar operaciones
1. Clic en el icono **Eliminar** de un lote
2. Confirmar en el dialog
3. **Esperado:** Se eliminan todas las operaciones del lote; el lote deja de aparecer en la lista (o muestra "Aún no se han guardado operaciones" si era el único).

### Prueba 7: Filtros
1. Usar **Buscar operación** (sección, operación o máquina)
2. Usar **Lote** (filtrar por lote específico o "Todos")
3. **Esperado:** La tabla y las cards de resumen se filtran correctamente.

---

## Notas técnicas

- **Endpoints API:**
  - `GET /produccion/listados-operacionales` — Vista Inertia
  - `GET /produccion/listados-operacionales/data?product_sheet_id=N` — Operaciones de un lote
  - `POST /produccion/listados-operacionales` — Crear operación
  - `PUT /produccion/listados-operacionales/{id}` — Actualizar operación
  - `DELETE /produccion/listados-operacionales/{id}` — Eliminar operación
  - `GET /produccion/listados-operacionales/{id}/history` — Historial de una operación

- **Datos necesarios:** El módulo requiere que existan **product_sheets** (lotes), **operation_sections**, **operations** y **machines** en la base de datos. Si no hay lotes, el select estará vacío y no se podrán crear operaciones.
