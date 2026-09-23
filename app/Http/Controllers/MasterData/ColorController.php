<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Color;
use App\Services\MasterData\ColorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ColorController extends Controller
{
    public function __construct(protected ColorService $service) {}

    public function index()
    {
        return $this->service->getAll();
    }

    public function nextCode()
    {
        return response()->json(['code' => $this->service->previewNextCode()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:colors,name',
            'code' => 'nullable|string|max:3',
            'rgb' => ['nullable', 'string', 'regex:/^\d{1,3},\d{1,3},\d{1,3}$/'],
            'hexadecimal' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => 'nullable|string|max:500',
        ]);

        $data['name'] = trim((string) $data['name']);
        $data['code'] = isset($data['code']) && trim((string) $data['code']) !== '' ? trim((string) $data['code']) : null;
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

    public function update(Request $request, Color $color)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('colors', 'name')
                    ->ignore($color->id),
            ],
            'rgb' => ['nullable', 'string', 'regex:/^\d{1,3},\d{1,3},\d{1,3}$/'],
            'hexadecimal' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => 'nullable|string|max:500',
        ]);

        $data['name'] = trim((string) $data['name']);

        $data['rgb'] = isset($data['rgb']) && trim((string) $data['rgb']) !== ''
            ? trim((string) $data['rgb'])
            : null;

        $data['hexadecimal'] = isset($data['hexadecimal'])
            && trim((string) $data['hexadecimal']) !== ''
            ? strtoupper(trim((string) $data['hexadecimal']))
            : null;

        $data['description'] = isset($data['description'])
            && trim((string) $data['description']) !== ''
            ? trim((string) $data['description'])
            : null;

        return $this->service->update($color, $data);
    }

    public function destroy(Color $color)
    {
        try {
            $this->service->delete($color);

            return response()->noContent();
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
