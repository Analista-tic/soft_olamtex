<?php

namespace App\Models\Settings;

use App\Models\Concerns\HasAuditLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Configuración de un evento que avisa a alguien (cuándo, cómo y a quién). */
class NotificationEvent extends Model
{
    use HasAuditLabel;

    protected $table = 'notification_events';

    protected $fillable = ['event_key', 'enabled', 'channel_database', 'channel_mail', 'exclude_actor'];

    protected $casts = [
        'enabled' => 'boolean',
        'channel_database' => 'boolean',
        'channel_mail' => 'boolean',
        'exclude_actor' => 'boolean',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationEventRecipient::class, 'notification_event_id');
    }

    public static function auditLabel(): string
    {
        return 'Regla de notificación';
    }
}
