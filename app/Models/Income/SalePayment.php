<?php

namespace App\Models\Income;

use App\Models\Finance\CashReceipt;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use HasFactory;

    protected $table = 'sale_payments';

    protected $fillable = [
        'cash_receipt_id',
        'cash_receipt_number',
        'sale_invoice_id',
        'payment_date',
        'amount',
        'reference',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'cash_receipt_id' => 'integer',
        'sale_invoice_id' => 'integer',
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'cash_receipt_number' => 'integer',
        'created_by_user_id' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaleDocument::class, 'sale_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cashReceipt(): BelongsTo
    {
        return $this->belongsTo(CashReceipt::class, 'cash_receipt_id');
    }
}
