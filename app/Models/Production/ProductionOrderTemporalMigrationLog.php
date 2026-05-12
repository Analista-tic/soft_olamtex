<?php

namespace App\Models\Production;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de trazabilidad del backfill de Mes/Año.
 *
 * Permite auditar cómo se resolvió cada orden en el proceso de migración
 * y detectar ambiguos para revisión manual.
 */
class ProductionOrderTemporalMigrationLog extends Model
{
    protected $table = 'production_order_temporal_migration_logs';

    protected $fillable = [
        'production_order_id',
        'legacy_season',
        'legacy_dispatch_month',
        'resolved_season_month',
        'resolved_season_year',
        'resolved_dispatch_month_num',
        'resolved_dispatch_year',
        'resolution_source',
        'is_ambiguous',
        'notes',
        'resolved_by_user_id',
    ];

    protected $casts = [
        'is_ambiguous'           => 'boolean',
        'resolved_season_month'  => 'integer',
        'resolved_season_year'   => 'integer',
        'resolved_dispatch_month_num' => 'integer',
        'resolved_dispatch_year' => 'integer',
    ];

    // ─── Relaciones ───────────────────────────────────────

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    // ─── Helpers ──────────────────────────────────────────

    public function isAmbiguous(): bool
    {
        return $this->is_ambiguous;
    }

    public function isResolved(): bool
    {
        return $this->resolution_source === 'manual' || ! $this->is_ambiguous;
    }
}
