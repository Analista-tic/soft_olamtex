<?php

namespace App\Notifications;

use App\Models\Expenses\PurchaseDocument;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Notifications\Notification;

class PurchaseInvoiceDifferenceNotification extends Notification
{
    use UsesConfiguredChannels;

    public function __construct(
        protected PurchaseDocument $invoice,
        protected ?string $requesterName = null
    ) {}

    public function toArray(object $notifiable): array
    {
        $documentNumber = $this->invoice->document_number ?? (string) $this->invoice->id;

        return [
            'type'            => 'invoice_difference',
            'invoice_id'      => $this->invoice->id,
            'document_number' => $documentNumber,
            'area'            => 'Compras',
            'module'          => 'Gastos',
            'user_name'       => $this->requesterName ?? 'Usuario',
            'message'         => "Diferencia en factura {$documentNumber} requiere aprobación",
            'title'           => "Aprobación requerida — Factura #{$documentNumber}",
            'body'            => sprintf(
                '%s registró una diferencia en la factura %s y requiere aprobación.',
                $this->requesterName ?? 'Un usuario',
                $documentNumber
            ),
            'url'             => '/gastos/facturas-compra?approval_status=pending',
        ];
    }
}
