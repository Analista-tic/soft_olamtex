<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\ItemInventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemInventoryController extends Controller
{
    public function __construct(protected ItemInventoryService $service) {}

    public function index()
    {
        return Inertia::render('MasterData/Item/ItemInventory');
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['search', 'warehouse_id', 'status', 'sort_by', 'sort_dir']);
        $perPage = (int) $request->input('per_page', 10);

        $data = $this->service->paginate($filters, $perPage);

        return response()->json($data);
    }
}
