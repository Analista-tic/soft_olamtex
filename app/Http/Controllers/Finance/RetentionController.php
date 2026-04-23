<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RetentionRequest;
use App\Models\Finance\Retention;
use App\Services\Finance\RetentionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RetentionController extends Controller
{
    public function __construct(protected RetentionService $service) {}
    public function index()
    {
        return Inertia::render('Finance/Retention/Index', [
            'retentions' => $this->service->all(),
        ]);
    }

    public function store(RetentionRequest $request)
    {
        $retention = $this->service->create($request->validated());
        return response()->json(['data' => $retention], 201);
    }

    public function update(RetentionRequest $request, $id)
    {
        $retention = $this->service->findOrFail($id);
        $retention = $this->service->update($retention, $request->validated());
        return response()->json(['data' => $retention]);
    }

    public function destroy($id)
    {
        $retention = $this->service->findOrFail($id);
        $this->service->delete($retention);
        return response()->json(['message' => 'Retención eliminada']);
    }

    public function data(Request $request)
    {
        $filters = $request->only(['search']);
        $perPage = $request->per_page ?? 20;
        $retentions = $this->service->paginate($filters, $perPage);

        return response()->json([
            'data' => $retentions->items(),
            'total' => $retentions->total(),
            'current_page' => $retentions->currentPage()
        ]);
    }

    public function list(Request $request)
    {
        return response()->json(
            Retention::select('id', 'name', 'percent', 'type', 'status')
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
        );
    }
}
