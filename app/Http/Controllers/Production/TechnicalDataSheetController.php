<?php

namespace App\Http\Controllers\Production;

use App\Helpers\MonthLabel;
use App\Http\Controllers\Controller;
use App\Models\ProductSheet\Programmer;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TechnicalDataSheetController extends Controller
{
    public function __construct(
        private SettingService $settingService,
    ) {
    }

    /**
     * Muestra la vista principal de Ficha Técnica.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Production/TechnicalDataSheet/Index', [
            'initialOpId' => $request->query('op_id'),
        ]);
    }

    /**
     * Retorna las opciones de OP disponibles para el selector.
     * Formato: [{ id, name, groupLabel }]
     */
    public function options(Request $request): JsonResponse
    {
        $search = $request->query('search', '');

        $query = Programmer::query()
            ->with('productSheet:id,sample_reference,product_reference')
            ->whereNotNull('op_code')
            ->orderByDesc('created_at')
            ->limit(50);

        if ($search) {
            $searchTerm = '%' . $search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('op_code', 'like', $searchTerm)
                    ->orWhereHas('productSheet', function ($ps) use ($searchTerm) {
                        $ps->where('sample_reference', 'like', $searchTerm)
                            ->orWhere('product_reference', 'like', $searchTerm);
                    });
            });
        }

        $programmers = $query->get();

        $options = $programmers->map(function (Programmer $programmer) {
            $opCode = $programmer->op_code ?? '';
            $reference = $this->buildReference($programmer);
            $type = $this->extractOpType($opCode);
            $groupLabel = in_array($type, ['MU', 'MUT']) ? 'Muestras' : 'Productos';

            return [
                'id' => $programmer->id,
                'name' => "{$opCode} — {$reference}",
                'groupLabel' => $groupLabel,
            ];
        });

        return response()->json($options->values());
    }

    /**
     * Retorna la información básica de una OP específica.
     * Reutiliza la misma lógica del módulo Órdenes de Producción.
     */
    public function basicInfo(int $opId): JsonResponse
    {
        $programmer = Programmer::with([
            'productSheet:id,type,sample_reference,product_reference,section_id,group_id,world_id,brand_id,fabric_base_id,yarn_base_id,billing_price,selling_price,sizes,description,status,flat_sketch_path,created_at',
        ])->findOrFail($opId);

        $destinations = $this->settingService->getCatalogOptions('destinations')->keyBy('id');
        $sections = $this->settingService->getCatalogOptions('sections')->keyBy('id');
        $groups = $this->settingService->getCatalogOptions('groups')->keyBy('id');
        $brands = $this->settingService->getCatalogOptions('brands')->keyBy('id');
        $worlds = $this->settingService->getCatalogOptions('worlds')->keyBy('id');

        $destination = $destinations->get($programmer->destination_id);
        $productSheet = $programmer->productSheet;

        $section = null;
        $group = null;
        $brand = null;
        $world = null;

        if ($productSheet) {
            $section = $productSheet->section_id
                ? $sections->get($productSheet->section_id)
                : null;
            $group = $productSheet->group_id
                ? $groups->get($productSheet->group_id)
                : null;
            $brand = $productSheet->brand_id
                ? $brands->get($productSheet->brand_id)
                : null;
            $world = $productSheet->world_id
                ? $worlds->get($productSheet->world_id)
                : null;
        }

        $season = $programmer->season;
        $seasonDisplay = is_numeric($season)
            ? MonthLabel::fromNumber((int) $season)
            : $season;

        $priority = is_numeric($programmer->priority)
            ? (int) $programmer->priority
            : null;
        $flatDrawingPath = $productSheet?->flat_sketch_path;
        $flatDrawingUrl = null;

        if (is_string($flatDrawingPath) && trim($flatDrawingPath) !== '') {
            $path = trim($flatDrawingPath);
            if (preg_match('/^https?:\/\//i', $path)) {
                $flatDrawingUrl = $path;
            } else {
                $flatDrawingUrl = Storage::url(ltrim($path, '/'));
            }
        }

        return response()->json([
            'id' => $programmer->id,
            'opCode' => $programmer->op_code,
            'reference' => $this->buildReference($programmer),
            'date' => $productSheet?->created_at?->toDateString(),
            'flatDrawingUrl' => $flatDrawingUrl,
            'destination' => $destination['name'] ?? null,
            'section' => $section['name'] ?? null,
            'group' => $group['name'] ?? null,
            'priority' => $priority,
            'brand' => $brand['name'] ?? null,
            'world' => $world['name'] ?? null,
            'season' => $seasonDisplay,
        ]);
    }

    /**
     * Construye la referencia a partir del product_sheet.
     */
    private function buildReference(Programmer $programmer): string
    {
        $sampleRef = trim($programmer->productSheet?->sample_reference ?? '');
        $productRef = trim($programmer->productSheet?->product_reference ?? '');

        if ($sampleRef && $productRef) {
            return $sampleRef === $productRef ? $sampleRef : "{$sampleRef} - {$productRef}";
        }
        if ($sampleRef) {
            return $sampleRef;
        }
        if ($productRef) {
            return $productRef;
        }

        return 'Sin referencia';
    }

    /**
     * Extrae el tipo de OP desde el código.
     */
    private function extractOpType(string $opCode): string
    {
        $code = strtoupper(trim($opCode));

        if (str_starts_with($code, 'MUT')) {
            return 'MUT';
        }
        if (str_starts_with($code, 'MU')) {
            return 'MU';
        }
        if (str_starts_with($code, 'MFT')) {
            return 'MFT';
        }
        if (str_starts_with($code, 'MF')) {
            return 'MF';
        }

        return 'MF';
    }
}
