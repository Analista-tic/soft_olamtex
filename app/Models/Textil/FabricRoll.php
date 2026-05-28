<?php

namespace App\Models\Textil;

use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\MasterData\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FabricRoll extends Model
{
    use HasFactory;

    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_PENDING = 'pending_approval';
    public const APPROVAL_NOT_APPROVED = 'not_approved';

    /**
     * price_per_meter persiste desde el precio unitario de PurchaseDocumentItem.
     * Se puede actualizar después también para rollos sin compra vinculada.
     */
    protected $fillable = [
        'legacy_id',
        'roll_number',
        'purchase_document_item_id',
        'item_id',
        'location',
        'quantity',
        'roll_width',
        'price_per_meter',
        'description',
        'internal_color_name',
        'internal_color_code',
        'movement_type',
        'partial_output',
        'actual_output',
        'entry_date',
        'approval_status',
        'batch',
    ];

    protected $casts = [
        'legacy_id'                 => 'integer',
        'purchase_document_item_id' => 'integer',
        'item_id'                   => 'integer',
        'quantity'                  => 'decimal:2',
        'roll_width'                => 'decimal:2',
        'price_per_meter'           => 'decimal:4',
        'partial_output'            => 'decimal:2',
        'actual_output'             => 'decimal:2',
        'entry_date'                => 'date',
    ];

    // Relación con el item de factura de compra
    public function purchaseDocumentItem()
    {
        return $this->belongsTo(PurchaseDocumentItem::class, 'purchase_document_item_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    // Obtener la cantidad disponible
    public function getAvailableQuantityAttribute()
    {
        return $this->quantity - $this->actual_output;
    }

    // Verificar si el rollo está agotado
    public function getIsDepletedAttribute()
    {
        return $this->available_quantity <= 0;
    }

    public function consumptions()
    {
        return $this->hasMany(\App\Models\Production\ProductionRollConsumption::class, 'fabric_roll_id');
    }

    public function adjustments()
    {
        return $this->hasMany(FabricRollAdjustment::class, 'fabric_roll_id');
    }
}
