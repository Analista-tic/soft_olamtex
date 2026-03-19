<?php

namespace App\Models\MasterData;

use App\Models\Finance\AccountingAccount;
use App\Models\Finance\Tax;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Item extends Model implements \App\Contracts\HasAuditLabel
{
    use \App\Models\Concerns\HasAuditLabel;
    protected $fillable = [
        'category_sub_group_id',
        'tax_id', // Nuevo campo
        'img',
        'bar_code',
        'negative_movement',
        'description',
        'extra_fields',
        'name',
        'reference',
        'item_type',
        'tracks_inventory',
        'status',
        'unit_measure', // EN: unidad de medida
        'income_account_id',
        'inventory_account_id',
        'cost_of_sale_account_id',
    ];

    protected $casts = [
        'extra_fields' => 'array',
        'negative_movement' => 'integer',
        'tracks_inventory' => 'boolean',
        'status' => 'boolean',
    ];

    protected $appends = ['img_url'];

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

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'income_account_id');
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'inventory_account_id');
    }

    public function costOfSaleAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'cost_of_sale_account_id');
    }

    /**
     * Bodegas donde está asignado el item con datos de stock en el pivot.
     */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_items', 'item_id', 'warehouse_id')
            ->withPivot(['quantity', 'stock_min', 'stock_max'])
            ->withTimestamps();
    }

    public function getImgUrlAttribute(): ?string
    {
        if (!$this->img) {
            return null;
        }

        $path = ltrim((string) $this->img, '/');
        $path = preg_replace('/^storage\//', '', $path);

        return "/storage/{$path}";
    }
}
