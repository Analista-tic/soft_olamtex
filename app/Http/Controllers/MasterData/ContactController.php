<?php

namespace App\Http\Controllers\MasterData;

use App\Helpers\NitHelper;
use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseDocument;
use App\Models\MasterData\Contact;
use App\Services\MasterData\ContactBulkImportService;
use App\Services\MasterData\ContactExcelService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContactController extends Controller
{
    public function __construct(
        protected ContactExcelService $excelService,
        protected ContactBulkImportService $bulkImportService,
    ) {}

    public function downloadTemplate()
    {
        return $this->excelService->downloadTemplate();
    }

    public function exportExcel()
    {
        return $this->excelService->exportAll();
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        // La plantilla mueve "Instrucciones" al frente y la deja como hoja activa
        // (moveInstructionsToFirst()), así que un Excel guardado sin cambiar de
        // pestaña reabre en "Instrucciones" — buscar la hoja de datos por nombre
        // evita leer esa hoja en vez de "contactos".
        $sheet = $spreadsheet->getSheetByName('contactos') ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $result = $this->bulkImportService->processBulkUploadArray($rows);

        $inserted = $result['inserted'] ?? 0;
        $updated = $result['updated'] ?? 0;

        if ($inserted === 0 && $updated === 0 && ! empty($result['errors'])) {
            return response()->json([
                'message' => 'No se pudo procesar ningún contacto. Errores: '.implode('; ', $result['errors']),
            ], 422);
        }

        $parts = [];
        if ($inserted > 0) {
            $parts[] = "{$inserted} contacto(s) creado(s)";
        }
        if ($updated > 0) {
            $parts[] = "{$updated} contacto(s) actualizado(s)";
        }

        return response()->json([
            'message' => 'Carga masiva completada. '.implode(', ', $parts),
            'errors' => $result['errors'] ?? [],
            'inserted' => $inserted,
            'updated' => $updated,
        ]);
    }

    public function index(Request $request)
    {
        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = $request->input('sort_direction', $request->input('sort_dir', 'asc'));
        $allowedSorts = ['id', 'id_number'];

        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'asc';
        }

        // El frontend (MasterData/Contact/Index.vue) trae los contactos paginados vía
        // getData() en su propio onMounted() — nunca declaró 'contacts' como prop, así
        // que enviar Contact::all() aquí era una consulta completa de la tabla en cada
        // carga de la página que ni siquiera se usaba.
        return Inertia::render('MasterData/Contact/Index', [
            'filters' => [
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    public function getData(Request $request)
    {
        $contactType = $request->input('contact_type');
        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = $request->input('sort_direction', $request->input('sort_dir', 'asc'));
        $allowedSorts = ['id', 'id_number'];

        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'asc';
        }

        $query = Contact::query()
            ->when($request->search, fn ($query, $search) =>
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('id_number', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhereRaw('CAST(id AS CHAR) LIKE ?', ["%{$search}%"])
                        ->orWhere('contact_type', 'like', "%$search%");
                }))
            ->when(in_array($contactType, Contact::CONTACT_TYPES, true), fn ($query) =>
                $query->where('contact_type', $contactType))
            ->when($request->group_id, fn ($query, $groupId) =>
                $query->whereJsonContains('groups', $groupId));

        if ($sortBy === 'id_number') {
            $query->orderByRaw("(id_number + 0) {$sortDirection}");
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $contacts = $query->paginate($request->get('per_page', 10));

        return response()->json($contacts);
    }

    public function list(Request $request)
    {
        $contactType  = $request->input('contact_type');
        $contactTypes = $request->input('contact_types', []);

        $query = Contact::query()
            ->select('id', 'full_name', 'contact_type')
            ->orderBy('full_name');

        if (!empty($contactTypes) && is_array($contactTypes)) {
            $valid = array_filter($contactTypes, fn ($t) => in_array($t, Contact::CONTACT_TYPES, true));
            if (!empty($valid)) {
                $query->whereIn('contact_type', array_values($valid));
            }
        } elseif (in_array($contactType, Contact::CONTACT_TYPES, true)) {
            $query->where('contact_type', $contactType);
        }

        return response()->json($query->get());
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
