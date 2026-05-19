<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Concerns\HandlesValidationErrors;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Item;
use App\Services\MasterData\ItemExcelService;
use App\Services\MasterData\ItemService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ItemController extends Controller
{
    use HandlesValidationErrors;

    public function __construct(
        protected ItemService $service,
        protected ItemExcelService $excelService,
    ) {}

    public function index()
    {
        return Inertia::render('MasterData/Item/Index');
    }

    public function show($id)
    {
        $item = $this->service->findWithRelations($id, ['categorySubGroup', 'tax', 'warehouseItems.warehouse']);
        $item->setAttribute('warehouse_stocks', $this->normalizeWarehouseStocks($item));

        return $item;
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->itemValidationRules());
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->unexpectedErrorResponse();
        }

        $payload = $this->prepareItemPayload($request, $data);

        return $this->service->create($payload['data'], $payload['warehouses']);
    }

    public function update(Request $request, $id)
    {
        $item = $this->service->find($id);

        try {
            $data = $request->validate($this->itemValidationRules(true, $item));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->unexpectedErrorResponse();
        }

        $payload = $this->prepareItemPayload($request, $data, $item);

        $updated = $this->service->update($item, $payload['data'], $payload['warehouses']);

        return $updated;
    }

    public function destroy($id)
    {
        $item = $this->service->find($id);

        try {
            $this->service->delete($item);
            return response()->noContent();
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    public function getData(Request $request)
    {
        $filters = $request->only([
            'search', 'status', 'item_type', 'warehouse_id',
            'origin_type', 'category_sub_group_id', 'category_group_id', 'sort_by', 'sort_dir'
        ]);
        $perPage = $request->input('per_page', 10);
        $items = $this->service->paginate($filters, $perPage);

        return response()->json($items);
    }

    public function togglestatus($id)
    {
        $item = $this->service->find($id);
        $status = $this->service->toggleStatus($item);

        return response()->json([
            'status' => $status,
            'message' => $status ? 'item habilitado' : 'item deshabilitado',
        ]);
    }

    public function checkDuplicate(Request $request)
    {
        $data = $request->validate([
            'reference' => 'required|string',
            'category_sub_group_id' => 'required|integer',
            'color_id' => 'nullable|integer|exists:colors,id',
            'extra_fields' => 'nullable',
            'item_id' => 'nullable|integer',
            'unit_measure' => 'nullable|string',
        ]);

        $extraFields = $this->service->normalizeExtraFields($request->input('extra_fields') ?? []);
        $subGroupId = (int) $data['category_sub_group_id'];
        $colorId = !empty($data['color_id']) ? (int) $data['color_id'] : null;
        $unitMeasure = $data['unit_measure'] ?? null;
        $name = $this->service->computeName($subGroupId, $extraFields, $colorId, $unitMeasure);
        $excludeId = $data['item_id'] ?? null;

        $status = $this->service->duplicateStatus($data['reference'], $name, $excludeId);

        return response()->json($status);
    }

    public function downloadTemplate()
    {
        return $this->excelService->downloadTemplate();
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $result = $this->service->processBulkUploadArray($rows);

        $inserted = $result['inserted'] ?? 0;
        $updated  = $result['updated']  ?? 0;

        if ($inserted === 0 && $updated === 0 && !empty($result['errors'])) {
            return response()->json([
                'message' => 'No se pudo procesar ningún ítem. Errores: ' . implode('; ', $result['errors'])
            ], 422);
        }

        $parts = [];
        if ($inserted > 0) $parts[] = "{$inserted} ítem(s) creado(s)";
        if ($updated  > 0) $parts[] = "{$updated} ítem(s) actualizado(s)";

        return response()->json([
            'message' => 'Carga masiva completada. ' . implode(', ', $parts),
            'errors'   => $result['errors'] ?? [],
            'inserted' => $inserted,
            'updated'  => $updated,
        ]);
    }

    public function getWarehouses($id)
    {
        $item = Item::findOrFail($id);

        return response()->json($this->normalizeWarehouseStocks($item)->map(fn (array $stock) => [
            'id' => $stock['warehouse_id'],
            'name' => $stock['name'],
            'pivot' => [
                'quantity' => $stock['quantity'],
                'stock_min' => $stock['stock_min'],
                'stock_max' => $stock['stock_max'],
            ],
        ]));
    }

    public function exportExcel()
    {
        return $this->excelService->exportAll();
    }

    public function listView()
    {
        return Inertia::render('MasterData/Item/Index');
    }

    public function advancedForm(Request $request)
    {
        $payload = [];
        $itemId = $request->query('item_id');
        if ($itemId) {
            $item = $this->service->findWithRelations(
                (int) $itemId,
                ['categorySubGroup', 'tax'],
            );
            $payload['initialForm'] = $item->toArray();
        }

        return Inertia::render('MasterData/Item/AdvancedForm', $payload);
    }

    public function adjustmentView()
    {
        return Inertia::render('Inventory/InventoryAdjustment/Index');
    }

    public function search(Request $request)
    {
        $q      = trim($request->input('q', ''));
        $limit  = min((int) $request->input('limit', 30), 100);
        $group  = $request->input('group');
        $status = $request->input('status');

        $query = Item::query()
            ->select('id', 'name', 'reference', 'img', 'unit_measure', 'item_type', 'status')
            ->where('status', true);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        if ($group === 'insumos') {
            $query->whereHas('categorySubGroup.categoryGroup', function ($sub) {
                $sub->where('name', 'like', '%insumo%');
            });
        }

        $items = $query->orderBy('name')->limit($limit)->get();

        return response()->json($items->map(fn($item) => [
            'id'           => $item->id,
            'name'         => $item->name,
            'reference'    => $item->reference,
            'label'        => $item->reference
                                 ? "{$item->name} ({$item->reference})"
                                 : $item->name,
            'unit_measure' => $item->unit_measure,
            'item_type'    => $item->item_type,
            'img_url'      => $item->img ? asset("storage/{$item->img}") : null,
        ]));
    }

    private function resolveWarehouses(Request $request): array
    {
        // Delegar la resolución de payloads de bodegas al servicio
        return $this->service->resolveWarehousePayload($request->input('warehouses'));
    }

    private function prepareItemPayload(Request $request, array $data, Item $item = null): array
    {
        $extraFields = $this->service->normalizeExtraFields(
            $request->input('extra_fields') ?? ($item?->extra_fields ?? [])
        );
        $data['extra_fields'] = $extraFields;
        $data['color_id'] = $request->filled('color_id') ? (int) $request->input('color_id') : null;

        $subGroupId = $request->has('category_sub_group_id')
            ? (int) $request->input('category_sub_group_id')
            : ($item?->category_sub_group_id ?? null);

        $reference = trim($data['reference'] ?? '');
        $data['reference'] = $reference;

        $colorId = !empty($data['color_id']) ? (int) $data['color_id'] : null;
        $itemName = $this->service->computeName((int) ($subGroupId ?? 0), $extraFields, $colorId);
        $this->service->assertUniqueReferenceAndName($reference, $itemName, $item?->id);

        $itemType = ($data['item_type'] ?? ($item?->item_type ?? 'product')) === 'service'
            ? 'service'
            : 'product';
        $tracksInventoryInput = Arr::exists($data, 'tracks_inventory')
            ? $data['tracks_inventory']
            : ($item?->tracks_inventory ?? ($itemType !== 'service'));
        $tracksInventory = $itemType === 'service'
            ? false
            : filter_var($tracksInventoryInput, FILTER_VALIDATE_BOOLEAN);

        $data['item_type'] = $itemType;
        $data['tracks_inventory'] = $tracksInventory;

        // Las cuentas contables solo son obligatorias desde el formulario avanzado.
        // El dialog básico no las expone, por lo que se omiten si no vienen en el payload.

        $isAdvancedForm = $request->hasAny([
            'income_account_id',
            'inventory_account_id',
            'cost_of_sale_account_id',
        ]);

        if ($isAdvancedForm && ! $tracksInventory && empty($data['cost_of_sale_account_id'])) {
            throw ValidationException::withMessages([
                'cost_of_sale_account_id' => ['La cuenta de costo/gasto directo es obligatoria cuando el item no controla inventario.'],
            ]);
        }

        if (!$tracksInventory) {
            $data['negative_movement'] = false;
        }

        $this->handleImageUploadIfPresent($request, $data, 'items');
        $warehouses = $this->resolveWarehouses($request);

        return [
            'data' => $data,
            'warehouses' => $warehouses,
        ];
    }

    private function itemValidationRules(bool $isUpdate = false, $item = null): array
    {
        $rules = [
            'category_sub_group_id' => ($isUpdate ? 'sometimes' : 'required') . '|integer',
            'img' => 'nullable|string',
            'image' => 'nullable|file|image',
            'color_id' => 'nullable|integer|exists:colors,id',
            'tax_id' => 'nullable|integer',
            'tax' => ($isUpdate ? 'sometimes' : 'required') . '|numeric',
            'bar_code' => 'nullable|string',
            'negative_movement' => $isUpdate ? 'sometimes' : 'required',
            'description' => ($isUpdate ? 'sometimes' : 'required') . '|string',
            'extra_fields' => 'nullable',
            'reference' => ['required', 'string'],
            'status' => 'sometimes|boolean',
            'item_type' => 'required|string|in:product,service,draft',
            'tracks_inventory' => 'sometimes|boolean',
            'unit_measure' => 'nullable|string',
            'income_account_id' => 'nullable|integer|exists:accounting_accounts,id',
            'inventory_account_id' => 'nullable|integer|exists:accounting_accounts,id',
            'cost_of_sale_account_id' => 'nullable|integer|exists:accounting_accounts,id',
        ];

        // Reference unique rule: ignore current item on update
        $uniqueRule = Rule::unique('items', 'reference')->whereNull('deleted_at');
        if ($isUpdate && $item) {
            $uniqueRule = $uniqueRule->ignore($item->id);
        }
        $rules['reference'] = ['required', 'string', $uniqueRule];

        return $rules;
    }

    private function handleImageUploadIfPresent(Request $request, array &$data, string $folder = 'articles')
    {
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store($folder, 'public');
            $data['img'] = $path;
        }
    }

    private function normalizeWarehouseStocks(Item $item): Collection
    {
        $item->loadMissing('warehouseItems.warehouse');

        return $item->warehouseItems
            ->sortByDesc(fn ($warehouseItem) => (float) ($warehouseItem->quantity ?? 0))
            ->values()
            ->map(fn ($warehouseItem) => [
                'warehouse_id' => $warehouseItem->warehouse_id,
                'name' => $warehouseItem->warehouse->name ?? '',
                'quantity' => (float) ($warehouseItem->quantity ?? 0),
                'stock_min' => (float) ($warehouseItem->stock_min ?? 0),
                'stock_max' => (float) ($warehouseItem->stock_max ?? 0),
                'weighted_average_price' => $warehouseItem->weighted_average_price !== null
                    ? (float) $warehouseItem->weighted_average_price
                    : null,
            ]);
    }






    private function unexpectedErrorResponse()
    {
        return response()->json([
            'message' => 'Ocurrió un error inesperado al procesar la solicitud.',
            'errors' => [],
        ], 500);
    }

}
