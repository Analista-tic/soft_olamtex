<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\AdditionalField;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdditionalFieldController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuration/AdditionalFields/Index', [
            'fieldTypes' => AdditionalField::FIELD_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $data['status'] = $data['status'] ?? true;

        return AdditionalField::create($data);
    }

    public function show($id)
    {
        return AdditionalField::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $field = AdditionalField::findOrFail($id);
        $field->update($this->validateData($request));

        return $field;
    }

    public function destroy($id)
    {
        AdditionalField::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function getData(Request $request)
    {
        $query = AdditionalField::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        $perPage = $request->input('per_page', 10);
        $fieldTypes = $request->input('field_type');
        if ($fieldTypes) {
            $query->whereIn('field_type', (array) $fieldTypes);
        }

        $additionalFields = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($additionalFields);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'field_type' => ['required', Rule::in(AdditionalField::FIELD_TYPES)],
            'options' => 'nullable',
            'default_value' => 'nullable|string',
            'description' => 'nullable|string',
            'is_required' => 'sometimes|boolean',
            'status' => 'sometimes|boolean',
        ]);

        $data['is_required'] = $request->has('is_required') ? $request->boolean('is_required') : false;
        $data['status'] = $request->has('status') ? $request->boolean('status') : null;

        $options = $this->normalizeOptions($request->input('options'));
        $data['options'] = $options;

        return $data;
    }

    private function normalizeOptions(mixed $options): ?array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $options = $decoded;
            } else {
                $options = preg_split('/\\r?\\n/', $options) ?: [];
            }
        }

        if (!is_array($options)) {
            return null;
        }

        $sanitized = array_values(array_filter(array_map(function ($value) {
            if ($value === null) {
                return null;
            }
            return trim((string) $value);
        }, $options), fn ($value) => $value !== ''));

        return $sanitized ?: null;
    }
}
