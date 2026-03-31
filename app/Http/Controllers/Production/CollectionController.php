<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\Collection;
use App\Services\Production\CollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function __construct(private CollectionService $service) {}

    public function index(): Response
    {
        return Inertia::render('Production/Collections/Index');
    }

    public function getData(Request $request): JsonResponse
    {
        $collections = $this->service->paginate(
            perPage: (int) $request->query('per_page', 15),
            search: $request->query('search'),
        );

        return response()->json([
            'success' => true,
            'data'    => $collections,
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->allActive(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:collections,name',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);

        $collection = $this->service->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Colección creada correctamente.',
            'data'    => $collection,
        ], 201);
    }

    public function update(Request $request, Collection $collection): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:collections,name,' . $collection->id,
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);

        $updated = $this->service->update($collection, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Colección actualizada correctamente.',
            'data'    => $updated,
        ]);
    }

    public function destroy(Collection $collection): JsonResponse
    {
        $this->service->delete($collection);

        return response()->json([
            'success' => true,
            'message' => 'Colección eliminada correctamente.',
        ]);
    }

    public function toggleActive(Collection $collection): JsonResponse
    {
        $updated = $this->service->toggleActive($collection);

        return response()->json([
            'success' => true,
            'message' => $updated->is_active ? 'Colección activada.' : 'Colección desactivada.',
            'data'    => $updated,
        ]);
    }
}
