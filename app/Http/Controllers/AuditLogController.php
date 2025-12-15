<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->when($request->filled('user'), fn($q) =>
                $q->where('user_name', 'like', '%'.$request->user.'%')
            )
            ->when($request->filled('module'), fn($q) =>
                $q->where('module', $request->module)
            )
            ->when($request->filled('action'), fn($q) =>
                $q->where('action', $request->action)
            )
            ->latest()
            ->paginate(50);

        // Si usas Inertia:
        return inertia('Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only('user', 'module', 'action'),
        ]);
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
