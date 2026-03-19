<?php

namespace App\Enums\Income;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Sin pago',
            self::PARTIAL => 'Pago parcial',
            self::PAID => 'Pagada',
        };
    }
}
