<?php

namespace App\Models\Expenses;

use App\Models\Common\Contact;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number',
        'contact_id',
        'requester_user_id',
        'date',
        'destination',
        'estimated_amount',
        'description',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'estimated_amount' => 'decimal:2',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }
}
