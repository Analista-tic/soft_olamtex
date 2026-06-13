# Módulo de Producción

**Última actualización:** 2026-06-12
**Propósito:** Explicar qué hace el módulo de producción, cómo funciona, qué datos maneja y cómo se conecta con el resto del sistema.

---

## 1. ¿Qué hace este módulo?

El módulo de Producción es el **corazón del sistema**. Permite gestionar todo el ciclo de vida de una prenda de ropa, desde que se diseña hasta que llega al cliente.

En términos simples:
1. Se crea el **diseño del producto** (sábana de producto).
2. Se genera una **orden de producción** para fabricar unidades de ese diseño.
3. Se define la **ficha técnica**: cómo se hace, con qué materiales y cuánto cuesta.
4. Se hace seguimiento de la orden a través de los diferentes **procesos de fabricación**.
5. Al final, se registra la **entrega** y **recepción en bodega**.

---

## 2. ¿Quién usa este módulo?

| Rol | ¿Para qué lo usa? |
|-----|-------------------|
| Diseñador / Jefe de producción | Crear sábanas de producto y órdenes de producción |
| Programador de producción | Asignar fechas, prioridades y hacer seguimiento |
| Técnico / Analista | Completar la ficha técnica (materiales, operaciones, costos) |
| Logística / Bodega | Registrar salidas de insumos y recepciones en bodega |
| Gerencia | Consultar costos, estado de órdenes y reportes |

---

## 3. Pantallas principales

| Pantalla | Ruta en el código | ¿Qué hace? |
|----------|-------------------|------------|
| Sábanas de producto | `resources/js/pages/Production/ProductSheets/` | Lista y crea los diseños base de productos |
| Órdenes de producción | `resources/js/pages/Production/ProductionOrder/` | Lista y crea órdenes de producción |
| Ficha técnica | `resources/js/pages/Production/TechnicalDataSheet/` | Detalle completo de una orden: materiales, operaciones, costos |
| Control de lotes | `resources/js/pages/Production/BatchControl/` | Seguimiento del avance de cada orden por proceso |
| Lista operacional | `resources/js/pages/Production/ProductOperationList/` | Catálogo de operaciones estándar por producto |
| Colecciones | `resources/js/pages/Production/Collections/` | Agrupa sábanas de producto en colecciones de temporada |
| Programadores | `resources/js/pages/Production/Programmers/` | Gestión de programadores de producción |

---

## 4. Flujo principal (paso a paso)

```
[1] Sábana de Producto
        ↓
[2] Orden de Producción (OP)
        ↓
[3] Ficha Técnica
    ├── Operaciones (cómo se confecciona)
    ├── Rollos de tela (materiales)
    ├── Hilos
    ├── Insumos
    ├── Procesos externos
    └── Costos calculados
        ↓
[4] Control de Lotes (seguimiento por proceso)
    ├── Desarrollo de muestras
    ├── Confección
    ├── Terminación
    ├── Logística (salida de insumos)
    ├── Inspección / Aprobación
    └── Recepción en bodega
        ↓
[5] Orden completada ✓
```

---

## 5. Piezas clave del módulo

### 5.1 Sábana de Producto (`product_sheets`)
Es el **diseño base** de una prenda. Contiene toda la información del producto antes de que se produzca.

Datos principales:
- Referencia de muestra y referencia de producto
- Tipo: `muestra` o `producto`
- Sección, grupo, marca, mundo (obtenidos de parámetros)
- Talla base y tallas disponibles
- Precio de facturación y precio de venta
- Boceto plano (imagen del diseño)
- Colección a la que pertenece
- Versión (puede haber múltiples versiones de un mismo diseño)

> Una sábana puede tener varias **versiones** (v1, v2, v3...) cuando el diseño evoluciona.
> Una sábana puede generar múltiples **órdenes de producción**.

---

### 5.2 Orden de Producción (`production_orders`)
Representa la **instrucción de fabricar unidades** de una sábana de producto.

Datos principales:
- Código OP (ej: `MU-001`, `MF-045`)
- Número de unidades a producir
- Temporada, mes y año de despacho
- Destino (a quién va)
- Prioridad
- Estado actual del proceso (en qué etapa está)
- Tipo de origen: `manual` (creada por un usuario) o `auto` (generada automáticamente)
- Precio de facturación override y precio de venta override (se pueden editar en inspección)
- Costo calculado total

Los tipos de código OP son:
- `MU` = Muestra (orden de muestra)
- `MF` = Manufactura (orden de producción real)

---

### 5.3 Ficha Técnica (`technical_sheets`)
Es el **cerebro técnico** de una orden de producción. Se crea una ficha técnica por cada orden.

Contiene:
| Sub-sección | ¿Qué es? |
|-------------|----------|
| **Procesos** | Etapas externas de fabricación (ej: estampado, bordado) |
| **Rollos de tela** | Qué telas se usan y en qué cantidad |
| **Hilos** | Tipos de hilo y consumo |
| **Insumos** | Materiales adicionales (botones, cremalleras, etiquetas) |
| **Materia prima** | Referencia de materias primas usadas |
| **Operaciones** | Lista detallada de operaciones de confección con tiempos y costos |
| **Tallas** | Distribución de unidades por talla y trazos de corte |
| **Piezas de corte** | Despiece de la prenda (partes que se cortan) |

---

