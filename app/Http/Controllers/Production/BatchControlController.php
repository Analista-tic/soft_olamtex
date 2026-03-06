<?php

namespace App\Http\Controllers\Production;

use App\Helpers\MonthLabel;
use App\Http\Controllers\Controller;
use App\Models\Production\BatchControlDelivery;
use App\Models\Production\BatchControlNovelty;
use App\Models\Production\ProductionOrder;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        return Inertia::render('Production/BatchControl/Index', [
            'processes' => $processes,
        ]);
    }

    /**
     * Devuelve todas las órdenes con datos para Control de lotes.
     * Incluye la última entrega (observaciones) y fecha estimada.
     */
    public function getData(Request $request): JsonResponse
    {
        $query = ProductionOrder::with([
            'productSheet:id,type,sample_reference,product_reference,section_id,group_id',
            'lastDelivery',
        ])
        ->withCount(['pendingNovelties'])
        ->orderBy('batch_priority')
        ->orderByDesc('id');

        $orders = $query->get();

        $destinations = $this->settingService->getCatalogOptions('destinations')->keyBy('id');
        $sections = $this->settingService->getCatalogOptions('sections')->keyBy('id');
        $groups = $this->settingService->getCatalogOptions('groups')->keyBy('id');

        $today = Carbon::today();

        $rows = $orders->map(function (ProductionOrder $p) use ($destinations, $sections, $groups, $today) {
            $ps = $p->productSheet;
            $displayRef = $ps
                ? (($ps->type ?? '') === 'producto'
                    ? trim((string) ($ps->product_reference ?? ''))
                    : trim((string) ($ps->sample_reference ?? '')))
                : '';
            $sectionId = $ps?->section_id ?? null;
            $groupId = $ps?->group_id ?? null;

            // Fechas estimadas por proceso (JSON)
            $allDates = $p->process_estimated_dates ?? [];

            // Calcular deadline por proceso
            $deadlines = [];
            foreach ($allDates as $proc => $dateStr) {
                if (!$dateStr) {
                    $deadlines[$proc] = 'none';
                    continue;
                }
                $estDate = Carbon::parse($dateStr);
                $daysLeft = $today->diffInDays($estDate, false);
                if ($daysLeft < 0) {
                    $deadlines[$proc] = 'overdue';
                } elseif ($daysLeft <= 2) {
                    $deadlines[$proc] = 'due_soon';
                } else {
                    $deadlines[$proc] = 'on_time';
                }
            }

            // Última entrega (observaciones del paso anterior)
            $lastDelivery = $p->lastDelivery;

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
                'product_sheet_type' => $ps?->type ?? 'producto',
                'batch_priority' => $p->batch_priority ?? 0,
                'process_estimated_dates' => $allDates,
                'deadline_statuses' => $deadlines,
                'last_delivery' => $lastDelivery ? [
                    'from_process' => $lastDelivery->from_process,
                    'to_process' => $lastDelivery->to_process,
                    'observations' => $lastDelivery->observations,
                    'delivered_at' => $lastDelivery->created_at?->toIso8601String(),
                ] : null,
                'has_pending_novelties' => ($p->pending_novelties_count ?? 0) > 0,
                'pending_novelties_count' => $p->pending_novelties_count ?? 0,
            ];
        });

        return response()->json([
            'data' => $rows,
        ]);
    }

    /**
     * Entregar una orden al siguiente estado/proceso.
     * Registra la entrega con observaciones y avanza al nuevo proceso.
     */
    public function deliver(Request $request, ProductionOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'to_process' => 'required|string|max:80',
            'observations' => 'nullable|string|max:2000',
        ]);

        $processes = config('product-sheet.batch_control_processes', []);
        if (!in_array($validated['to_process'], $processes, true)) {
            return response()->json(['message' => 'El proceso destino no es válido.'], 422);
        }

        if ($validated['to_process'] === $order->current_process) {
            return response()->json(['message' => 'El proceso destino debe ser diferente al actual.'], 422);
        }

        $fromProcess = $order->current_process;

        // Registrar la entrega
        BatchControlDelivery::create([
            'production_order_id' => $order->id,
            'from_process' => $fromProcess,
            'to_process' => $validated['to_process'],
            'observations' => $validated['observations'] ?? null,
            'delivered_by' => $request->user()?->id,
        ]);

        // Avanzar al nuevo proceso (las fechas estimadas por estado se mantienen)
        $order->update([
            'current_process' => $validated['to_process'],
        ]);

        return response()->json([
            'message' => "Orden entregada de {$fromProcess} a {$validated['to_process']}.",
            'data' => [
                'id' => $order->id,
                'current_process' => $order->current_process,
            ],
        ]);
    }

    /**
     * Actualizar prioridades de órdenes (reordenar).
     */
    public function updatePriorities(Request $request): JsonResponse
    {
        $request->validate([
            'priorities' => 'required|array|min:1',
            'priorities.*.id' => 'required|integer|exists:production_orders,id',
            'priorities.*.batch_priority' => 'required|integer|min:0',
        ]);

        foreach ($request->input('priorities') as $item) {
            ProductionOrder::where('id', $item['id'])
                ->update(['batch_priority' => $item['batch_priority']]);
        }

        return response()->json([
            'message' => 'Prioridades actualizadas.',
        ]);
    }

    /**
     * Actualizar la fecha estimada de un proceso específico.
     * Valida que no supere la fecha final de entrega de la orden.
     */
    public function updateEstimatedDate(Request $request, ProductionOrder $order): JsonResponse
    {
        $request->validate([
            'process' => 'required|string|max:80',
            'estimated_date' => 'nullable|date',
        ]);

        $process = $request->input('process');
        $date = $request->input('estimated_date');

        // Validar que la fecha del proceso no supere la fecha final de entrega
        if ($date && $order->estimated_delivery_date) {
            $processDate = Carbon::parse($date);
            $finalDate = $order->estimated_delivery_date;

            if ($processDate->gt($finalDate)) {
                return response()->json([
                    'message' => "La fecha estimada del proceso ({$processDate->format('d/m/Y')}) no puede ser posterior a la fecha final de entrega ({$finalDate->format('d/m/Y')}).",
                ], 422);
            }
        }

        $dates = $order->process_estimated_dates ?? [];

        if ($date) {
            $dates[$process] = $date;
        } else {
            unset($dates[$process]);
        }

        $order->update([
            'process_estimated_dates' => $dates,
        ]);

        return response()->json([
            'message' => 'Fecha estimada actualizada.',
            'data' => [
                'id' => $order->id,
                'process' => $process,
                'estimated_date' => $date,
            ],
        ]);
    }

    /**
     * Historial de entregas de una orden.
     */
    public function deliveryHistory(ProductionOrder $order): JsonResponse
    {
        $deliveries = BatchControlDelivery::where('production_order_id', $order->id)
            ->with('deliveredByUser:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BatchControlDelivery $d) => [
                'id' => $d->id,
                'from_process' => $d->from_process,
                'to_process' => $d->to_process,
                'observations' => $d->observations,
                'delivered_by_name' => $d->deliveredByUser?->name ?? 'N/A',
                'delivered_at' => $d->created_at?->format('d/m/Y H:i'),
            ]);

        return response()->json([
            'data' => $deliveries,
        ]);
    }

    // ─── Novedades / Devoluciones ─────────────────────────

    /**
     * Reportar una novedad al estado anterior.
     */
    public function reportNovelty(Request $request, ProductionOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'to_process' => 'required|string|max:80',
            'description' => 'required|string|max:2000',
        ]);

        $processes = config('product-sheet.batch_control_processes', []);

        if (!in_array($validated['to_process'], $processes, true)) {
            return response()->json(['message' => 'El proceso destino no es válido.'], 422);
        }

        // Validar que to_process sea un estado anterior al actual
        $currentIdx = array_search($order->current_process, $processes);
        $targetIdx = array_search($validated['to_process'], $processes);

        if ($currentIdx === false || $targetIdx === false || $targetIdx >= $currentIdx) {
            return response()->json([
                'message' => 'Solo se puede reportar una novedad a un estado anterior al actual.',
            ], 422);
        }

        $novelty = BatchControlNovelty::create([
            'production_order_id' => $order->id,
            'from_process' => $order->current_process,
            'to_process' => $validated['to_process'],
            'description' => $validated['description'],
            'status' => 'pending',
            'reported_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => "Novedad reportada a {$validated['to_process']}.",
            'data' => [
                'id' => $novelty->id,
                'from_process' => $novelty->from_process,
                'to_process' => $novelty->to_process,
            ],
        ]);
    }

    /**
     * Resolver una novedad.
     */
    public function resolveNovelty(Request $request, ProductionOrder $order, BatchControlNovelty $novelty): JsonResponse
    {
        if ($novelty->production_order_id !== $order->id) {
            return response()->json(['message' => 'La novedad no pertenece a esta orden.'], 422);
        }

        if ($novelty->isResolved()) {
            return response()->json(['message' => 'La novedad ya fue resuelta.'], 422);
        }

        $request->validate([
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $novelty->update([
            'status' => 'resolved',
            'resolved_by' => $request->user()?->id,
            'resolved_at' => now(),
            'resolution_notes' => $request->input('resolution_notes'),
        ]);

        return response()->json([
            'message' => 'Novedad resuelta.',
            'data' => [
                'id' => $novelty->id,
                'status' => 'resolved',
            ],
        ]);
    }

    /**
     * Listar novedades de una orden.
     */
    public function novelties(ProductionOrder $order): JsonResponse
    {
        $novelties = BatchControlNovelty::where('production_order_id', $order->id)
            ->with(['reportedByUser:id,name', 'resolvedByUser:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BatchControlNovelty $n) => [
                'id' => $n->id,
                'from_process' => $n->from_process,
                'to_process' => $n->to_process,
                'description' => $n->description,
                'status' => $n->status,
                'reported_by_name' => $n->reportedByUser?->name ?? 'N/A',
                'reported_at' => $n->created_at?->format('d/m/Y H:i'),
                'resolved_by_name' => $n->resolvedByUser?->name ?? null,
                'resolved_at' => $n->resolved_at?->format('d/m/Y H:i'),
                'resolution_notes' => $n->resolution_notes,
            ]);

        return response()->json([
            'data' => $novelties,
        ]);
    }
}
