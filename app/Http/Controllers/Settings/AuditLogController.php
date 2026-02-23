<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct(protected AuditLogService $service) {}

    public function index(Request $request): Response
    {
        $filters = $this->extractFilters($request);
        $logs = $this->service->paginate($filters);

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $perPage = max(1, min((int) $request->input('per_page', 50), 200));
        $logs = $this->service->paginate($filters, $perPage);

        return response()->json([
            'data' => $logs->items(),
            'total' => $logs->total(),
            'per_page' => $logs->perPage(),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
        ]);
    }

    protected function extractFilters(Request $request): array
    {
        $filters = $request->only(['user', 'module', 'action', 'start_date', 'end_date', 'area_id']);

        foreach ($filters as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $filters[$key] = null;
            }
        }

        return $filters;
    }
}
