<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Model;

use App\Models\Inventory\Item;

class CategorySubGroup extends Model implements \App\Contracts\HasAuditLabel
{
    protected $fillable = ['name', 'category_group_id']; // Ejemplo: "Materia Prima"

    public static function auditLabel(): string
    {
        return 'Subgrupo de categorías';
    }

    public function categoryGroup()
    {
        return $this->belongsTo(CategoryGroup::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'category_sub_group_id');
    }
}
