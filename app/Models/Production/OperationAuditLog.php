<?php

namespace App\Models\Production;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;

class OperationAuditLog extends Model
{
    protected $table = 'operation_audit_logs';

    protected $fillable = [
        'operation_id',
        'field_changed',
        'old_value',
        'new_value',
        'motivo',
        'changed_by',
    ];

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
