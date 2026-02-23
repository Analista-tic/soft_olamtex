<?php

namespace App\Models\Inventory;

use App\Models\Concerns\HasAuditLabel;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseTransfer extends Model implements \App\Contracts\HasAuditLabel
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $fillable = [
        'document_number',
        'date',
        'origin_warehouse_id',
        'destination_warehouse_id',
        'observation',
        'created_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public static function auditLabel(): string
    {
        return 'Transferencia de bodega';
    }

    public function originWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    public function destinationWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function items()
    {
        return $this->hasMany(WarehouseTransferItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
