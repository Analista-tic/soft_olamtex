<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model implements \App\Contracts\HasAuditLabel
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'percentage',
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
