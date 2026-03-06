<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de entrega de una orden de producción de un estado a otro
 * dentro del Control de Lotes.
 *
 * Cada vez que un encargado completa su proceso y entrega al siguiente,
 * se crea un registro con las observaciones correspondientes.
 *
 * @property int $id
 * @property int $production_order_id
 * @property string $from_process   Estado/proceso completado (ej. 'I&D')
 * @property string $to_process     Estado/proceso destino (ej. 'Logística')
 * @property string|null $observations  Observaciones o novedades
 * @property int|null $delivered_by
 * @property \Carbon\Carbon $created_at
 */
class BatchControlDelivery extends Model
{
    protected $table = 'batch_control_deliveries';

    protected $fillable = [
        'production_order_id',
        'from_process',
        'to_process',
        'observations',
        'delivered_by',
    ];

    // ─── Relaciones ───────────────────────────────────────

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function deliveredByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Settings\User::class, 'delivered_by');
    }
}
