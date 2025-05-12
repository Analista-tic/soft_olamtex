<?php

namespace App\Models\ProductSheet;

use App\Models\ProductSheet\ProductSheet;
use Illuminate\Database\Eloquent\Model;

class FabricBase extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class, 'base_id'); // Relación con ProductSheet
    }
}
