<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case INVOICED = 'invoiced';
    case PARTIALLY_INVOICED = 'partially_invoiced';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pendiente',
            self::APPROVED => 'Aprobada',
            self::INVOICED => 'Facturada',
            self::PARTIALLY_INVOICED => 'Parcialmente facturada',
            self::REJECTED => 'Rechazada',
        };
    }

    public function canEdit(): bool
    {
        return $this === self::PENDING;
    }

    public function canApprove(): bool
    {
        return $this === self::PENDING;
    }

    public function canReject(): bool
    {
        return $this === self::PENDING;
    }

    public function canInvoice(): bool
    {
        return $this === self::APPROVED;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::INVOICED, self::PARTIALLY_INVOICED, self::REJECTED]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
