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
        'total',
        'total_tax',
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
