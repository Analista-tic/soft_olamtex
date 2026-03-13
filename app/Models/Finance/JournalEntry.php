<?php

namespace App\Models\Finance;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $table = 'journal_entries';

    // Tipos de asiento
    public const TYPE_STANDARD = 'standard';
    public const TYPE_EXPENSE_VOUCHER = 'expense_voucher';
    public const TYPE_CASH_RECEIPT = 'cash_receipt';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_OPENING = 'opening';
    public const TYPE_CLOSING = 'closing';

    // Estados
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'entry_number',
        'entry_date',
        'entry_type',
        'status',
        'posted_at',
        'description',
        'reversal_reason',
        'source_type',
        'source_id',
        'reversed_by_entry_id',
        'reverses_entry_id',
        'total_debit',
        'total_credit',
        'created_by_user_id',
    ];

    protected $casts = [
        'entry_number' => 'integer',
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'source_id' => 'integer',
        'reversed_by_entry_id' => 'integer',
        'reverses_entry_id' => 'integer',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'created_by_user_id' => 'integer',
    ];

    /**
     * Líneas del asiento contable.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }

    /**
     * Usuario que creó el asiento.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Asiento que reversó este asiento.
     */
    public function reversedByEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    /**
     * Asiento que este asiento reversa.
     */
    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    /**
     * Scope para asientos contabilizados.
     */
    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    /**
     * Scope por tipo de documento fuente.
     */
    public function scopeForSourceType($query, string $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }

    /**
     * Scope por documento fuente específico.
     */
    public function scopeForSource($query, string $sourceType, int $sourceId)
    {
        return $query->where('source_type', $sourceType)->where('source_id', $sourceId);
    }

    /**
     * Scope para asientos de reversión (los que revierten otro asiento).
     */
    public function scopeReversalEntries($query)
    {
        return $query->whereNotNull('reverses_entry_id');
    }

    /**
     * Scope para asientos base (no son asientos de reversión).
     */
    public function scopeBaseEntries($query)
    {
        return $query->whereNull('reverses_entry_id');
    }

    /**
     * Scope para asiento activo contabilizado de una fuente.
     */
    public function scopeActivePosted($query)
    {
        return $query
            ->where('status', self::STATUS_POSTED)
            ->whereNull('reverses_entry_id')
            ->whereNull('reversed_by_entry_id');
    }

    /**
     * Verifica si el asiento está balanceado.
     */
    public function isBalanced(): bool
    {
        return bccomp((string) $this->total_debit, (string) $this->total_credit, 2) === 0;
    }

    /**
     * Verifica si el asiento está contabilizado.
     */
    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * Verifica si el asiento fue reversado.
     */
    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    /**
     * Recalcula los totales desde las líneas.
     */
    public function recalculateTotals(): void
    {
        $this->total_debit = $this->lines()->sum('debit');
        $this->total_credit = $this->lines()->sum('credit');
    }

    /**
     * Busca el asiento contable asociado a un documento fuente.
     */
    public static function findBySource(string $sourceType, int $sourceId): ?self
    {
        return static::query()
            ->forSource($sourceType, $sourceId)
            ->activePosted()
            ->latest('id')
            ->first();
    }

    /**
     * Busca el último asiento (de cualquier estado) asociado a una fuente.
     */
    public static function findLatestBySource(string $sourceType, int $sourceId): ?self
    {
        return static::query()
            ->forSource($sourceType, $sourceId)
            ->latest('id')
            ->first();
    }

    /**
     * Verifica si ya existe un asiento para un documento fuente.
     */
    public static function existsForSource(string $sourceType, int $sourceId): bool
    {
        return static::query()
            ->forSource($sourceType, $sourceId)
            ->exists();
    }

    /**
     * Mapa de tipos de asiento para UI.
     */
    public static function getEntryTypeOptions(): array
    {
        return [
            ['id' => self::TYPE_STANDARD, 'name' => 'Estándar'],
            ['id' => self::TYPE_EXPENSE_VOUCHER, 'name' => 'Comprobante de egreso'],
            ['id' => self::TYPE_CASH_RECEIPT, 'name' => 'Recibo de caja'],
            ['id' => self::TYPE_ADJUSTMENT, 'name' => 'Ajuste'],
            ['id' => self::TYPE_OPENING, 'name' => 'Apertura'],
            ['id' => self::TYPE_CLOSING, 'name' => 'Cierre'],
        ];
    }

    /**
     * Mapa de estados para UI.
     */
    public static function getStatusOptions(): array
    {
        return [
            ['id' => self::STATUS_DRAFT, 'name' => 'Borrador'],
            ['id' => self::STATUS_POSTED, 'name' => 'Contabilizado'],
            ['id' => self::STATUS_REVERSED, 'name' => 'Reversado'],
        ];
    }
}
