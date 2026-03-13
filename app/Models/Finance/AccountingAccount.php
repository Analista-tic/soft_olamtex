<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    protected $table = 'accounting_accounts';

    // Tipos principales de cuenta
    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_INCOME = 'income';
    public const TYPE_COST = 'cost';
    public const TYPE_EXPENSE = 'expense';

    // Tipos especiales para parametrización
    public const SPECIAL_ACCOUNTS_PAYABLE = 'accounts_payable';
    public const SPECIAL_ACCOUNTS_RECEIVABLE = 'accounts_receivable';
    public const SPECIAL_CASH = 'cash';
    public const SPECIAL_BANK = 'bank';

    protected $fillable = [
        'code',
        'name',
        'account_type',
        'parent_id',
        'level',
        'allows_entries',
        'is_active',
        'special_type',
        'description',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'level' => 'integer',
        'allows_entries' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Cuenta padre en la jerarquía.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Cuentas hijas en la jerarquía.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Líneas de asiento asociadas a esta cuenta.
     */
    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /**
     * Scope para cuentas activas.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para cuentas que permiten movimientos.
     */
    public function scopeAllowsEntries($query)
    {
        return $query->where('allows_entries', true);
    }

    /**
     * Scope para cuentas de un tipo específico.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Scope para cuentas con tipo especial.
     */
    public function scopeWithSpecialType($query, string $specialType)
    {
        return $query->where('special_type', $specialType);
    }

    /**
     * Verifica si la cuenta es de naturaleza débito (activo, costo, gasto).
     */
    public function isDebitNature(): bool
    {
        return in_array($this->account_type, [
            self::TYPE_ASSET,
            self::TYPE_COST,
            self::TYPE_EXPENSE,
        ], true);
    }

    /**
     * Verifica si la cuenta es de naturaleza crédito (pasivo, patrimonio, ingreso).
     */
    public function isCreditNature(): bool
    {
        return in_array($this->account_type, [
            self::TYPE_LIABILITY,
            self::TYPE_EQUITY,
            self::TYPE_INCOME,
        ], true);
    }

    /**
     * Busca la primera cuenta activa con un tipo especial.
     */
    public static function findBySpecialType(string $specialType): ?self
    {
        return static::query()
            ->active()
            ->allowsEntries()
            ->withSpecialType($specialType)
            ->first();
    }

    /**
     * Opción de mapa de tipos para UI.
     */
    public static function getAccountTypeOptions(): array
    {
        return [
            ['id' => self::TYPE_ASSET, 'name' => 'Activo'],
            ['id' => self::TYPE_LIABILITY, 'name' => 'Pasivo'],
            ['id' => self::TYPE_EQUITY, 'name' => 'Patrimonio'],
            ['id' => self::TYPE_INCOME, 'name' => 'Ingreso'],
            ['id' => self::TYPE_COST, 'name' => 'Costo'],
            ['id' => self::TYPE_EXPENSE, 'name' => 'Gasto'],
        ];
    }
}
