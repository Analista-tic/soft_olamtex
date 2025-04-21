<?php

namespace App\Models\ProductSheet;

use App\Models\Inventory\BaseFabric;
use App\Models\ProductSheet\ProductSheet;
use Illuminate\Database\Eloquent\Model;

class Base extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class);
    }

    public function baseFabrics()
    {
        return $this->hasMany(BaseFabric::class);
    }
}
