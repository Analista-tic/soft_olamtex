<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Common\Color;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ColorController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuration/Color/Index', [
            'colors' => Color::all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:colors,name',
            'rgb_value' => 'required|string',
        ]);
        return Color::create($request->only(['name', 'rgb_value']));
    }

    public function show($id)
    {
        return Color::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $color = Color::findOrFail($id);
        $request->validate([
            'name' => 'sometimes|required|string|unique:colors,name,' . $id,
            'rgb_value' => 'sometimes|required|string',
        ]);
        $color->update($request->only(['name', 'rgb_value']));
        return $color;
    }

    public function destroy($id)
    {
        $color = Color::findOrFail($id);
        $color->delete();
        return response()->json(['message' => 'Color deleted']);
    }

    public function getData()
    {
        return response()->json([
            'data' => Color::all()
        ]);
    }
}