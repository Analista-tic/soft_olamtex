<?php

namespace App\Models\MasterData;

use App\Models\Finance\AccountingAccount;
use App\Models\MasterData\Color;
use App\Models\Finance\Tax;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model implements \App\Contracts\HasAuditLabel
{
    use \App\Models\Concerns\HasAuditLabel, SoftDeletes;

    public const ORIGIN_SYSTEM = 'system';
    public const ORIGIN_MIGRATION = 'migration';

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
        'origin_type',
        'was_edited_after_import',
        'imported_at',
        'import_batch_id',
        'unit_measure', // EN: unidad de medida
        'income_account_id',
        'inventory_account_id',
        'cost_of_sale_account_id',
        'color_id',
    ];

    protected $casts = [
        'extra_fields' => 'array',
        'negative_movement' => 'integer',
        'tracks_inventory' => 'boolean',
        'status' => 'boolean',
        'was_edited_after_import' => 'boolean',
        'imported_at' => 'datetime',
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
    public function setWarehouseStock(
        int $warehouseId,
        float $quantity,
        float $stockMin = 0,
        float $stockMax = 0
    ): void {
        WarehouseItem::updateOrCreate(
            [
                'item_id'      => $this->id,
                'warehouse_id' => $warehouseId,
            ],
            [
                'quantity'  => $quantity,
                'stock_min' => $stockMin,
                'stock_max' => $stockMax,
            ]
        );
    }

    public function removeWarehouse(int $warehouseId): void
    {
        WarehouseItem::where('item_id', $this->id)
            ->where('warehouse_id', $warehouseId)
            ->delete();
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

    public function warehouseItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WarehouseItem::class);
    }

    public function warehouses(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            Warehouse::class,
            WarehouseItem::class,
            'item_id',
            'id',
            'id',
            'warehouse_id'
        );
    }

    public function getImgUrlAttribute(): ?string
    {
        if (!$this->img) {
            return null;
        }

        $path = ltrim((string) $this->img, '/');
        $path = preg_replace('/^storage\//', '', $path);

        return route('storage.file.show', ['path' => $path]);
    }

    public function isMigrationOrigin(): bool
    {
        return $this->origin_type === self::ORIGIN_MIGRATION;
    }

    public function shouldHighlightAsImported(): bool
    {
        return $this->isMigrationOrigin() && !$this->was_edited_after_import;
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }
}
