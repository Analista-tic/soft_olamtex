<?php

namespace App\Services\MasterData;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ParameterService
{
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
        $setting = Setting::findOrFail($id);

        if ($setting->kind !== Setting::KIND_PARAMETER) {
            abort(422, 'Solo se pueden eliminar parámetros.');
        }

        $setting->delete();
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed !== '' && in_array($trimmed[0], ['{', '['], true)) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }

            return $value;
        }

        return $value;
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
