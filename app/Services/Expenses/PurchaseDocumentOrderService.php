<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use Illuminate\Support\Facades\DB;

class PurchaseDocumentOrderService extends PurchaseDocumentService
{
    public function createOrder(array $data, array $items = []): PurchaseDocument
    {
        return DB::transaction(function () use ($data, $items) {
            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateOrderNumber();
            }
            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_ORDER);

            $order = PurchaseDocument::create([
                ...$base,
                'parent_document_id'    => $data['parent_document_id'] ?? null,
                'status'                => $data['status'] ?? 'pending',
                'ordered_at'            => $data['ordered_at'] ?? now(),
                'supplier_order_number' => $data['supplier_order_number'] ?? null,
            ]);

            $this->syncItems($order, $items);

            return $this->loadWithRelations($order);
        });
    }

    public function convertOrderToInvoice(
        PurchaseDocument $order,
        array $invoiceData = [],
        ?array $invoiceItems = null,
        ?array $withholdings = null
    ): PurchaseDocument {
        return DB::transaction(function () use ($order, $invoiceData, $invoiceItems, $withholdings) {
            $data = $this->normalizeInvoicePayload($invoiceData);

            $order->update([
                'status'      => $data['order_status'] ?? 'sent',
                'invoiced_at' => $data['linked_invoiced_at'] ?? now(),
            ]);

            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateInvoiceNumber();
            }
            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_INVOICE);

            $invoice = PurchaseDocument::create([
                ...$base,
                'parent_document_id'        => $order->id,
                'status'                    => $data['status'] ?? 'pending',
                'approval_status'           => $data['approval_status'] ?? 'pending',
                'invoiced_at'               => $data['invoiced_at'] ?? now(),
                'supplier_invoice_number'   => $data['supplier_invoice_number'] ?? null,
                'withholdings'              => $withholdings ?? $data['withholdings'] ?? [],
                'rejection_reason'          => $data['rejection_reason'] ?? null,
                'rejection_evidence_image'  => $data['rejection_evidence_image'] ?? null,
                'rejection_notes'           => $data['rejection_notes'] ?? null,
                'rejected_by_user_id'       => $data['rejected_by_user_id'] ?? null,
                'rejected_at'               => $data['rejected_at'] ?? null,
            ]);

            $items = $invoiceItems ?? $order->items
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

            $this->syncItems($invoice, $items);

            $this->updateParentOrderStatus(
                $order->id,
                $data['order_status'] ?? 'approved',
                $data['linked_invoiced_at'] ?? $invoice->invoiced_at
            );

            return $this->loadWithRelations($invoice);
        });
    }
}
