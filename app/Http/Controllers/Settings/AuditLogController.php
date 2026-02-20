<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\AuditLog;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(protected AuditLogService $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $this->extractFilters($request);
        $logs = $this->service->paginate($filters);

        return inertia('Audit/Index', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }

    public function data(Request $request)
    {
        $filters = $this->extractFilters($request);
        $perPage = (int) $request->input('per_page', 50);
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
        return $request->only(['user', 'module', 'action', 'start_date', 'end_date', 'area_id']);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(AuditLog $auditLog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AuditLog $auditLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AuditLog $auditLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AuditLog $auditLog)
    {
        //
    }
}
