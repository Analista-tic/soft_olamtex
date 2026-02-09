<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\StoreParameterRequest;
use App\Http\Requests\MasterData\UpdateParameterRequest;
use App\Services\MasterData\ParameterService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ParameterController extends Controller
{
    public function __construct(protected ParameterService $service) {}

    public function index(Request $request)
    {
        return Inertia::render('MasterData/Parameters/Index', [
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function data(Request $request)
    {
        $filters = $request->only(['search', 'status', 'sort_by', 'sort_direction']);
        $parameters = $this->service->list($filters);

        return response()->json(['data' => $parameters]);
    }

    public function store(StoreParameterRequest $request)
    {
        $parameter = $this->service->create($request->validated());

        return response()->json($parameter, 201);
    }

    public function show($id)
    {
        return response()->json($this->service->find((int) $id));
    }

    public function update(UpdateParameterRequest $request, $id)
    {
        $parameter = $this->service->update((int) $id, $request->validated());

        return response()->json($parameter);
    }

    public function destroy($id)
    {
        $this->service->delete((int) $id);

        return response()->noContent();
    }
}
