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
            // Aceptar parent_document_id o purchase_order_id (frontend histórico)
            $parentId = $data['parent_document_id'] ?? $data['purchase_order_id'] ?? null;
            $parentId = $parentId ? (int) $parentId : null;
            
            // Validar que no exista ya una factura para esta orden
            if (!empty($parentId)) {
                $parentOrder = PurchaseDocument::find($parentId);
                if (!$parentOrder) {
                    throw new \RuntimeException('La orden de compra especificada no existe.');
                }
                
                if ($parentOrder->document_type !== PurchaseDocument::TYPE_ORDER) {
                    throw new \RuntimeException('El documento padre debe ser una orden de compra.');
                }
                
                // Verificar si ya existe una factura para esta orden
                $existingInvoice = PurchaseDocument::where('parent_document_id', $parentId)
                    ->where('document_type', PurchaseDocument::TYPE_INVOICE)
                    ->whereNull('deleted_at')
                    ->first();
                
                if ($existingInvoice) {
                    throw new \RuntimeException('Ya existe una factura de compra para esta orden. No se pueden crear múltiples facturas desde la misma orden.');
                }
                
                // Validar que la orden esté aprobada
                if (!$parentOrder->isApproved()) {
                    throw new \RuntimeException('Solo se pueden crear facturas desde órdenes aprobadas.');
                }
            }

            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateInvoiceNumber();
            }
            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_INVOICE);

            $invoice = PurchaseDocument::create([
                ...$base,
                'parent_document_id'        => $parentId,
                'status'                    => $data['status'] ?? 'pending_receipt',
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

            // Actualizar el estado de la orden a "invoiced" cuando se crea una factura
            if (!empty($parentId)) {
                $parentOrder = PurchaseDocument::find($parentId);
                if ($parentOrder && $parentOrder->document_type === PurchaseDocument::TYPE_ORDER) {
                    $parentOrder->update([
                        'status' => \App\Enums\PurchaseOrderStatus::INVOICED->value,
                    ]);
                }
            }

            return $this->loadWithRelations($invoice);
        });
    }
}
