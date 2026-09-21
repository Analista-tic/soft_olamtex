<?php

namespace App\Notifications;

use App\Models\Expenses\PurchaseRequest;
use Illuminate\Notifications\Notification;

class PurchaseRequestSubmittedNotification extends Notification
{
    public function __construct(
        protected PurchaseRequest $purchaseRequest,
        protected ?string $requesterName = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $number = $this->purchaseRequest->request_number ?? (string) $this->purchaseRequest->id;
        $target = $this->purchaseRequest->solicited_to ?? 'aprobación';

        return [
            'type'            => 'purchase_request_submitted',
            'request_id'      => $this->purchaseRequest->id,
            'document_number' => $number,
            'area'            => ucfirst($target),
            'module'          => 'Gastos',
            'user_name'       => $this->requesterName ?? 'Usuario',
            'title'           => "Nueva solicitud de compra #{$number}",
            'body'            => sprintf(
                '%s envió la solicitud %s para aprobación.',
                $this->requesterName ?? 'Un usuario',
                $number
            ),
            'url'             => '/gastos/solicitudes-compra?ver=' . $this->purchaseRequest->id,
        ];
    }
}
