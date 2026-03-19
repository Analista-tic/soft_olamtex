<?php

namespace App\Enums\Income;

enum SaleNoteStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case VOIDED = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::ISSUED => 'Emitida',
            self::VOIDED => 'Anulada',
        };
    }
}
