<?php

namespace App\Services\MasterData;

use App\Models\Settings\Setting;
use App\Support\TextNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdditionalFieldService
{
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->fieldQuery();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['field_type'])) {
            $types = is_array($filters['field_type']) ? $filters['field_type'] : [$filters['field_type']];
            $query->whereIn('field_type', $types);
        }

        if (isset($filters['status']) && $filters['status'] !== null) {
            $query->where('status', (bool) $filters['status']);
        }

        return $query->with('categorySubGroups:id,name')->orderBy('position', 'asc')->paginate($perPage);
    }

    public function find(int $id): Setting
    {
        return $this->fieldQuery()->with('categorySubGroups:id')->findOrFail($id);
    }

    public function create(array $data): Setting
    {
        $payload = $this->preparePayload($data, true);
        $field = Setting::create($payload);
        $this->syncCategorySubGroups($field, $data);

        return $field;
    }

    public function update(Setting $field, array $data): Setting
    {
        $payload = $this->preparePayload($data, false, $field);
        $field->update($payload);
        $this->syncCategorySubGroups($field, $data);

        return $field;
    }

    protected function syncCategorySubGroups(Setting $field, array $data): void
    {
        if (! array_key_exists('category_sub_group_ids', $data)) {
            return;
        }

        $field->categorySubGroups()->sync(array_filter((array) $data['category_sub_group_ids']));
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

    public function options(?int $categorySubGroupId = null): Collection
    {
        $fields = Cache::remember(self::OPTIONS_CACHE_KEY, self::OPTIONS_CACHE_TTL, function () {
            return $this->fieldQuery()
                ->where('status', true)
                ->orderBy('position', 'asc')
                ->with('categorySubGroups:id')
                ->get(['id', 'label', 'field_type', 'options', 'source_type', 'catalog_key', 'default_value', 'description', 'is_required', 'position'])
                ->map(fn (Setting $field) => [
                    'id' => $field->id,
                    'name' => $field->label,
                    'label' => $field->label,
                    'field_type' => $field->field_type,
                    'options' => $field->options,
                    'source_type' => $field->source_type,
                    'catalog_key' => $field->catalog_key,
                    'default_value' => $field->default_value,
                    'description' => $field->description,
                    'is_required' => $field->is_required,
                    'position' => $field->position,
                    'category_sub_group_ids' => $field->categorySubGroups->pluck('id')->all(),
                ]);
        });

        // Las opciones de un campo de catálogo se resuelven en vivo, fuera del cache de
        // definiciones: un FabricName creado después de que este cache se llenó debe
        // aparecer igual, sin esperar a que expire ni tocar el campo para invalidarlo.
        $fields = $fields->map(function (array $field) {
            if (($field['source_type'] ?? Setting::SOURCE_TYPE_LIST) === Setting::SOURCE_TYPE_CATALOG && $field['catalog_key']) {
                $field['options'] = $this->resolveCatalogOptions($field['catalog_key']);
            }

            return $field;
        });

        if ($categorySubGroupId === null) {
            return $fields;
        }

        return $fields->filter(function (array $field) use ($categorySubGroupId) {
            $ids = $field['category_sub_group_ids'] ?? [];

            return empty($ids) || in_array($categorySubGroupId, $ids, true);
        })->values();
    }

    public function resolveCatalogOptions(string $catalogKey): array
    {
        $catalog = config("additional_field_catalogs.{$catalogKey}");

        if (! $catalog || ! is_subclass_of($catalog['model'], Model::class)) {
            return [];
        }

        /** @var Model $modelClass */
        $modelClass = $catalog['model'];
        $query = $modelClass::query();

        if (! empty($catalog['active_scope'])) {
            $query->{$catalog['active_scope']}();
        }

        return $query
            ->orderBy($catalog['value_column'])
            ->pluck($catalog['value_column'])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->unique()
            ->values()
            ->all();
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
            ->map(fn ($label) => is_string($label) ? trim($label) : '')
            ->filter(fn ($label) => $label !== '')
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
        $sourceType = $data['source_type'] ?? Setting::SOURCE_TYPE_LIST;
        $isCatalogSourced = $sourceType === Setting::SOURCE_TYPE_CATALOG;

        $payload = [
            'kind' => Setting::KIND_FIELD,
            'key' => $this->determineKey($data, $field),
            'label' => $data['name'],
            'field_type' => $data['field_type'],
            // Un campo de catálogo no guarda lista manual: sus opciones se resuelven en
            // vivo desde el catálogo (ver options()/resolveCatalogOptions()).
            'options' => $isCatalogSourced ? null : $this->normalizeOptions($data['options'] ?? null),
            'source_type' => $sourceType,
            'catalog_key' => $isCatalogSourced ? ($data['catalog_key'] ?? null) : null,
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
        if (! empty($data['key'])) {
            return Str::slug($data['key']);
        }

        if (! empty($data['name'])) {
            return Str::slug($data['name']);
        }

        if ($field) {
            return $field->key;
        }

        return 'field-'.Str::random(6);
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

        if (! is_array($options)) {
            return null;
        }

        $sanitized = [];
        $seen = [];

        foreach ($options as $value) {
            $display = TextNormalizer::titleCaseWords($value);
            $semanticKey = TextNormalizer::semanticKey($display);

            if ($display === '' || $semanticKey === '' || isset($seen[$semanticKey])) {
                continue;
            }

            $seen[$semanticKey] = true;
            $sanitized[] = $display;
        }

        return $sanitized ?: null;
    }

    protected function normalizeDefaultValue(mixed $value, ?string $fieldType): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $fieldType === 'select'
            ? TextNormalizer::titleCaseWords($value)
            : TextNormalizer::normalizeDisplayText($value);

        return $normalized !== null && $normalized !== '' ? $normalized : null;
    }

    protected function nextPosition(): int
    {
        return (Setting::fields()->max('position') ?? 0) + 1;
    }
}
