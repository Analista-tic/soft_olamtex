<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\FabricName;
use App\Services\MasterData\FabricNameService;
use App\Services\Production\FabricBaseService;
use Illuminate\Http\Request;

class FabricNameController extends Controller
{
    public function __construct(
        protected FabricNameService $service,
        protected FabricBaseService $fabricBaseService,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'base_id', 'sort_direction']);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $names = $this->service->paginate($filters, $perPage);

        return response()->json([
            'data' => $names->items(),
            'total' => $names->total(),
            'current_page' => $names->currentPage(),
        ]);
    }

    public function options()
    {
        return $this->service->options();
    }

    public function baseOptions()
    {
        return $this->fabricBaseService->options();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'fabric_base_id' => ['required', 'integer', 'exists:fabric_bases,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->service->create($data);
    }

    public function update(Request $request, FabricName $fabricName)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'fabric_base_id' => ['required', 'integer', 'exists:fabric_bases,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->service->update($fabricName, $data);
    }

    public function destroy(FabricName $fabricName)
    {
        $this->service->delete($fabricName);

        return response()->noContent();
    }
}
