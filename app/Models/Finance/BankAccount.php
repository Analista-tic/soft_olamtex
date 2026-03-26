<?php

namespace App\Models\Finance;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory;

    protected $table = 'bank_accounts';

    public const TYPE_BANK = 'bank';
    public const TYPE_CASH = 'cash';
    public const TYPE_CREDIT_CARD = 'credit_card';

    protected $fillable = [
        'name',
        'type',
        'bank_name',
        'account_number',
        'currency',
        'opening_balance',
        'opening_balance_date',
        'accounting_account_id',
        'is_active',
        'description',
        'created_by_user_id',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'is_active' => 'boolean',
        'accounting_account_id' => 'integer',
        'created_by_user_id' => 'integer',
    ];

    public function accountingAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getCurrentBalanceAttribute(): ?float
    {
        return null;
    }
}
