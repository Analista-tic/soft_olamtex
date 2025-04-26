<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Model;

class CategorySubGroup extends Model
{
    protected $fillable = ['name', 'category_group_id']; // Ejemplo: "Materia Prima"

    public function categoryGroup()
    {
        return $this->belongsTo(CategoryGroup::class);
    }
}
