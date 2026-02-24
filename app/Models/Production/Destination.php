<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    protected $fillable = ['name'];

    public function product()
    {
        return $this->hasMany(ProductSheet::class);
    }
}