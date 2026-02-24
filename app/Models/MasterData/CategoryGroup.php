<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;

class CategoryGroup extends Model implements \App\Contracts\HasAuditLabel
{
    protected $fillable = ['name'];

    public static function auditLabel(): string
    {
        return 'Grupo de categorías';
    }

    public function subGroups()
    {
        return $this->belongsTo(CategorySubGroup::class);
    }
}