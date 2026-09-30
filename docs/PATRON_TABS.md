# Patrón de tabs

**Última actualización:** 2026-09-27
**Para qué sirve:** que todas las pestañas del sistema se vean y se comporten igual. Antes cada pantalla armaba su propio `TabsList`/`TabsTrigger` con clases distintas.

## La regla

Las pestañas de navegación se hacen con `resources/js/components/common/PillTabs.vue`, dentro de un `<Tabs v-model>`. **No** se escriben `TabsList`/`TabsTrigger` a mano con clases propias.

```vue
<Tabs v-model="activeTab">
    <PillTabs :tabs="tabs" />
    <TabsContent value="items">...</TabsContent>
    <TabsContent value="rolls">...</TabsContent>
</Tabs>
```

```ts
const tabs: PillTab[] = [
    { value: 'items', label: 'Ítems', icon: Package2 },
    { value: 'rolls', label: 'Rollos', icon: Layers, count: totalRolls, hidden: !isTelas },
];
```

`TabsContent` sigue usándose tal cual; `PillTabs` solo reemplaza la barra de pestañas.

## Props

| Prop | Valores | Uso |
|---|---|---|
| `tabs` | `{ value, label, icon?, count?, disabled?, hidden? }[]` | `count` solo se muestra si es > 0. `hidden` oculta la pestaña sin filtrar el arreglo. |
| `size` | `md` (defecto) / `sm` | `md` en páginas y secciones (`text-sm`). `sm` en diálogos y espacios reducidos (`text-xs`). |
| `align` | `start` (defecto) / `center` | `center` solo cuando las pestañas son la navegación principal de la pantalla (Ficha técnica). |
| `class` | clases Tailwind | Se aplica al contenedor (por ejemplo `mb-0` cuando va en una fila de filtros). |

## Aspecto (no cambiar por pantalla)

- Contenedor en forma de píldora: `rounded-full border border-border bg-muted/40 p-1`.
- Pestaña inactiva: texto gris, se oscurece al pasar el mouse.
- Pestaña activa: `bg-primary/10`, texto `text-primary`, borde `border-primary/20`.
- Etiqueta en una sola línea (`whitespace-nowrap`), con scroll horizontal si no cabe.
- Contador: pastilla `bg-primary` con texto claro, junto a la etiqueta.

Este es el mismo estilo que ya tenían Ficha técnica y el diálogo de ficha técnica de Control de lotes.

## Pantallas migradas

Recepción, Ficha técnica, diálogo de ficha técnica (Control de lotes), selector Muestras/Productos de Control de lotes y de Órdenes de producción, Catálogo de materiales, Inventario de rollos, Reportes de textil, Contactos, Catálogo de operaciones.

## Excepciones (no usan `PillTabs`)

- **Procesos de Control de lotes** (`Production/BatchControl/Index.vue`): cada proceso tiene su color propio (`tabColor`) y contadores de novedades; el color significa el proceso, no solo "activo".
- **Interruptores pequeños** de dos opciones (Manual/Automática en `ItemFormContent.vue`, `OperationalListTab.vue`, `ProductOperationListView.vue`): son selectores dentro de un formulario, no navegación. Si se repiten más, evaluar `SegmentedToggle.vue`.

## Al agregar una pantalla con tabs

1. Usar `PillTabs`; no copiar clases de otra pantalla.
2. Si falta una variante (otro tamaño, otro estilo), agregarla a `PillTabs` para que todas las pantallas la tengan, no resolverla con clases locales.
