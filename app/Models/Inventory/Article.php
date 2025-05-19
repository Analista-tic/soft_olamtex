<?php

namespace App\Models\Inventory;

use App\Models\Common\CategorySubGroup;
use App\Models\Common\Color;
use App\Models\Common\Warehouse;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'warehouse_id',
        'category_sub_group_id',
        'color_id',
        'img',
        'impuesto',
        'stock_min',
        'stock_max',
        'bar_code',
        'negative_movement',
        'description',
        'extra_fields',
        'name'
    ];

    protected $casts = [
        'extra_fields' => 'array',
        'negative_movement' => 'integer',
    ];

    /**
     * Mutador para guardar extra_fields como JSON.
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
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function categorySubGroup()
    {
        return $this->belongsTo(CategorySubGroup::class, 'category_sub_group_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }
}
