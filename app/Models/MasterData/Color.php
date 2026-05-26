<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Color extends Model
{
    protected $fillable = ['code', 'name', 'rgb', 'hexadecimal', 'description'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
