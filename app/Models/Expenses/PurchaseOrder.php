<?php

namespace App\Models\Expenses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contact_id',
        'date',
        'delivery_date',
        'supplier_order_number',
        'description',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function contact()
    {
        return $this->belongsTo(\App\Models\Common\Contact::class);
    }

    public function invoice()
    {
        return $this->hasOne(PurchaseInvoice::class);
    }
}
