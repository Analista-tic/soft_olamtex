<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ProductOperationList extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'product_sheet_operations';

    protected $fillable = [
        'product_sheet_id',
        'operation_id',
        'machine_id',
        'operation_section_id',
        'sequence',
        'sequence_order', // Alias para sequence (mapeado vía mutator)
        'fabric_base_id',
        'quantity',
        'sam_base',
        'sam',
        'fabric_base_factor',
        'sam_total_unitario',
        'sam_total_operacion',
        'is_active',
        'optimal_time',
        'operator_efficiency_factor',
        'machine_supplement_factor',
        'production_time',
        'actual_time',
        'variance',
        'notes',
    ];

    protected $casts = [
        'product_sheet_id' => 'integer',
        'operation_id' => 'integer',
        'machine_id' => 'integer',
        'operation_section_id' => 'integer',
        'sequence' => 'integer',
        'is_active' => 'boolean',
        'optimal_time' => 'float',
        'sam' => 'float',
        'operator_efficiency_factor' => 'float',
        'machine_supplement_factor' => 'float',
        'production_time' => 'float',
        'actual_time' => 'float',
        'variance' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $with = [];

    // Relaciones
    public function productSheet()
    {
        return $this->belongsTo(ProductSheet::class);
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function operationSection()
    {
        return $this->belongsTo(OperationSection::class);
    }

    public function calculateSamTotalUnitario(): float
    {
        $samBase = $this->sam_base ?? $this->sam ?? 0;
        $sup = $this->machine_supplement_factor ?? 1.0;
        $fabric = $this->fabric_base_factor ?? 1.0;
        $op = $this->operator_efficiency_factor ?? 1.0;

        return $samBase * $sup * $fabric * $op;
    }

    public function calculateSamTotalOperacion(): float
    {
        return $this->calculateSamTotalUnitario() * ($this->quantity ?? 1);
    }

    /**
     * Alias: sequence_order mapea a sequence para compatibilidad con frontend.
     */
    public function getSequenceOrderAttribute(): int
    {
        return (int) ($this->attributes['sequence'] ?? 0);
    }

    public function setSequenceOrderAttribute($value): void
    {
        $this->attributes['sequence'] = $value;
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBySheet(Builder $query, int $sheetId): Builder
    {
        return $query->where('product_sheet_id', $sheetId);
    }

    public function scopeBySection(Builder $query, int $sectionId): Builder
    {
        return $query->where('operation_section_id', $sectionId);
    }

    public function scopeByOperation(Builder $query, int $operationId): Builder
    {
        return $query->where('operation_id', $operationId);
    }

    public function scopeByMachine(Builder $query, int $machineId): Builder
    {
        return $query->where('machine_id', $machineId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sequence');
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with(['operation', 'machine', 'operationSection', 'productSheet']);
    }
}