<?php

namespace App\Services\Production;

use Illuminate\Support\Facades\Storage;

class TechnicalDataSheetRollsStore
{
    private const DEFAULT_TRAZO = 0.8;
    private const DEFAULT_EXTRA = 0.03;

    public function getPath(int $opId): string
    {
        return "production/op_{$opId}_rolls.json";
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
                'trazo' => $this->toFloatOrDefault($params['trazo'] ?? null, self::DEFAULT_TRAZO),
                'defaultExtra' => $this->toFloatOrDefault($params['defaultExtra'] ?? null, self::DEFAULT_EXTRA),
            ],
            'lines' => $lines,
        ];
    }

    public function write(int $opId, array $payload): void
    {
        $data = [
            'params' => [
                'trazo' => $this->toFloatOrDefault($payload['params']['trazo'] ?? null, self::DEFAULT_TRAZO),
                'defaultExtra' => $this->toFloatOrDefault($payload['params']['defaultExtra'] ?? null, self::DEFAULT_EXTRA),
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
                'trazo' => self::DEFAULT_TRAZO,
                'defaultExtra' => self::DEFAULT_EXTRA,
            ],
            'lines' => [],
        ];
    }

    private function toFloatOrDefault(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }
}

