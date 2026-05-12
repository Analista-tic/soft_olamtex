<?php

namespace App\Console\Commands;

use App\Helpers\SeasonParser;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductionOrderTemporalMigrationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill canónico de temporada y despacho (Fase B del Plan Mes/Año).
 *
 * Parsea season / dispatch_month legados y rellena
 * season_month, season_year, dispatch_month_num, dispatch_year.
 *
 * Es idempotente: reprocesa solo registros sin columnas canónicas,
 * o todos si se pasa --force.
 *
 * Uso:
 *   php artisan production:backfill-season
 *   php artisan production:backfill-season --force
 *   php artisan production:backfill-season --dry-run
 */
class BackfillSeasonCanonicalCommand extends Command
{
    protected $signature = 'production:backfill-season
                            {--force : Re-procesa todos los registros aunque ya tengan canónico}
                            {--dry-run : Solo muestra el reporte sin persistir}';

    protected $description = 'Rellena season_month/year y dispatch_month_num/year desde strings legados';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force  = $this->option('force');

        $query = ProductionOrder::withTrashed();
        if (! $force) {
            $query->whereNull('season_month');
        }

        $total     = $query->count();
        $resolved  = 0;
        $ambiguous = 0;

        $this->info("Procesando {$total} registros" . ($dryRun ? ' [dry-run]' : '') . '...');

        $query->chunkById(200, function ($orders) use ($dryRun, &$resolved, &$ambiguous) {
            foreach ($orders as $order) {
                [$seasonData, $dispatchData, $source, $isAmbiguous, $notes] =
                    $this->resolveOrder($order);

                if (! $dryRun) {
                    DB::transaction(function () use ($order, $seasonData, $dispatchData, $source, $isAmbiguous, $notes) {
                        $order->season_month       = $seasonData['month'];
                        $order->season_year        = $seasonData['year'];
                        $order->dispatch_month_num = $dispatchData['month'];
                        $order->dispatch_year      = $dispatchData['year'];
                        $order->saveQuietly();   // sin disparar observers

                        // Upsert del log de trazabilidad
                        ProductionOrderTemporalMigrationLog::updateOrCreate(
                            ['production_order_id' => $order->id],
                            [
                                'legacy_season'           => $order->season,
                                'legacy_dispatch_month'   => $order->dispatch_month,
                                'resolved_season_month'   => $seasonData['month'],
                                'resolved_season_year'    => $seasonData['year'],
                                'resolved_dispatch_month_num' => $dispatchData['month'],
                                'resolved_dispatch_year'  => $dispatchData['year'],
                                'resolution_source'       => $source,
                                'is_ambiguous'            => $isAmbiguous,
                                'notes'                   => $notes,
                            ]
                        );
                    });
                }

                if ($isAmbiguous) {
                    $ambiguous++;
                    $this->warn("  [AMBIGUO] OP#{$order->id} season='{$order->season}' dispatch='{$order->dispatch_month}' → {$notes}");
                } else {
                    $resolved++;
                }
            }
        });

        $this->info("Resueltos automáticamente: {$resolved}");
        $this->warn("Ambiguos (revisión manual): {$ambiguous}");

        if ($ambiguous > 0) {
            $this->line('');
            $this->info('Consulta los ambiguos con:');
            $this->line('  SELECT po.id, po.season, po.dispatch_month FROM production_orders po');
            $this->line('  JOIN production_order_temporal_migration_logs l ON l.production_order_id = po.id');
            $this->line('  WHERE l.is_ambiguous = 1;');
        }

        return self::SUCCESS;
    }

    /**
     * Resuelve los valores canónicos para una orden.
     * Retorna: [seasonData, dispatchData, source, isAmbiguous, notes]
     */
    private function resolveOrder(ProductionOrder $order): array
    {
        $seasonParsed   = SeasonParser::parse($order->season);
        $dispatchParsed = SeasonParser::parse($order->dispatch_month);

        $isAmbiguous = false;
        $notes       = null;
        $source      = 'explicit';

        // ── Resolver año de temporada ──────────────────────
        if ($seasonParsed['month'] !== null && $seasonParsed['year'] === null) {
            [$year, $yearSource] = $this->inferYear($order);
            $seasonParsed['year'] = $year;
            $source = $yearSource;
        }

        // ── Resolver año de despacho ───────────────────────
        if ($dispatchParsed['month'] !== null && $dispatchParsed['year'] === null) {
            // Intentar deducir del despacho con la misma fuente anual
            [$year, $yearSource] = $this->inferYear($order);
            $dispatchParsed['year'] = $year;
            if ($source === 'explicit') {
                $source = $yearSource;
            }
        }

        // ── Detectar ambiguos ─────────────────────────────
        if ($seasonParsed['ambiguous']) {
            $isAmbiguous = true;
            $notes = "season no reconocible: '{$order->season}'";
        } elseif ($dispatchParsed['ambiguous']) {
            $isAmbiguous = true;
            $notes = "dispatch_month no reconocible: '{$order->dispatch_month}'";
        } elseif ($seasonParsed['month'] !== null && $seasonParsed['year'] === null) {
            $isAmbiguous = true;
            $notes = 'año no inferible (sin estimated_delivery_date ni created_at)';
        }

        return [$seasonParsed, $dispatchParsed, $source, $isAmbiguous, $notes];
    }

    /**
     * Infiere el año a partir de estimated_delivery_date y luego created_at.
     * @return array{ 0: int|null, 1: string }
     */
    private function inferYear(ProductionOrder $order): array
    {
        $year = SeasonParser::yearFromDate($order->estimated_delivery_date);
        if ($year !== null) {
            return [$year, 'estimated_delivery_date'];
        }

        $year = SeasonParser::yearFromDate($order->created_at);
        if ($year !== null) {
            return [$year, 'created_at'];
        }

        return [null, 'explicit'];
    }
}
