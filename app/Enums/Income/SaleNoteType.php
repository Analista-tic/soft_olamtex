<?php

namespace App\Enums\Income;

use App\Models\Income\SaleDocument;

enum SaleNoteType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT => 'Nota credito',
            self::DEBIT => 'Nota debito',
        };
    }

    public function documentType(): string
    {
        return match ($this) {
            self::CREDIT => SaleDocument::TYPE_CREDIT_NOTE,
            self::DEBIT => SaleDocument::TYPE_DEBIT_NOTE,
        };
    }
}
