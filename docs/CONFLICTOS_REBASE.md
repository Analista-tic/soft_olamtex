# Documentación de conflictos resueltos (rebase 2025-03-06)

Resumen de las decisiones tomadas al resolver conflictos durante el `git pull --rebase origin master`, priorizando mejoras técnicas, design system y funcionalidades relevantes.

---

## Criterios de decisión

- **Design tokens vs colores hardcodeados**: Se priorizaron tokens (`text-destructive`, `bg-accent`, `ring-ring`, `border-input`) sobre colores fijos (`text-red-500`, `bg-blue-50`).
- **Accesibilidad**: Se mantuvo `focus-visible` frente a `focus`, y `DialogDescription` cuando aporta a11y.
- **Funcionalidad**: En caso de features nuevas (notificaciones, CreditDebitNote, etc.), se conservó la versión más completa.

---

## Componentes base

### AppSidebar.vue
- **Mantenido**: Estructura HEAD (remoto) con NavFooter, Tooltip, icon-column, Collapsible.
- **Justificación**: Sidebar más consistente, tooltips en modo colapsado y layout icon-column/label-column.
- **No se mantuvo**: NavMain con `filteredMainNavItems` (referencia inexistente en nuestra rama).

### FormDialogContent.vue
- **Mantenido**: `bg-background text-foreground` (design tokens).
- **Incorporado**: `DialogDescription` con `sr-only` para accesibilidad.
- **No se mantuvo**: `bg-white` hardcodeado.

### InputField.vue
- **Mantenido**: `text-destructive`, `ring-ring`, `focus-visible`, `placeholder:text-muted-foreground`.
- **Justificación**: Design system y accesibilidad.
- **No se mantuvo**: `text-red-500`, `focus:ring-blue-500`, `text-gray-500`.

### NavFooter.vue
- **Mantenido**: Estilos scoped de `.icon-column` y `.label-column` (HEAD).
- **Justificación**: Mantiene alineación con AppSidebar.

### NavUser.vue
- **Mantenido**: Versión HEAD con notificaciones (Bell, Popover, axios, archive/delete).
- **Justificación**: Feature de notificaciones completa (badge, listado, marcar leídas, archivar, eliminar).
- **No se mantuvo**: Versión simple sin notificaciones.

### SelectField.vue
- **Mantenido**: Design tokens (`bg-accent`, `border-border`, `text-primary`, `bg-muted`).
- **No se mantuvo**: `bg-blue-50`, `text-blue-600`, `focus:ring-blue-500`.

### AlertBox.vue
- **Mantenido**: `typeConfig` con iconos por tipo (CheckCircle2, XCircle, Info, AlertTriangle).
- **No se mantuvo**: `typeClasses` sin iconos específicos.

### AlertStack.vue
- **Mantenido**: Transitions para animar entrada/salida de alertas.
- **No se mantuvo**: Layout sin transiciones.

### DatePickerField.vue
- **Mantenido**: Versión ours con `nextTick`, `onMounted`, `onBeforeUnmount`, posicionamiento y Teleport.
- **Justificación**: Implementación más completa (calculatePosition, etc.).

---

## Dominio Finance

### index.ts, service.ts, mappers.ts
- **Mantenido**: HEAD con CreditDebitNote (DTOs, mappers, list/get/create/update/remove).
- **Justificación**: Módulo de notas crédito/débito completo.
- **No se mantuvo**: Versión reducida solo con Tax y Retention.

---

## Páginas por módulo

### Configuration, Expenses, Finance, Inventory, MasterData, Textil
- **Mantenido**: Versión remota (HEAD) con refactors recientes.
- **Justificación**: Mejoras de UX, design system y nuevas rutas del remoto.

### Production (Programmers, ProductionOrder, ProductSheets, ProductOperationList, ProcessesTab)
- **Mantenido**: Nuestra versión (theirs).
- **Justificación**: Cambios de dominio (Programmer→ProductionOrder, ficha técnica, etc.).

### Audit/Index.vue
- **Mantenido**: DatePickerField con dateFilterModel (HEAD).
- **Justificación**: Consistencia con `filters.date` y uso del componente de fecha actual.

---

## Rutas (web.php)

### Auditoría
- **Mantenido**: Rutas planas sin `Route::middleware(['auth','verified'])->group` anidado.
- **Justificación**: Evitar middleware redundante (ya dentro de auth/verified).

---

## Archivos eliminados / movidos

### BatchControl
- **Decisión**: Eliminado `resources/js/pages/BatchControl/Index.vue`.
- **Justificación**: Migrado a `resources/js/pages/Production/BatchControl/Index.vue`.

---

## Correcciones posteriores (post-rebase)

### BatchControl Index.vue (2025-03-06)

**Acciones en la tabla**:
- **Problema**: Las acciones (Ver novedades, Historial, Novedad, Entregar) ocupaban mucho espacio y podían saltar de fila.
- **Solución**: Acciones agrupadas en un dropdown (DropdownMenu con MoreVertical), patrón igual que `PurchaseOrders/Index.vue`.
- **Cambios**: `flex-nowrap`, `whitespace-nowrap`, columna Acciones con ancho fijo; creado `BatchControlRowActions.vue`.

**Componentización**:
- Extraídos: `types.ts`, `BatchControlTable.vue`, `BatchControlRowActions.vue`.
- Estructura reutilizable y fácil de mantener.

**Novedad y cambio de estado**:
- Al reportar novedad, la orden pasa al proceso anterior (backend actualiza `current_process`).
- Desaparece del tab actual y aparece en el tab del proceso anterior.

---

## Resumen por tipo

| Tipo                | Criterio principal                    | Versión preferida |
|---------------------|----------------------------------------|-------------------|
| Componentes UI      | Design tokens, a11y                   | HEAD (remoto)     |
| Alertas             | Transiciones, iconos                   | Ours              |
| Finance             | CreditDebitNote completo               | HEAD              |
| Production          | Programmer→ProductionOrder, ficha BD   | Ours              |
| Otros módulos       | Refactors remotos recientes            | HEAD              |

---

*Generado tras rebase exitoso sobre `origin/master` (2025-03-06).*
