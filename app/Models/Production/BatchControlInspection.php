<?php

namespace App\Models\Production;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchControlInspection extends Model
{
    protected $table = 'batch_control_inspections';

    protected $fillable = [
        'production_order_id',
        'confirmed_curves',
        'matches_cut',
        'reason',
        'observations',
        'approved_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'confirmed_curves' => 'array',
        'matches_cut'      => 'boolean',
        'approved_at'      => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
