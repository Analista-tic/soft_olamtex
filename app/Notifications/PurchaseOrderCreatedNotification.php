<?php

namespace App\Notifications;

use App\Models\Expenses\PurchaseDocument;
use Illuminate\Notifications\Notification;

class PurchaseOrderCreatedNotification extends Notification
{
    public function __construct(
        protected PurchaseDocument $order,
        protected ?string $creatorName = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $number = $this->order->document_number ?? (string) $this->order->id;

        return [
            'type'            => 'purchase_order_created',
            'order_id'        => $this->order->id,
            'document_number' => $number,
            'area'            => 'Contabilidad',
            'module'          => 'Gastos',
            'user_name'       => $this->creatorName ?? 'Usuario',
            'title'           => "Nueva orden de compra #{$number}",
            'body'            => sprintf(
                '%s creó la orden de compra %s. Proceder a generar la factura.',
                $this->creatorName ?? 'Un usuario',
                $number
            ),
            'url'             => '/gastos/ordenes-compra',
        ];
    }
}
