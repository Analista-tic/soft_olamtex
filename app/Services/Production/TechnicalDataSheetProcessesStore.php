<?php

namespace App\Services\Production;

use Illuminate\Support\Facades\Storage;

class TechnicalDataSheetProcessesStore
{
    public function getPath(int $opId): string
    {
        return "production/op_{$opId}_processes.json";
    }

    public function read(int $opId): array
    {
        $path = $this->getPath($opId);
        if (!Storage::exists($path)) {
            return [];
        }

        $raw = Storage::get($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn ($item) => is_array($item)));
    }

    public function write(int $opId, array $items): void
    {
        Storage::put(
            $this->getPath($opId),
            json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }
}
