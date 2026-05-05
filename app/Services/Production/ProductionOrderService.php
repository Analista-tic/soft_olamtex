<?php

namespace App\Services\Production;

use App\Enums\EstatusOperacionOrden;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetOperation;
use App\Models\Production\TechnicalSheetThread;
use App\Services\Production\ProductOperationListService;
use App\Services\Settings\SettingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Servicio unificado para Órdenes de Producción.
 *
 * Gestiona la creación de órdenes (manuales y automáticas), generación de
 * op_code, resolución de estado y snapshots congelados (TechnicalSheet +
 * TechnicalSheetOperation) a partir del listado operacional.
 */
class ProductionOrderService
{
    private const TYPE_SAMPLE = 'muestra';
    private const DEFAULT_DESTINATION = 'OLAMTEX';
    private const STATUS_COMPLETE = 'complete';
    private const STATUS_INCOMPLETE = 'incomplete';
    private const DEFAULT_THREAD_NAMES = [
        'Pespunte / Amarre',
        'Filetes',
        'Ojales',
        'Presilla',
        'Puntada Seguridad',
    ];

    private const MONTH_NAMES = [
        'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
        'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE',
    ];

    public function __construct(
        protected ProductOperationListService $listService,
        protected SettingService $settingService,
    ) {
    }

    // ─── Creación automática desde ficha muestra ──────────────────────

