<?php

namespace App\Models\Inventory;

use App\Models\Common\CategorySubGroup;
use App\Models\Common\Color;
use App\Models\Common\Tax;
use App\Models\Common\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Item extends Model
{
    protected $fillable = [
        'category_sub_group_id',
        'color_id',
        'tax_id', // Nuevo campo
        'img',
        'bar_code',
        'negative_movement',
        'description',
        'extra_fields',
        'name',
        'reference',
        'status',
        'unit_measure', // EN: unidad de medida
    ];

    protected $casts = [
        'extra_fields' => 'array',
        'negative_movement' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * Mutador para guardar extra_fields como SON.
     */
    public function setExtraFieldsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['extra_fields'] = json_encode($value, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            $this->attributes['extra_fields'] = (is_array($decoded)) ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : null;
        } else {
            $this->attributes['extra_fields'] = null;
        }
    }

    /**
     * Accesor para obtener extra_fields como array.
     */
    public function getExtraFieldsAttribute($value)
    {
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    // Relaciones
    public function getWarehouseStocks()
    {
        return $this->warehouses()->get();
    }

    public function setWarehouseStock($warehouseId, $quantity, $stockMin, $stockMax)
    {
        $this->warehouses()->syncWithoutDetaching([
            $warehouseId => [
                'quantity' => $quantity,
                'stock_min' => $stockMin,
                'stock_max' => $stockMax,
            ]
        ]);
    }

    public function removeWarehouse($warehouseId)
    {
        $this->warehouses()->detach($warehouseId);
    }

    public function categorySubGroup()
    {
        return $this->belongsTo(CategorySubGroup::class, 'category_sub_group_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    /**
     * Bodegas donde está asignado el item con datos de stock en el pivot.
     */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_article')
            ->withPivot(['quantity', 'stock_min', 'stock_max'])
            ->withTimestamps();
    }
}
