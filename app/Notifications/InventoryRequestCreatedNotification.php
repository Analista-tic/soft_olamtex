<?php

namespace App\Notifications;

use App\Models\Inventory\InventoryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InventoryRequestCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected InventoryRequest $inventoryRequest,
        protected ?string $requesterName = null,
        protected ?string $requesterArea = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $documentNumber = $this->inventoryRequest->document_number ?? $this->inventoryRequest->id;
        $itemsCount = $this->inventoryRequest->items()->count();

        return [
            'type' => 'inventory_request.created',
            'title' => "Nueva solicitud de insumos #{$documentNumber}",
            'body' => sprintf(
                '%s ha creado una solicitud de insumos con %d ítem(s).',
                $this->requesterName ?? 'Un usuario',
                $itemsCount
            ),
            'module' => 'Inventario',
            'url' => '/inventario/solicitudes',
            'entity_id' => $this->inventoryRequest->id,
            'area' => $this->requesterArea,
            'user_name' => $this->requesterName,
        ];
    }
}
