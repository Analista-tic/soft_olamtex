<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rgb_value',
    ];

    public function articles()
    {
        return $this->hasMany(\App\Models\Inventory\Article::class);
    }
}
