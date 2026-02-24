<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ProductSheetOperation extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'product_sheet_operations';

    protected $fillable = [
        'product_sheet_id',
        'sequence',
        'operation_id',
        'machine_id',
        'operation_section_id',
        'fabric_base_id',
        'quantity',
        'sam_base',
        'sam',
        'machine_supplement_factor',
        'fabric_base_factor',
        'operator_efficiency_factor',
        'sam_total_unitario',
        'sam_total_operacion',
        'optimal_time',
        'production_time',
        'actual_time',
        'variance',
        'notes',
        'is_active',
    ];

    public array $auditIgnore = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'product_sheet_id' => 'integer',
        'sequence' => 'integer',
        'operation_id' => 'integer',
        'machine_id' => 'integer',
        'operation_section_id' => 'integer',
        'fabric_base_id' => 'integer',
        'quantity' => 'integer',
        'sam_base' => 'float',
        'sam' => 'float',
        'machine_supplement_factor' => 'float',
        'fabric_base_factor' => 'float',
        'operator_efficiency_factor' => 'float',
        'sam_total_unitario' => 'float',
        'sam_total_operacion' => 'float',
        'optimal_time' => 'float',
        'production_time' => 'float',
        'actual_time' => 'float',
        'variance' => 'float',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

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

    public function fabricBase()
    {
        return $this->belongsTo(FabricBase::class, 'fabric_base_id');
    }

    public function history()
    {
        return $this->hasMany(ProductSheetOperationHistory::class, 'product_sheet_operation_id');
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

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBySheet(Builder $query, int $sheetId): Builder
    {
        return $query->where('product_sheet_id', $sheetId)->orderBy('sequence');
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
