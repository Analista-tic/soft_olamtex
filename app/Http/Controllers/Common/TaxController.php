<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Common\Tax;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaxController extends Controller
{
    public function index()
    {
        return inertia('Configuration/Taxes/Index');
    }

    public function data(Request $request)
    {
        try {
            $query = Tax::query();
            if ($request->has('search') && $request->search) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }
            if ($request->has('status') && $request->status !== '' && $request->status !== 'all') {
                $status = $request->status;
                if ($status === 'active') {
                    $status = true;
                } elseif ($status === 'inactive') {
                    $status = false;
                } else {
                    $status = filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }

                if ($status !== null) {
                    $query->where('status', $status);
                }
            }
            $taxes = $query->orderBy('name')->paginate($request->per_page ?? 20);
            return response()->json([
                'data' => $taxes->items(),
                'total' => $taxes->total(),
                'current_page' => $taxes->currentPage()
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener los impuestos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        return Tax::findOrFail($id);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'percentage' => 'required|numeric|min:0|max:100',
            'status' => 'boolean',
        ]);
        $tax = Tax::create($data);
        return response()->json($tax, 201);
    }

    public function update(Request $request, $id)
    {
        $tax = Tax::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'percentage' => 'required|numeric|min:0|max:100',
            'status' => 'boolean',
        ]);
        $tax->update($data);
        return response()->json($tax);
    }

    public function destroy($id)
    {
        $tax = Tax::findOrFail($id);
        $tax->delete();
        return response()->noContent();
    }

    public function toggleStatus($id)
    {
        $tax = Tax::findOrFail($id);
        $tax->status = !$tax->status;
        $tax->save();
        return response()->json(['status' => $tax->status]);
    }
}
