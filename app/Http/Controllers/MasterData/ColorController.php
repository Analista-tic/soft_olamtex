<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Services\MasterData\ColorService;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    public function __construct(protected ColorService $service) {}

    public function index()
    {
        return $this->service->getAll();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:colors,name',
            'rgb' => ['nullable', 'string', 'regex:/^\d{1,3},\d{1,3},\d{1,3}$/'],
            'hexadecimal' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => 'nullable|string|max:500',
        ]);

        $data['name'] = trim((string) $data['name']);
        $data['rgb'] = isset($data['rgb']) && trim((string) $data['rgb']) !== '' ? trim((string) $data['rgb']) : null;
        $data['hexadecimal'] = isset($data['hexadecimal'])
            && trim((string) $data['hexadecimal']) !== ''
            ? strtoupper(trim((string) $data['hexadecimal']))
            : null;
        $data['description'] = isset($data['description'])
            && trim((string) $data['description']) !== ''
            ? trim((string) $data['description'])
            : null;

        return $this->service->create($data);
    }
}
