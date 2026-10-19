<?php

namespace App\Http\Controllers\Settings;

use App\Exceptions\Settings\AreaInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAreaRequest;
use App\Http\Requests\Settings\UpdateAreaRequest;
use App\Models\Settings\Area;
use App\Services\Settings\AreaService;
use Illuminate\Http\JsonResponse;

class AreaController extends Controller
{
    public function __construct(protected AreaService $service) {}

    public function list(): JsonResponse
    {
        return response()->json($this->service->list());
    }

    public function store(StoreAreaRequest $request): JsonResponse
    {
        return response()->json($this->service->create($request->validated()), 201);
    }

    public function update(UpdateAreaRequest $request, Area $area): JsonResponse
    {
        return response()->json($this->service->update($area, $request->validated()));
    }

    public function destroy(Area $area): JsonResponse
    {
        try {
            $this->service->delete($area);
        } catch (AreaInUseException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(null, 204);
    }
}
