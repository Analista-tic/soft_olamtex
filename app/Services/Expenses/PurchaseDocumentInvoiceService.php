<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseDocumentInvoiceService extends PurchaseDocumentService
{
    public function getInvoiceNotesSummary(PurchaseDocument $invoice): array
    {
        $creditTotal = 0.0;
        $debitTotal = 0.0;

        $notes = $invoice->children()
            ->whereIn('document_type', [PurchaseDocument::TYPE_CREDIT_NOTE, PurchaseDocument::TYPE_DEBIT_NOTE])
            ->with(['items:id,purchase_document_id,total,total_tax'])
            ->get(['id', 'document_type']);

        foreach ($notes as $note) {
            $noteTotal = (float) $note->items->sum(function ($item) {
                return ((float) ($item->total ?? 0)) + ((float) ($item->total_tax ?? 0));
            });

            if ($note->document_type === PurchaseDocument::TYPE_DEBIT_NOTE) {
                $debitTotal += $noteTotal;
                continue;
            }

            $creditTotal += $noteTotal;
        }

        $invoiceTotal = (float) $invoice->items()->sum(DB::raw('COALESCE(total, 0) + COALESCE(total_tax, 0)'));
        $netAdjustment = $debitTotal - $creditTotal;
        $balanceAfterNotes = $invoiceTotal + $netAdjustment;

        return [
            'credit_total' => round($creditTotal, 2),
            'debit_total' => round($debitTotal, 2),
            'net_adjustment' => round($netAdjustment, 2),
            'invoice_total' => round($invoiceTotal, 2),
            'balance_after_notes' => round($balanceAfterNotes, 2),
        ];
    }

    public function getLinkedCreditDebitNotes(PurchaseDocument $invoice): Collection
    {
        return $invoice->children()
            ->whereIn('document_type', [PurchaseDocument::TYPE_CREDIT_NOTE, PurchaseDocument::TYPE_DEBIT_NOTE])
            ->with(['items:id,purchase_document_id,total,total_tax'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get(['id', 'document_number', 'document_type', 'date', 'reason', 'status'])
            ->map(function (PurchaseDocument $note) {
                $total = (float) $note->items->sum(function ($item) {
                    return ((float) ($item->total ?? 0)) + ((float) ($item->total_tax ?? 0));
                });

                return [
                    'id' => $note->id,
                    'document_number' => $note->document_number,
                    'document_type' => $note->document_type,
                    'date' => $note->date?->format('Y-m-d'),
                    'reason' => $note->reason,
                    'status' => $note->status,
                    'total' => round($total, 2),
                ];
            })
            ->values();
    }

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
