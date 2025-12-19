<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseDocument;
use Illuminate\Support\Facades\DB;

class PurchaseDocumentInvoiceService extends PurchaseDocumentService
{
    public function createInvoice(array $data, array $items = [], ?array $withholdings = null): PurchaseDocument
    {
        $data = $this->normalizeInvoicePayload($data);

        return DB::transaction(function () use ($data, $items, $withholdings) {
            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateInvoiceNumber();
            }
            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_INVOICE);

            $parentId = $data['parent_document_id'] ?? $data['purchase_order_id'] ?? null;

            $invoice = PurchaseDocument::create([
                ...$base,
                'parent_document_id'        => $parentId,
                'status'                    => $data['status'] ?? 'pending',
                'approval_status'           => $data['approval_status'] ?? 'pending',
                'supplier_invoice_number'   => $data['supplier_invoice_number'] ?? null,
                'withholdings'              => $withholdings ?? $data['withholdings'] ?? [],
                'invoiced_at'               => $data['invoiced_at'] ?? now(),
                'rejection_reason'          => $data['rejection_reason'] ?? null,
                'rejection_evidence_image'  => $data['rejection_evidence_image'] ?? null,
                'rejection_notes'           => $data['rejection_notes'] ?? null,
                'rejected_by_user_id'       => $data['rejected_by_user_id'] ?? null,
                'rejected_at'               => $data['rejected_at'] ?? null,
            ]);

            $this->syncItems($invoice, $items);

            $this->updateParentOrderStatus(
                $parentId,
                $data['order_status'] ?? 'approved',
                $data['linked_invoiced_at'] ?? $invoice->invoiced_at
            );

            return $this->loadWithRelations($invoice);
        });
    }
}
