<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Common\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuration/Contact/Index', [
            'contacts' => Contact::all(),
        ]);
    }

    public function getData(Request $request)
    {
        $contacts = Contact::query()
            ->when($request->search, fn($query, $search) => 
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%");
                })
            )
            ->when($request->contact_type, fn($query, $contactType) => 
                $query->where('contact_type', $contactType)
            )
            ->when($request->group_id, fn($query, $groupId) => 
                $query->whereJsonContains('groups', $groupId)
            )
            ->paginate($request->get('per_page', 10));

        return response()->json($contacts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_type' => 'required|string|max:255',
            'id_type' => 'required|string|max:255',
            'id_number' => 'required|string|max:255|unique:contacts,id_number',
            'dv' => 'nullable|string|max:10',
            'full_name' => 'required|string|max:255',
            'person_type' => 'required|string|max:255',
            'groups' => 'nullable|array',
            'groups.*' => 'exists:category_groups,id',
            'municipality' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'email' => 'required|email|unique:contacts,email',
            'phone' => 'nullable|string|max:20',
            'additional_contacts' => 'nullable|array',
            'additional_contacts.*.name' => 'required_with:additional_contacts|string|max:255',
            'additional_contacts.*.phone' => 'nullable|string|max:20',
            'additional_contacts.*.email' => 'nullable|email|max:255',
            'branches' => 'nullable|array',
            'branches.*.name' => 'required_with:branches|string|max:255',
            'branches.*.address' => 'nullable|string|max:255',
            'branches.*.phone' => 'nullable|string|max:20',
        ], [
            'additional_contacts.*.name.required_with' => 'El nombre del contacto adicional es obligatorio.',
            'additional_contacts.*.email.email' => 'El correo del contacto adicional debe ser válido.',
            'branches.*.name.required_with' => 'El nombre de la sucursal es obligatorio.',
        ]);

        try {
            // Validar que no haya contactos adicionales duplicados
            $uniqueEmails = collect($validated['additional_contacts'] ?? [])
                ->pluck('email')
                ->filter()
                ->unique();
            if ($uniqueEmails->count() !== count($validated['additional_contacts'] ?? [])) {
                return response()->json(['message' => 'Los correos de los contactos adicionales deben ser únicos.'], 422);
            }

            // Validar que no haya sucursales duplicadas
            $uniqueBranchNames = collect($validated['branches'] ?? [])
                ->pluck('name')
                ->filter()
                ->unique();
            if ($uniqueBranchNames->count() !== count($validated['branches'] ?? [])) {
                return response()->json(['message' => 'Los nombres de las sucursales deben ser únicos.'], 422);
            }

            // Asegurarse de que additional_contacts y branches sean arrays válidos
            $validated['additional_contacts'] = $validated['additional_contacts'] ?? [];
            $validated['groups'] = $validated['groups'] ?? [];
            $validated['branches'] = $validated['branches'] ?? [];

            Contact::create($validated);
            return response()->json(['message' => 'Contacto creado exitosamente.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el contacto.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function show(Contact $contact)
    {
        return response()->json($contact);
    }

    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'contact_type' => 'required|string|max:255',
            'id_type' => 'required|string|max:255',
            'id_number' => 'required|string|max:255|unique:contacts,id_number,' . $contact->id,
            'dv' => 'nullable|string|max:10',
            'full_name' => 'required|string|max:255',
            'person_type' => 'required|string|max:255',
            'groups' => 'nullable|array',
            'groups.*' => 'exists:category_groups,id',
            'municipality' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'email' => 'required|email|unique:contacts,email,' . $contact->id,
            'phone' => 'nullable|string|max:20',
            'additional_contacts' => 'nullable|array', // Validar contactos adicionales
            'additional_contacts.*.name' => 'required_with:additional_contacts|string|max:255',
            'additional_contacts.*.phone' => 'nullable|string|max:20',
            'additional_contacts.*.email' => 'nullable|email|max:255',
            'branches' => 'nullable|array',
            'branches.*.name' => 'required_with:branches|string|max:255',
            'branches.*.address' => 'nullable|string|max:255',
            'branches.*.phone' => 'nullable|string|max:20',
        ], [
            'additional_contacts.*.name.required_with' => 'El nombre del contacto adicional es obligatorio.',
            'additional_contacts.*.email.email' => 'El correo del contacto adicional debe ser válido.',
            'branches.*.name.required_with' => 'El nombre de la sucursal es obligatorio.',
        ]);

        try {
            // Validar que no haya contactos adicionales duplicados
            $uniqueEmails = collect($validated['additional_contacts'] ?? [])
                ->pluck('email')
                ->filter()
                ->unique();
            if ($uniqueEmails->count() !== count($validated['additional_contacts'] ?? [])) {
                return response()->json(['message' => 'Los correos de los contactos adicionales deben ser únicos.'], 422);
            }

            // Validar que no haya sucursales duplicadas
            $uniqueBranchNames = collect($validated['branches'] ?? [])
                ->pluck('name')
                ->filter()
                ->unique();
            if ($uniqueBranchNames->count() !== count($validated['branches'] ?? [])) {
                return response()->json(['message' => 'Los nombres de las sucursales deben ser únicos.'], 422);
            }

            // Asegurarse de que additional_contacts y branches sean arrays válidos
            $validated['additional_contacts'] = $validated['additional_contacts'] ?? [];
            $validated['groups'] = $validated['groups'] ?? [];
            $validated['branches'] = $validated['branches'] ?? [];

            $contact->update($validated); // Actualizar el contacto con los datos validados
            return response()->json(['message' => 'Contacto actualizado exitosamente.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el contacto.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return response()->json(['message' => 'Contacto eliminado exitosamente.']);
    }
}
