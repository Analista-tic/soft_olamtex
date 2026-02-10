<?php

namespace App\Enums;

enum PurchaseInvoiceStatus: string
{
    case PENDING_RECEIPT = 'pending_receipt';
    case RECEIVED = 'received';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::PENDING_RECEIPT => 'Pendiente de recepción',
            self::RECEIVED => 'Recibida',
            self::REJECTED => 'Rechazada',
        };
    }

    public function canEdit(): bool
    {
        return $this === self::PENDING_RECEIPT;
    }

    public function canReject(): bool
    {
        return $this === self::PENDING_RECEIPT;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::RECEIVED, self::REJECTED]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
