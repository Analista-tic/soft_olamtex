<?php

namespace App\Models\Common;

use App\Models\Inventory\Article;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'observation'];

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'warehouse_article')
            ->withPivot(['quantity', 'stock_min', 'stock_max'])
            ->withTimestamps();
    }
}
