<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class Retention extends Model
{
    protected $fillable = [
        'name',
        'percent',
        'type',
        'description',
        'status',
    ];
}
