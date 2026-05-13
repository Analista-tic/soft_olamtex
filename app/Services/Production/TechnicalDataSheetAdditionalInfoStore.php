<?php

namespace App\Services\Production;

use Illuminate\Support\Facades\Storage;

class TechnicalDataSheetAdditionalInfoStore
{
    public function getPath(int $opId): string
    {
        return "production/op_{$opId}_additional_info.json";
    }

    public function read(int $opId): array
    {
        $path = $this->getPath($opId);
        if (!Storage::exists($path)) {
            return $this->defaultPayload();
        }

        $raw = Storage::get($path);
        if (!is_string($raw) || trim($raw) === '') {
            return $this->defaultPayload();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->defaultPayload();
        }

        return $this->mergeWithDefaults($decoded);
    }

    public function write(int $opId, array $payload): void
    {
        $data = $this->mergeWithDefaults($payload);

        Storage::put(
            $this->getPath($opId),
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    private function defaultPayload(): array
    {
        return [
            'cutting' => [
                'params' => [
                    'cutCostCop' => 350,
                ],
                'sizes' => [],
                'espigaLength' => null,
                'espigaCurve' => null,
                'liqLength' => null,
                'liqCurve' => null,
                'pieces' => [],
                'generalNotes' => null,
            ],
            'stamping' => [
                'designNotes'        => null,
                'attachments'        => [],
                'drawingAttachments' => [],
            ],
            'laundry' => [
                'type'         => null,
                'instructions' => null,
                'attachments'  => [],
            ],
        ];
    }

    private function mergeWithDefaults(array $payload): array
    {
        $defaults = $this->defaultPayload();

        $cuttingInput = is_array($payload['cutting'] ?? null) ? $payload['cutting'] : [];
        $cuttingParamsInput = is_array($cuttingInput['params'] ?? null) ? $cuttingInput['params'] : [];
        $stampingInput = is_array($payload['stamping'] ?? null) ? $payload['stamping'] : [];
        $laundryInput = is_array($payload['laundry'] ?? null) ? $payload['laundry'] : [];

        return [
            'cutting' => [
                'params' => [
                    'cutCostCop' => is_numeric($cuttingParamsInput['cutCostCop'] ?? null)
                        ? (float) $cuttingParamsInput['cutCostCop']
                        : $defaults['cutting']['params']['cutCostCop'],
                ],
                'sizes' => is_array($cuttingInput['sizes'] ?? null) ? array_values($cuttingInput['sizes']) : [],
                'espigaLength' => is_numeric($cuttingInput['espigaLength'] ?? null) ? (float) $cuttingInput['espigaLength'] : null,
                'espigaCurve' => is_string($cuttingInput['espigaCurve'] ?? null) ? trim((string) $cuttingInput['espigaCurve']) ?: null : null,
                'liqLength' => is_numeric($cuttingInput['liqLength'] ?? null) ? (float) $cuttingInput['liqLength'] : null,
                'liqCurve' => is_string($cuttingInput['liqCurve'] ?? null) ? trim((string) $cuttingInput['liqCurve']) ?: null : null,
                'pieces' => is_array($cuttingInput['pieces'] ?? null) ? array_values($cuttingInput['pieces']) : [],
                'generalNotes' => is_string($cuttingInput['generalNotes'] ?? null) ? trim((string) $cuttingInput['generalNotes']) ?: null : null,
            ],
            'stamping' => [
                'designNotes'        => is_string($stampingInput['designNotes'] ?? null) ? trim((string) $stampingInput['designNotes']) ?: null : null,
                'attachments'        => is_array($stampingInput['attachments'] ?? null) ? array_values($stampingInput['attachments']) : [],
                'drawingAttachments' => is_array($stampingInput['drawingAttachments'] ?? null) ? array_values($stampingInput['drawingAttachments']) : [],
            ],
            'laundry' => [
                'type'         => is_string($laundryInput['type'] ?? null) ? trim((string) $laundryInput['type']) ?: null : null,
                'instructions' => is_string($laundryInput['instructions'] ?? null) ? trim((string) $laundryInput['instructions']) ?: null : null,
                'attachments'  => is_array($laundryInput['attachments'] ?? null) ? array_values($laundryInput['attachments']) : [],
            ],
        ];
    }
}

