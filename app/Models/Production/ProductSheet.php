<?php

namespace App\Models\Production;

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
        'costo',
        'selling_price',
        'sizes',
        'woven_product',
        'description',
        'observaciones',
        'processes',
        'status',
        'yarn_base_id',
        'flat_sketch_path',
        'operational_list_id',
        'collection_id',
        'sample_reference',
        'product_reference',
        'product_reference_2',
        'version',
        'parent_id',
        'garment_type',
        'is_enabled',
    ];

    protected $guarded = [
        'flat_sketch',
    ];

    protected $casts = [
        'sizes' => 'array',
        'processes' => 'array',
        'woven_product' => 'boolean',
        'operational_list_id' => 'integer',
        'collection_id' => 'integer',
        'type' => 'string',
        'flat_sketch_path' => 'string',
        'sample_reference' => 'string',
        'product_reference' => 'string',
        'product_reference_2' => 'string',
        'version' => 'integer',
        'parent_id' => 'integer',
        'garment_type' => 'string',
        'is_enabled' => 'boolean',
        'costo' => 'float',
    ];

    // Relaciones eliminadas: section, group, brand, world, yarnBase, fabricBase
    // Ahora estos datos se obtienen desde la tabla parameters usando ParameterService

    /**
     * Registro padre (template) del que se creó esta versión.
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Colección a la que pertenece esta ficha.
     */
    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * Versiones derivadas de este registro.
     */
    public function versions()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Órdenes de producción asociadas a esta ficha.
     * Si existen, el listado operacional no debe editarse.
     */
    public function productionOrders()
    {
        return $this->hasMany(ProductionOrder::class);
    }

    /**
     * Indica si la ficha tiene órdenes de producción asociadas.
     */
    public function hasProductionOrders(): bool
    {
        return $this->productionOrders()->exists();
    }

    /**
     * Calcula la siguiente versión disponible para las mismas referencias base.
     */
    public static function nextVersion(?string $sampleRef, ?string $productRef, ?int $excludeId = null): int
    {
        $query = self::query();

        if ($sampleRef) {
            $query->where('sample_reference', $sampleRef);
        }
        if ($productRef) {
            $query->where('product_reference', $productRef);
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $maxVersion = $query->max('version');

        return ($maxVersion ?? 0) + 1;
    }
}