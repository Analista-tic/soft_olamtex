Plan: Dialogs Inspección/Bodega + Reporte de Inconsistencias en BatchControl
Context
El flujo de Control de Lotes tiene procesos definidos en config/product-sheet.php. Los dos últimos pasos del flujo PRODUCTO son Inspección y aprobación (11) y Bodega (12). Se requiere:

Dialogs de confirmación de unidades en cada proceso.
Registro automático de inconsistencias de cantidades en la entrega (visible en la línea de tiempo del lote).
Flujo de negocio
[Inspección y aprobación] → botón "Inspección" (tab nuevo)
    ↓ InspectionApprovalDialog
    · Tabla ref (read-only): unidades del corte (de cut_consumption guardado)
    · Tabla real (editable): unidades confirmadas (pre-poblada con valores corte)
    · Si difieren → "Motivo" obligatorio
    · Guardar → POST inspection-approval
    → Al entregar a Bodega: si hay discrepancia → BatchControlDelivery se crea
      con campo quantity_notes (JSON resumen de diferencias) + auto-novelty
      visible en la línea de tiempo

[Bodega] → botón "Recepción Bodega" (tab nuevo)
    ↓ WarehouseReceiptDialog (3 secciones)
    · Sección 1 (read-only): unidades de inspección aprobada
    · Sección 2 (editable): imperfectos S/M/L/XL + Responsable (texto libre)
      → Computed: Pérdidas = Recibidas − Imperfectos
    · Sección 3 (editable): remisión S/M/L/XL + selector bodega
      → POST warehouse-receipt → crea/actualiza WarehouseItem
      → Si imperfectos > 0 → auto-novelty registrada en la entrega/timeline
Inconsistencias de cantidades → Timeline
Cuando ocurre una discrepancia de cantidades, se genera automáticamente un registro en la línea de tiempo del lote (mismo sistema de NoveltyItem ya existente, o anotación en BatchControlDelivery):

Casos que generan registro de inconsistencia:

En inspection-approval: matches_cut = false → se guarda el resumen de diferencias
En warehouse-receipt: imperfect_curves con total > 0 → se registran pérdidas con responsable
Mecanismo: Al guardar, el backend crea automáticamente un BatchControlNovelty (o agrega un campo quantity_notes JSON al BatchControlDelivery más reciente) con:

Tipo: 'quantity_discrepancy'
Descripción: resumen generado (ej. "Inspección: 5 unidades menos que corte. Motivo: [...]" / "Bodega: 3 imperfectos - Responsable: Juan")
Status: 'pending' si hay responsable a cobrar, 'resolved' si es solo informativo
Esto aparece en OrderTimelineDialog.vue igual que las demás novedades.

Cambios backend
1. Migraciones
batch_control_inspections

id, production_order_id FK, confirmed_curves JSON,
matches_cut bool, reason text nullable,
approved_by_user_id FK users, approved_at timestamp, timestamps
batch_control_warehouse_receipts

id, production_order_id FK, received_curves JSON, imperfect_curves JSON,
responsible varchar(255) nullable, remision_curves JSON,
warehouse_id FK warehouses, remisionado_at timestamp nullable, timestamps
Modificar batch_control_deliveries (o batch_control_novelties):

Añadir columna quantity_notes JSON nullable a la tabla de deliveries, O
Reutilizar batch_control_novelties con type 'quantity_discrepancy' (opción preferida si ya existe la tabla)
2. Modelos
app/Models/Production/BatchControlInspection.php
app/Models/Production/BatchControlWarehouseReceipt.php
3. Endpoints (BatchControlController)
Método	Ruta	Nombre Ziggy	Acción
GET	/{order}/inspection-data	control-de-lotes.inspection-data	Cut lines+curves para recalcular unitsReport
POST	/{order}/inspection-approval	control-de-lotes.inspection-approval	Guarda inspección; si discrepancia → auto-novelty
GET	/{order}/warehouse-receipt-data	control-de-lotes.warehouse-receipt-data	Datos inspección + warehouses
POST	/{order}/warehouse-receipt	control-de-lotes.warehouse-receipt	Guarda recibo + WarehouseItem; si imperfectos → auto-novelty
4. Lógica auto-novelty (backend, servicio reutilizable)
// En inspection-approval POST, si !matches_cut:
BatchControlNovelty::create([
    'production_order_id' => $order->id,
    'description' => "Discrepancia en Inspección: {$summary}. Motivo: {$reason}",
    'type' => 'quantity_discrepancy',
    'status' => 'pending',
    'reported_by_user_id' => auth()->id(),
]);

