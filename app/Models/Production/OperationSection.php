<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class OperationSection extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel, \App\Models\Concerns\NormalizesAttributes;

    /** Reglas de normalización al guardar. type (enum PREPARACION/ENSAMBLE/TERMINACION) queda fuera. */
    protected array $normalizedAttributes = [
        'name' => 'catalogName',
    ];

    protected $table = 'operation_sections';

    protected $fillable = [
        'name',
        'type',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Determina si esta sección es de tipo PREPARACION.
     */
    public function isPreparation(): bool
    {
        return $this->type === 'PREPARACION';
    }

    /**
     * Determina si esta sección es de tipo ENSAMBLE.
     */
    public function isAssembly(): bool
    {
        return $this->type === 'ENSAMBLE';
    }

    /**
     * Determina si esta sección es de tipo TERMINACION.
     */
    public function isFinishing(): bool
    {
        return $this->type === 'TERMINACION';
    }

    // Relaciones
    public function productOperationLists()
    {
        return $this->hasManyThrough(
            ProductOperationList::class,
            Operation::class,
            'section_id',
            'operation_id',
            'id',
            'id'
        );
    }

    public function operations()
    {
        return $this->hasMany(Operation::class, 'section_id');
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

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}