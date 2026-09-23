<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\Machine;
use App\Models\Production\Operation;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use App\Models\Settings\AuditLog;
use App\Services\Production\OperationCatalogService;
use App\Services\Production\OperationSectionService;
use App\Services\Production\OperationService;
use App\Services\Production\ProductOperationListService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OperationController extends Controller
{
    public function __construct(
        protected OperationService $service,
        protected OperationSectionService $sectionService,
        protected OperationCatalogService $operationCatalogService,
        protected ProductOperationListService $productOperationListService,
    ) {}

    private function actionOptions()
    {
        return $this->operationCatalogService->actionOptions();
    }

    private function garmentPartOptions()
    {
        return $this->operationCatalogService->garmentPartOptions();
    }

    /**
     * Vista Inertia: Base de datos de operaciones
     * GET /produccion/base-operaciones
     */
    public function indexView(): Response
    {
        $productSheets = ProductSheet::select('id', 'sample_reference', 'product_reference', 'type', 'garment_type')
            ->orderBy('sample_reference')
            ->get()
            ->map(fn ($ps) => [
                'id' => $ps->id,
                'name' => trim(($ps->sample_reference ?: $ps->product_reference ?: "#{$ps->id}")." ({$ps->type})"),
            ]);

        $machines = Machine::active()
            ->select('id', 'code', 'name', 'supplement_factor')
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'supplement_factor' => $m->supplement_factor,
            ]);

        return Inertia::render('Operations/Index', [
            'sections' => $this->sectionService->options(),
            'actions' => $this->actionOptions(),
            'garmentParts' => $this->garmentPartOptions(),
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
                    ->orWhere('anexo', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        if ($sectionId = $request->input('section_id')) {
            $query->where('section_id', $sectionId);
        }
        if ($request->boolean('sin_ficha')) {
            $query->whereNull('product_sheet_id_reference');
        }

        $perPage = min((int) $request->input('per_page', 25), 100);

        $allowedSorts = ['codigo', 'action', 'garment_part', 'measure', 'quantity', 'sam_base', 'factor_operario', 'is_active', 'created_at', 'section_id'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : null;
        $sortDir = $request->input('sort_dir') === 'asc' ? 'asc' : 'desc';

        if ($sortBy) {
            $paginated = $query->orderBy($sortBy, $sortDir)->paginate($perPage);
        } else {
            $paginated = $query->orderBy('section_id')->orderBy('action')->orderBy('garment_part')->paginate($perPage);
        }

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
     * Metadata de catálogos para actualización reactiva en el frontend
     * GET /produccion/operaciones/metadata
     */
    public function metadata(): JsonResponse
    {
        return response()->json([
            'data' => [
                'actions' => $this->actionOptions(),
                'garment_parts' => $this->garmentPartOptions(),
            ],
        ]);
    }

    /**
     * Agrega una nueva opción a un catálogo de operaciones.
     * POST /produccion/operaciones/catalog-option
     *
     * Catálogos permitidos: operation_actions, operation_garment_parts
     */
    public function addCatalogOption(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'catalog' => ['required', 'string', 'in:operation_actions,operation_garment_parts'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = mb_strtoupper(trim($validated['name']));

        try {
            $option = $this->operationCatalogService->addOption($validated['catalog'], $name);

            return response()->json([
                'message' => "Opción '{$name}' agregada correctamente.",
                'option' => $option,
                'catalog' => $validated['catalog'],
                'options' => $this->getCatalogList($validated['catalog']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function getCatalogList(string $catalog): Collection
    {
        return match ($catalog) {
            'operation_actions' => $this->actionOptions(),
            'operation_garment_parts' => $this->garmentPartOptions(),
            default => collect(),
        };
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
            'anexo' => ['nullable', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'section_id' => ['required', 'integer', 'exists:operation_sections,id'],
            'measure' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'sam_base' => ['required', 'numeric', 'min:0.001'],
            'factor_operario' => ['required', 'numeric', 'min:0.01'],
            'product_sheet_id_reference' => ['required', 'integer', 'exists:product_sheets,id'],
            'machine_id_reference' => ['required', 'integer', 'exists:machines,id'],
            'is_active' => ['sometimes', 'boolean'],
            'disable_auto_link' => ['sometimes', 'boolean'],
        ]);

        // Nota: el tipo de prenda (garment_type) vive en product_sheets, no en la operación.
        // La operación es un catálogo maestro reutilizable; el contexto lo define product_sheet.

        try {
            $result = $this->service->findOrCreate($validated);
            $operation = $result['operation'];
            $reused = $result['reused'];
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'type' => 'warning',
            ], 422);
        } catch (QueryException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'operations_action_garment_unique')) {
                return response()->json([
                    'message' => 'Hay una restricción antigua de unicidad por acción + parte. Ejecuta migraciones pendientes para usar la nueva regla por descripción y variables.',
                    'type' => 'warning',
                ], 422);
            }
            throw $e;
        }

        // ── Auto-link: vincular operación al listado operacional de la sábana ──
        $linkedToList = false;
        $productSheetId = $validated['product_sheet_id_reference'] ?? null;
        $disableAutoLink = (bool) ($validated['disable_auto_link'] ?? false);

        if (! $disableAutoLink && $productSheetId && $operation->id) {
            $linkedToList = $this->autoLinkToProductOperationList(
                $operation,
                (int) $productSheetId,
                (int) ($validated['machine_id_reference'] ?? $operation->machine_id_reference),
                (int) ($validated['section_id'] ?? $operation->section_id),
                (float) ($validated['sam_base'] ?? $operation->sam_base ?? 0),
                (int) ($validated['quantity'] ?? $operation->quantity ?? 1),
            );
        }

        $operation->load(['section', 'productSheetReference', 'machineReference']);

        if ($reused) {
            return response()->json([
                'message' => $linkedToList
                    ? "Operación '{$operation->name}' ya existe. Se reutilizó y vinculó al listado operacional."
                    : "Operación '{$operation->name}' ya existe. Se reutilizará.",
                'data' => $operation,
                'reused' => true,
                'linked_to_list' => $linkedToList,
            ], 200);
        }

        return response()->json([
            'message' => $linkedToList
                ? 'Operación registrada y vinculada al listado operacional.'
                : 'Operación registrada correctamente.',
            'data' => $operation,
            'reused' => false,
            'linked_to_list' => $linkedToList,
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
            'anexo' => ['nullable', 'string', 'max:255'],
            'section_id' => ['nullable', 'integer', 'exists:operation_sections,id'],
            'measure' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'sam_base' => ['nullable', 'numeric', 'min:0'],
            'factor_operario' => ['sometimes', 'numeric', 'min:0.01'],
            'product_sheet_id_reference' => ['required', 'integer', 'exists:product_sheets,id'],
            'machine_id_reference' => ['required', 'integer', 'exists:machines,id'],
            'is_active' => ['sometimes', 'boolean'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        // Nota: el tipo de prenda (garment_type) vive en product_sheets, no en la operación.

        $motivo = $validated['motivo'] ?? null;
        unset($validated['motivo']);

        try {
            $operation = $this->service->update($operation, $validated, $motivo);

            return response()->json([
                'message' => 'Operación actualizada correctamente.',
                'data' => $operation->load(['section', 'productSheetReference', 'machineReference']),
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
        $logs = AuditLog::query()
            ->where('auditable_type', Operation::class)
            ->where('auditable_id', $operation->id)
            ->where('action', 'updated')
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();

        $normalized = $logs->map(function (AuditLog $log) use ($operation) {
            $oldValues = is_array($log->old_values) ? $log->old_values : [];
            $newValues = is_array($log->new_values) ? $log->new_values : [];
            $field = array_key_first($newValues) ?? array_key_first($oldValues) ?? null;

            $motivo = null;
            if (is_string($log->description) && preg_match('/Motivo:\s*(.*)$/u', $log->description, $matches) === 1) {
                $motivo = trim($matches[1]) ?: null;
            }

            return [
                'id' => $log->id,
                'operation_id' => $operation->id,
                'field_changed' => $field,
                'old_value' => $field ? ($oldValues[$field] ?? null) : null,
                'new_value' => $field ? ($newValues[$field] ?? null) : null,
                'motivo' => $motivo,
                'changed_by' => $log->user_id,
                'changed_by_user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'created_at' => $log->created_at,
                'updated_at' => $log->updated_at,
            ];
        })->values();

        return response()->json(['data' => $normalized]);
    }

    /**
     * Sábanas activas donde se usa esta operación
     * GET /produccion/operaciones/{operation}/referencias-activas
     */
    public function activeProductSheets(Operation $operation): JsonResponse
    {
        $sheets = ProductOperationList::query()
            ->where('operation_id', $operation->id)
            ->with('productSheet:id,sample_reference,product_reference,version,flat_sketch_path,is_enabled')
            ->get()
            ->map(fn ($pol) => $pol->productSheet)
            ->filter(fn ($ps) => $ps && $ps->is_enabled)
            ->unique('id')
            ->values()
            ->map(fn ($ps) => [
                'id' => $ps->id,
                'reference' => $ps->sample_reference ?? $ps->product_reference ?? "#{$ps->id}",
                'version' => $ps->version ?? 1,
                'flat_sketch_url' => $ps->flat_sketch_path ? $this->resolveFlatSketchUrl($ps->flat_sketch_path) : null,
            ]);

        return response()->json([
            'data' => $sheets,
            'total' => $sheets->count(),
        ]);
    }

    private function resolveFlatSketchUrl(string $rawPath): ?string
    {
        $path = str_replace('\\', '/', trim($rawPath));
        if (preg_match('/^https?:\/\//i', $path) || preg_match('/^(data:|blob:)/i', $path)) {
            return $path;
        }
        $normalized = ltrim($path, '/');
        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = Str::after($normalized, 'storage/');
        }
        $candidates = [$normalized];
        if (Str::startsWith($normalized, 'product-sheets/flat-sketches/')) {
            $candidates[] = Str::replaceFirst('product-sheets/flat-sketches/', 'product_sheets/', $normalized);
        }
        if (Str::startsWith($normalized, 'product_sheets/')) {
            $candidates[] = Str::replaceFirst('product_sheets/', 'product-sheets/flat-sketches/', $normalized);
        }
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && Storage::disk('public')->exists($candidate)) {
                return route('storage.file.show', ['path' => $candidate]);
            }
        }

        return null;
    }

    /**
     * Auto-vincula una operación al listado operacional de la sábana seleccionada.
     * Si ya existe un registro con la misma operación, máquina y sección, no duplica.
     * Retorna true si se creó la vinculación, false si ya existía o no pudo crearse.
     */
    private function autoLinkToProductOperationList(
        Operation $operation,
        int $productSheetId,
        int $machineId,
        int $sectionId,
        float $samBase,
        int $quantity,
    ): bool {
        // Verificar si ya existe la combinación en el listado
        $exists = ProductOperationList::where('product_sheet_id', $productSheetId)
            ->where('operation_id', $operation->id)
            ->where('machine_id', $machineId)
            ->whereHas('operation', fn ($q) => $q->where('section_id', $sectionId))
            ->withTrashed()
            ->exists();

        if ($exists) {
            return false;
        }

        // Obtener el suplemento de la máquina
        $machine = Machine::find($machineId);
        $machineSupplement = $machine ? (float) ($machine->supplement_factor ?? 1.0) : 1.0;

        // Calcular secuencia: siguiente disponible para esta ficha
        $maxSequence = ProductOperationList::where('product_sheet_id', $productSheetId)
            ->max('sequence') ?? 0;

        try {
            $this->productOperationListService->create([
                'product_sheet_id' => $productSheetId,
                'operation_id' => $operation->id,
                'machine_id' => $machineId,
                'operation_section_id' => $sectionId,
                'sequence' => $maxSequence + 10,
                'sam_base' => $samBase,
                'optimal_time' => $samBase,
                'operator_efficiency_factor' => 1.0,
                'machine_supplement_factor' => $machineSupplement,
                'fabric_base_id' => null,
                'quantity' => $quantity,
                'grupo_preparacion' => null,
                'notes' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Auto-link operación a listado falló', [
                'operation_id' => $operation->id,
                'product_sheet_id' => $productSheetId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
