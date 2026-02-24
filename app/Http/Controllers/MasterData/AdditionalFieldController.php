<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdditionalFieldRequest;
use App\Models\Settings\Setting;
use App\Services\MasterData\AdditionalFieldService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdditionalFieldController extends Controller
{
    public function __construct(protected AdditionalFieldService $service) {}

    public function index()
    {
        return Inertia::render('MasterData/AdditionalFields/Index', [
            'fieldTypes' => Setting::FIELD_TYPES,
        ]);
    }

    public function store(AdditionalFieldRequest $request)
    {
        return $this->service->create($request->validated());
    }

    public function show($id)
    {
        return $this->service->find($id);
    }

    public function update(AdditionalFieldRequest $request, $id)
    {
        $field = $this->service->find($id);

        return $this->service->update($field, $request->validated());
    }

    public function destroy($id)
    {
        $field = $this->service->find($id);
        $this->service->delete($field);

        return response()->noContent();
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|min:1',
        ]);

        $this->service->reorder($validated['order']);

        return response()->json(['message' => 'Orden actualizado correctamente.']);
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['search', 'field_type']);
        if ($request->has('status')) {
            $filters['status'] = filter_var($request->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        $perPage = $request->input('per_page', 10);
        $additionalFields = $this->service->paginate($filters, $perPage);

        return response()->json($additionalFields);
    }

    public function options()
    {
        return response()->json([
            'data' => $this->service->options(),
        ]);
    }

}
