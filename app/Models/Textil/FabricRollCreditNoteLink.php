<?php

namespace App\Models\Textil;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Enlace entre un rollo rechazado en recepción y la nota crédito (manual) que lo cubre.
 */
class FabricRollCreditNoteLink extends Model
{
    protected $table = 'fabric_roll_credit_note_links';

    protected $fillable = [
        'fabric_roll_id',
        'credit_note_document_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'fabric_roll_id' => 'integer',
        'credit_note_document_id' => 'integer',
        'created_by_user_id' => 'integer',
    ];

    public function roll()
    {
        return $this->belongsTo(FabricRoll::class, 'fabric_roll_id');
    }

    public function creditNote()
    {
        return $this->belongsTo(PurchaseDocument::class, 'credit_note_document_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
