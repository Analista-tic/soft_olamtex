<?php

namespace App\Models\Production;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchControlWarehouseReceipt extends Model
{
    protected $table = 'batch_control_warehouse_receipts';

    protected $fillable = [
        'production_order_id',
        'received_curves',
        'imperfect_curves',
        'responsible',
        'imperfect_observations',
        'remision_curves',
        'remision_observations',
        'warehouse_id',
        'remisionado_at',
    ];

    protected $casts = [
        'received_curves'  => 'array',
        'imperfect_curves' => 'array',
        'remision_curves'  => 'array',
        'remisionado_at'   => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
