<?php

namespace App\Models\MasterData;

use App\Contracts\HasAuditLabel;
use Illuminate\Database\Eloquent\Model;

class CategoryGroup extends Model implements HasAuditLabel
{
    protected $fillable = ['name', 'code'];

    public static function auditLabel(): string
    {
        return 'Grupo de categorías';
    }

    public function subGroups()
    {
        return $this->hasMany(CategorySubGroup::class, 'category_group_id');
    }
}
