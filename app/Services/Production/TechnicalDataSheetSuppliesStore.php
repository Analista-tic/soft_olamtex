<?php

namespace App\Services\Production;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Textil\FabricRoll;
use App\Models\MasterData\Item;
use Illuminate\Support\Facades\Storage;

class TechnicalDataSheetSuppliesStore
{
    public function getPath(int $opId): string
    {
        return "production/op_{$opId}_supplies.json";
    }

    public function read(int $opId): array
    {
        $fromDb = $this->readFromDb($opId);
        if ($fromDb !== null) {
            return $fromDb;
        }

        $path = $this->getPath($opId);
        if (!Storage::exists($path)) {
            return [];
        }

        $raw = Storage::get($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    public function write(int $opId, array $items): void
    {
        $items = array_values($items);

        $sheet = \App\Models\Production\TechnicalSheet::firstOrCreate(
            ['production_order_id' => $opId],
            ['status' => \App\Models\Production\TechnicalSheet::STATUS_DRAFT],
        );
        \App\Models\Production\TechnicalSheetSupply::updateOrCreate(
            ['technical_sheet_id' => $sheet->id],
            ['items' => $items],
        );

        Storage::put($this->getPath($opId), json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function readFromDb(int $opId): ?array
    {
        $sheet = \App\Models\Production\TechnicalSheet::where('production_order_id', $opId)->first();
        if (!$sheet) {
            return null;
        }

        $snapshot = $sheet->suppliesSnapshot()->first();
        if (!$snapshot) {
            return null;
        }

        return is_array($snapshot->items) ? array_values($snapshot->items) : [];
    }

    /**
     * Retorna la ultima compra registrada para un item de insumo.
     *
     * @return array{
     *   provider: array{id:int|null,name:string|null}|null,
     *   price: float|null,
     *   currency: string,
     *   date: string|null,
     *   source: string
     * }|null
     */
    public function getLastPurchaseForItem(int $itemId): ?array
    {
        $row = PurchaseDocumentItem::query()
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_items.purchase_document_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'purchase_documents.contact_id')
            ->where('purchase_document_items.item_id', $itemId)
            ->where('purchase_documents.document_type', PurchaseDocument::TYPE_INVOICE)
            ->where('purchase_documents.status', 'approved')
            ->whereNull('purchase_documents.deleted_at')
            ->orderByDesc('purchase_documents.approved_at')
            ->orderByDesc('purchase_documents.date')
            ->orderByDesc('purchase_documents.id')
            ->selectRaw('
                purchase_documents.contact_id as provider_id,
                COALESCE(contacts.full_name, purchase_documents.provider_name) as provider_name,
                purchase_document_items.price as unit_price,
                purchase_documents.date as invoice_date
            ')
            ->first();

        if (!$row) {
            return null;
        }

        $providerId = $row->provider_id !== null ? (int) $row->provider_id : null;
        $providerName = $row->provider_name !== null ? trim((string) $row->provider_name) : null;

        return [
            'provider' => $providerId !== null || $providerName !== null
                ? [
                    'id' => $providerId,
                    'name' => $providerName,
                ]
                : null,
            'price' => $row->unit_price !== null ? (float) $row->unit_price : null,
            'currency' => 'COP',
            'date' => $row->invoice_date !== null ? (string) $row->invoice_date : null,
            'source' => 'invoice',
        ];
    }

    /**
     * Retorna metadata derivada del item para ficha tecnica:
     * - ultima compra (proveedor/precio/fecha)
     * - ancho (desde inventario de rollos o item.extra_fields)
     *
     * @return array{
     *   itemId:int,
     *   provider:array{id:int|null,name:string|null}|null,
     *   price:float|null,
     *   currency:string,
     *   date:string|null,
     *   invoiceNumber:string|null,
     *   width:float|null,
     *   widthUnit:string|null,
     *   source:array{price:string,width:string}
     * }
     */
    public function getItemMeta(int $itemId): array
    {
        $lastPurchase = $this->getLastPurchaseForItemWithInvoiceNumber($itemId);
        $widthMeta = $this->resolveWidthMeta($itemId);

        return [
            'itemId' => $itemId,
            'provider' => $lastPurchase['provider'],
            'price' => $lastPurchase['price'],
            'currency' => 'COP',
            'date' => $lastPurchase['date'],
            'invoiceNumber' => $lastPurchase['invoiceNumber'],
            'width' => $widthMeta['width'],
            'widthUnit' => $widthMeta['widthUnit'],
            'source' => [
                'price' => $lastPurchase['source'],
                'width' => $widthMeta['source'],
            ],
        ];
    }

    /**
     * @return array{
     *   provider:array{id:int|null,name:string|null}|null,
     *   price:float|null,
     *   date:string|null,
     *   invoiceNumber:string|null,
     *   source:string
     * }
     */
    private function getLastPurchaseForItemWithInvoiceNumber(int $itemId): array
    {
        $row = PurchaseDocumentItem::query()
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_items.purchase_document_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'purchase_documents.contact_id')
            ->where('purchase_document_items.item_id', $itemId)
            ->where('purchase_documents.document_type', PurchaseDocument::TYPE_INVOICE)
            ->where('purchase_documents.status', 'approved')
            ->whereNull('purchase_documents.deleted_at')
            ->orderByDesc('purchase_documents.approved_at')
            ->orderByDesc('purchase_documents.date')
            ->orderByDesc('purchase_documents.id')
            ->selectRaw('
                purchase_documents.contact_id as provider_id,
                COALESCE(contacts.full_name, purchase_documents.provider_name) as provider_name,
                purchase_document_items.price as unit_price,
                purchase_documents.date as invoice_date,
                purchase_documents.document_number as invoice_number
            ')
            ->first();

        if (!$row) {
            return [
                'provider' => null,
                'price' => null,
                'date' => null,
                'invoiceNumber' => null,
                'source' => 'unknown',
            ];
        }

        $providerId = $row->provider_id !== null ? (int) $row->provider_id : null;
        $providerName = $row->provider_name !== null ? trim((string) $row->provider_name) : null;

        return [
            'provider' => $providerId !== null || $providerName !== null
                ? [
                    'id' => $providerId,
                    'name' => $providerName,
                ]
                : null,
            'price' => $row->unit_price !== null ? (float) $row->unit_price : null,
            'date' => $row->invoice_date !== null ? (string) $row->invoice_date : null,
            'invoiceNumber' => $row->invoice_number !== null ? (string) $row->invoice_number : null,
            'source' => 'invoice',
        ];
    }

    /**
     * @return array{width:float|null,widthUnit:string|null,source:string}
     */
    private function resolveWidthMeta(int $itemId): array
    {
        $inventoryWidth = FabricRoll::query()
            ->join('purchase_document_items', 'purchase_document_items.id', '=', 'fabric_rolls.purchase_document_item_id')
            ->where('purchase_document_items.item_id', $itemId)
            ->whereNotNull('fabric_rolls.roll_width')
            ->where('fabric_rolls.roll_width', '>', 0)
            ->orderByDesc('fabric_rolls.entry_date')
            ->orderByDesc('fabric_rolls.id')
            ->value('fabric_rolls.roll_width');

        if ($inventoryWidth !== null) {
            return [
                'width' => (float) $inventoryWidth,
                'widthUnit' => 'm',
                'source' => 'inventory',
            ];
        }

        $item = Item::query()->find($itemId);
        if ($item) {
            $fromItem = $this->extractWidthFromItem($item);
            if ($fromItem !== null) {
                return [
                    'width' => $fromItem['width'],
                    'widthUnit' => $fromItem['unit'],
                    'source' => 'item',
                ];
            }
        }

        return [
            'width' => null,
            'widthUnit' => null,
            'source' => 'unknown',
        ];
    }

    /**
     * @return array{width:float,unit:string}|null
     */
    private function extractWidthFromItem(Item $item): ?array
    {
        $extra = is_array($item->extra_fields) ? $item->extra_fields : [];
        foreach ($extra as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalizedKey = $this->normalizeText($key);
            if (!str_contains($normalizedKey, 'ancho')) {
                continue;
            }

            $parsed = $this->parseNumeric($value);
            if ($parsed === null || $parsed <= 0) {
                continue;
            }

            $unit = str_contains($normalizedKey, 'cm') ? 'cm' : 'm';
            return [
                'width' => $parsed,
                'unit' => $unit,
            ];
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $text = mb_strtolower(trim($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) {
            $text = mb_strtolower(trim($ascii));
        }
        return preg_replace('/\s+/', ' ', $text) ?: '';
    }

    private function parseNumeric(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $parsed = (float) $value;
            return $parsed > 0 ? $parsed : null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $sanitized = preg_replace('/[^0-9,.\-]/', '', $raw);
        if (!is_string($sanitized) || $sanitized === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $sanitized);
        if (!is_numeric($normalized)) {
            return null;
        }

        $parsed = (float) $normalized;
        return $parsed > 0 ? $parsed : null;
    }
}
