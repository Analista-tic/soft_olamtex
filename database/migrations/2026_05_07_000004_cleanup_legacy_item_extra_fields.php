<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $lastId = 0;

        do {
            $items = DB::table('items')
                ->select('id', 'extra_fields')
                ->where('id', '>', $lastId)
                ->whereNotNull('extra_fields')
                ->orderBy('id')
                ->limit(500)
                ->get();

            foreach ($items as $item) {
                $lastId = max($lastId, (int) $item->id);
                $cleaned = $this->sanitizeExtraFields($item->extra_fields);

                if ($cleaned === null) {
                    continue;
                }

                DB::table('items')
                    ->where('id', $item->id)
                    ->update([
                        'extra_fields' => empty($cleaned) ? null : json_encode($cleaned, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            }
        } while ($items->isNotEmpty());
    }

    public function down(): void
    {
        // Limpieza irreversible: no se restauran metadatos tecnicos eliminados.
    }

    private function sanitizeExtraFields(mixed $rawValue): ?array
    {
        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
        } elseif (is_array($rawValue)) {
            $decoded = $rawValue;
        } else {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $cleaned = [];
        $changed = false;

        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                $changed = true;
                continue;
            }

            $fieldName = preg_replace('/\s+/u', ' ', trim($key));
            if ($fieldName === null || $fieldName === '') {
                $changed = true;
                continue;
            }

            if ($this->isTechnicalExtraField($fieldName)) {
                $changed = true;
                continue;
            }

            $cleaned[$fieldName] = $value;
        }

        if (!$changed && $cleaned === $decoded) {
            return null;
        }

        return $cleaned;
    }

    private function isTechnicalExtraField(string $fieldName): bool
    {
        $lower = mb_strtolower(trim($fieldName), 'UTF-8');
        foreach (['legacy_', 'import_', '__'] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return in_array($this->normalize($fieldName), [
            'COLOR',
            'PRECIO',
            'LEGACY_COLOR_NAME',
            'LEGACY_INITIAL_UNIT_COST',
            'LEGACY_INITIAL_UNIT_COST_SOURCE',
            'LEGACY_IMPORT',
            'LEGACY_SOURCE',
            'LEGACY_ROW_NUMBER',
            'LEGACY_RAW_ROW',
            'MIGRATION_BATCH',
            'ORIGIN_TYPE',
            'IMPORTED_AT',
            'SOURCE_REFERENCE',
        ], true);
    }

    private function normalize(string $value): string
    {
        $upper = mb_strtoupper(trim($value), 'UTF-8');
        $upper = (string) preg_replace('/\s+/u', ' ', $upper);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $upper);

        return ($ascii !== false && $ascii !== '')
            ? (string) preg_replace('/\s+/', ' ', trim($ascii))
            : $upper;
    }
};
