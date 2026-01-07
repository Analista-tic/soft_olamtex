<?php

namespace App\Models\ProductSheet;

use App\Models\Concerns\HasAuditLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Machine extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'machines';

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function productOperationLists()
    {
        return $this->hasMany(ProductOperationList::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }
}
