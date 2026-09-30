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

    // Eventos por rollo (recepción de telas)
    public const TYPE_ROLL_REJECTED = 'roll_rejected';

    public const TYPE_ROLL_DIFFERENCE_APPROVED = 'roll_difference_approved';

    public const TYPE_ROLL_LINKED_TO_NOTE = 'roll_linked_to_note';

    public const TYPE_ROLL_WRITTEN_OFF = 'roll_written_off';

    public const TYPE_ROLL_ADDED_AFTER_APPROVAL = 'roll_added_after_approval';

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
