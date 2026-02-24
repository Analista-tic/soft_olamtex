<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\Programmer;
use App\Services\Production\TechnicalDataSheetAdditionalInfoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicalDataSheetAdditionalInfoController extends Controller
{
    public function __construct(
        private TechnicalDataSheetAdditionalInfoStore $store,
    ) {
    }

    public function show(int $opId): JsonResponse
    {
        Programmer::query()->findOrFail($opId);
        return response()->json($this->store->read($opId));
    }

    public function store(int $opId, Request $request): JsonResponse
    {
        Programmer::query()->findOrFail($opId);

        $validated = $request->validate([
            'cutting' => ['required', 'array'],
            'cutting.params' => ['required', 'array'],
            'cutting.params.cutCostCop' => ['required', 'numeric', 'min:0'],
            'cutting.sizes' => ['nullable', 'array'],
            'cutting.sizes.*.size' => ['required_with:cutting.sizes', 'string', 'max:50'],
            'cutting.sizes.*.units' => ['required_with:cutting.sizes', 'numeric', 'min:0'],
            'cutting.sizes.*.curve' => ['nullable', 'numeric', 'min:0'],
            'cutting.espigaLength' => ['nullable', 'numeric', 'min:0'],
            'cutting.espigaCurve' => ['nullable', 'string', 'max:100'],
            'cutting.liqLength' => ['nullable', 'numeric', 'min:0'],
            'cutting.liqCurve' => ['nullable', 'string', 'max:100'],
            'cutting.pieces' => ['nullable', 'array'],
            'cutting.pieces.*.id' => ['required_with:cutting.pieces', 'string', 'max:100'],
            'cutting.pieces.*.name' => ['required_with:cutting.pieces', 'string', 'max:120'],
            'cutting.pieces.*.type' => ['required_with:cutting.pieces', 'in:PRINCIPAL,SECUNDARIO'],
            'cutting.pieces.*.side' => ['required_with:cutting.pieces', 'in:IZQUIERDA,DERECHA,AMBOS,NO_APLICA'],
            'cutting.pieces.*.grain' => ['required_with:cutting.pieces', 'in:90,180,SESGO'],
            'cutting.pieces.*.notes' => ['nullable', 'string', 'max:1000'],
            'cutting.generalNotes' => ['nullable', 'string', 'max:2000'],

            'stamping' => ['required', 'array'],
            'stamping.designNotes' => ['nullable', 'string', 'max:2000'],
            'stamping.attachments' => ['nullable', 'array'],

            'laundry' => ['required', 'array'],
            'laundry.instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->store->write($opId, $validated);
        return response()->json($this->store->read($opId));
    }
}
