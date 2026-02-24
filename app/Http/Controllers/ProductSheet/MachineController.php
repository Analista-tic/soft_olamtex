<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Services\ProductSheet\MachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    public function __construct(protected MachineService $service) {}

    public function data(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->options(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $machine = $this->service->create($validated);

        return response()->json([
            'message' => 'Máquina creada correctamente.',
            'data' => [
                'id' => $machine->id,
                'code' => $machine->code,
                'name' => $machine->name,
                'supplement_factor' => $machine->supplement_factor,
            ],
        ], 201);
    }
}
