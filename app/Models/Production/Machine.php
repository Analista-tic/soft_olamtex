<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use App\Models\Settings\User;
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
        'supplement_factor',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'supplement_factor' => 'float',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $with = [];

    // Relaciones
    public function productOperationLists()
    {
        return $this->hasMany(ProductOperationList::class);
    }

    public function operations()
    {
        return $this->hasMany(Operation::class, 'machine_id_reference');
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