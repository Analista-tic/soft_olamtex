<?php

namespace App\Models\ProductSheet;

use App\Models\Inventory\FabricOrderItem;
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
        'fabric_base_id',
        'reference',
        'billing_price',
        'selling_price',
        'sizes',
        'woven_product',
        'description',
        'processes',
        'status',
        'yarn_base_id', // Nuevo campo
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

    public function yarnBase()
    {
        return $this->belongsTo(YarnBase::class, 'yarn_base_id'); // Relación con YarnBase
    }

    public function fabricBase()
    {
        return $this->belongsTo(FabricBase::class, 'fabric_base_id'); // Relación con FabricBase
    }
}