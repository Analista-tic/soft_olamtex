<?php

namespace App\Console\Commands;

use App\Services\Production\OperationService;
use Illuminate\Console\Command;

class DeduplicateOperationsCommand extends Command
{
    protected $signature = 'production:operations-deduplicate
                            {--apply : Aplica cambios (por defecto solo dry-run)}
                            {--limit=10 : Máximo de grupos a mostrar en el resumen}';

    protected $description = 'Depura operaciones duplicadas por firma y conserva relaciones en listados por producto';

    public function __construct(private readonly OperationService $operationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(1, (int) $this->option('limit'));

        $this->info('Iniciando depuración de operaciones duplicadas' . ($apply ? ' [APPLY]' : ' [DRY-RUN]'));

        $summary = $this->operationService->deduplicateBySignature($apply);

        $this->line('');
        $this->info('Resumen');
        $this->line('Grupos duplicados: ' . (int) ($summary['groups'] ?? 0));
        $this->line('Operaciones a depurar: ' . (int) ($summary['operations_to_soft_delete'] ?? 0));
        $this->line('Filas relink en product_sheet_operations: ' . (int) ($summary['list_rows_relinked'] ?? 0));
        $this->line('Filas depuradas por colisión de sábana: ' . (int) ($summary['list_rows_soft_deleted_by_collision'] ?? 0));

        $details = collect($summary['details'] ?? [])->take($limit);

        if ($details->isNotEmpty()) {
            $this->line('');
            $this->info('Muestra de grupos');

            foreach ($details as $index => $detail) {
                $this->line(sprintf(
                    '%d) keeper=%d | duplicados=[%s] | relink=%d | colisiones_dep=%d | %s',
                    $index + 1,
                    (int) ($detail['keeper_id'] ?? 0),
                    implode(',', $detail['duplicate_ids'] ?? []),
                    (int) ($detail['relinked_rows'] ?? 0),
                    (int) ($detail['collision_rows_deleted'] ?? 0),
                    (string) ($detail['description'] ?? '')
                ));
            }
        }

        if (! $apply) {
            $this->line('');
            $this->warn('Dry-run: no se aplicaron cambios. Ejecuta con --apply para persistir.');
        }

        return self::SUCCESS;
    }
}
