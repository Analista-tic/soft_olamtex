# Patrón de UI en Compras: página para crear/editar, diálogo para ver

**Última actualización:** 2026-09-27
**Para qué sirve:** define cómo debe verse la interacción de crear, editar y ver un documento en el módulo de Compras, para que Solicitudes, Órdenes y Facturas (normal y tela) se comporten igual.

## La regla

- **Crear y editar** → navegan a una **página completa**, con su propia ruta.
- **Ver** → se muestra en un **diálogo modal de solo lectura**, sobre el índice.

No hay excepciones dentro de Solicitudes/Órdenes/Facturas. **Recepción** queda fuera de este patrón: no tiene "crear" (nace de una factura ya aprobada) y su detalle ya es una página, no un diálogo.

## Convención de archivos y rutas

Por cada documento (`<Entidad>` = `PurchaseRequest`, `PurchaseOrder`, `PurchaseInvoice`, `PurchaseInvoiceTela`):

| Pieza | Archivo | Rol |
|---|---|---|
| Página de crear/editar | `resources/js/pages/Expenses/<Módulo>/<Entidad>Form.vue` | Único lugar con lógica de escritura (validar, guardar, `handleSubmit`) |
| Diálogo de ver | `resources/js/pages/Expenses/<Módulo>/<Entidad>Dialog.vue` | Solo lectura; sin botones de guardar ni validación |
| Índice | `resources/js/pages/Expenses/<Módulo>/Index.vue` | Decide a cuál de los dos ir |

En `Index.vue`:
```js
// Crear
router.visit('/gastos/<recurso>/crear')
// Editar
router.visit('/gastos/<recurso>/' + id + '/editar')
// Ver — sigue siendo el diálogo
verFn(row) // abre <Entidad>Dialog con readonly: true
```

En `routes/expenses.php`, dentro de cada grupo, `/crear` y `/{id}/editar` van **antes** de `/data` y de la ruta genérica `/{id}` (show), para que Laravel no las capture como parámetro:
```php
Route::get('/crear', [Controller::class, 'create'])->name('create')->middleware('permission:crear ...');
Route::get('/{id}/editar', [Controller::class, 'edit'])->name('edit')->middleware('permission:editar ...');
Route::get('/data', [Controller::class, 'getData'])->name('getData');
// ...
Route::get('/{id}', [Controller::class, 'show'])->name('show');
```

En el controlador:
- `create()`: `Inertia::render('Expenses/<Módulo>/<Entidad>Form', ['mode' => 'create'])`, sin precargar datos (la página los pide por axios).
- `edit($model)`: mismo chequeo de acceso que ya usa `show()`, **más** el candado de estado del documento (`canEditRequest`/`canEditOrder`/`canEditInvoice`) devolviendo **403** si no aplica; renderiza `Inertia::render('Expenses/<Módulo>/<Entidad>Form', ['mode' => 'edit', 'form' => $model])`.

## Qué va en la página y qué en el diálogo

- La página (`Form.vue`) tiene toda la escritura: campos editables, alta de ítems, `handleSubmit`, validación, atajos de precarga (p. ej. `?orden=ID` en Factura). Al guardar, `router.visit()` de vuelta al índice.
- El diálogo (`Dialog.vue`) es un visor: carga el documento por id, renderiza los mismos componentes de ítems/totales que la página pero en modo `readonly`, y solo tiene un botón de cerrar. No debe tener `handleSubmit` ni botones de guardar — si los tiene, es señal de que quedó código de un flujo de escritura que ya nadie invoca (deuda técnica, ver más abajo).

## Deuda técnica a vigilar

Cuando se convierte un `Dialog.vue` a solo-lectura, hay que **eliminar** su código de escritura, no dejarlo "por si acaso": handlers de guardar, validación, selectores de precarga que solo aplicaban a "crear". Un diálogo que conserva ese código sin que nada lo invoque es exactamente el tipo de deuda que motivó este documento (pasó con `PurchaseOrderDialog.vue` antes de esta migración).

Al añadir `dusk="..."` en un componente nuevo, verificar primero qué esperan los tests en `tests/Browser/Expenses/`, para no dejar selectores huérfanos ni tests rotos por selectores que nunca existieron.

## Historial de esta decisión

- Facturas normal y de tela **tuvieron** página completa (`PurchaseInvoiceForm.vue`/`PurchaseInvoiceTelaForm.vue`) y se consolidaron en el diálogo en los commits `c06ea58c`/`ee7cdef2`.
- Órdenes fue en la dirección contraria (de diálogo a página, commit `c65dcc9f`), sin que quedara documentado el motivo.
- Esta migración estandariza el patrón de Órdenes para los tres documentos, y lo deja documentado aquí para que no se repita la divergencia.
