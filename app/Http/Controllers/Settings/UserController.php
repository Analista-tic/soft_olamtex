<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role; // Importar el modelo de roles

class UserController extends Controller
{
    //ENVIA TODOS LOS USUARIOS A LA VISTA
    public function index()
    {
        $users = User::with('roles.permissions')->get(); // Asegurar que los roles y permisos se carguen correctamente
        $roles = Role::with('permissions')->get(); // Obtener roles con permisos

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles, // Enviar roles al frontend
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'roles' => 'required|array', // Validar que sea un array
            'roles.*' => 'exists:roles,name', // Validar que cada rol exista
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['roles'] = is_array($validated['roles']) ? $validated['roles'] : []; // Asegurar que roles sea un array antes de procesarlo
        $validated['roles'] = array_map('strval', $validated['roles']); // Asegurar que los roles sean cadenas antes de sincronizarlos
        $user = User::create($validated);

        $user->syncRoles($validated['roles']); // Asignar múltiples roles al usuario

        return response()->json($user, 201);
    }

    public function update(Request $request, $usuario)
    {
        $user = User::findOrFail($usuario);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'roles' => 'required|array', // Validar que sea un array
            'roles.*' => 'exists:roles,name', // Validar que cada rol exista
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']); // No actualizar la contraseña si está vacía
        }

        $roles = $validated['roles'];
        unset($validated['roles']);

        $user->update($validated);

        $user->syncRoles($roles); // Sincronizar roles correctamente

        // Sincronizar permisos basados en los roles asignados
        $permissions = Role::whereIn('name', $roles)->with('permissions')->get()->pluck('permissions.*.name')->flatten();
        $user->syncPermissions($permissions);

        return response()->json($user); // Asegurar que se devuelva el usuario actualizado
    }

    public function destroy($usuario)
    {
        $user = User::findOrFail($usuario);
        $user->delete();

        return response()->json(null, 204);
    }

    public function getData(Request $request)
    {
        $query = User::with('roles'); // Incluir los roles asociados a los usuarios

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('sortBy')) {
            $query->orderBy($request->sortBy, $request->sortDirection ?? 'asc');
        }

        return response()->json($query->paginate($request->perPage ?? 10));
    }
}