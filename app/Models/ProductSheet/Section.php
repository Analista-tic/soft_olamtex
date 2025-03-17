<?php

namespace App\Models\ProductSheet;

use App\Models\ProductSheet;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class);
    }
}
