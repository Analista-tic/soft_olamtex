<?php

namespace App\Models;

use App\Models\ProductSheet\Base;
use App\Models\ProductSheet\Brand;
use App\Models\ProductSheet\Group;
use App\Models\ProductSheet\Section;
use App\Models\ProductSheet\World;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'group_id',
        'brand_id',
        'world_id',
        'base_id',
        'reference',
        'billing_price',
        'selling_price',
        'sizes',
        'woven_product',
        'description',
        'processes',
        'status',
    ];

    protected $casts = [
        'sizes' => 'array',
        'processes' => 'array',
        'woven_product' => 'boolean',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function world()
    {
        return $this->belongsTo(World::class);
    }

    public function base()
    {
        return $this->belongsTo(Base::class);
    }
}