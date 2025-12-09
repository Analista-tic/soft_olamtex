<?php

namespace App\Models\Vault;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultSubKey extends Model
{
    protected $fillable = [
        'vault_entry_id',
        'encrypted_payload',
        'iv',
        'salt',
    ];

    /**
     * Get the vault entry that owns the sub-key.
     */
    public function vaultEntry(): BelongsTo
    {
        return $this->belongsTo(VaultEntry::class);
    }
}
