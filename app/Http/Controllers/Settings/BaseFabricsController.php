<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Inventory\BaseFabric;
use Illuminate\Http\Request;

class BaseFabricsController extends Controller
{
    public function index()
    {
        try {
            $baseFabrics = BaseFabric::all();
            return response()->json($baseFabrics, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las bases de tela'], 500);
        }
    }
}
