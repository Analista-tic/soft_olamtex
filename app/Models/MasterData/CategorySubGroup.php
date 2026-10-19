<?php

namespace App\Models\MasterData;

use App\Contracts\HasAuditLabel;
use Illuminate\Database\Eloquent\Model;

class CategorySubGroup extends Model implements HasAuditLabel
{
    use \App\Models\Concerns\NormalizesAttributes;

    /** Reglas de normalización al guardar. material_kind (enum) queda fuera. */
    protected array $normalizedAttributes = [
        'name' => 'catalogName',
        'code' => 'code',
    ];

    protected $fillable = ['name', 'category_group_id', 'material_kind', 'code']; // Ejemplo: "Materia Prima"

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
