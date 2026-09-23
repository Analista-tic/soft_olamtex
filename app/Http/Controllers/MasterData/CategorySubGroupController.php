<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\StoreCategorySubGroupRequest;
use App\Http\Requests\MasterData\UpdateCategorySubGroupRequest;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Services\MasterData\CategorySubGroupService;
use App\Support\MasterData\MaterialClassifier;
use Illuminate\Http\Request;
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
        $created = $this->service->create($groupId, $request->validated('subgroups'));

        return response()->json([
            'success' => true,
            'message' => 'Subgrupos creados exitosamente.',
            'data' => $created->values(),
        ]);
    }

    public function update(UpdateCategorySubGroupRequest $request, CategorySubGroup $subgroup)
    {
        $groupId = (int) $request->validated('category_group_id');

        $this->service->update(
            $subgroup,
            $groupId,
            $request->validated('name'),
            $request->validated('code'),
            $request->validated('material_kind'),
        );

        MaterialClassifier::forgetCache();

        return response()->json([
            'success' => true,
            'message' => 'Subgrupo actualizado exitosamente.',
            'data' => $subgroup,
        ]);
    }

    public function getData(Request $request)
    {
        $query = CategorySubGroup::select('id', 'name', 'category_group_id', 'material_kind')
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
}
