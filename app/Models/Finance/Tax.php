<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model implements \App\Contracts\HasAuditLabel
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'percentage',
        'debit_account_id',
        'credit_account_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'percentage' => 'decimal:2',
    ];

    public static function auditLabel(): string
    {
        return 'Impuesto';
    }
}
