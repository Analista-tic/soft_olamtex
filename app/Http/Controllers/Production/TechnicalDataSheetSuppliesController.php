<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Item;
use App\Models\Production\ProductionOrder;
use App\Services\Production\SupplyRequestService;
use App\Services\Production\TechnicalDataSheetItemOptionsService;
use App\Services\Production\TechnicalDataSheetSuppliesStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TechnicalDataSheetSuppliesController extends Controller
{
    public function __construct(
        private TechnicalDataSheetSuppliesStore $store,
        private TechnicalDataSheetItemOptionsService $itemOptionsService,
        private SupplyRequestService $supplyRequestService,
    ) {}

    public function index(int $opId): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);
        return response()->json($this->store->read($opId));
    }

    public function itemOptions(Request $request): JsonResponse
    {
        $group  = trim((string) $request->query('group', ''));
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'habilitado'));
        return response()->json($this->itemOptionsService->getOptions($group, $search, $status));
    }

    public function lastPurchaseForItem(int $itemId): JsonResponse
    {
        Item::query()->findOrFail($itemId);
        return response()->json(['data' => $this->store->getLastPurchaseForItem($itemId)]);
    }

    public function itemMeta(int $itemId): JsonResponse
    {
        Item::query()->findOrFail($itemId);
        return response()->json(['data' => $this->store->getItemMeta($itemId)]);
    }

    public function upsert(int $opId, Request $request): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);
        $validated = $request->validate($this->singleItemRules());
        $supply = $this->store->upsertOne($opId, $validated);
        // Retornar lista completa actualizada (compatibilidad con frontend)
        return response()->json($this->store->read($opId));
    }

    public function saveAll(int $opId, Request $request): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);

        $validated = $request->validate([
            'items'                       => ['required', 'array'],
            'items.*.id'                  => ['nullable', 'string', 'max:100'],
            'items.*.name'                => ['required', 'string', 'max:200'],
            'items.*.stage'               => ['required', 'in:GENERAL,CORTE,CONFECCION,TERMINACION,OTRO'],
            'items.*.sequence'            => ['nullable', 'integer', 'min:1'],
            'items.*.displayLabel'        => ['nullable', 'string', 'max:100'],
            'items.*.itemId'              => ['nullable', 'integer'],
            'items.*.requestedName'       => ['nullable', 'string', 'max:200'],
            'items.*.supplier'            => ['nullable', 'string', 'max:200'],
            'items.*.description'         => ['nullable', 'string', 'max:1000'],
            'items.*.modaLinea'           => ['nullable', 'in:MODA,LINEA,NA'],
            'items.*.uom'                 => ['nullable', 'string', 'max:60'],
            'items.*.qtyPerUnit'          => ['required', 'numeric', 'min:0'],
            'items.*.unitCost'            => ['required', 'numeric', 'min:0'],
            'items.*.internalRequestId'   => ['nullable', 'integer'],
            'items.*.purchaseRequestId'   => ['nullable', 'integer'],
            'items.*.documentUrl'         => ['nullable', 'string', 'max:2000'],
            'items.*.notes'               => ['nullable', 'string', 'max:1000'],
            'items.*.isDraft'             => ['nullable', 'boolean'],
            'items.*.sizeMeasurements'          => ['nullable', 'array'],
            'items.*.sizeMeasurements.*'        => ['nullable', 'array'],
            'items.*.sizeMeasurements.*.corte'  => ['nullable', 'numeric', 'min:0'],
            'items.*.sizeMeasurements.*.termi'  => ['nullable', 'numeric', 'min:0'],
        ]);

        // Merge sizeMeasurements from raw input because Laravel's validated()
        // may strip deeply nested keys not covered by explicit wildcard rules.
        $rawItems = $request->input('items', []);
        $items = array_values(array_map(function (array $item, int $index) use ($rawItems): array {
            $raw = $rawItems[$index] ?? [];
            $item['sizeMeasurements'] = isset($raw['sizeMeasurements']) && is_array($raw['sizeMeasurements'])
                ? $raw['sizeMeasurements']
                : null;
            return $item;
        }, $validated['items'], array_keys($validated['items'])));

        $this->store->write($opId, $items);
        return response()->json($this->store->read($opId));
    }

    public function destroy(int $opId, string $supplyId): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);
        $this->store->deleteOne($opId, $supplyId);
        return response()->json($this->store->read($opId));
    }

    public function requestSupply(int $opId, Request $request): JsonResponse
    {
        $op   = ProductionOrder::query()->findOrFail($opId);
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Usuario no autenticado.');
        }

        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:200'],
            'description'           => ['nullable', 'string', 'max:1000'],
            'category_sub_group_id' => ['nullable', 'integer'],
            'sub_group_name'        => ['nullable', 'string', 'max:100'],
            'unit_measure'          => ['nullable', 'string', 'max:60'],
            'reference'             => ['nullable', 'string', 'max:100'],
            'quantity'              => ['nullable', 'integer', 'min:1'],
            'unit_price'            => ['nullable', 'numeric', 'min:0'],
            'provider_name'         => ['nullable', 'string', 'max:255'],
            'observation'           => ['nullable', 'string', 'max:1000'],
            'reference_image'       => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
        ]);

        $validated['op_id']   = $opId;
        $validated['op_code'] = $op->op_code ?? "OP-{$opId}";

        if ($request->hasFile('reference_image')) {
            $validated['document_path'] = $this->supplyRequestService->handleReferenceImageUpload(
                $request->file('reference_image')
            );
        }

        $result          = $this->supplyRequestService->createDraftSupply($validated, $user);
        $item            = $result['item'];
        $inventoryRequest = $result['request'];
        $purchaseRequest = $result['purchase_request'];

        $documentUrl = $purchaseRequest->document_path
            ? route('storage.file.show', ['path' => ltrim((string) $purchaseRequest->document_path, '/')])
            : null;

        return response()->json([
            'message' => 'Solicitud de insumo creada correctamente.',
            'item' => [
                'id'          => (int) $item->id,
                'name'        => trim((string) $item->name),
                'reference'   => $item->reference,
                'unit'        => $item->unit_measure,
                'item_type'   => $item->item_type,
                'status'      => (bool) $item->status,
                'description' => $item->description,
            ],
            'request' => [
                'id'              => $inventoryRequest->id,
                'document_number' => $inventoryRequest->document_number,
                'status'          => $inventoryRequest->status,
            ],
            'purchase_request' => [
                'id'             => (int) $purchaseRequest->id,
                'request_number' => $purchaseRequest->request_number,
                'status'         => $purchaseRequest->status instanceof \BackedEnum
                    ? $purchaseRequest->status->value
                    : (string) $purchaseRequest->status,
                'document_url'   => $documentUrl,
            ],
        ], 201);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:2048'],
        ]);

        $file = $request->file('image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        Storage::disk('public')->putFileAs('supply-images', $file, $filename);

        $url = route('storage.file.show', ['path' => 'supply-images/' . $filename]);

        return response()->json(['url' => $url]);
    }

    public function supplySubGroups(): JsonResponse
    {
        return response()->json($this->supplyRequestService->getSupplySubGroups());
    }

    public function supplyGroups(): JsonResponse
    {
        return response()->json($this->supplyRequestService->getSupplyGroups());
    }

    // ── Privado ──────────────────────────────────────────────────────

    private function singleItemRules(): array
    {
        return [
            'id'                => ['nullable', 'string', 'max:100'],
            'name'              => ['required', 'string', 'max:200'],
            'stage'             => ['required', 'in:GENERAL,CORTE,CONFECCION,TERMINACION,OTRO'],
            'sequence'          => ['nullable', 'integer', 'min:1'],
            'displayLabel'      => ['nullable', 'string', 'max:100'],
            'itemId'            => ['nullable', 'integer'],
            'requestedName'     => ['nullable', 'string', 'max:200'],
            'supplier'          => ['nullable', 'string', 'max:200'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'modaLinea'         => ['nullable', 'in:MODA,LINEA,NA'],
            'uom'               => ['nullable', 'string', 'max:60'],
            'qtyPerUnit'        => ['required', 'numeric', 'min:0'],
            'unitCost'          => ['required', 'numeric', 'min:0'],
            'internalRequestId' => ['nullable', 'integer'],
            'purchaseRequestId' => ['nullable', 'integer'],
            'documentUrl'       => ['nullable', 'string', 'max:2000'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            'isDraft'                  => ['nullable', 'boolean'],
            'sizeMeasurements'         => ['nullable', 'array'],
            'sizeMeasurements.*'       => ['nullable', 'array'],
            'sizeMeasurements.*.corte' => ['nullable', 'numeric', 'min:0'],
            'sizeMeasurements.*.termi' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
