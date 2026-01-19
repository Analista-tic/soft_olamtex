<?php

namespace App\Models\ProductSheet;

use App\Models\Inventory\FabricColor;
use App\Models\ProductSheet\ProductSheet;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class);
    }
}
