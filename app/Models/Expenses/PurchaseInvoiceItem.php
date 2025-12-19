<?php

namespace App\Models\Expenses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id',
        'article_id',
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

    public function article()
    {
        return $this->belongsTo(\App\Models\Inventory\Article::class);
    }
}
