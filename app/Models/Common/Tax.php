<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model
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
}
