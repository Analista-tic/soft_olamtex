<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    // Campos a ignorar en el registro de auditoría
    public array $auditIgnore = ['created_at', 'updated_at'];
}
