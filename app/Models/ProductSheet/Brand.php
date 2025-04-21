<?php

namespace App\Models\ProductSheet;

use App\Models\Inventory\FabricProvider;
use App\Models\ProductSheet\ProductSheet;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class);
    }

    public function fabricProviders()
    {
        return $this->hasMany(FabricProvider::class);
    }
}
