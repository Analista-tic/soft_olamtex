<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Retention;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RetentionController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuration/Retention/Index', [
            'retentions' => Retention::all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'percent' => 'required|numeric|min:0|max:100',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);
        $retention = Retention::create($data);
        return response()->json(['data' => $retention], 201);
    }

    public function update(Request $request, $id)
    {
        $retention = Retention::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'percent' => 'required|numeric|min:0|max:100',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);
        $retention->update($data);
        return response()->json(['data' => $retention]);
    }

    public function destroy($id)
    {
        $retention = Retention::findOrFail($id);
        $retention->delete();
        return response()->json(['message' => 'Retención eliminada']);
    }

    public function data(Request $request)
    {
        $query = Retention::query();

        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('type', 'like', '%' . $request->search . '%');
        }

        $retentions = $query->orderByDesc('id')->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $retentions->items(),
            'total' => $retentions->total(),
            'current_page' => $retentions->currentPage()
        ]);
    }
}
