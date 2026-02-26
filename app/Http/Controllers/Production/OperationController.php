<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\Machine;
use App\Models\Production\Operation;
use App\Models\Production\OperationAuditLog;
use App\Models\Production\ProductSheet;
use App\Services\Production\OperationSectionService;
use App\Services\Production\OperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    public function __construct(
        protected OperationService $service,
        protected OperationSectionService $sectionService,
    ) {}

    /**
     * Vista Inertia: Base de datos de operaciones
     */
    public function indexView(): Response
    {
        $productSheets = ProductSheet::select('id', 'sample_reference', 'product_reference', 'type')
            ->orderBy('sample_reference')
            ->get()
            ->map(fn ($ps) => [
                'id' => $ps->id,
                'name' => trim(($ps->sample_reference ?: $ps->product_reference ?: "#{$ps->id}") . " ({$ps->type})"),
            ]);

        $machines = Machine::active()
            ->select('id', 'code', 'name', 'supplement_factor')
            ->orderBy('code')
            ->get()
            ->map(fn ($m) => ['id' => $m->id, 'name' => "{$m->code} · {$m->name}"]);

        return Inertia::render('Operations/Index', [
            'sections' => $this->sectionService->options(),
            'actions' => $this->service->getUniqueActions(),
            'garmentParts' => $this->service->getUniqueGarmentParts(),
            'locations' => $this->service->getUniqueLocations(),
            'productSheets' => $productSheets,
            'machines' => $machines,
        ]);
    }

    /**
     * Operaciones paginadas con filtros
     * GET /produccion/operaciones/paginated
     */
    public function paginated(Request $request): JsonResponse
    {
        $query = Operation::with(['section', 'productSheetReference', 'machineReference'])
            ->active();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw("CONCAT(action, ' ', garment_part) LIKE ?", ["%{$search}%"])
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        if ($sectionId = $request->input('section_id')) {
            $query->where('section_id', $sectionId);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $paginated = $query->orderBy('section_id')
            ->orderBy('action')
            ->orderBy('garment_part')
            ->paginate($perPage);

        return response()->json($paginated);
    }

    /**
     * Lista todas las opciones de operaciones activas (para selects)
     * GET /produccion/operaciones/data
     */
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->options(),
        ]);
    }

    /**
     * Obtiene valores únicos de acciones, partes de prenda y ubicaciones
     * GET /produccion/operaciones/metadata
     */
    public function metadata(): JsonResponse
    {
        return response()->json([
            'data' => [
                'actions' => $this->service->getUniqueActions(),
                'garment_parts' => $this->service->getUniqueGarmentParts(),
                'locations' => $this->service->getUniqueLocations(),
            ],
        ]);
    }

    /**
     * Crea o reutiliza una operación existente (por action+garment_part).
     * POST /produccion/operaciones
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:255'],
            'garment_part' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'section_id' => ['required', 'integer', 'exists:operation_sections,id'],
            'location' => ['nullable', 'string', 'max:100'],
            'measure' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'sam_base' => ['required', 'numeric', 'min:0.001'],
            'product_sheet_id_reference' => ['nullable', 'integer', 'exists:product_sheets,id'],
            'machine_id_reference' => ['nullable', 'integer', 'exists:machines,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $result = $this->service->findOrCreate($validated);
        $operation = $result['operation'];
        $reused = $result['reused'];

        $operation->load(['section', 'productSheetReference', 'machineReference']);

        if ($reused) {
            return response()->json([
                'message' => "Operación '{$operation->name}' ya existe. Se reutilizará.",
                'data' => $operation,
                'reused' => true,
            ], 200);
        }

        return response()->json([
            'message' => 'Operación registrada correctamente.',
            'data' => $operation,
            'reused' => false,
        ], 201);
    }

    /**
     * Actualiza una operación existente
     * PUT /produccion/operaciones/{operation}
     */
    public function update(Request $request, Operation $operation): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['sometimes', 'string', 'max:255'],
            'garment_part' => ['sometimes', 'string', 'max:255'],
            'section_id' => ['nullable', 'integer', 'exists:operation_sections,id'],
            'location' => ['nullable', 'string', 'max:100'],
            'measure' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'sam_base' => ['nullable', 'numeric', 'min:0'],
            'product_sheet_id_reference' => ['nullable', 'integer', 'exists:product_sheets,id'],
            'machine_id_reference' => ['nullable', 'integer', 'exists:machines,id'],
            'is_active' => ['sometimes', 'boolean'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $motivo = $validated['motivo'] ?? null;
        unset($validated['motivo']);

        try {
            $operation = $this->service->update($operation, $validated, $motivo);

            return response()->json([
                'message' => 'Operación actualizada correctamente.',
                'data' => $operation->load(['section', 'productSheetReference']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'type' => 'warning',
            ], 422);
        }
    }

    /**
     * Elimina (soft delete) una operación
     * DELETE /produccion/operaciones/{operation}
     */
    public function destroy(Operation $operation): JsonResponse
    {
        $name = $operation->name;
        $this->service->delete($operation);

        return response()->json([
            'message' => "Operación '{$name}' eliminada correctamente.",
        ]);
    }

    /**
     * Actualiza el tiempo estándar de una operación
     * POST /produccion/operaciones/{operation}/update-standard-time
     */
    public function updateStandardTime(Request $request, Operation $operation): JsonResponse
    {
        $validated = $request->validate([
            'sam_base' => ['required', 'numeric', 'min:0'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $operation = $this->service->updateStandardTime(
            $operation,
            $validated['sam_base'],
            $validated['motivo'] ?? null,
        );

        return response()->json([
            'message' => 'SAM base actualizado y recalculado en todos los listados.',
            'data' => $operation,
        ]);
    }

    /**
     * Historial de auditoría de una operación
     * GET /produccion/operaciones/{operation}/audit-log
     */
    public function auditLog(Operation $operation): JsonResponse
    {
        $logs = OperationAuditLog::where('operation_id', $operation->id)
            ->with('changedByUser:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $logs]);
    }
}
