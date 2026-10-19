<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class Retention extends Model implements \App\Contracts\HasAuditLabel
{
    use \App\Models\Concerns\NormalizesAttributes;

    /** Reglas de normalización al guardar (ver NormalizesAttributes). */
    protected array $normalizedAttributes = [
        'description' => 'freeText',
    ];

    protected $fillable = [
        'name',
        'percent',
        'type',
        'description',
        'account_id',
        'status',
    ];

    protected $casts = [
        'percent' => 'decimal:2',
        'account_id' => 'integer',
    ];

    public static function auditLabel(): string
    {
        return 'Retención';
    }
}
