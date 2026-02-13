<?php

namespace App\Services\Production;

use Illuminate\Support\Facades\Storage;

class TechnicalDataSheetThreadsStore
{
    public function getPath(int $opId): string
    {
        return "production/op_{$opId}_threads.json";
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

        $params = is_array($decoded['params'] ?? null) ? $decoded['params'] : [];
        $lines = is_array($decoded['lines'] ?? null) ? array_values($decoded['lines']) : [];

        return [
            'params' => [
                'defaultYear' => $this->toIntOrDefault($params['defaultYear'] ?? null, (int) date('Y')),
                'method' => 'PER_GARMENT',
            ],
            'lines' => $lines,
        ];
    }

    public function write(int $opId, array $payload): void
    {
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
        $data = [
            'params' => [
                'defaultYear' => $this->toIntOrDefault($params['defaultYear'] ?? null, (int) date('Y')),
                'method' => 'PER_GARMENT',
            ],
            'lines' => is_array($payload['lines'] ?? null) ? array_values($payload['lines']) : [],
        ];

        Storage::put(
            $this->getPath($opId),
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    private function defaultPayload(): array
    {
        return [
            'params' => [
                'defaultYear' => (int) date('Y'),
                'method' => 'PER_GARMENT',
            ],
            'lines' => [],
        ];
    }

    private function toIntOrDefault(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}

