<?php

namespace App\Models\MasterData;

use App\Models\Concerns\HasAuditLabel;
use App\Models\Production\YarnBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class YarnName extends Model
{
    use HasAuditLabel, HasFactory, SoftDeletes;

    protected $table = 'yarn_names';

    protected $fillable = [
        'nombre',
        'yarn_base_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function yarnBase(): BelongsTo
    {
        return $this->belongsTo(YarnBase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
