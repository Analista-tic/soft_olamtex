<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\YarnName;
use App\Services\MasterData\YarnNameService;
use App\Services\Production\YarnBaseService;
use Illuminate\Http\Request;

class YarnNameController extends Controller
{
    public function __construct(
        protected YarnNameService $service,
        protected YarnBaseService $yarnBaseService,
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
        return $this->yarnBaseService->options();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'yarn_base_id' => ['required', 'integer', 'exists:yarn_bases,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->service->create($data);
    }

    public function update(Request $request, YarnName $yarnName)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'yarn_base_id' => ['required', 'integer', 'exists:yarn_bases,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->service->update($yarnName, $data);
    }

    public function destroy(YarnName $yarnName)
    {
        $this->service->delete($yarnName);

        return response()->noContent();
    }
}
