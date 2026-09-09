<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Production\FabricBase;
use App\Models\Production\YarnBase;
use App\Services\Production\FabricBaseService;
use App\Services\Production\FabricFactorPropagationService;
use App\Services\Production\YarnBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de bases de tela e hilaza (fabric_bases / yarn_bases) desde la pantalla
 * única de Datos Maestros. Reutiliza los mismos servicios que antes se exponían desde
 * el diálogo de catálogos operativos de Producción (OperationCatalogController) — esa
 * ruta queda retirada para no dejar dos APIs gestionando lo mismo.
 */
class MaterialCatalogController extends Controller
{
    public function __construct(
        protected FabricBaseService $fabricBaseService,
        protected YarnBaseService $yarnBaseService,
        protected FabricFactorPropagationService $propagationService,
    ) {}

    /**
     * Solo lectura: cuántas fichas y operaciones de orden se recalcularían si se
     * guarda el factor de esta base ahora. Lo consulta el frontend antes de pedir
     * confirmación al usuario ("esto recalculará N listados y M operaciones").
     */
    public function factorImpact(FabricBase $fabricBase): JsonResponse
    {
        return response()->json(['data' => $this->propagationService->preview($fabricBase)]);
    }

    /**
     * Listado paginado de bases (tela o hilaza, según ?type) para la pantalla única de
     * catálogo de materiales. Antes devolvía ambos catálogos completos en una sola
     * respuesta (managementList() sin paginar) — con 38+ bases de tela ya registradas,
     * eso ya no escala; ahora cada pestaña pide solo lo suyo, con búsqueda/orden/página.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type') === 'yarn' ? 'yarn' : 'fabric';
        $filters = $request->only(['search', 'sort_by', 'sort_direction']);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        $bases = $type === 'fabric'
            ? $this->fabricBaseService->paginate($filters, $perPage)
            : $this->yarnBaseService->paginate($filters, $perPage);

        if ($type === 'fabric') {
            $bases->setCollection(
                collect($this->stripFactorIfUnauthorized($request, collect($bases->items())))
            );
        }

        return response()->json([
            'data' => $bases->items(),
            'total' => $bases->total(),
            'current_page' => $bases->currentPage(),
        ]);
    }

    public function store(Request $request, string $catalog): JsonResponse
    {
        $catalog = $this->normalizeCatalog($catalog);
        $validated = $this->validatePayload($request, $catalog);

        $data = $catalog === 'fabric-bases'
            ? $this->fabricBaseService->create($validated)
            : $this->yarnBaseService->create($validated);

        return response()->json([
            'message' => 'Registro creado correctamente.',
            'data' => $data,
        ], 201);
    }

    public function update(Request $request, string $catalog, int $id): JsonResponse
    {
        $catalog = $this->normalizeCatalog($catalog);
        $validated = $this->validatePayload($request, $catalog);

        $factorPropagation = null;
        if ($catalog === 'fabric-bases') {
            $data = $this->fabricBaseService->update(FabricBase::query()->findOrFail($id), $validated);
            $factorPropagation = $this->fabricBaseService->lastFactorPropagation();
        } else {
            $data = $this->yarnBaseService->update(YarnBase::query()->findOrFail($id), $validated);
        }

        $message = 'Registro actualizado correctamente.';
        if ($factorPropagation !== null) {
            $message .= " Se recalcularon {$factorPropagation['sheets']} ficha(s) y {$factorPropagation['order_operations']} operación(es) de orden.";
        }

        return response()->json([
            'message' => $message,
            'data' => $data,
            'factor_propagation' => $factorPropagation,
        ]);
    }

    public function destroy(string $catalog, int $id): JsonResponse
    {
        $catalog = $this->normalizeCatalog($catalog);

        if ($catalog === 'fabric-bases') {
            $this->fabricBaseService->delete(FabricBase::query()->findOrFail($id));
        } else {
            $this->yarnBaseService->delete(YarnBase::query()->findOrFail($id));
        }

        return response()->json(['message' => 'Registro eliminado correctamente.']);
    }

    /**
     * El factor afecta el costeo (SAM) y es información de Diseño, no de quien solo
     * selecciona una tela al crear un ítem. Se retira aquí, en la capa HTTP, para que
     * FabricBaseService siga siendo reutilizable sin conocer permisos de usuario.
     */
    private function stripFactorIfUnauthorized(Request $request, Collection $bases): Collection
    {
        if ($request->user()?->can('ver factor material')) {
            return $bases;
        }

        return $bases->map(function (array $base) {
            unset($base['factor']);

            return $base;
        });
    }

    private function validatePayload(Request $request, string $catalog): array
    {
        return $request->validate(
            $catalog === 'fabric-bases'
                ? [
                    'nombre' => ['required', 'string', 'max:150'],
                    'factor' => ['required', 'numeric', 'min:0.0001', 'max:99.9999'],
                    'is_active' => ['sometimes', 'boolean'],
                ]
                : [
                    'nombre' => ['required', 'string', 'max:150'],
                    'is_active' => ['sometimes', 'boolean'],
                ]
        );
    }

    private function normalizeCatalog(string $catalog): string
    {
        if (! in_array($catalog, ['fabric-bases', 'yarn-bases'], true)) {
            throw ValidationException::withMessages([
                'catalog' => 'El catálogo solicitado no es válido.',
            ]);
        }

        return $catalog;
    }
}
