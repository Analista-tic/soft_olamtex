<?php

namespace App\Models\ProductSheet;

use App\Models\ProductSheet\ProductSheet;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class);
    }
}
