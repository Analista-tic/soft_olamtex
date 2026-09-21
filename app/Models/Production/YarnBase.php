<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use App\Models\MasterData\YarnName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class YarnBase extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel, \App\Models\Concerns\NormalizesAttributes;

    /** Reglas de normalización al guardar (ver NormalizesAttributes). */
    protected array $normalizedAttributes = [
        'codigo' => 'code',
        'nombre' => 'catalogName',
    ];

    protected $table = 'yarn_bases';

    protected $fillable = [
        'codigo',
        'nombre',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class, 'yarn_base_id');
    }

    public function yarnNames(): HasMany
    {
        return $this->hasMany(YarnName::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCodigo(Builder $query, string $codigo): Builder
    {
        return $query->where('codigo', $codigo);
    }
}
