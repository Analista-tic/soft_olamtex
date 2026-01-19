<?php

namespace App\Models\ProductSheet;

use App\Models\Inventory\FabricOrderItem;
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

    // Relaciones eliminadas: section, group, brand, world, yarnBase, fabricBase
    // Ahora estos datos se obtienen desde la tabla parameters usando ParameterService
}