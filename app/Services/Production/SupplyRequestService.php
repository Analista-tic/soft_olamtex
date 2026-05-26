<?php

namespace App\Services\Production;

use App\Enums\PurchaseRequestStatus;
use App\Models\Expenses\PurchaseRequest;
use App\Models\Inventory\InventoryRequest;
use App\Models\Inventory\InventoryRequestItem;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Notifications\InventoryRequestCreatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para solicitar insumos desde la Ficha Técnica.
 *
 * Flujo:
 * 1. Crea un Item tipo "draft" (status=false) bajo el grupo "Insumos"
 *    para que sea visible en el selector pero sin stock real.
 * 2. Crea una InventoryRequest vinculada al item draft para que
 *    Logística pueda gestionarla (aprobar, entregar stock, etc.).
 * 3. Crea una PurchaseRequest (Solicitud de Compra) para gestionar
 *    la adquisición del insumo, incluyendo imagen de referencia.
 * 4. Cuando Logística apruebe/entregue, el item se puede habilitar
 *    manualmente o al recibir stock.
 */
class SupplyRequestService
{
    /**
     * Crea un item borrador, una solicitud de inventario y una solicitud de compra.
     *
     * @return array{item: Item, request: InventoryRequest, purchase_request: PurchaseRequest}
     */
    public function createDraftSupply(array $data, User $requester): array
    {
        return DB::transaction(function () use ($data, $requester) {
            $subGroupId = $this->resolveSubGroupId($data['category_sub_group_id'] ?? null, $data['sub_group_name'] ?? null);

            $item = $this->createDraftItem($data, $subGroupId);

            $inventoryRequest = $this->createInventoryRequest($item, $data, $requester);

            $purchaseRequest = $this->createPurchaseRequest($item, $data, $requester);

            Log::info('SupplyRequestService: Item draft + solicitudes creados', [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'inventory_request_id' => $inventoryRequest->id,
                'inventory_request_number' => $inventoryRequest->document_number,
                'purchase_request_id' => $purchaseRequest->id,
                'purchase_request_number' => $purchaseRequest->request_number,
                'requester' => $requester->name,
                'op_id' => $data['op_id'] ?? null,
            ]);

            return [
                'item' => $item,
                'request' => $inventoryRequest->load(['items.item', 'requester']),
                'purchase_request' => $purchaseRequest,
            ];
        });
    }

    /**
     * Resuelve el ID del subgrupo de categoría.
     * Prioriza ID directo; si no existe, busca por nombre dentro del grupo "Insumos".
     */
    private function resolveSubGroupId(?int $directId, ?string $subGroupName): int
    {
        if ($directId) {
            $exists = CategorySubGroup::query()
                ->whereHas('categoryGroup', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%insumo%']))
                ->where('id', $directId)
                ->exists();

            if ($exists) {
                return $directId;
            }
        }

        if ($subGroupName) {
            $subGroup = CategorySubGroup::query()
                ->whereHas('categoryGroup', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%insumo%']))
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($subGroupName))])
                ->first();

            if ($subGroup) {
                return (int) $subGroup->id;
            }
        }

        // Fallback: primer subgrupo del grupo Insumos
        $fallback = CategorySubGroup::query()
            ->whereHas('categoryGroup', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%insumo%']))
            ->orderBy('id')
            ->first();

        if (!$fallback) {
            throw ValidationException::withMessages([
                'category' => ['No se encontró un grupo de categoría de Insumos. Contacte al administrador.'],
            ]);
        }

        return (int) $fallback->id;
    }

    /**
     * Crea el item en estado draft (status=false, item_type=draft).
     */
    private function createDraftItem(array $data, int $subGroupId): Item
    {
        $name = trim((string) ($data['name'] ?? 'Insumo solicitado'));
        $description = trim((string) ($data['description'] ?? $name));
        $unitMeasure = trim((string) ($data['unit_measure'] ?? 'und'));

        // Obtener un tax_id válido (IVA 0% o el primero disponible)
        $taxId = $this->resolveDefaultTaxId();

        return Item::create([
            'category_sub_group_id' => $subGroupId,
            'name' => $name,
            'reference' => $data['reference'] ?? null,
            'description' => $description,
            'unit_measure' => $unitMeasure !== '' ? $unitMeasure : 'und',
            'item_type' => 'draft',
            'status' => false,
            'tax_id' => $taxId,
            'negative_movement' => false,
        ]);
    }

    /**
     * Crea una solicitud de inventario vinculada al item draft.
     */
    private function createInventoryRequest(Item $item, array $data, User $requester): InventoryRequest
    {
        $documentNumber = $this->generateRequestNumber();
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $opCode = trim((string) ($data['op_code'] ?? ''));
        $observation = trim((string) ($data['observation'] ?? ''));

        $baseObservation = "Solicitud de insumo desde Ficha Técnica";
        if ($opCode !== '') {
            $baseObservation .= " - OP {$opCode}";
        }
        if ($observation !== '') {
            $baseObservation .= ". {$observation}";
        }

        $request = InventoryRequest::create([
            'document_number' => $documentNumber,
            'date' => now(),
            'status' => InventoryRequest::STATUS_REQUESTED,
            'observation' => $baseObservation,
            'created_by_user_id' => $requester->id,
        ]);

        InventoryRequestItem::create([
            'inventory_request_id' => $request->id,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'requested_quantity' => $quantity,
            'delivered_quantity' => 0,
        ]);

        // Notificar a Logística
        $this->notifyLogistics($request, $requester);

        return $request;
    }

