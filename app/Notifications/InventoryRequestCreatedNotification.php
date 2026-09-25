<?php

namespace App\Notifications;

use App\Models\Inventory\InventoryRequest;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Notifications\Notification;

class InventoryRequestCreatedNotification extends Notification
{
    use UsesConfiguredChannels;

    public function __construct(
        protected InventoryRequest $inventoryRequest,
        protected ?string $requesterName = null,
        protected ?string $requesterArea = null
    ) {}

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $documentNumber = $this->inventoryRequest->document_number ?? (string) $this->inventoryRequest->id;

        return [
            'type' => 'inventory_request',
            'request_id' => $this->inventoryRequest->id,
            'document_number' => $documentNumber,
            'area' => 'Logística',
            'module' => 'Inventario',
            'user_name' => $this->requesterName ?? 'Usuario',
            'message' => "Nueva solicitud de insumos {$documentNumber}",
            'title' => "Nueva solicitud de insumos #{$documentNumber}",
            'body' => sprintf(
                '%s ha creado una nueva solicitud de insumos.',
                $this->requesterName ?? 'Un usuario'
            ),
            'url' => '/inventario/solicitudes-internas',
        ];
    }
}