### 5.4 Control de Lotes (`BatchControl`)
Es el módulo de **seguimiento en tiempo real** de cada orden de producción.

Cada orden pasa por estos procesos en orden:
1. **Desarrollo de muestras** — Se trabaja el prototipo
2. **Confección** — Se cose la prenda
3. **Terminación** — Acabados finales (planchado, etiquetado, etc.)
4. **Logística** — Salida de insumos desde bodega
5. **Inspección** — Revisión de calidad y aprobación de cantidades
6. **Recepción en bodega** — Ingreso final al inventario

En cada proceso se puede:
- Ver las órdenes activas en ese proceso
- Registrar entregas parciales o totales
- Reportar novedades (devoluciones, problemas)
- Editar fechas estimadas y prioridades
- Ver la ficha técnica resumida

---

### 5.5 Lista Operacional (`product_operation_lists`)
Es el **catálogo de operaciones** que se aplican a un producto. Define qué operaciones de confección lleva una prenda y en qué orden, junto con los tiempos SAM (tiempo estándar por operación).

---

### 5.6 Colecciones (`collections`)
Agrupan varias sábanas de producto bajo un mismo nombre de colección o temporada (ej: "Colección Verano 2026").

---

## 6. Tablas de base de datos

| Tabla | ¿Qué guarda? |
|-------|-------------|
| `product_sheets` | Diseños base de productos |
| `production_orders` | Órdenes de producción |
| `technical_sheets` | Ficha técnica por orden |
| `technical_sheet_processes` | Procesos externos de la ficha |
| `technical_sheet_rolls` | Rollos de tela usados |
| `technical_sheet_threads` | Hilos usados |
| `technical_sheet_supplies` | Insumos usados |
| `technical_sheet_raw_materials` | Materias primas |
| `technical_sheet_operations` | Operaciones de confección |
| `technical_sheet_sizes` | Distribución de tallas y trazos |
| `product_sheet_cutting_pieces` | Piezas de corte |
| `product_operation_lists` | Listas operacionales |
| `batch_control_deliveries` | Entregas registradas en control de lotes |
| `batch_control_novelties` | Novedades/devoluciones en control de lotes |
| `batch_control_inspections` | Registros de inspección de calidad |
| `batch_control_warehouse_receipts` | Recepciones en bodega |
| `collections` | Colecciones de temporada |
| `production_roll_consumptions` | Consumo de rollos de tela por orden |

---

## 7. Relaciones entre las piezas (en lenguaje simple)

```
Una SÁBANA DE PRODUCTO
    → puede tener muchas ÓRDENES DE PRODUCCIÓN
    → puede tener muchas VERSIONES de sí misma
    → pertenece a una COLECCIÓN

Una ORDEN DE PRODUCCIÓN
    → tiene una FICHA TÉCNICA (con todos los detalles técnicos)
    → tiene muchas ENTREGAS (batch control)
    → tiene muchas NOVEDADES (devoluciones o problemas)
    → puede tener una INSPECCIÓN
    → puede tener una RECEPCIÓN EN BODEGA
    → fue creada por un USUARIO
```

---

## 8. Conexiones con otros módulos

| Módulo | ¿Cómo se conecta con Producción? |
|--------|----------------------------------|
| **Textil** | Los rollos de tela del módulo Textil se asignan a las fichas técnicas |
| **Inventario** | Al completar una orden, los productos entran a inventario (bodega) |
| **Gastos** | Las órdenes de compra de insumos se vinculan al proceso de producción |
| **Datos Maestros** | Secciones, grupos, marcas, mundos y parámetros vienen de aquí |
| **Configuración** | Los permisos de usuario controlan quién puede editar cada parte |

---

## 9. Archivos clave para un desarrollador

| Archivo | ¿Para qué? |
|---------|------------|
| `app/Models/Production/ProductionOrder.php` | Modelo principal de órdenes |
| `app/Models/Production/ProductSheet.php` | Modelo de sábanas de producto |
| `app/Models/Production/TechnicalSheet.php` | Modelo de ficha técnica |
| `app/Http/Controllers/Production/BatchControlController.php` | Toda la lógica del control de lotes |
| `app/Http/Controllers/Production/TechnicalDataSheetController.php` | Lógica de la ficha técnica |
| `app/Http/Controllers/Production/ProductSheetController.php` | Lógica de sábanas de producto |
| `resources/js/pages/Production/BatchControl/Index.vue` | Pantalla principal de control de lotes |
| `resources/js/pages/Production/TechnicalDataSheet/Index.vue` | Pantalla de ficha técnica |

---

## 10. Reglas de negocio importantes (¡no romper!)

1. **Una sábana con órdenes de producción no puede cambiar su lista operacional** — Si ya se fabricó, no se puede cambiar el proceso.
2. **El código OP (MU/MF) define el tipo de orden** — MU es muestra, MF es manufactura real.
3. **Los precios de facturación y venta se pueden sobrescribir en la inspección** — Se guardan como `billing_price_override` y `selling_price_override` en la orden.
4. **El proceso actual de una orden avanza en secuencia** — No se puede saltear etapas en el control de lotes.
5. **Las novedades pendientes bloquean el avance** — Una orden con novedades no resueltas no debería avanzar al siguiente proceso.
6. **Los rollos de tela que se asignan a una ficha técnica quedan vinculados** — Se registra el consumo real de tela por orden (`production_roll_consumptions`).
