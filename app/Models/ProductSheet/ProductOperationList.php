<?php

namespace App\Models\ProductSheet;

use App\Models\Concerns\HasAuditLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductOperationList extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    public const SECTIONS = [
        'delantero',
        'trasero',
        'pretina',
        'ensamble',
        'terminacion',
        'presentacion',
    ];

    protected $table = 'product_operation_lists';

    protected $fillable = [
        'product_sheet_id',
        'operation_id',
        'machine_id',
        'sequence_order',
        'section',
        'is_active',
        'optimal_time',
        'operator_efficiency_factor',
        'machine_supplement_factor',
        'production_time',
        'actual_time',
        'variance',
        'operator_name',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sequence_order' => 'integer',
        'is_active' => 'boolean',
        'optimal_time' => 'float',
        'operator_efficiency_factor' => 'float',
        'machine_supplement_factor' => 'float',
        'production_time' => 'float',
        'actual_time' => 'float',
        'variance' => 'float',
    ];

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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
