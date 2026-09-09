<?php

namespace App\Models\MasterData;

use App\Models\Concerns\HasAuditLabel;
use App\Models\Production\FabricBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FabricName extends Model
{
    use HasAuditLabel, HasFactory, SoftDeletes;

    protected $table = 'fabric_names';

    protected $fillable = [
        'nombre',
        'fabric_base_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function fabricBase(): BelongsTo
    {
        return $this->belongsTo(FabricBase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
