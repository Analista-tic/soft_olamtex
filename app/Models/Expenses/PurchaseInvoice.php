<?php

namespace App\Models\Expenses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Common\Contact;

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
        'withholdings',
    ];

    protected $casts = [
        'date' => 'date',
        'delivery_date' => 'date',
        'withholdings' => 'array',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
