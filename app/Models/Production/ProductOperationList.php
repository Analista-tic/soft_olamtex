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

    protected $table = 'product_operation_lists';

    protected $fillable = [
        'product_sheet_id',
        'operation_id',
        'machine_id',
        'operation_section_id',
        'sequence_order',
        'is_active',
        'optimal_time',
        'sam',
        'operator_efficiency_factor',
        'machine_supplement_factor',
        'production_time',
        'actual_time',
        'variance',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'product_sheet_id' => 'integer',
        'operation_id' => 'integer',
        'machine_id' => 'integer',
        'operation_section_id' => 'integer',
        'sequence_order' => 'integer',
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
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
        return $query->orderBy('sequence_order');
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with(['operation', 'machine', 'operationSection', 'productSheet']);
    }
}