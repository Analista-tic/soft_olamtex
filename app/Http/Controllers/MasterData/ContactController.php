<?php

namespace App\Http\Controllers\MasterData;

use App\Helpers\NitHelper;
use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseDocument;
use App\Models\MasterData\Contact;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ContactController extends Controller
{
    public function index()
    {
        return Inertia::render('MasterData/Contact/Index', [
            'contacts' => Contact::all(),
        ]);
    }

    public function getData(Request $request)
    {
        $contactType = $request->input('contact_type');

        $contacts = Contact::query()
            ->when($request->search, fn ($query, $search) =>
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%");
                }))
            ->when(in_array($contactType, Contact::CONTACT_TYPES, true), fn ($query) =>
                $query->where('contact_type', $contactType))
            ->when($request->group_id, fn ($query, $groupId) =>
                $query->whereJsonContains('groups', $groupId))
            ->paginate($request->get('per_page', 10));

        return response()->json($contacts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), $this->messages());
        $this->validateNitDv($request, $validated);

        try {
            $uniqueEmails = collect($validated['additional_contacts'] ?? [])
                ->pluck('email')
                ->filter()
                ->unique();
            if ($uniqueEmails->count() !== count($validated['additional_contacts'] ?? [])) {
                return response()->json(['message' => 'Los correos de los contactos adicionales deben ser únicos.'], 422);
            }

            $uniqueBranchNames = collect($validated['branches'] ?? [])
                ->pluck('name')
                ->filter()
                ->unique();
            if ($uniqueBranchNames->count() !== count($validated['branches'] ?? [])) {
                return response()->json(['message' => 'Los nombres de las sucursales deben ser únicos.'], 422);
            }

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
        $validated = $request->validate($this->rules($contact), $this->messages());
        $this->validateNitDv($request, $validated);

        try {
            $uniqueEmails = collect($validated['additional_contacts'] ?? [])
                ->pluck('email')
                ->filter()
                ->unique();
            if ($uniqueEmails->count() !== count($validated['additional_contacts'] ?? [])) {
                return response()->json(['message' => 'Los correos de los contactos adicionales deben ser únicos.'], 422);
            }

            $uniqueBranchNames = collect($validated['branches'] ?? [])
                ->pluck('name')
                ->filter()
                ->unique();
            if ($uniqueBranchNames->count() !== count($validated['branches'] ?? [])) {
                return response()->json(['message' => 'Los nombres de las sucursales deben ser únicos.'], 422);
            }

            $validated['additional_contacts'] = $validated['additional_contacts'] ?? [];
            $validated['groups'] = $validated['groups'] ?? [];
            $validated['branches'] = $validated['branches'] ?? [];

            $contact->update($validated);

            return response()->json(['message' => 'Contacto actualizado exitosamente.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el contacto.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function destroy(Contact $contact)
    {
        if ($this->hasActiveAssociations($contact)) {
            return response()->json(
                [
                    'message' => 'No se puede eliminar el contacto porque tiene facturas u órdenes activas asociadas.',
                ],
                409,
            );
        }

        try {
            $contact->delete();

            return response()->json(['message' => 'Contacto eliminado exitosamente.']);
        } catch (QueryException $exception) {
            report($exception);

            return response()->json(['message' => 'Error al eliminar el contacto.'], 500);
        }
    }

    private function hasActiveAssociations(Contact $contact): bool
    {
        return PurchaseDocument::where('contact_id', $contact->id)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function validateNitDv(Request $request, array $validated): void
    {
        $idType = $validated['id_type'] ?? $request->input('id_type');
        $nit = $validated['id_number'] ?? $request->input('id_number');
        $dvIngresado = $request->input('dv');

        if ($idType !== 'nit' || empty($nit) || is_null($dvIngresado)) {
            return;
        }

        $dvCalculado = NitHelper::calcularDV($nit);

        if ((int) $dvIngresado !== $dvCalculado) {
            throw ValidationException::withMessages([
                'dv' => 'El digito de verificacion no corresponde al NIT ingresado.',
            ]);
        }
    }

    private function rules(?Contact $contact = null): array
    {
        $idNumberUnique = Rule::unique('contacts', 'id_number');
        $emailUnique = Rule::unique('contacts', 'email');

        if ($contact) {
            $idNumberUnique = $idNumberUnique->ignore($contact->id);
            $emailUnique = $emailUnique->ignore($contact->id);
        }

        return [
            'contact_type' => ['required', Rule::in(Contact::CONTACT_TYPES)],
            'id_type' => ['required', Rule::in(Contact::ID_TYPES)],
            'id_number' => ['required', 'string', 'max:255', $idNumberUnique],
            'dv' => ['nullable', 'string', 'max:10'],
            'full_name' => ['required', 'string', 'max:255'],
            'person_type' => ['required', Rule::in(Contact::PERSON_TYPES)],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['exists:category_groups,id'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'email' => ['required', 'email', $emailUnique],
            'phone' => ['nullable', 'string', 'max:20'],
            'additional_contacts' => ['nullable', 'array'],
            'additional_contacts.*.name' => ['required_with:additional_contacts', 'string', 'max:255'],
            'additional_contacts.*.phone' => ['nullable', 'string', 'max:20'],
            'additional_contacts.*.email' => ['nullable', 'email', 'max:255'],
            'branches' => ['nullable', 'array'],
            'branches.*.name' => ['required_with:branches', 'string', 'max:255'],
            'branches.*.address' => ['nullable', 'string', 'max:255'],
            'branches.*.phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function messages(): array
    {
        return [
            'contact_type.in' => 'El tipo de contacto seleccionado no es válido.',
            'id_type.in' => 'El tipo de identificación seleccionado no es válido.',
            'person_type.in' => 'El tipo de persona seleccionado no es válido.',
            'additional_contacts.*.name.required_with' => 'El nombre del contacto adicional es obligatorio.',
            'additional_contacts.*.email.email' => 'El correo del contacto adicional debe ser válido.',
            'branches.*.name.required_with' => 'El nombre de la sucursal es obligatorio.',
        ];
    }
}
