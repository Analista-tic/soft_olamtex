<?php

namespace App\Enums;

enum PurchaseRequestStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PROCESSED = 'processed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Borrador',
            self::PENDING => 'Pendiente',
            self::APPROVED => 'Aprobada',
            self::PROCESSED => 'Procesada',
            self::REJECTED => 'Rechazada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING]);
    }

    public function canApprove(): bool
    {
        return $this === self::PENDING;
    }

    public function canReject(): bool
    {
        return $this === self::PENDING;
    }

    public function canSendToApproval(): bool
    {
        return $this === self::DRAFT;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::APPROVED, self::PROCESSED, self::REJECTED, self::CANCELLED]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
