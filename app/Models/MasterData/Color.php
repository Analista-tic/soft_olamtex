<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Color extends Model
{
    use \App\Models\Concerns\NormalizesAttributes;

    /** Reglas de normalización al guardar (ver NormalizesAttributes). */
    protected array $normalizedAttributes = [
        'code' => 'code',
        'rgb' => 'code',
        'hexadecimal' => 'code',
        'description' => 'freeText',
    ];

    public const NOT_APPLICABLE_CODE = '000';

    protected $fillable = ['code', 'name', 'rgb', 'hexadecimal', 'description'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
