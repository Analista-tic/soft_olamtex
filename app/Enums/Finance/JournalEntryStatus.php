<?php

namespace App\Enums\Finance;

enum JournalEntryStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case REVERSED = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::POSTED => 'Contabilizado',
            self::REVERSED => 'Reversado',
        };
    }
}
