<?php

namespace App\Services\Production;

use App\Models\Production\ProductSheet;
use App\Models\Production\Programmer;
use App\Services\Settings\SettingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProgrammerService
{
    private const DEFAULT_DESTINATION = 'OLAMTEX';
    private const STATUS_COMPLETE = 'complete';
    private const STATUS_INCOMPLETE = 'incomplete';

    public function __construct(private SettingService $settingService)
    {
    }

    public function createFromSample(ProductSheet $productSheet): Programmer
    {
        Log::info('programmer.auto_create.start', [
            'endpoint' => 'product_sheet.create',
            'product_sheet_id' => $productSheet->id,
            'type' => $productSheet->type,
            'timestamp' => now()->toIso8601String(),
        ]);

        $destinationId = $this->resolveDestinationId();
        $season = $this->resolveSeasonFromCreationMonth($productSheet);

        $programmer = Programmer::create([
            'product_sheet_id' => $productSheet->id,
            'season' => $season,
            'destination_id' => $destinationId,
            'priority' => null,
            'units' => null,
            'estimated_delivery_date' => null,
            'ecommerce_units' => null,
            'status' => self::STATUS_INCOMPLETE,
            'current_process' => config('product-sheet.batch_control_processes.0', 'I&D'),
            'source' => 'auto',
        ]);

        $programmer->op_code = $this->nextOpCode($productSheet->type, (bool) $productSheet->woven_product);
        $programmer->save();

        Log::info('programmer.auto_create.completed', [
            'endpoint' => 'product_sheet.create',
            'product_sheet_id' => $productSheet->id,
            'programmer_id' => $programmer->id,
            'op_code' => $programmer->op_code,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $programmer;
    }

    public function resolveStatus(?int $units, ?string $estimatedDeliveryDate): string
    {
        return $units !== null && $units > 0 && filled($estimatedDeliveryDate)
            ? self::STATUS_COMPLETE
            : self::STATUS_INCOMPLETE;
    }

    private function resolveDestinationId(): int|string
    {
        $destination = $this->settingService
            ->getCatalogOptions('destinations')
            ->first(function (array $item) {
                return strtoupper(trim((string) ($item['name'] ?? ''))) === self::DEFAULT_DESTINATION;
            });

        if (!$destination || !array_key_exists('id', $destination)) {
            throw new RuntimeException('No se encontrÃƒÂ³ el destino OLAMTEX en parÃƒÂ¡metros.');
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

    /**
     * Fallback: retorna el nÃƒÂºmero del mes como string (1-12).
     * Preferimos el id numÃƒÂ©rico para consistencia con el catÃƒÂ¡logo.
     */
    private function fallbackSeasonName(int $monthNumber): string
    {
        return (string) $monthNumber;
    }

    /**
     * Convierte un nÃƒÂºmero de mes (1-12) a su nombre en espaÃƒÂ±ol.
     */
    public function getMonthName(int|string $monthNumber): string
    {
        $month = is_string($monthNumber) ? (int) $monthNumber : $monthNumber;

        if ($month < 1 || $month > 12) {
            return (string) $monthNumber;
        }

        return self::MONTH_NAMES[$month - 1] ?? (string) $monthNumber;
    }

    private const MONTH_NAMES = [
        'ENERO',
        'FEBRERO',
        'MARZO',
        'ABRIL',
        'MAYO',
        'JUNIO',
        'JULIO',
        'AGOSTO',
        'SEPTIEMBRE',
        'OCTUBRE',
        'NOVIEMBRE',
        'DICIEMBRE',
    ];

    /**
     * Genera el siguiente op_code segÃƒÂºn el tipo de ficha y si es tejido.
     * Reglas:
     * - Muestra + NO tejido  Ã¢â€ â€™ MU
     * - Muestra + Tejido     Ã¢â€ â€™ MUT
     * - Producto + NO tejido Ã¢â€ â€™ MF
     * - Producto + Tejido    Ã¢â€ â€™ MFT
     */
    public function nextOpCode(string $type, bool $wovenProduct): string
    {
        $prefix = $this->resolveOpCodePrefix($type, $wovenProduct);
        $codes = Programmer::withTrashed()
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

    private function nextSequence(Collection $codes, string $prefix): int
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';

        $max = $codes
            ->map(function (?string $code) use ($pattern): ?int {
                if (!is_string($code)) {
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
}
