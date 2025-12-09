<?php

namespace App\Models\Vault;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaultEntry extends Model
{
    protected $fillable = [
        'user_id',
        'encrypted_payload',
        'iv',
        'salt',
    ];

    /**
     * Get the user that owns the vault entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sub-keys for the vault entry.
     */
    public function subKeys(): HasMany
    {
        return $this->hasMany(VaultSubKey::class);
    }
}
