<?php

namespace App\Models\Finance;

use App\Models\MasterData\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    protected $table = 'journal_entry_lines';

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'contact_id',
        'debit',
        'credit',
        'description',
        'line_reference',
    ];

    protected $casts = [
        'journal_entry_id' => 'integer',
        'account_id' => 'integer',
        'contact_id' => 'integer',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    /**
     * Asiento contable padre.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Cuenta contable de la línea.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_id');
    }

    /**
     * Tercero asociado a la línea (para auxiliares).
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Verifica que la línea tenga exactamente débito o crédito, no ambos.
     */
    public function isValidMovement(): bool
    {
        $debit = (float) $this->debit;
        $credit = (float) $this->credit;

        // Debe tener valor en exactamente una columna
        if ($debit > 0 && $credit > 0) {
            return false;
        }

        // Debe tener al menos un valor positivo
        return $debit > 0 || $credit > 0;
    }

    /**
     * Obtiene el monto neto de la línea (positivo para débito, negativo para crédito).
     */
    public function getNetAmount(): float
    {
        return (float) $this->debit - (float) $this->credit;
    }

    /**
     * Scope para líneas de débito.
     */
    public function scopeDebits($query)
    {
        return $query->where('debit', '>', 0);
    }

    /**
     * Scope para líneas de crédito.
     */
    public function scopeCredits($query)
    {
        return $query->where('credit', '>', 0);
    }

    /**
     * Scope por cuenta.
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope por tercero.
     */
    public function scopeForContact($query, int $contactId)
    {
        return $query->where('contact_id', $contactId);
    }
}
