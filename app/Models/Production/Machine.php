<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catálogo maestro de máquinas de producción.
 *
 * Define las máquinas disponibles para asociarlas a operaciones base
 * (`Operation.machine_id_reference`) y calcular el suplemento aplicado en
 * tiempos/SAM dentro de listados operacionales.
 *
 * Campos principales:
 * - code: código interno o abreviatura de la máquina.
 * - name: nombre descriptivo de la máquina.
 * - supplement_factor: factor multiplicador de suplemento (>= 1.0).
 * - is_active: estado lógico de activación.
 * - created_by: usuario que registró la máquina.
 */
class Machine extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel, \App\Models\Concerns\NormalizesAttributes;

    /** Reglas de normalización al guardar (ver NormalizesAttributes). */
    protected array $normalizedAttributes = [
        'code' => 'code',
        'name' => 'catalogName',
    ];

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
    /**
     * Operaciones base que referencian esta máquina.
     */
    public function operations()
    {
        return $this->hasMany(Operation::class, 'machine_id_reference');
    }

    /**
     * Usuario creador del registro de máquina.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    /**
     * Filtra únicamente máquinas activas.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Filtra por código exacto de máquina.
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }
}