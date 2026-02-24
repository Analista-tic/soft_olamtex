<?php

namespace App\Models\Settings;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'auditable_type',
        'auditable_id',
        'module',
        'description',
        'company_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public array $auditIgnore = ['created_at', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Audit logs are append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('Audit logs are append-only and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
