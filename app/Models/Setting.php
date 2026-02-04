<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
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
}
