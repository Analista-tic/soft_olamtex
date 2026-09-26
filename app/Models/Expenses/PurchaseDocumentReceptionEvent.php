<?php

namespace App\Models\Expenses;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Historial de la recepción de una factura de compra: cada rechazo y cada reapertura queda como un
 * evento propio (con motivo, usuario y fecha), de modo que nada se pisa si se rechaza más de una vez.
 */
class PurchaseDocumentReceptionEvent extends Model
{
    public const TYPE_REJECTED = 'rejected';

    public const TYPE_REOPENED = 'reopened';

    protected $table = 'purchase_document_reception_events';

    protected $fillable = [
        'purchase_document_id',
        'event_type',
        'reason',
        'notes',
        'user_id',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(PurchaseDocument::class, 'purchase_document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
