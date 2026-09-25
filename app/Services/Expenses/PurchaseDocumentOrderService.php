<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseRequest;
use App\Models\Settings\User;
use App\Notifications\GenericEventNotification;
use App\Notifications\PurchaseOrderCreatedNotification;
use App\Services\Settings\NotificationDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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
                
                // Permitir múltiples órdenes por solicitud (entregas parciales)
            }

            $userId = Auth::id();
            $status = $data['status'] ?? 'pending';
            $isUpdatingPending = false;

            // Si existe una orden pendiente, actualizarla en lugar de crear una nueva
            $existingPendingOrder = null;
            // Si la orden viene de una solicitud y no trae buyer_user_id, heredarlo de la solicitud
            if (!empty($data['purchase_request_id']) && empty($data['buyer_user_id'])) {
                $req = PurchaseRequest::find($data['purchase_request_id']);
                if ($req && $req->buyer_user_id) {
                    $data['buyer_user_id'] = $req->buyer_user_id;
                }
            }

            // Para órdenes directas (sin solicitud), asignar buyer_user_id al usuario actual
            if (empty($data['purchase_request_id']) && empty($data['buyer_user_id'])) {
                $data['buyer_user_id'] = $userId;
            }

            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateOrderNumber();
            }
            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_ORDER);
            $order = PurchaseDocument::create([
                ...$base,
                'parent_document_id'    => $data['parent_document_id'] ?? null,
                'purchase_request_id'   => $data['purchase_request_id'] ?? null,
                'buyer_user_id'         => $data['buyer_user_id'] ?? null,
                'status'                => $status,
                'approval_status'       => $status === 'pending' ? 'pending' : 'approved',
                'approved_by_user_id'   => $status === 'pending' ? null : $userId,
                'approved_at'           => $status === 'pending' ? null : now(),
                'ordered_at'            => $data['ordered_at'] ?? now(),
                'supplier_order_number' => $data['supplier_order_number'] ?? null,
            ]);
            $this->syncItems($order, $items);

            $createdOrder = $this->loadWithRelations($order);

            $this->notifyOrderCreated($createdOrder);

            return $createdOrder;
        });
    }

    protected function notifyOrderCreated(PurchaseDocument $order): void
    {
        $creator = Auth::user();
        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->dispatch('purchase_order_created', new PurchaseOrderCreatedNotification($order, $creator?->name), ['actor' => $creator]);

        $status = $order->status instanceof \BackedEnum ? $order->status->value : $order->status;
        if ($status === 'pending') {
            $number = $order->document_number ?? (string) $order->id;
            $dispatcher->dispatch(
                'purchase_order_pending_approval',
                new GenericEventNotification(
                    'purchase_order_pending_approval',
                    'Gastos',
                    "Orden de compra #{$number} pendiente de aprobación",
                    ($creator?->name ?? 'Un usuario')." creó la orden de compra {$number}; debe aprobarse antes de facturarse.",
                    '/gastos/ordenes-compra'
                ),
                ['actor' => $creator]
            );
        }
    }

    public function convertOrderToInvoice(
        PurchaseDocument $order,
        array $invoiceData = [],
        ?array $invoiceItems = null,
        ?array $withholdings = null
    ): PurchaseDocument {
        if (! $order->isOrder()) {
            throw new \InvalidArgumentException('Solo las órdenes de compra pueden convertirse a factura.');
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
                'status'                  => $invoiceData['status'] ?? 'pending_receipt',
                'approval_status'         => $invoiceData['approval_status'] ?? 'pending',
                'supplier_invoice_number' => $invoiceData['supplier_invoice_number'] ?? null,
                'document_number'         => $invoiceData['document_number'] ?? null,
                'invoiced_at'             => $invoiceData['invoiced_at'] ?? now(),
                'order_status'            => $invoiceData['order_status'] ?? 'approved',
                'buyer_user_id'           => $order->buyer_user_id,
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
