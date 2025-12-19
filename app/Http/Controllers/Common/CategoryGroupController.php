<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Common\CategoryGroup;
use Illuminate\Http\Request;

class CategoryGroupController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:category_groups,name',
        ]);

        $group = CategoryGroup::create($request->only('name'));

        return response()->json($group, 201);
    }

    public function getData()
    {
        $groups = CategoryGroup::all(['id', 'name']); // Seleccionar solo los campos necesarios
        return response()->json($groups);
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
