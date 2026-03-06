<?php

namespace App\Models\Inventory;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryPeriodLock extends Model
{
    protected $table = 'inventory_period_locks';

    protected $fillable = [
        'year',
        'month',
        'closed_at',
        'closed_by_user_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'closed_at' => 'datetime',
    ];

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public static function isPeriodClosed(int $year, int $month): bool
    {
        return static::where('year', $year)
            ->where('month', $month)
            ->exists();
    }
}
