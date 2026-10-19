<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\CategoryGroup;
use App\Services\MasterData\CategoryGroupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryGroupController extends Controller
{
    public function __construct(private CategoryGroupService $service) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:category_groups,name',
        ]);

        $group = $this->service->create($data);

        return response()->json($group, 201);
    }

    /**
     * No existía update() para grupos — se agrega en la Fase 13 del refactor de
     * materiales, principalmente para poder asignarle `code` a los grupos existentes
     * (necesario para la referencia automática de ítems); de paso permite corregir
     * el nombre, algo que tampoco era posible antes.
     */
    public function update(Request $request, CategoryGroup $group)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('category_groups', 'name')->ignore($group->id)],
        ]);

        $group->update($request->only('name'));

        return response()->json($group);
    }

    public function getData()
    {
        $groups = CategoryGroup::all(['id', 'name', 'code']); // Seleccionar solo los campos necesarios

        return response()->json($groups);
    }

    public function management()
    {
        return Inertia::render('MasterData/Category/Groups', [
            'groups' => CategoryGroup::query()->withCount('subGroups')->orderBy('name')->get(),
        ]);
    }

    public function destroy(CategoryGroup $group)
    {
        try {
            // Verificar si el grupo tiene dependencias antes de eliminarlo
            if ($group->subGroups()->exists()) {
                return response()->json(['message' => 'No se puede eliminar el grupo porque tiene subgrupos asociados.'], 400);
            }

            $group->delete();

            return response()->json(['message' => 'Grupo eliminado correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ocurrió un error al eliminar el grupo.'], 500);
        }
    }
}
