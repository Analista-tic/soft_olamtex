<?php

namespace App\Models\ProductSheet;

use App\Models\Inventory\Fabric;
use App\Models\ProductSheet\ProductSheet;
use Illuminate\Database\Eloquent\Model;

class World extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class);
    }

    public function fabrics()
    {
        return $this->hasMany(Fabric::class);
    }
}
