<?php

namespace App\Models\Production;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Novedad / Devolución en el control de lotes.
 *
 * Permite que el responsable de un proceso reporte al estado anterior
 * que algo falta o necesita corrección. La orden se resalta visualmente
 * mientras haya novedades pendientes.
 *
 * @property int $id
 * @property int $production_order_id
 * @property string $from_process    Estado que reporta (actual)
 * @property string $to_process      Estado al que se le reporta (anterior)
 * @property string $description
 * @property string $status          pending|resolved
 * @property int $reported_by
 * @property int|null $resolved_by
 * @property \Carbon\Carbon|null $resolved_at
 * @property string|null $resolution_notes
 */
class BatchControlNovelty extends Model
{
    protected $table = 'batch_control_novelties';

    protected $fillable = [
        'production_order_id',
        'from_process',
        'to_process',
        'description',
        'status',
        'reported_by',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────────────

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ─── Helpers ──────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
