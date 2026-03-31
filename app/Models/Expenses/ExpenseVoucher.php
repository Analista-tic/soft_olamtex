<?php

namespace App\Models\Expenses;

use App\Models\MasterData\Contact;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseVoucher extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'finance_expense_vouchers';

    protected $fillable = [
        'voucher_number',
        'contact_id',
        'payment_date',
        'account_id',
        'payment_method',
        'reference',
        'notes',
        'voided_at',
        'void_reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'voucher_number' => 'integer',
        'contact_id' => 'integer',
        'payment_date' => 'date',
        'account_id' => 'integer',
        'voided_at' => 'datetime',
        'created_by_user_id' => 'integer',
    ];

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
