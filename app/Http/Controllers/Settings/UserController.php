<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Settings\User;
use App\Services\Settings\UserService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role; // Importar el modelo de roles

class UserController extends Controller
{
    public function __construct(protected UserService $service) {}

    public function index()
    {
        $users = $this->service->listAll();
        $roles = Role::with('permissions')->get();

        return Inertia::render('Configuration/Users/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
            'area_id' => 'nullable|integer|exists:parameters,id',
        ]);

        $validated['roles'] = is_array($validated['roles']) ? $validated['roles'] : [];
        $validated['roles'] = array_map('strval', $validated['roles']);

        $user = $this->service->create($validated);

        return response()->json($user, 201);
    }

    public function update(Request $request, $usuario)
    {
        $user = $this->service->findOrFail($usuario);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
            'area_id' => 'nullable|integer|exists:parameters,id',
        ]);

        $user = $this->service->update($user, $validated);

        return response()->json($user);
    }

    public function destroy($usuario)
    {
        $user = $this->service->findOrFail($usuario);
        $this->service->delete($user);

        return response()->json(null, 204);
    }

    public function getData(Request $request)
    {
        $perPage = $request->perPage ?? 10;
        $data = $this->service->paginate($request->all(), $perPage);

        return response()->json($data);
    }
}