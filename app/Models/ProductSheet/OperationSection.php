<?php

namespace App\Models\ProductSheet;

use App\Models\Concerns\HasAuditLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class OperationSection extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'operation_sections';

    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    public array $auditIgnore = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function productSheetOperations()
    {
        return $this->hasMany(ProductSheetOperation::class, 'operation_section_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
