<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Inventory\Item;

class Color extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rgb_value',
    ];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
