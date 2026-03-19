<?php

namespace App\Enums\Income;

enum QuoteStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case CONVERTED = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::SENT => 'Enviada',
            self::ACCEPTED => 'Aceptada',
            self::REJECTED => 'Rechazada',
            self::EXPIRED => 'Vencida',
            self::CONVERTED => 'Convertida',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'border-slate-200 bg-slate-100 text-slate-700',
            self::SENT => 'border-blue-200 bg-blue-100 text-blue-700',
            self::ACCEPTED => 'border-emerald-200 bg-emerald-100 text-emerald-700',
            self::REJECTED => 'border-rose-200 bg-rose-100 text-rose-700',
            self::EXPIRED => 'border-orange-200 bg-orange-100 text-orange-700',
            self::CONVERTED => 'border-violet-200 bg-violet-100 text-violet-700',
        };
    }
}
