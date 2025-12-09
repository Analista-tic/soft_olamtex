<?php

namespace App\Models\Expenses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Inventory\Item;

class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id',
        'item_id',
        'price',
        'tax',
        'quantity',
        'description',
        'supplier_code',
        'supplier_color',
        'order_width',
        'total',
        'total_tax',
    ];

    protected $casts = [
        // 'order_width' => 'float', // Comentado/removido
    ];

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
