<?php

namespace App\Notifications;

use App\Models\Expenses\PurchaseDocument;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Notifications\Notification;

class PurchaseInvoiceDifferenceRejectedNotification extends Notification
{
    use UsesConfiguredChannels;

    public function __construct(protected PurchaseDocument $invoice) {}

    public function toArray(object $notifiable): array
    {
        $number = $this->invoice->document_number ?? (string) $this->invoice->id;

        return [
            'type' => 'invoice_difference_rejected',
            'invoice_id' => $this->invoice->id,
            'document_number' => $number,
            'area' => 'Compras',
            'module' => 'Gastos',
            'title' => "Diferencia rechazada — Factura #{$number}",
            'body' => sprintf(
                'La diferencia registrada en la factura %s fue rechazada. Motivo: %s. Por favor gestiona la nota crédito correspondiente.',
                $number,
                $this->invoice->approval_notes ?: 'sin motivo indicado'
            ),
            'url' => '/gastos/facturas-compra',
        ];
    }
}
