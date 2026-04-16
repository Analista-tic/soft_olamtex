<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AppearanceController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'theme_appearance' => ['nullable', 'in:light,dark,system'],
            'theme_color' => ['nullable', 'in:zinc,red,rose,orange,green,blue,yellow,violet'],
        ]);

        $request->user()->update($validated);

        return response()->noContent();
    }
}
