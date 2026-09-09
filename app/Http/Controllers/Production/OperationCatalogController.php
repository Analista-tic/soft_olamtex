<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\Machine;
use App\Models\Production\OperationSection;
use App\Services\Production\MachineService;
use App\Services\Production\OperationCatalogService;
use App\Services\Production\OperationSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OperationCatalogController extends Controller
{
    public function __construct(
        protected OperationSectionService $sectionService,
        protected OperationCatalogService $catalogService,
        protected MachineService $machineService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'sections' => $this->sectionService->managementList(),
                'actions' => $this->catalogService->managementList('operation_actions'),
                'garment_parts' => $this->catalogService->managementList('operation_garment_parts'),
                'machines' => $this->machineService->managementList(),
            ],
        ]);
    }

    public function store(Request $request, string $catalog): JsonResponse
    {
        $catalog = $this->normalizeCatalog($catalog);
        $validated = $this->validatePayload($request, $catalog);

        $data = match ($catalog) {
            'sections' => $this->sectionService->create($validated),
            'machines' => $this->machineService->create($validated),
            default => $this->catalogService->createOption($this->catalogToServiceKey($catalog), $validated),
        };

        return response()->json([
            'message' => 'Registro creado correctamente.',
            'data' => $data,
        ], 201);
    }

    public function update(Request $request, string $catalog, int $id): JsonResponse
    {
        $catalog = $this->normalizeCatalog($catalog);
        $validated = $this->validatePayload($request, $catalog);

        $data = match ($catalog) {
            'sections' => $this->sectionService->update(OperationSection::query()->findOrFail($id), $validated),
            'machines' => $this->machineService->update(Machine::query()->findOrFail($id), $validated),
            default => $this->catalogService->updateOption($this->catalogToServiceKey($catalog), $id, $validated),
        };

        return response()->json([
            'message' => 'Registro actualizado correctamente.',
            'data' => $data,
        ]);
    }

    public function destroy(string $catalog, int $id): JsonResponse
    {
        $catalog = $this->normalizeCatalog($catalog);

        match ($catalog) {
            'sections' => $this->sectionService->delete(OperationSection::query()->findOrFail($id)),
            'machines' => $this->machineService->delete(Machine::query()->findOrFail($id)),
            default => $this->catalogService->deleteOption($this->catalogToServiceKey($catalog), $id),
        };

        return response()->json([
            'message' => 'Registro eliminado correctamente.',
        ]);
    }

    private function validatePayload(Request $request, string $catalog): array
    {
        return $request->validate(
            match ($catalog) {
                'sections' => [
                    'name' => ['required', 'string', 'max:255'],
                    'type' => ['required', 'string', 'in:PREPARACION,ENSAMBLE,TERMINACION,confeccion,presentacion'],
                    'is_active' => ['sometimes', 'boolean'],
                ],
                'machines' => [
                    'code' => ['required', 'string', 'max:255'],
                    'name' => ['required', 'string', 'max:255'],
                    'supplement_factor' => ['required', 'numeric', 'min:0'],
                    'is_active' => ['sometimes', 'boolean'],
                ],
                default => [
                    'name' => ['required', 'string', 'max:255'],
                    'is_active' => ['sometimes', 'boolean'],
                ],
            }
        );
    }

    private function normalizeCatalog(string $catalog): string
    {
        // fabric-bases / yarn-bases se retiraron de aquí: se gestionan desde
        // Datos Maestros → Catálogo de materiales (MaterialCatalogController), la
        // pantalla única de la Fase 2 del refactor de materiales. Mantener ambas rutas
        // hubiera dejado dos APIs gestionando la misma tabla.
        if (! in_array($catalog, ['sections', 'actions', 'garment-parts', 'machines'], true)) {
            throw ValidationException::withMessages([
                'catalog' => 'El catálogo solicitado no es válido.',
            ]);
        }

        return $catalog;
    }

    private function catalogToServiceKey(string $catalog): string
    {
        return match ($catalog) {
            'actions' => 'operation_actions',
            'garment-parts' => 'operation_garment_parts',
            default => throw ValidationException::withMessages([
                'catalog' => 'El catálogo solicitado no es válido.',
            ]),
        };
    }
}
