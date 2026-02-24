<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class Retention extends Model implements \App\Contracts\HasAuditLabel
{
    protected $fillable = [
        'name',
        'percent',
        'type',
        'description',
        'status',
    ];

    public static function auditLabel(): string
    {
        return 'Retención';
    }
}