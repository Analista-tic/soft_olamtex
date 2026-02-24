<?php

namespace App\Http\Controllers\ProductSheet;

use App\Helpers\MonthLabel;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductSheet\UpdateProgrammerProcessRequest;
use App\Models\ProductSheet\Programmer;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BatchControlController extends Controller
{
    public function __construct(
        private SettingService $settingService,
    ) {
    }

    public function index(): Response
    {
        $processes = config('product-sheet.batch_control_processes', []);

        return Inertia::render('BatchControl/Index', [
            'processes' => $processes,
        ]);
    }

    /**
     * Devuelve todas las órdenes con datos para Control de lotes.
     * Columnas: referencia, temporada, prioridad, cant_programadas, destino, seccion, grupo, fecha, lote.
     */
    public function getData(Request $request): JsonResponse
    {
        $query = Programmer::with([
            'productSheet:id,type,sample_reference,product_reference,section_id,group_id',
        ])->orderByDesc('id');

        $programmers = $query->get();

        $destinations = $this->settingService->getCatalogOptions('destinations')->keyBy('id');
        $sections = $this->settingService->getCatalogOptions('sections')->keyBy('id');
        $groups = $this->settingService->getCatalogOptions('groups')->keyBy('id');

        $rows = $programmers->map(function (Programmer $p) use ($destinations, $sections, $groups) {
            $ps = $p->productSheet;
            $displayRef = $ps
                ? (($ps->type ?? '') === 'producto'
                    ? trim((string) ($ps->product_reference ?? ''))
                    : trim((string) ($ps->sample_reference ?? '')))
                : '';
            $sectionId = $ps?->section_id ?? null;
            $groupId = $ps?->group_id ?? null;

            return [
                'id' => $p->id,
                'op_code' => $p->op_code,
                'current_process' => $p->current_process,
                'referencia' => $displayRef ?: 'Sin referencia',
                'temporada' => is_numeric($p->season)
                    ? MonthLabel::fromNumber((int) $p->season)
                    : (string) $p->season,
                'prioridad' => $p->priority ?? '-',
                'cant_programadas' => $p->units ?? 0,
                'destino' => $destinations->get($p->destination_id, ['name' => 'N/A'])['name'] ?? 'N/A',
                'seccion' => $sectionId
                    ? ($sections->get($sectionId, ['name' => 'N/A'])['name'] ?? 'N/A')
                    : 'N/A',
                'grupo' => $groupId
                    ? ($groups->get($groupId, ['name' => 'N/A'])['name'] ?? 'N/A')
                    : 'N/A',
                'fecha' => $p->estimated_delivery_date?->format('Y-m-d') ?? '-',
            ];
        });

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function updateProcess(UpdateProgrammerProcessRequest $request, Programmer $programmer): JsonResponse
    {
        $programmer->update([
            'current_process' => $request->validated('current_process'),
        ]);

        return response()->json([
            'message' => 'Proceso actualizado correctamente.',
            'data' => [
                'id' => $programmer->id,
                'current_process' => $programmer->current_process,
            ],
        ]);
    }
}
