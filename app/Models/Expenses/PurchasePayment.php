<?php

namespace App\Models\Expenses;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    use HasFactory;

    protected $table = 'purchase_payments';

    protected $fillable = [
        'voucher_number',
        'purchase_invoice_id',
        'payment_date',
        'amount',
        'reference',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'float',
        'voucher_number' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseDocument::class, 'purchase_invoice_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
