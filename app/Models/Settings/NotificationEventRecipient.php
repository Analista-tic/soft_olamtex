<?php

namespace App\Models\Settings;

use App\Models\Concerns\HasAuditLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationEventRecipient extends Model
{
    use HasAuditLabel;

    public const TYPE_ROLE = 'role';
    public const TYPE_AREA = 'area';
    public const TYPE_USER = 'user';
    public const TYPE_DYNAMIC = 'dynamic';

    protected $table = 'notification_event_recipients';

    protected $fillable = ['notification_event_id', 'recipient_type', 'recipient_value', 'permission_filter'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(NotificationEvent::class, 'notification_event_id');
    }

    public static function auditLabel(): string
    {
        return 'Destinatario de notificación';
    }
}
