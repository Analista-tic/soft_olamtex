<?php

namespace App\Models\ProductSheet;

use App\Models\Concerns\HasAuditLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Operation extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'operations';

    protected $fillable = [
        'action',
        'garment_part',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['name', 'normalized_name'];

    protected $with = [];

    // Relaciones
    public function productOperationLists()
    {
        return $this->hasMany(ProductOperationList::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Accessors
    public function getNameAttribute(): string
    {
        return "{$this->action} {$this->garment_part}";
    }

    public function getNormalizedNameAttribute(): string
    {
        return strtoupper(trim("{$this->action} {$this->garment_part}"));
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeByGarmentPart(Builder $query, string $garmentPart): Builder
    {
        return $query->where('garment_part', $garmentPart);
    }

    public function scopeByNormalizedName(Builder $query, string $action, string $garmentPart): Builder
    {
        return $query->whereRaw('UPPER(TRIM(CONCAT(action, " ", garment_part))) = ?', [
            strtoupper(trim("{$action} {$garmentPart}"))
        ]);
    }
}
