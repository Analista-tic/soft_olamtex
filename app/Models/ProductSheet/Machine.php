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

    /**
     * Obtiene el factor de suplemento: BD si existe, si no el accessor calculado (backward compat).
     */
    public function getSupplementFactorAttribute($value): float
    {
        return $value !== null ? (float) $value : $this->getSupplementFactorComputed();
    }

    /**
     * Factor calculado por código/tipo (cuando supplement_factor no está en BD).
     */
    protected function getSupplementFactorComputed(): float
    {
        $supplements = [
            'RECTA' => 1.05,
            'OVERLOCK' => 1.08,
            'COLLARIN' => 1.10,
            'RECUBRIDORA' => 1.12,
            'BOTONERA' => 1.15,
            'OJAL' => 1.15,
            'ELASTIQUERA' => 1.10,
            'CERRADORA' => 1.08,
            'PRESILLADORA' => 1.12,
            'DEFAULT' => 1.05,
        ];

        $code = strtoupper($this->code ?? '');
        $name = strtoupper($this->name ?? '');

        foreach ($supplements as $type => $supplement) {
            if ($type !== 'DEFAULT' && (str_contains($code, $type) || str_contains($name, $type))) {
                return $supplement;
            }
        }

        return $supplements['DEFAULT'];
    }

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