// En warehouse-receipt POST, si imperfectos > 0:
BatchControlNovelty::create([...  "Imperfectos en Bodega: {$total}. Responsable: {$responsible}" ...]);
5. warehouse-receipt POST → Inventario
FinishedProductCatalogItemService::ensureForProductionOrder() → obtiene/crea Item
Por cada color en remision_curves: WarehouseItem::updateOrCreate(...) sumando quantity
6. Rutas en routes/production.php
4 rutas nuevas dentro del grupo existente de control-de-lotes.

Cambios frontend
Nuevos tipos en types.ts
export type InspectionApprovalPayload = {
    confirmed_curves: CutUnitsReportRow[];
    matches_cut: boolean;
    reason: string | null;
};

export type WarehouseReceiptPayload = {
    imperfect_curves: CutUnitsReportRow[];
    responsible: string;
    remision_curves: CutUnitsReportRow[];
    warehouse_id: number;
};
Nuevo InspectionApprovalDialog.vue
Patrón igual a CutFabricConsumptionDialog
Load: GET inspection-data → recalcular unitsReport en front (misma lógica)
Tabla referencia (read-only) + tabla real (editable, pre-poblada)
Badge por fila: verde si coincide, rojo si difiere
Si difiere: textarea "Motivo" required (validación en front)
Props: open, row, isLoading, isSaving, viewOnly, saveError
Nuevo WarehouseReceiptDialog.vue
Load: GET warehouse-receipt-data
3 secciones (acordeón colapsable):
Unidades recibidas (read-only)
Imperfectos (editable) + campo texto Responsable + computed Pérdidas
Remisión (editable) + <Select> bodega + warning si excede (Recibidas − Imperfectos)
Props: open, row, isLoading, isSaving, viewOnly, saveError
BatchControlRowActions.vue
Dos botones nuevos siguiendo patrón del botón Corte:

v-if="activeProcess === 'Inspección y aprobación'"  →  emit('inspection-approval')
v-if="activeProcess === 'Bodega'"                   →  emit('warehouse-receipt')
Index.vue
Refs y handlers para cada nuevo dialog (idéntico al patrón de cutConsumption*)
Escuchar inspection-approval y warehouse-receipt desde BatchControlTable/RowActions
Después de guardar → refreshTable() para reflejar auto-novelty en has_pending_novelties
dialogs/index.ts
Exportar InspectionApprovalDialog y WarehouseReceiptDialog.

Archivos críticos
Archivo	Cambio
app/Http/Controllers/Production/BatchControlController.php	4 métodos nuevos
app/Models/Production/BatchControlInspection.php	Nuevo
app/Models/Production/BatchControlWarehouseReceipt.php	Nuevo
database/migrations/*_create_batch_control_inspections_table.php	Nueva
database/migrations/*_create_batch_control_warehouse_receipts_table.php	Nueva
routes/production.php	4 rutas nuevas
resources/js/pages/Production/BatchControl/types.ts	2 tipos nuevos
resources/js/pages/Production/BatchControl/Index.vue	Handlers + refs nuevos
resources/js/pages/Production/BatchControl/BatchControlRowActions.vue	2 botones nuevos
resources/js/pages/Production/BatchControl/components/dialogs/InspectionApprovalDialog.vue	Nuevo
resources/js/pages/Production/BatchControl/components/dialogs/WarehouseReceiptDialog.vue	Nuevo
resources/js/pages/Production/BatchControl/components/dialogs/index.ts	2 exports nuevos
Verificación
Tab "Inspección y aprobación" → botón visible → abre dialog con datos del corte
Diferencia de cantidades → Motivo requerido → guardar crea auto-novelty → aparece en timeline
Tab "Bodega" → botón "Recepción" → abre WarehouseReceiptDialog
Imperfectos > 0 → auto-novelty en timeline con responsable
Guardar remisión → warehouse_items.quantity actualizado correctamente
FinishedProductCatalogItemService crea el Item si no existe
Timeline del lote muestra las inconsistencias de ambos procesos
