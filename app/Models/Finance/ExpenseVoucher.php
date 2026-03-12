<?php

namespace App\Models\Finance;

use App\Models\MasterData\Contact;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseVoucher extends Model
{
    use HasFactory;

    protected $table = 'finance_expense_vouchers';

    protected $fillable = [
        'voucher_number',
        'contact_id',
        'payment_date',
        'account_id',
        'payment_method',
        'reference',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'voucher_number' => 'integer',
        'contact_id' => 'integer',
        'payment_date' => 'date',
        'account_id' => 'integer',
        'created_by_user_id' => 'integer',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

