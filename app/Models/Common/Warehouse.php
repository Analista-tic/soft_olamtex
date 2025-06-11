<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'observation']; // Renombrado de nombre y ubicacion

    public function articles()
    {
        return $this->belongsToMany(\App\Models\Inventory\Article::class, 'warehouse_article')
            ->withPivot(['quantity', 'stock_min', 'stock_max'])
            ->withTimestamps();
    }

    public function warehouses()
    {
        return $this->belongsToMany(\App\Models\Common\Warehouse::class, 'warehouse_article')
            ->withPivot(['quantity', 'stock_min', 'stock_max'])
            ->withTimestamps();
    }
}
