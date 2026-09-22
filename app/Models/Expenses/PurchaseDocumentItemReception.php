<?php

namespace App\Models\Expenses;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;

class PurchaseDocumentItemReception extends Model
{
    protected $table = 'purchase_document_item_receptions';

    protected $fillable = [
        'purchase_document_item_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'over_received_reason',
        'inventory_movement_id',
        'received_by_user_id',
        'received_at',
    ];

    protected $casts = [
        'quantity'     => 'decimal:3',
        'unit_price'   => 'decimal:2',
        'received_at'  => 'datetime',
    ];

    public function purchaseDocumentItem()
    {
        return $this->belongsTo(PurchaseDocumentItem::class, 'purchase_document_item_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function inventoryMovement()
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
