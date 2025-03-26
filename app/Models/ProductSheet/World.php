<?php

namespace App\Models\ProductSheet;

use App\Models\ProductSheet\ProductSheet;
use Illuminate\Database\Eloquent\Model;

class World extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class);
    }
}
