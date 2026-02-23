<?php

namespace App\Models\Production;

use App\Models\Production\ProductSheet;
use Illuminate\Database\Eloquent\Model;

class FabricBase extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class, 'base_id'); // Relación con ProductSheet
    }
}