    private function generateRequestNumber(): string
    {
        $year = now()->year;
        $prefix = "SOL-INS-{$year}-";

        $lastNumber = InventoryRequest::query()
            ->where('document_number', 'like', "{$prefix}%")
            ->orderByDesc('document_number')
            ->value('document_number');

        if ($lastNumber) {
            $lastSeq = (int) str_replace($prefix, '', $lastNumber);
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    private function resolveDefaultTaxId(): int
    {
        $tax = DB::table('taxes')
            ->where('status', true)
            ->orderBy('percentage')
            ->first();

        if (!$tax) {
            throw ValidationException::withMessages([
                'tax' => ['No hay impuestos configurados. Contacte al administrador.'],
            ]);
        }

        return (int) $tax->id;
    }

    private function notifyLogistics(InventoryRequest $request, User $creator): void
    {
        try {
            $recipients = User::role('logistica')
                ->where('id', '!=', $creator->id)
                ->get();

            if ($recipients->isEmpty()) {
                return;
            }

            $requesterArea = $this->resolveUserArea($creator);

            Notification::send(
                $recipients,
                new InventoryRequestCreatedNotification($request, $creator->name, $requesterArea)
            );
        } catch (\Throwable $e) {
            Log::warning('SupplyRequestService: No se pudo notificar a Logística', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveUserArea(User $user): ?string
    {
        if (!$user->area_id) {
            return null;
        }

        $areasSetting = \App\Models\Settings\Setting::query()
            ->where('kind', 'parameter')
            ->where('key', 'areas')
            ->first();

        $areas = is_array($areasSetting?->value) ? $areasSetting->value : [];

        foreach ($areas as $area) {
            if (is_array($area) && isset($area['id']) && (int) $area['id'] === $user->area_id) {
                return $area['name'] ?? null;
            }
        }

        return null;
    }

    /**
     * Crea una Solicitud de Compra (PurchaseRequest) vinculada al item.
     */
    private function createPurchaseRequest(Item $item, array $data, User $requester): PurchaseRequest
    {
        $opCode = trim((string) ($data['op_code'] ?? ''));
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $unitPrice = max(0, (float) ($data['unit_price'] ?? 0));
        $providerName = trim((string) ($data['provider_name'] ?? ''));
        $observation = trim((string) ($data['observation'] ?? ''));

        $description = "Solicitud de insumo desde Ficha Técnica";
        if ($opCode !== '') {
            $description .= " - OP {$opCode}";
        }
        if ($observation !== '') {
            $description .= ". {$observation}";
        }

        $total = round($unitPrice * $quantity, 2);

        $purchaseRequest = PurchaseRequest::create([
            'request_number' => $this->generatePurchaseRequestNumber(),
            'provider_name' => $providerName !== '' ? $providerName : null,
            'requester_user_id' => $requester->id,
            'date' => now(),
            'destination' => 'Producción / Ficha Técnica',
            'estimated_amount' => $total > 0 ? $total : null,
            'description' => $description,
            'items' => [
                [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'price' => $unitPrice,
                    'quantity' => $quantity,
                    'description' => trim((string) ($data['description'] ?? '')),
                    'total' => $total,
                ],
            ],
            'document_path' => $data['document_path'] ?? null,
            'status' => PurchaseRequestStatus::DRAFT,
        ]);

        return $purchaseRequest;
    }

    /**
     * Genera un número único para la solicitud de compra.
     */
    private function generatePurchaseRequestNumber(): string
    {
        $year = now()->year;
        $prefix = "SC-{$year}-";

        $lastNumber = PurchaseRequest::query()
            ->where('request_number', 'like', "{$prefix}%")
            ->orderByDesc('request_number')
            ->value('request_number');

        if ($lastNumber) {
            $lastSeq = (int) str_replace($prefix, '', $lastNumber);
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Sube imagen de referencia para la solicitud de insumo.
     */
    public function handleReferenceImageUpload($file): ?string
    {
        if (!$file) {
            return null;
        }

        $extension = $file->getClientOriginalExtension();
        $path = sprintf(
            'supply-requests/references/%s/%s.%s',
            now()->format('Y/m/d'),
            \Illuminate\Support\Str::uuid(),
            $extension
        );

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Lista los subgrupos disponibles bajo el grupo "Insumos".
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getSupplySubGroups(): array
    {
        return CategorySubGroup::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($sg) => ['id' => (int) $sg->id, 'name' => trim((string) $sg->name)])
            ->values()
            ->all();
    }

    public function getSupplyGroups(): array
    {
        return \App\Models\MasterData\CategoryGroup::query()
            ->with(['subGroups' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($g) => [
                'id'         => (int) $g->id,
                'name'       => trim((string) $g->name),
                'sub_groups' => $g->subGroups->map(fn ($sg) => [
                    'id'   => (int) $sg->id,
                    'name' => trim((string) $sg->name),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
