<?php

namespace App\Http\Controllers\MasterData;

use App\Exceptions\MasterData\ParameterInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\StoreParameterRequest;
use App\Http\Requests\MasterData\UpdateParameterRequest;
use App\Models\Settings\Setting;
use App\Services\MasterData\ParameterService;
use Illuminate\Http\JsonResponse;
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
        try {
            $parameter = $this->service->update((int) $id, $request->validated());
        } catch (ParameterInUseException $exception) {
            return response()->json($exception->context(), 409);
        }

        return response()->json($parameter);
    }

    public function deletionCheck($id): JsonResponse
    {
        return response()->json($this->service->getDeletionCheck((int) $id));
    }

    public function destroy($id)
    {
        try {
            $this->service->delete((int) $id);
        } catch (ParameterInUseException $exception) {
            return response()->json($exception->context(), 409);
        }

        return response()->noContent();
    }

    public function areas(): JsonResponse
    {
        $setting = Setting::parameters()->where('key', 'areas')->first();

        if (! $setting) {
            return response()->json([]);
        }

        $areas = is_array($setting->value) ? array_values($setting->value) : [];

        $areas = array_map(function (mixed $area, int $index): ?array {
            if (! is_array($area)) {
                return null;
            }
            $id = isset($area['id']) && is_int($area['id']) ? $area['id'] : ($index + 1);

            return array_merge($area, ['id' => $id]);
        }, $areas, array_keys($areas));

        return response()->json(array_values(array_filter($areas)));
    }
}
