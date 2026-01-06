<?php

namespace App\Models\ProductSheet;

use App\Models\Concerns\HasAuditLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operation extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'operations';

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'standard_time',
        'usage_count',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'standard_time' => 'float',
        'usage_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function operationLists()
    {
        return $this->hasMany(ProductOperationList::class);
    }
}
