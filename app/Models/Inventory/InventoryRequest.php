<?php

namespace App\Models\Inventory;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_REQUESTED = 'solicitado';
    public const STATUS_REJECTED = 'rechazado';
    public const STATUS_DELIVERED = 'entregado';

    protected $fillable = [
        'document_number',
        'date',
        'delivery_date',
        'status',
        'observation',
        'image',
        'created_by_user_id',
        'approved_by_user_id',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'delivery_date' => 'date',
        'rejected_at' => 'datetime',
    ];

    protected $appends = ['image_url'];

    public function items()
    {
        return $this->hasMany(InventoryRequestItem::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        $path = ltrim($this->image, '/');
        $path = preg_replace('/^storage\\//', '', $path);

        return route('storage.file.show', ['path' => $path]);
    }
}