<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YarnBase extends Model
{
    use HasFactory;

    protected $fillable = ['name']; // Campos asignables

    public function productSheets()
    {
        return $this->hasMany(ProductSheet::class, 'yarn_base_id');
    }
}