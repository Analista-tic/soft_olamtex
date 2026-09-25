<?php

namespace App\Notifications;

use App\Models\Production\BatchControlNovelty;
use App\Models\Production\ProductionOrder;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Notifications\Notification;

class BatchControlNoveltyNotification extends Notification
{
    use UsesConfiguredChannels;

    public function __construct(
        protected BatchControlNovelty $novelty,
        protected ProductionOrder $order,
        protected string $reporterName,
    ) {}

    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'batch_control_novelty',
            'module' => 'Control de Lotes',
            'novelty_id' => $this->novelty->id,
            'order_id' => $this->order->id,
            'op_code' => $this->order->op_code ?? $this->order->id,
            'from_process' => $this->novelty->from_process,
            'to_process' => $this->novelty->to_process,
            'description' => $this->novelty->description,
            'reporter' => $this->reporterName,
            'reported_at' => $this->novelty->created_at?->toDateTimeString(),
            'title' => "Novedad en lote {$this->order->op_code}: {$this->novelty->from_process}",
            'body' => "Reportado por {$this->reporterName} en {$this->novelty->from_process} → {$this->novelty->to_process}.",
            'url' => '/control-de-lotes',
            'area' => null,
        ];
    }
}
