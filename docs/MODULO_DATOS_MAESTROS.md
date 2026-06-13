# Módulo de Datos Maestros

**Última actualización:** 2026-06-12
**Propósito:** Explicar qué hace el módulo de Datos Maestros, cómo funciona y cómo se conecta con el resto del sistema.

---

## 1. ¿Qué hace este módulo?

Los Datos Maestros son los **catálogos base** del sistema. Son los datos que todos los demás módulos necesitan para funcionar. Aquí se define:

- Quiénes son los **clientes y proveedores** (contactos).
- Cuáles son los **productos e insumos** que maneja la empresa (ítems).
- Los **colores** disponibles para los productos.
- Las **categorías** para clasificar los ítems.
- Los **parámetros** de producción (secciones, grupos, marcas, mundos, etc.).
- Los **campos adicionales** personalizados.

> Piénsalo así: si los otros módulos son las "operaciones" del negocio, los Datos Maestros son la "materia prima" que esas operaciones necesitan.

---

## 2. ¿Quién usa este módulo?

| Rol | ¿Para qué lo usa? |
|-----|-------------------|
| Administrador | Crear y mantener el catálogo de ítems, contactos y parámetros |
| Contabilidad | Configurar las cuentas contables de cada ítem |
| Producción | Consultar parámetros, colores y referencias de producto |
| Comercial | Buscar clientes y proveedores |

---

## 3. Pantallas principales

| Pantalla | Ruta en el código | ¿Qué hace? |
|----------|-------------------|------------|
| Contactos | `resources/js/pages/MasterData/Contact/` | Gestionar clientes y proveedores |
| Ítems | `resources/js/pages/MasterData/Item/` | Catálogo de productos e insumos |
| Categorías | `resources/js/pages/MasterData/Category/` | Grupos y subgrupos de clasificación de ítems |
| Parámetros | `resources/js/pages/MasterData/Parameters/` | Valores configurables usados en producción |
| Campos adicionales | `resources/js/pages/MasterData/AdditionalFields/` | Campos personalizados para ítems |

---

## 4. Piezas clave del módulo

### 4.1 Contactos (`contacts`)
Representa a cualquier **persona o empresa** con la que la empresa tiene relación comercial.

Tipos de contacto:
| Tipo | Significado |
|------|-------------|
| `proveedor` | Solo proveedor |
| `cliente` | Solo cliente |
| `cliente_proveedor` | Es ambos a la vez |

Datos principales:
- Tipo de identificación (NIT, CC, pasaporte, etc.)
- Número de identificación y dígito de verificación (DV)
- Nombre completo, tipo de persona (natural o jurídica)
- Municipio, departamento, dirección
- Email, teléfono
- Contactos adicionales y sucursales (guardados como JSON)

> Un mismo contacto puede ser cliente Y proveedor al mismo tiempo gracias al tipo `cliente_proveedor`.

---

### 4.2 Ítems (`items`)
Es el **catálogo de todos los productos** que maneja la empresa: insumos, telas, prendas terminadas, servicios, etc.

Datos principales:
- Nombre, referencia, código de barras
- Tipo de ítem (`item_type`) — define si es producto, insumo, servicio, etc.
- Categoría (`category_sub_group_id`)
- Color asociado (`color_id`)
- Unidad de medida (`unit_measure`) — metros, unidades, kilogramos, etc.
- Si rastrea inventario (`tracks_inventory`) — si es `false`, el ítem no genera movimientos de stock
- Impuesto aplicable (`tax_id`) — viene del módulo de Finanzas
- Cuentas contables:
  - `income_account_id` — cuenta de ingreso (para ventas)
  - `inventory_account_id` — cuenta de inventario (para activos)
  - `cost_of_sale_account_id` — cuenta de costo de ventas
- Imagen del producto
- Campos extra personalizados (`extra_fields`)
- Origen: `system` (creado en el sistema) o `migration` (importado desde datos anteriores)

> Un ítem con `tracks_inventory = false` se puede vender o comprar pero **no afecta las cantidades en bodega**.

---

### 4.3 Colores (`colors`)
Catálogo de colores disponibles para los productos.

Datos: nombre del color, código de color.

Se usa principalmente en producción para identificar colores de prendas e insumos.

---

