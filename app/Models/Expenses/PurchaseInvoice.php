<?php

namespace App\Models\Expenses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contact_id',
        'purchase_order_id',
        'date',
        'delivery_date',
        'supplier_invoice_number',
        'description',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function contact()
    {
        return $this->belongsTo(\App\Models\Common\Contact::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
