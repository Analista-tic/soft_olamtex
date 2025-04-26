<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Model;

class CategoryGroup extends Model
{
    protected $fillable = ['name'];

    public function subGroups()
    {
        return $this->belongsTo(CategorySubGroup::class);
    }
}
