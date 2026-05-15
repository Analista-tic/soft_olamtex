<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseDocumentOrderService extends PurchaseDocumentService
{
    public function __construct(private PurchaseDocumentInvoiceService $invoiceService)
    {
    }

    public function createOrder(array $data, array $items = []): PurchaseDocument
    {
        return DB::transaction(function () use ($data, $items) {
            $purchaseRequest = null;
            
            // Validar y bloquear la solicitud si existe
            if (!empty($data['purchase_request_id'])) {
                // Usar lockForUpdate para evitar condiciones de carrera
                $purchaseRequest = PurchaseRequest::where('id', $data['purchase_request_id'])
                    ->lockForUpdate()
                    ->first();
                
                if (!$purchaseRequest) {
                    throw new \RuntimeException('La solicitud de compra especificada no existe.');
                }
                
                // Validar que la solicitud esté aprobada (no procesada)
                if (!$purchaseRequest->isApproved()) {
                    if ($purchaseRequest->isProcessed()) {
                        throw new \RuntimeException('Esta solicitud ya fue procesada y tiene una orden asociada.');
                    }
                    throw new \RuntimeException('Solo se pueden crear órdenes desde solicitudes aprobadas.');
                }
                
                // Buscar si ya existe una orden pendiente para esta solicitud
                $existingOrder = PurchaseDocument::where('purchase_request_id', $data['purchase_request_id'])
                    ->where('document_type', PurchaseDocument::TYPE_ORDER)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder && $existingOrder->status !== 'pending') {
                    throw new \RuntimeException('Ya existe una orden de compra activa para esta solicitud.');
                }
            }

            $userId = Auth::id();
            $status = $data['status'] ?? 'approved';
            $isUpdatingPending = false;

            // Si existe una orden pendiente, actualizarla en lugar de crear una nueva
            $existingPendingOrder = null;
            if (!empty($data['purchase_request_id'])) {
                $existingPendingOrder = PurchaseDocument::where('purchase_request_id', $data['purchase_request_id'])
                    ->where('document_type', PurchaseDocument::TYPE_ORDER)
                    ->where('status', 'pending')
                    ->whereNull('deleted_at')
                    ->first();
            }

            if ($existingPendingOrder) {
                $isUpdatingPending = true;
                if (empty($data['document_number'])) {
                    $data['document_number'] = $existingPendingOrder->document_number;
                }
                $base = $this->buildBaseData($data, PurchaseDocument::TYPE_ORDER);
                $existingPendingOrder->update([
                    ...$base,
                    'parent_document_id'    => $data['parent_document_id'] ?? $existingPendingOrder->parent_document_id,
                    'status'                => $status,
                    'approval_status'       => $status === 'pending' ? 'pending' : 'approved',
                    'approved_by_user_id'   => $status === 'pending' ? null : $userId,
                    'approved_at'           => $status === 'pending' ? null : now(),
                    'ordered_at'            => $data['ordered_at'] ?? now(),
                    'supplier_order_number' => $data['supplier_order_number'] ?? $existingPendingOrder->supplier_order_number,
                ]);
                $this->syncItems($existingPendingOrder, $items);
                $order = $existingPendingOrder;
            } else {
                if (empty($data['document_number'])) {
                    $data['document_number'] = $this->generateOrderNumber();
                }
                $base = $this->buildBaseData($data, PurchaseDocument::TYPE_ORDER);
                $order = PurchaseDocument::create([
                    ...$base,
                    'parent_document_id'    => $data['parent_document_id'] ?? null,
                    'purchase_request_id'   => $data['purchase_request_id'] ?? null,
                    'status'                => $status,
                    'approval_status'       => $status === 'pending' ? 'pending' : 'approved',
                    'approved_by_user_id'   => $status === 'pending' ? null : $userId,
                    'approved_at'           => $status === 'pending' ? null : now(),
                    'ordered_at'            => $data['ordered_at'] ?? now(),
                    'supplier_order_number' => $data['supplier_order_number'] ?? null,
                ]);
                $this->syncItems($order, $items);
            }

            // Marcar la solicitud como procesada al crear o confirmar la orden
            if ($purchaseRequest && $status !== 'pending') {
                $purchaseRequest->markAsProcessed(Auth::id());
            }

            return $this->loadWithRelations($order);
        });
    }

    public function convertOrderToInvoice(
        PurchaseDocument $order,
        array $invoiceData = [],
        ?array $invoiceItems = null,
        ?array $withholdings = null
    ): PurchaseDocument {
        if (! $order->isOrder()) {
            throw new \InvalidArgumentException('Solo las ordenes de compra pueden convertirse a factura.');
        }

        return DB::transaction(function () use ($order, $invoiceData, $invoiceItems, $withholdings) {
            $order->refresh()->load('items');

            if ($order->children()->where('document_type', PurchaseDocument::TYPE_INVOICE)->exists()) {
                throw new \RuntimeException('La orden de compra ya tiene una factura vinculada.');
            }

            $payload = [
                'parent_document_id'      => $order->id,
                'contact_id'              => $order->contact_id,
                'provider_name'           => $order->provider_name,
                'provider_phone'          => $order->provider_phone,
                'date'                    => $invoiceData['date'] ?? now()->toDateString(),
                'delivery_date'           => $invoiceData['delivery_date'] ?? $order->delivery_date,
                'description'             => $invoiceData['description'] ?? $order->description,
                'status'                  => $invoiceData['status'] ?? 'pending',
                'approval_status'         => $invoiceData['approval_status'] ?? 'pending',
                'supplier_invoice_number' => $invoiceData['supplier_invoice_number'] ?? null,
                'document_number'         => $invoiceData['document_number'] ?? null,
                'invoiced_at'             => $invoiceData['invoiced_at'] ?? now(),
                'order_status'            => $invoiceData['order_status'] ?? 'approved',
            ];

            $items = $invoiceItems ?? $order->items
                ->map(fn (PurchaseDocumentItem $item) => [
                    'item_id'        => $item->item_id,
                    'item_name'      => $item->item_name,
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

            return $this->invoiceService->createInvoice($payload, $items, $withholdings ?? []);
        });
    }
}
