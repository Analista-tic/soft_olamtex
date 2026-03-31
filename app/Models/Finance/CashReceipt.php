<?php

namespace App\Models\Finance;

use App\Models\Income\SalePayment;
use App\Models\MasterData\Contact;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashReceipt extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'finance_cash_receipts';

    protected $fillable = [
        'receipt_number',
        'contact_id',
        'payment_date',
        'account_id',
        'payment_method',
        'reference',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'receipt_number' => 'integer',
        'contact_id' => 'integer',
        'payment_date' => 'date',
        'account_id' => 'integer',
        'created_by_user_id' => 'integer',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_id');
    }

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'cash_receipt_id');
    }
}
