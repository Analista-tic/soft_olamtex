<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

class World extends Model
{
    protected $fillable = ['name'];

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class);
    }
}