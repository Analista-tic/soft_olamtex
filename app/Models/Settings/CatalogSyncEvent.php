<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

/**
 * Marca de tiempo por catálogo (grupos, impuestos, ítems, etc.): permite que otras
 * pestañas/equipos detecten por polling que un catálogo cambió en otro lugar, sin
 * guardar un historial de eventos — solo la última vez que cambió cada uno.
 */
class CatalogSyncEvent extends Model
{
    protected $table = 'catalog_sync_events';
    protected $primaryKey = 'catalog_key';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['catalog_key', 'updated_at'];

    protected $casts = [
        'updated_at' => 'datetime',
    ];
}
