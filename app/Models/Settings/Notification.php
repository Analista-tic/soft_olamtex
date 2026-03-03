<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Modelo de notificación personalizado que extiende el de Laravel
 * para agregar soporte para archived_at y soft deletes.
 */
class Notification extends DatabaseNotification
{
    use SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scope para obtener notificaciones no archivadas.
     */
    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope para obtener notificaciones no eliminadas.
     */
    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope para obtener notificaciones activas (no archivadas ni eliminadas).
     */
    public function scopeActive($query)
    {
        return $query->notArchived()->notDeleted();
    }

    /**
     * Determina si la notificación está archivada.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Determina si la notificación está eliminada (soft delete).
     */
    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
