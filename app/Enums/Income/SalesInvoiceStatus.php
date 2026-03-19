<?php

namespace App\Enums\Income;

enum SalesInvoiceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case VOIDED = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::ISSUED => 'Emitida',
            self::PARTIAL => 'Parcial',
            self::PAID => 'Pagada',
            self::VOIDED => 'Anulada',
        };
    }
}
