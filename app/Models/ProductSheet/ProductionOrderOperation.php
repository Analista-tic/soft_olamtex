<?php

namespace App\Models\ProductSheet;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ProductionOrderOperation extends Model
{
    use HasFactory;

    protected $table = 'production_order_operations';

    protected $fillable = [
        'programmer_id',
        'sequence',
        'operation_id',
        'machine_id',
        'quantity',
        'sam_base',
        'machine_supplement_factor',
        'fabric_base_factor',
        'operator_efficiency_factor',
        'sam_total_unitario',
        'sam_total_operacion',
        'sam_total_lote',
        'quantity_progressed',
        'status',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'quantity' => 'integer',
        'sam_base' => 'float',
        'machine_supplement_factor' => 'float',
        'fabric_base_factor' => 'float',
        'operator_efficiency_factor' => 'float',
        'sam_total_unitario' => 'float',
        'sam_total_operacion' => 'float',
        'sam_total_lote' => 'float',
        'quantity_progressed' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function programmer()
    {
        return $this->belongsTo(Programmer::class);
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function scopeByOrder(Builder $query, int $programmerId): Builder
    {
        return $query->where('programmer_id', $programmerId)->orderBy('sequence');
    }
}
