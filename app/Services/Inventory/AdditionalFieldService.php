<?php

namespace App\Services\Inventory;

use App\Models\Inventory\AdditionalField;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdditionalFieldService
{
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = AdditionalField::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        if (!empty($filters['field_type'])) {
            $types = is_array($filters['field_type']) ? $filters['field_type'] : [$filters['field_type']];
            $query->whereIn('field_type', $types);
        }

        return $query->orderBy('position', 'asc')->paginate($perPage);
    }

    public function find(int $id): AdditionalField
    {
        return AdditionalField::findOrFail($id);
    }

    public function create(array $data): AdditionalField
    {
        $payload = $this->preparePayload($data, true);
        return AdditionalField::create($payload);
    }

    public function update(AdditionalField $field, array $data): AdditionalField
    {
        $payload = $this->preparePayload($data, false, $field);
        $field->update($payload);
        return $field;
    }

    public function delete(AdditionalField $field): void
    {
        $field->delete();
    }

    public function reorder(array $order): void
    {
        foreach ($order as $index => $fieldId) {
            AdditionalField::where('id', $fieldId)->update([
                'position' => $index + 1,
            ]);
        }
    }

    public function options(): Collection
    {
        return AdditionalField::where('status', true)
            ->orderBy('position', 'asc')
            ->get(['id', 'name', 'field_type', 'options', 'default_value', 'description', 'is_required', 'position']);
    }

    public function orderedNames(): array
    {
        return AdditionalField::where('status', true)
            ->orderBy('position', 'asc')
            ->pluck('name')
            ->map(fn($name) => is_string($name) ? trim($name) : '')
            ->filter(fn($name) => $name !== '')
            ->unique()
            ->values()
            ->toArray();
    }

    protected function preparePayload(array $data, bool $isNew, ?AdditionalField $field = null): array
    {
        $payload = [
            'name' => $data['name'],
            'field_type' => $data['field_type'],
            'options' => $this->normalizeOptions($data['options'] ?? null),
            'default_value' => $data['default_value'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        if (array_key_exists('is_required', $data)) {
            $payload['is_required'] = (bool) $data['is_required'];
        } elseif ($isNew) {
            $payload['is_required'] = false;
        }

        if (array_key_exists('status', $data)) {
            $payload['status'] = (bool) $data['status'];
        } elseif ($isNew) {
            $payload['status'] = true;
        }

        if ($isNew) {
            $payload['position'] = $this->nextPosition();
        } elseif (array_key_exists('position', $data)) {
            $payload['position'] = (int) $data['position'];
        }

        return $payload;
    }

    protected function normalizeOptions(mixed $options): ?array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $options = $decoded;
            } else {
                $options = preg_split('/\\r?\\n/', $options) ?: [];
            }
        }

        if (!is_array($options)) {
            return null;
        }

        $sanitized = array_values(array_filter(array_map(function ($value) {
            if ($value === null) {
                return null;
            }
            return trim((string) $value);
        }, $options), fn($value) => $value !== ''));

        return $sanitized ?: null;
    }

    protected function nextPosition(): int
    {
        return (AdditionalField::max('position') ?? 0) + 1;
    }
}
