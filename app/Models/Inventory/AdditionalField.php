<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'field_type',
        'options',
        'default_value',
        'description',
        'is_required',
        'status',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'status' => 'boolean',
    ];

    public const FIELD_TYPES = [
        'text',
        'number',
        'select',
        'textarea',
        'date',
    ];
}
