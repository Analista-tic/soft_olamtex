<?php

namespace App\Enums\Income;

enum SalesOrderStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case IN_PROCESS = 'in_process';
    case INVOICED = 'invoiced';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::CONFIRMED => 'Confirmado',
            self::IN_PROCESS => 'En proceso',
            self::INVOICED => 'Facturado',
            self::CANCELLED => 'Cancelado',
        };
    }
}
