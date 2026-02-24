<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Settings\RoleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function __construct(protected RoleService $service) {}
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = $this->service->listAll();
        $permissions = $this->service->allPermissions();
        return Inertia::render('Configuration/Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'area_id' => [
                'nullable',
                'integer',
                'required_without:area_name',
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $areas = \App\Models\MasterData\Parameter::where('nombre', 'areas')->first();
                    $ids = collect($areas ? $areas->valor : [])->pluck('id')->toArray();
                    if (!in_array($value, $ids)) {
                        $fail('El área seleccionada no es válida.');
                    }
                }
            ],
            'area_name' => [
                'nullable',
                'string',
                'max:255',
                'required_without:area_id',
                function ($attribute, $value, $fail) {
                    if ($value !== null && trim((string) $value) === '') {
                        $fail('El nombre del área es obligatorio.');
                    }
                }
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = $this->service->create($validated);
        return response()->json($role, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $roleId)
    {
        $role = $this->service->findOrFail($roleId);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'area_id' => [
                'nullable',
                'integer',
                'required_without:area_name',
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $areas = \App\Models\MasterData\Parameter::where('nombre', 'areas')->first();
                    $ids = collect($areas ? $areas->valor : [])->pluck('id')->toArray();
                    if (!in_array($value, $ids)) {
                        $fail('El área seleccionada no es válida.');
                    }
                }
            ],
            'area_name' => [
                'nullable',
                'string',
                'max:255',
                'required_without:area_id',
                function ($attribute, $value, $fail) {
                    if ($value !== null && trim((string) $value) === '') {
                        $fail('El nombre del área es obligatorio.');
                    }
                }
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = $this->service->update($role, $validated);

        return response()->json($role);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($roleId)
    {
        $role = $this->service->findOrFail($roleId);
        $this->service->delete($role);

        return response()->json(null, 204);
    }

    /**
     * Get data with pagination and search functionality.
     */
    public function getData(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $data = $this->service->paginate($request->all(), $perPage);

        return response()->json($data);
    }
}
