<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Expenses\PurchaseInvoiceItem;

class FabricRoll extends Model
{
    use HasFactory;

    protected $fillable = [
        'roll_number',
        'purchase_invoice_item_id',
        'quantity',
        'roll_width',
        'location',
        'description',
        'partial_output',
        'actual_output',
        'entry_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'roll_width' => 'decimal:2',
        'partial_output' => 'decimal:2',
        'actual_output' => 'decimal:2',
        'entry_date' => 'date',
    ];

    // Relación con el item de factura de compra
    public function purchaseInvoiceItem()
    {
        return $this->belongsTo(PurchaseInvoiceItem::class);
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
}
