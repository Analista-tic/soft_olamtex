<?php

namespace App\Services\MasterData;

use App\Exceptions\MasterData\ParameterInUseException;
use App\Models\Settings\Setting;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ParameterService
{
    public function __construct(
        protected ParameterUsageService $usageService,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = Setting::parameters();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->where('key', 'LIKE', "%{$search}%")
                    ->orWhere('label', 'LIKE', "%{$search}%");
            });
        }

        if (array_key_exists('status', $filters)) {
            $this->applyStatusFilter($query, $filters['status']);
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['label', 'key'], true)
            ? $filters['sort_by']
            : 'label';
        $direction = strtolower($filters['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $direction)->get();
    }

    public function create(array $data): Setting
    {
        $data['kind'] = Setting::KIND_PARAMETER;
        $data['key'] = $this->determineKey($data);
        $data['value'] = $this->normalizeValue($data['value'] ?? null);

        return Setting::create($data);
    }

    public function update(int $id, array $data): Setting
    {
        $setting = $this->find($id);

        unset($data['key']);

        if (array_key_exists('value', $data)) {
            $data['value'] = $this->normalizeValue($data['value']);

            if (is_array($data['value'])) {
                $this->usageService->assertNoRemovedOptionsInUse($setting, $data['value']);
            }
        }

        $setting->fill($data);
        $setting->save();

        return $setting;
    }

    public function find(int $id): Setting
    {
        return Setting::parameters()->findOrFail($id);
    }

    public function delete(int $id): void
    {
        $setting = $this->find($id);
        $deletionCheck = $this->getDeletionCheck($id);

        if (! ($deletionCheck['can_delete'] ?? false)) {
            throw new ParameterInUseException($deletionCheck);
        }

        $setting->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDeletionCheck(int $id): array
    {
        return $this->usageService->inspect($this->find($id));
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = TextNormalizer::normalizeDisplayText($value);

            if ($trimmed === '') {
                return '';
            }

            if ($trimmed !== '' && in_array($trimmed[0], ['{', '['], true)) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->normalizeValue($decoded);
                }
            }

            return $trimmed;
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            return $value;
        }

        if ($this->isNamedChipList($value)) {
            $normalized = [];
            $seen = [];
            $nextId = $this->nextAvailableOptionId($value);

            foreach ($value as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $display = TextNormalizer::titleCaseWords($entry['name'] ?? null);
                $semanticKey = TextNormalizer::semanticKey($display);

                if ($display === '' || $semanticKey === '' || isset($seen[$semanticKey])) {
                    continue;
                }

                $seen[$semanticKey] = true;
                $normalizedEntry = [
                    ...$entry,
                    'name' => $display,
                ];

                // Una opción sin id numérico (recién agregada desde el chip input, que
                // solo maneja strings) se queda sin identidad estable: cualquier consumidor
                // que guarde su id como FK (ej. product_sheets.section_id) terminaría
                // guardando el nombre. Se le asigna aquí el siguiente id disponible.
                if (! isset($normalizedEntry['id']) || ! is_numeric($normalizedEntry['id'])) {
                    $normalizedEntry['id'] = $nextId;
                    $nextId++;
                }

                $normalized[] = $normalizedEntry;
            }

            return $normalized;
        }

        if ($this->isScalarChipList($value)) {
            $normalized = [];
            $seen = [];

            foreach ($value as $entry) {
                $display = TextNormalizer::titleCaseWords($entry);
                $semanticKey = TextNormalizer::semanticKey($display);

                if ($display === '' || $semanticKey === '' || isset($seen[$semanticKey])) {
                    continue;
                }

                $seen[$semanticKey] = true;
                $normalized[] = $display;
            }

            return $normalized;
        }

        return $value;
    }

    private function nextAvailableOptionId(array $value): int
    {
        $max = 0;

        foreach ($value as $entry) {
            if (is_array($entry) && isset($entry['id']) && is_numeric($entry['id'])) {
                $max = max($max, (int) $entry['id']);
            }
        }

        return $max + 1;
    }

    private function isNamedChipList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        foreach ($value as $entry) {
            if (! is_array($entry) || ! array_key_exists('name', $entry)) {
                return false;
            }
        }

        return true;
    }

    private function isScalarChipList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        foreach ($value as $entry) {
            if (is_array($entry) || is_object($entry)) {
                return false;
            }
        }

        return true;
    }

    private function determineKey(array $data): string
    {
        $raw = trim((string) ($data['key'] ?? ''));
        $base = $raw !== ''
            ? Str::slug($raw)
            : Str::slug((string) ($data['label'] ?? ''));

        if ($base === '') {
            $base = 'parameter';
        }

        $candidate = $base;
        $suffix = 1;

        while (Setting::parameters()->where('key', $candidate)->exists()) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $candidate;
    }

    protected function applyStatusFilter(Builder $query, mixed $status): void
    {
        if ($status === null) {
            return;
        }

        if (is_string($status)) {
            $value = strtolower($status);

            if ($value === 'all') {
                return;
            }

            if ($value === 'active') {
                $query->where('status', true);

                return;
            }

            if ($value === 'inactive') {
                $query->where('status', false);

                return;
            }

            $boolean = filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolean !== null) {
                $query->where('status', $boolean);
            }

            return;
        }

        if (is_bool($status)) {
            $query->where('status', $status);

            return;
        }

        if (is_numeric($status)) {
            $query->where('status', (bool) $status);
        }
    }
}
