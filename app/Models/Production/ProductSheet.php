<?php

namespace App\Models\Production;

use App\Models\Textil\FabricOrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSheet extends Model
{
    use HasFactory;

    protected $table = 'product_sheets';

    protected $fillable = [
        'section_id',
        'group_id',
        'brand_id',
        'world_id',
        'fabric_base_id',
        'type',
        'billing_price',
        'selling_price',
        'sizes',
        'woven_product',
        'description',
        'processes',
        'status',
        'yarn_base_id', // Nuevo campo
        'flat_sketch_path',
        'operational_list_id',
        'sample_reference',
        'product_reference',
    ];

    protected $guarded = [
        'flat_sketch',
    ];

    protected $casts = [
        'sizes' => 'array',
        'processes' => 'array',
		'woven_product' => 'boolean',
        'operational_list_id' => 'integer',
        'type' => 'string',
        'flat_sketch_path' => 'string',
        'sample_reference' => 'string',
        'product_reference' => 'string',
    ];

    // Relaciones eliminadas: section, group, brand, world, yarnBase, fabricBase
    // Ahora estos datos se obtienen desde la tabla parameters usando ParameterService
}