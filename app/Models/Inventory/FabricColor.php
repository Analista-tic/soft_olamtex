<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class FabricColor extends Model
{
    protected $fillable = [
        'name',
        'code',
        'rgb',
    ];
}
