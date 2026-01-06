<?php

namespace App\Models\ProductSheet;

use App\Models\Concerns\HasAuditLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'machines';

    protected $fillable = [
        'code',
        'name',
        'category',
        'supplement_factor',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'supplement_factor' => 'float',
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
