<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_adjustments';

    protected $fillable = [
        'document_number',
        'date',
        'type',             // 'entrada' | 'salida'
        'movement_reason',
        'observation',
        'production_order',
        'subtotal',
        'iva',
        'total',
    ];

    protected $casts = [
        'date' => 'date',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function lines()
    {
        return $this->hasMany(InventoryAdjustmentLine::class);
    }
}
