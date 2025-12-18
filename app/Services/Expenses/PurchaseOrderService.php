<?php

namespace App\Services\Expenses;

use App\Mail\PurchaseOrderMail;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseInvoice;
use App\Models\Expenses\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderService
{
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = PurchaseOrder::with(['contact', 'items.item']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('supplier_order_number', 'like', "%{$search}%");
        }

        if (!empty($filters['status']) && strtolower($filters['status']) !== 'all') {
            $query->where('status', strtolower($filters['status']));
        }

        if (!empty($filters['provider_id']) && $filters['provider_id'] !== 'all') {
            $query->where('contact_id', $filters['provider_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);
    }

    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $order = PurchaseOrder::create($this->mapOrderData($data));
            $this->syncItems($order, $data['items'] ?? []);
            return $order->load('items.item', 'contact');
        });
    }

    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update($this->mapOrderData($data));
            $this->syncItems($purchaseOrder, $data['items'] ?? []);
            return $purchaseOrder->load('items.item', 'contact');
        });
    }

    public function delete(PurchaseOrder $purchaseOrder): void
    {
        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->items()->delete();
            $purchaseOrder->delete();
        });
    }

    public function loadWithRelations(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder->load(['contact', 'items.item']);
    }

    public function sendPdf(PurchaseDocument $purchaseOrder): void
    {
        $purchaseOrder->load(['contact', 'items.item']);

        $email = $purchaseOrder->contact?->email;
        if (empty($email)) {
            throw new \RuntimeException('El proveedor no tiene correo electrónico registrado.');
        }

        $pdf = Pdf::loadView('pdf.purchase_order', ['order' => $purchaseOrder]);

        Mail::to($email)->send(new PurchaseOrderMail($purchaseOrder, $pdf->output()));

        $purchaseOrder->email_sent = true;
        $purchaseOrder->save();
    }

    public function convertToInvoice(PurchaseOrder $purchaseOrder): PurchaseInvoice
    {
        $purchaseOrder->load(['items', 'contact', 'invoice']);

        if ($purchaseOrder->invoice) {
            throw new \RuntimeException('Esta orden de compra ya tiene una factura asociada.');
        }

        return DB::transaction(function () use ($purchaseOrder) {
            $invoice = PurchaseInvoice::create([
                'contact_id' => $purchaseOrder->contact_id,
                'purchase_order_id' => $purchaseOrder->id,
                'date' => $purchaseOrder->date,
                'delivery_date' => $purchaseOrder->delivery_date,
                'supplier_invoice_number' => $purchaseOrder->supplier_order_number,
                'description' => $purchaseOrder->description,
                'status' => 'pending',
            ]);

            foreach ($purchaseOrder->items as $item) {
                $invoice->items()->create([
                    'item_id' => $item->item_id,
                    'price' => $item->price,
                    'tax' => $item->tax,
                    'quantity' => $item->quantity,
                    'description' => $item->description,
                    'supplier_code' => $item->supplier_code,
                    'supplier_color' => $item->supplier_color,
                    'order_width' => $item->order_width,
                    'total' => $item->total,
                    'total_tax' => $item->total_tax ?? 0,
                ]);
            }

            $purchaseOrder->status = 'invoiced';
            $purchaseOrder->save();

            return $invoice->load('items', 'contact', 'purchaseOrder');
        });
    }

    protected function mapOrderData(array $data): array
    {
        return [
            'contact_id' => $data['contact_id'],
            'date' => $data['date'],
            'delivery_date' => $data['delivery_date'],
            'supplier_order_number' => $data['supplier_order_number'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'pending',
        ];
    }

    protected function syncItems(PurchaseOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $order->items()->create([
                'item_id' => $item['item_id'],
                'price' => $item['price'],
                'tax' => $item['tax'],
                'quantity' => $item['quantity'],
                'description' => $item['description'] ?? null,
                'supplier_code' => $item['supplier_code'] ?? null,
                'supplier_color' => $item['supplier_color'] ?? null,
                'order_width' => $item['order_width'] ?? null,
                'total' => $item['total'],
            ]);
        }
    }
}
