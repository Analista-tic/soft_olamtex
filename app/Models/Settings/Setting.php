<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Setting extends Model
{
    public const KEY_PATTERN = '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/i';

    public const KIND_PARAMETER = 'parameter';
    public const KIND_FIELD = 'field';

    public const FIELD_TYPES = [
        'text',
        'number',
        'decimal',
        'textarea',
        'date',
        'boolean',
        'select',
    ];

    protected $table = 'settings';

    protected $fillable = [
        'kind',
        'key',
        'label',
        'description',
        'value',
        'field_type',
        'options',
        'default_value',
        'is_required',
        'position',
        'status',
    ];

    protected $casts = [
        'value' => 'array',
        'options' => 'array',
        'is_required' => 'boolean',
        'status' => 'boolean',
        'position' => 'integer',
    ];

    protected $appends = ['name'];

    public function getNameAttribute(): ?string
    {
        return $this->label;
    }

    public function scopeParameters(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_PARAMETER);
    }

    public function scopeFields(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_FIELD);
    }

    protected static function booted(): void
    {
        static::saving(function (self $setting) {
            if ($setting->kind !== self::KIND_FIELD) {
                return;
            }

            if (trim((string) ($setting->key ?? '')) !== '') {
                return;
            }

            $setting->key = $setting->generateFieldKey();
        });
    }

    private function generateFieldKey(): string
    {
        $base = Str::slug($this->label ?? '');
        $base = $base ?: 'field';

        $candidate = $base;
        $suffix = 1;

        while ($this->fieldKeyExists($candidate)) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $candidate;
    }

    private function fieldKeyExists(string $key): bool
    {
        $query = self::fields()->where('key', $key);
        if ($this->exists) {
            $query->where('id', '<>', $this->getKey());
        }

        return $query->exists();
    }
}
