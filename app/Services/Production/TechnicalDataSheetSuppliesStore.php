<?php

namespace App\Services\Production;

use Illuminate\Support\Facades\Storage;

class TechnicalDataSheetSuppliesStore
{
    public function getPath(int $opId): string
    {
        return "production/op_{$opId}_supplies.json";
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
        return is_array($decoded) ? array_values($decoded) : [];
    }

    public function write(int $opId, array $items): void
    {
        $path = $this->getPath($opId);
        Storage::put($path, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