### 4.4 Categorías (`category_groups` y `category_sub_groups`)
Sistema de clasificación de los ítems en dos niveles:

```
Grupo de categoría (category_group)
    └── Subgrupo de categoría (category_sub_group)
            └── Ítem
```

Ejemplo:
```
Insumos textiles
    └── Hilos y agujas
            └── Hilo negro 40/2
```

---

### 4.5 Parámetros (`parameters`)
Son **valores configurables** que usa el módulo de Producción. En lugar de tener tablas separadas para cada catálogo de producción, se usan parámetros.

Cada parámetro tiene:
- `nombre` — el nombre del parámetro (ej: `"secciones"`, `"grupos"`, `"marcas"`, `"mundos"`)
- `valor` — un array JSON con la lista de opciones disponibles

Por ejemplo, el parámetro `"secciones"` puede contener:
```json
["Dama", "Caballero", "Niño", "Bebé"]
```

Esto permite que el administrador agregue o quite opciones sin tocar el código.

---

### 4.6 Campos adicionales (`additional_fields`)
Permite definir campos personalizados que se añaden a los ítems. Si la empresa necesita registrar información extra que no está en el formulario estándar, se crean aquí.

---

## 5. Relaciones con otros módulos

```
CONTACTO
    → Se usa en Ingresos como CLIENTE
    → Se usa en Gastos como PROVEEDOR

ÍTEM
    → Se vende en Ingresos (sale_document_items)
    → Se compra en Gastos (purchase_document_items)
    → Se almacena en Inventario (warehouse_items)
    → Tiene un IMPUESTO asignado (Finance)
    → Tiene CUENTAS CONTABLES asignadas (Finance)

PARÁMETROS
    → Se usan en Producción para secciones, grupos, marcas y mundos de las sábanas de producto

COLORES
    → Se usan en Ítems y en Producción (fichas técnicas e insumos)

CATEGORÍAS
    → Clasifican los ítems
```

---

## 6. Reglas de negocio importantes (¡no romper!)

1. **Un ítem no se puede eliminar físicamente si tiene movimientos de inventario** — se desactiva (`status = false`) pero queda en el historial.
2. **Un contacto puede ser cliente Y proveedor** — usar tipo `cliente_proveedor` en lugar de crear dos registros.
3. **Los ítems importados desde datos anteriores** se marcan con `origin_type = 'migration'` — tienen una bandera visual hasta que alguien los revisa y edita.
4. **Los parámetros de producción se editan desde aquí** — si alguien necesita agregar una nueva sección o marca, se hace en Parámetros, no en código.
5. **Las cuentas contables del ítem son obligatorias** para que la contabilidad funcione correctamente al vender o comprar ese ítem.
6. **Los campos `extra_fields` y `additional_contacts`** se guardan como JSON — si se cambia la estructura, pueden existir ítems/contactos con estructura antigua que hay que migrar.

---

## 7. Tablas de base de datos

| Tabla | ¿Qué guarda? |
|-------|-------------|
| `contacts` | Clientes y proveedores |
| `items` | Catálogo de productos e insumos |
| `colors` | Catálogo de colores |
| `category_groups` | Grupos de categoría |
| `category_sub_groups` | Subgrupos de categoría |
| `parameters` | Parámetros configurables de producción |

---

## 8. Archivos clave para un desarrollador

| Archivo | ¿Para qué? |
|---------|------------|
| `app/Models/MasterData/Contact.php` | Modelo de contactos — tipos de identificación y contacto |
| `app/Models/MasterData/Item.php` | Modelo de ítems — incluye relaciones con cuentas contables, impuesto, color y bodega |
| `app/Models/MasterData/Parameter.php` | Modelo de parámetros — valor guardado como array JSON |
| `app/Models/MasterData/Color.php` | Modelo de colores |
| `app/Http/Controllers/MasterData/ContactController.php` | Lógica de contactos |
| `app/Http/Controllers/MasterData/ItemController.php` | Lógica de ítems |
| `app/Http/Controllers/MasterData/ParameterController.php` | Lógica de parámetros |
| `resources/js/pages/MasterData/Contact/Index.vue` | Pantalla de contactos |
| `resources/js/pages/MasterData/Item/Index.vue` | Pantalla de ítems |
