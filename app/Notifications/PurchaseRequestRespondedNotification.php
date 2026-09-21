<?php

namespace App\Notifications;

use App\Models\Expenses\PurchaseRequest;
use Illuminate\Notifications\Notification;

class PurchaseRequestRespondedNotification extends Notification
{
    public function __construct(
        protected PurchaseRequest $purchaseRequest,
        protected string $action, // 'approved' | 'rejected' | 'cancelled'
        protected ?string $responderName = null,
        protected ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $number = $this->purchaseRequest->request_number ?? (string) $this->purchaseRequest->id;

        $actionLabels = [
            'approved'  => 'aprobada',
            'rejected'  => 'rechazada',
            'cancelled' => 'cancelada',
        ];
        $label = $actionLabels[$this->action] ?? $this->action;

        $isRequester = (int) $notifiable->getKey() === (int) $this->purchaseRequest->requester_user_id;

        $body = sprintf(
            '%s ha %s %s solicitud %s.',
            $this->responderName ?? 'Un usuario',
            $label,
            $isRequester ? 'tu' : 'la',
            $number
        );
        if ($this->reason) {
            $body .= " Motivo: {$this->reason}";
        }

        return [
            'type'            => 'purchase_request_responded',
            'request_id'      => $this->purchaseRequest->id,
            'document_number' => $number,
            'action'          => $this->action,
            'area'            => 'Compras',
            'module'          => 'Gastos',
            'user_name'       => $this->responderName ?? 'Usuario',
            'title'           => "Solicitud #{$number} {$label}",
            'body'            => $body,
            'url'             => '/gastos/solicitudes-compra?ver=' . $this->purchaseRequest->id,
        ];
    }
}