    /**
     * Crea una orden de producción automáticamente al crear una ficha de producto.
     */
    public function createFromSample(ProductSheet $productSheet): ProductionOrder
    {
        if (Str::lower(trim((string) $productSheet->type)) !== self::TYPE_SAMPLE) {
            throw new RuntimeException('La creación automática solo está permitida para fichas tipo muestra.');
        }

        Log::info('production_order.auto_create.start', [
            'product_sheet_id' => $productSheet->id,
            'type' => $productSheet->type,
            'timestamp' => now()->toIso8601String(),
        ]);

        $destinationId = $this->resolveDestinationId();
        $season = $this->resolveSeasonFromCreationMonth($productSheet);

        $order = ProductionOrder::create([
            'product_sheet_id' => $productSheet->id,
            'season' => $season,
            'destination_id' => $destinationId,
            'priority' => null,
            'units' => 1,
            'estimated_delivery_date' => null,
            'ecommerce_units' => null,
            'status' => self::STATUS_INCOMPLETE,
            'current_process' => config('product-sheet.batch_control_processes.0', 'I&D'),
            'source' => 'auto',
            'created_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        $order->op_code = $this->nextOpCode($productSheet->type, (bool) $productSheet->woven_product);
        $order->save();

        $this->createSnapshotForOrder($order);

        Log::info('production_order.auto_create.completed', [
            'product_sheet_id' => $productSheet->id,
            'order_id' => $order->id,
            'op_code' => $order->op_code,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $order;
    }

    // ─── Estado ───────────────────────────────────────────────────────

    /**
     * Resuelve el estado de una orden auto-generada.
     */
    public function resolveStatus(?int $units, ?string $estimatedDeliveryDate): string
    {
        return $units !== null && $units > 0 && filled($estimatedDeliveryDate)
            ? self::STATUS_COMPLETE
            : self::STATUS_INCOMPLETE;
    }

    // ─── Snapshots (TechnicalSheet + TechnicalSheetOperation) ─────────

    /**
     * Crea o reemplaza el snapshot de operaciones para una orden de producción.
     *
     * 1. Crea/reutiliza un TechnicalSheet vinculado a la orden.
     * 2. Elimina TechnicalSheetOperations previas y recrea desde el listado operacional.
     */
    public function createSnapshotForOrder(ProductionOrder $order): Collection
    {
        $productSheetId = $order->product_sheet_id;
        if (! $productSheetId) {
            return collect();
        }

        $operations = $this->listService->listBySheet($productSheetId);
        $quantityLote = max(1, (int) ($order->units ?? 1));

        return DB::transaction(function () use ($order, $operations, $quantityLote) {
            // Crear o reutilizar la ficha técnica
            $technicalSheet = TechnicalSheet::firstOrCreate(
                ['production_order_id' => $order->id],
                ['status' => TechnicalSheet::STATUS_DRAFT],
            );

            // Inicializar snapshot de hilos para OP nueva con colores vacíos.
            TechnicalSheetThread::firstOrCreate(
                ['technical_sheet_id' => $technicalSheet->id],
                [
                    'params' => [
                        'defaultYear' => (int) date('Y'),
                        'method' => 'PER_GARMENT',
                    ],
                    'lines' => $this->defaultThreadLines(),
                ],
            );

            // Reemplazar operaciones del snapshot previo
            TechnicalSheetOperation::where('technical_sheet_id', $technicalSheet->id)->forceDelete();

            return $operations->map(function ($psOp) use ($order, $technicalSheet, $quantityLote) {
                $samTotalOperacion = (float) ($psOp->sam_total_operacion ?? $psOp->sam_total_unitario * ($psOp->quantity ?? 1));
                $samTotalLote = round($samTotalOperacion * $quantityLote, 4);

                return TechnicalSheetOperation::create([
                    'technical_sheet_id' => $technicalSheet->id,
                    'product_sheet_operation_id' => $psOp->id,
                    'sequence' => $psOp->sequence,
                    'operation_id' => $psOp->operation_id,
                    'machine_id' => $psOp->machine_id,
                    'quantity' => $psOp->quantity ?? 1,
                    'sam_base' => $psOp->sam_base ?? $psOp->sam ?? $psOp->optimal_time ?? 0,
                    'machine_supplement_factor' => $psOp->machine_supplement_factor ?? 1.0,
                    'fabric_base_factor' => $psOp->fabric_base_factor ?? 1.0,
                    'operator_efficiency_factor' => $psOp->operator_efficiency_factor ?? 1.0,
                    'sam_total_unitario' => $psOp->sam_total_unitario ?? 0,
                    'sam_total_operacion' => $samTotalOperacion,
                    'sam_total_lote' => $samTotalLote,
                    'quantity_progressed' => 0,
                    'status' => EstatusOperacionOrden::PENDIENTE,
                ]);
            });
        });
    }

    /**
     * Obtiene las operaciones snapshot de una orden con relaciones cargadas.
     */
    public function getOperationsByOrder(int $orderId): Collection
    {
        $technicalSheet = TechnicalSheet::where('production_order_id', $orderId)->first();

        if (! $technicalSheet) {
            return collect();
        }

        return TechnicalSheetOperation::byTechnicalSheet($technicalSheet->id)
            ->with(['operation.section', 'machine', 'productSheetOperation.operationSection'])
            ->get();
    }

    /**
     * Actualiza SOLO el progreso de una operación del snapshot.
     */
    public function updateProgressOnly(
        TechnicalSheetOperation $operation,
        int $quantityProgressed,
        string $motivo,
    ): TechnicalSheetOperation {
        $operation->updateProgressOnly($quantityProgressed);
        return $operation->fresh();
    }

    /**
     * SAM total del lote para una orden (suma de sam_total_lote de sus operaciones).
     */
    public function getTotalSamLote(int $orderId): float
    {
        $technicalSheet = TechnicalSheet::where('production_order_id', $orderId)->first();

        if (! $technicalSheet) {
            return 0.0;
        }

        return (float) TechnicalSheetOperation::where('technical_sheet_id', $technicalSheet->id)
            ->sum('sam_total_lote');
    }

    // ─── Op Code ──────────────────────────────────────────────────────

    /**
     * Genera el siguiente op_code según el tipo de ficha y si es tejido.
     *
     * Prefijos: MU (muestra), MUT (muestra tejido), MF (producto), MFT (producto tejido).
     */
    public function nextOpCode(string $type, bool $wovenProduct): string
    {
        $prefix = $this->resolveOpCodePrefix($type, $wovenProduct);

        $codes = ProductionOrder::withTrashed()
            ->where('op_code', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->pluck('op_code');

        $nextSequence = $this->nextSequence($codes, $prefix);

        return sprintf('%s-%04d', $prefix, $nextSequence);
    }

    /**
     * Resuelve el prefijo del op_code basado en tipo y woven_product.
     */
    public function resolveOpCodePrefix(string $type, bool $wovenProduct): string
    {
        $isProduct = strtolower($type) === 'producto';

        if ($isProduct) {
            return $wovenProduct ? 'MFT' : 'MF';
        }

        return $wovenProduct ? 'MUT' : 'MU';
    }

    // ─── Helpers de mes / temporada ───────────────────────────────────

    /**
     * Convierte un número de mes (1-12) a su nombre en español.
     */
    public function getMonthName(int|string $monthNumber): string
    {
        $month = is_string($monthNumber) ? (int) $monthNumber : $monthNumber;

        if ($month < 1 || $month > 12) {
            return (string) $monthNumber;
        }

        return self::MONTH_NAMES[$month - 1] ?? (string) $monthNumber;
    }

    // ─── Private helpers ──────────────────────────────────────────────

    private function resolveDestinationId(): int|string
    {
        $destination = $this->settingService
            ->getCatalogOptions('destinations')
            ->first(function (array $item) {
                return strtoupper(trim((string) ($item['name'] ?? ''))) === self::DEFAULT_DESTINATION;
            });

        if (! $destination || ! array_key_exists('id', $destination)) {
            throw new RuntimeException('No se encontró el destino OLAMTEX en parámetros.');
        }

        return $destination['id'];
    }

    private function resolveSeasonFromCreationMonth(ProductSheet $productSheet): string
    {
        $monthNumber = (int) $productSheet->created_at?->month ?: now()->month;
        $months = $this->settingService->getCatalogOptions('months')->values();

        if ($months->has($monthNumber - 1)) {
            $option = $months->get($monthNumber - 1);
            if (is_array($option)) {
                return (string) ($option['id'] ?? $option['name'] ?? $this->fallbackSeasonName($monthNumber));
            }
        }

        return $this->fallbackSeasonName($monthNumber);
    }

    private function fallbackSeasonName(int $monthNumber): string
    {
        return (string) $monthNumber;
    }

    private function nextSequence(Collection $codes, string $prefix): int
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';

        $max = $codes
            ->map(function (?string $code) use ($pattern): ?int {
                if (! is_string($code)) {
                    return null;
                }

                if (preg_match($pattern, $code, $matches) === 1) {
                    return (int) $matches[1];
                }

                return null;
            })
            ->filter()
            ->max();

        return ($max ?? 0) + 1;
    }

    private function defaultThreadLines(): array
    {
        $now = now()->toISOString();

        return array_map(function (string $threadName) use ($now) {
            return [
                'id' => (string) Str::uuid(),
                'threadName' => $threadName,
                'gauge' => null,
                'brand' => null,
                'color' => null,
                'colorCode' => null,
                'stitchesPerInch' => null,
                'defaultThreadCost' => null,
                'createdAt' => $now,
                'updatedAt' => $now,
            ];
        }, self::DEFAULT_THREAD_NAMES);
    }
}
