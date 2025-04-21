<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FabricOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fabric_provider_id',
        'status',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(FabricOrderItem::class)->withTrashed();
    }
    
    public function fabricProvider()
    {
        return $this->belongsTo(FabricProvider::class, 'fabric_provider_id');
    }
}
