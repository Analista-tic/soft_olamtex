<?php

namespace App\Services\MasterData;

use App\Models\Settings\Setting;
use App\Support\TextNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdditionalFieldService
{
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->fieldQuery();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['field_type'])) {
            $types = is_array($filters['field_type']) ? $filters['field_type'] : [$filters['field_type']];
            $query->whereIn('field_type', $types);
        }

        if (isset($filters['status']) && $filters['status'] !== null) {
            $query->where('status', (bool) $filters['status']);
        }

        return $query->orderBy('position', 'asc')->paginate($perPage);
    }

    public function find(int $id): Setting
    {
        return $this->fieldQuery()->findOrFail($id);
    }

    public function create(array $data): Setting
    {
        $payload = $this->preparePayload($data, true);
        return Setting::create($payload);
    }

    public function update(Setting $field, array $data): Setting
    {
        $payload = $this->preparePayload($data, false, $field);
        $field->update($payload);
        return $field;
    }

    public function delete(Setting $field): void
    {
        $field->delete();
    }

    public function reorder(array $order): void
    {
        foreach ($order as $index => $fieldId) {
            $this->fieldQuery()->where('id', $fieldId)->update([
                'position' => $index + 1,
            ]);
        }
    }

    public const OPTIONS_CACHE_KEY = 'additional_fields_options';
    public const OPTIONS_CACHE_TTL = 3600; // 1 hora

    public function options(): Collection
    {
        return Cache::remember(self::OPTIONS_CACHE_KEY, self::OPTIONS_CACHE_TTL, function () {
            return $this->fieldQuery()
                ->where('status', true)
                ->orderBy('position', 'asc')
                ->get(['id', 'label', 'field_type', 'options', 'default_value', 'description', 'is_required', 'position'])
                ->map(fn(Setting $field) => [
                    'id'            => $field->id,
                    'name'          => $field->label,
                    'label'         => $field->label,
                    'field_type'    => $field->field_type,
                    'options'       => $field->options,
                    'default_value' => $field->default_value,
                    'description'   => $field->description,
                    'is_required'   => $field->is_required,
                    'position'      => $field->position,
                ]);
        });
    }

    public function clearOptionsCache(): void
    {
        Cache::forget(self::OPTIONS_CACHE_KEY);
    }

    public function orderedNames(): array
    {
        return $this->fieldQuery()
            ->where('status', true)
            ->orderBy('position', 'asc')
            ->pluck('label')
            ->map(fn($label) => is_string($label) ? trim($label) : '')
            ->filter(fn($label) => $label !== '')
            ->unique()
            ->values()
            ->toArray();
    }

    protected function fieldQuery(): Builder
    {
        return Setting::fields();
    }

    protected function preparePayload(array $data, bool $isNew, ?Setting $field = null): array
    {
        $payload = [
            'kind' => Setting::KIND_FIELD,
            'key' => $this->determineKey($data, $field),
            'label' => $data['name'],
            'field_type' => $data['field_type'],
            'options' => $this->normalizeOptions($data['options'] ?? null),
            'default_value' => $this->normalizeDefaultValue($data['default_value'] ?? null, $data['field_type'] ?? null),
            'description' => $data['description'] ?? null,
            'is_required' => array_key_exists('is_required', $data)
                ? (bool) $data['is_required']
                : ($isNew ? false : $field?->is_required ?? false),
            'status' => array_key_exists('status', $data)
                ? (bool) $data['status']
                : ($isNew ? true : $field?->status ?? true),
            'value' => null,
        ];

        if ($isNew) {
            $payload['position'] = $this->nextPosition();
        } elseif (array_key_exists('position', $data)) {
            $payload['position'] = (int) $data['position'];
        }

        return $payload;
    }

    protected function determineKey(array $data, ?Setting $field): string
    {
        if (!empty($data['key'])) {
            return Str::slug($data['key']);
        }

        if (!empty($data['name'])) {
            return Str::slug($data['name']);
        }

        if ($field) {
            return $field->key;
        }

        return 'field-' . Str::random(6);
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
            return TextNormalizer::titleCaseWords($value);
        }, $options), fn($value) => $value !== ''));

        $sanitized = array_values(array_unique($sanitized));

        return $sanitized ?: null;
    }

    protected function normalizeDefaultValue(mixed $value, ?string $fieldType): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = in_array($fieldType, ['text', 'textarea', 'select'], true)
            ? TextNormalizer::titleCaseWords($value)
            : preg_replace('/\s+/u', ' ', trim((string) $value));

        return $normalized !== null && $normalized !== '' ? $normalized : null;
    }

    protected function nextPosition(): int
    {
        return (Setting::fields()->max('position') ?? 0) + 1;
    }
}
