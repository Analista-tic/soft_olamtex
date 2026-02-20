<?php

namespace App\Models\Production;

use App\Models\Textil\FabricRoll;
use App\Models\Production\ProductSheet;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class);
    }
}
