<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use Illuminate\Support\Facades\DB;

class PurchaseDocumentRequestService extends PurchaseDocumentService
{
    public function createRequest(array $data, array $items = []): PurchaseDocument
    {
        return DB::transaction(function () use ($data, $items) {
            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateRequestNumber();
            }

            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_REQUEST);

            $document = PurchaseDocument::create([
                ...$base,
                'status'          => $data['status'] ?? 'pending',
                'approval_status' => $data['approval_status'] ?? null,
                'requested_at'    => $data['requested_at'] ?? now(),
            ]);

            $this->syncItems($document, $items);

            return $this->loadWithRelations($document);
        });
    }

    public function convertRequestToOrder(
        PurchaseDocument $request,
        array $orderData = [],
        ?array $orderItems = null
    ): PurchaseDocument {
        return DB::transaction(function () use ($request, $orderData, $orderItems) {
            $request->update([
                'status'      => $orderData['request_status'] ?? 'processed',
                'approved_at' => $orderData['approved_at'] ?? now(),
            ]);

            if (empty($orderData['document_number'])) {
                $orderData['document_number'] = $this->generateOrderNumber();
            }
            $base = $this->buildBaseData($orderData, PurchaseDocument::TYPE_ORDER);

            $order = PurchaseDocument::create([
                ...$base,
                'parent_document_id'    => $request->id,
                'status'                => $orderData['status'] ?? 'pending',
                'ordered_at'            => $orderData['ordered_at'] ?? now(),
                'supplier_order_number' => $orderData['supplier_order_number'] ?? null,
            ]);

            $items = $orderItems ?? $request->items
                ->map(fn (PurchaseDocumentItem $item) => [
                    'item_id'        => $item->item_id,
                    'quantity'       => $item->quantity,
                    'price'          => $item->price,
                    'tax'            => $item->tax,
                    'total'          => $item->total,
                    'total_tax'      => $item->total_tax,
                    'supplier_code'  => $item->supplier_code,
                    'supplier_color' => $item->supplier_color,
                    'order_width'    => $item->order_width,
                    'description'    => $item->description,
                ])
                ->toArray();

            $this->syncItems($order, $items);

            return $this->loadWithRelations($order);
        });
    }
}
