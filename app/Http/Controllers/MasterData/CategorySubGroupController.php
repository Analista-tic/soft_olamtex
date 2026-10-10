<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\StoreCategorySubGroupRequest;
use App\Http\Requests\MasterData\UpdateCategorySubGroupRequest;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Services\MasterData\CategorySubGroupService;
use App\Support\MasterData\MaterialClassifier;
use App\Support\TextNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CategorySubGroupController extends Controller
{
    public function __construct(private CategorySubGroupService $service) {}

    public function index()
    {
        return Inertia::render('MasterData/Category/Index', [
            'groups' => CategoryGroup::all(),
        ]);
    }

    public function store(StoreCategorySubGroupRequest $request)
    {
        $groupId = (int) $request->validated('category_group_id');
        $subgroups = $this->normalizeIncomingSubgroups($request->validated('subgroups'));
        $this->ensureSubgroupsDoNotExist($groupId, $subgroups);

        $created = $this->service->createMany($groupId, $subgroups);

        return response()->json([
            'success' => true,
            'message' => 'Subgrupos creados exitosamente.',
            'data' => $created,
        ]);
    }

    public function update(UpdateCategorySubGroupRequest $request, CategorySubGroup $subgroup)
    {
        $groupId = (int) $request->validated('category_group_id');
        $normalizedName = TextNormalizer::upperCaseWords($request->validated('name'));

        $this->ensureSubgroupDoesNotExist($groupId, $normalizedName, $subgroup->id);

        $subgroup->update([
            'name' => $normalizedName,
            'category_group_id' => $groupId,
            'material_kind' => $request->validated('material_kind'),
        ]);

        MaterialClassifier::forgetCache();

        return response()->json([
            'success' => true,
            'message' => 'Subgrupo actualizado exitosamente.',
            'data' => $subgroup,
        ]);
    }

    public function getData(Request $request)
    {
        $query = CategorySubGroup::select('id', 'name', 'code', 'category_group_id', 'material_kind')
            ->with('categoryGroup:id,name');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhereRaw('CAST(id AS CHAR) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('categoryGroup', function ($gq) use ($search) {
                        $gq->where('name', 'like', "%$search%");
                    });
            });
        }

        if ($request->has('group_id') && $request->group_id) {
            $query->where('category_group_id', $request->group_id);
        }

        $sortBy = $request->get('sort_by', 'id');
        $sortDir = $request->get('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'name'];

        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        $query->orderBy($sortBy, $sortDir);

        $subgroups = $query->paginate($request->get('per_page', 10)); // Manejar paginación

        // material_kind ya viene directo de la columna (Fase 3 del refactor de
        // materiales) — antes se recalculaba aquí contra config('master_data.*').

        return response()->json([
            'data' => $subgroups->items(), // Datos de la página actual
            'total' => $subgroups->total(), // Total de registros
            'current_page' => $subgroups->currentPage(), // Página actual
            'last_page' => $subgroups->lastPage(), // Última página
        ]);
    }

    public function destroy(CategorySubGroup $subgroup)
    {
        if (Item::withTrashed()->where('category_sub_group_id', $subgroup->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el subgrupo porque tiene ítems asociados.',
            ], 409);
        }

        try {
            $subgroup->delete();
            MaterialClassifier::forgetCache();

            return response()->json([
                'success' => true,
                'message' => 'Subgrupo eliminado exitosamente.',
            ]);
        } catch (\Throwable $e) {
            // Registrar el error en el log y devolver respuesta JSON con estado 500
            logger()->error('Error eliminando subgrupo: '.$e->getMessage(), [
                'id' => $subgroup->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error eliminando subgrupo.',
            ], 500);
        }
    }

    /**
     * @param  array<int, array{name?: mixed}>  $subgroups
     * @return array<int, array{name: string}>
     */
    private function normalizeIncomingSubgroups(array $subgroups): array
    {
        $normalized = [];
        $seen = [];

        foreach ($subgroups as $subgroup) {
            $display = TextNormalizer::upperCaseWords($subgroup['name'] ?? null);
            $semanticKey = TextNormalizer::semanticKey($display);

            if ($display === '' || $semanticKey === '' || isset($seen[$semanticKey])) {
                continue;
            }

            $seen[$semanticKey] = true;
            $normalized[] = ['name' => $display];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'subgroups' => ['Debe agregar al menos un subgrupo válido.'],
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{name: string}>  $subgroups
     */
    private function ensureSubgroupsDoNotExist(int $groupId, array $subgroups): void
    {
        $incomingKeys = array_map(
            static fn (array $subgroup): string => TextNormalizer::semanticKey($subgroup['name']),
            $subgroups,
        );

        $existing = CategorySubGroup::query()
            ->where('category_group_id', $groupId)
            ->get(['name']);

        foreach ($existing as $subgroup) {
            $existingKey = TextNormalizer::semanticKey($subgroup->name);

            if ($existingKey !== '' && in_array($existingKey, $incomingKeys, true)) {
                throw ValidationException::withMessages([
                    'subgroups' => ['Uno o más subgrupos ya existen en el grupo seleccionado.'],
                ]);
            }
        }
    }

    private function ensureSubgroupDoesNotExist(int $groupId, string $name, ?int $ignoreId = null): void
    {
        $candidateKey = TextNormalizer::semanticKey($name);

        $existing = CategorySubGroup::query()
            ->where('category_group_id', $groupId)
            ->when($ignoreId !== null, static fn ($query) => $query->where('id', '<>', $ignoreId))
            ->get(['id', 'name']);

        foreach ($existing as $subgroup) {
            if (TextNormalizer::semanticKey($subgroup->name) === $candidateKey) {
                throw ValidationException::withMessages([
                    'name' => ['Ya existe un subgrupo con ese nombre en el grupo seleccionado.'],
                ]);
            }
        }
    }
}
