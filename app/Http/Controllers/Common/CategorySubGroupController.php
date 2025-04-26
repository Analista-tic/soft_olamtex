<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Common\CategoryGroup;
use App\Models\Common\CategorySubGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategorySubGroupController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuration/Category/Index', [
            'groups' => CategoryGroup::all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_group_id' => 'required|exists:category_groups,id',
        ]);

        $subgroup = CategorySubGroup::create($request->only('name', 'category_group_id'));

        return response()->json([
            'success' => true,
            'message' => 'Subgrupo creado exitosamente.',
            'data' => $subgroup,
        ]);
    }

    public function update(Request $request, CategorySubGroup $subgroup)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_group_id' => 'required|exists:category_groups,id',
        ]);

        $subgroup->update($request->only('name', 'category_group_id'));

        return response()->json([
            'success' => true,
            'message' => 'Subgrupo actualizado exitosamente.',
            'data' => $subgroup,
        ]);
    }

    public function getData(Request $request)
    {
        $query = CategorySubGroup::with('categoryGroup');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%");
            });
        }

        if ($request->has('group_id') && $request->group_id) {
            $query->where('category_group_id', $request->group_id);
        }

        $subgroups = $query->paginate($request->get('per_page', 10)); // Manejar paginación

        return response()->json([
            'data' => $subgroups->items(), // Datos de la página actual
            'total' => $subgroups->total(), // Total de registros
            'current_page' => $subgroups->currentPage(), // Página actual
            'last_page' => $subgroups->lastPage(), // Última página
        ]);
    }

    public function destroy(CategorySubGroup $subgroup)
    {
        $subgroup->delete();

        return redirect()->route('subgroups.create')->with('success', 'Subgrupo eliminado exitosamente.');
    }
}
