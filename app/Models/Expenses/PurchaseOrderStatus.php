<?php

namespace App\Models\Expenses;

use App\Enums\PurchaseOrderStatus as PurchaseOrderStatusEnum;

/**
 * @deprecated Use App\Enums\PurchaseOrderStatus instead.
 *
 * Compatibility shim kept for legacy imports under App\Models\Expenses.
 */
final class PurchaseOrderStatus
{
    public const PENDING = PurchaseOrderStatusEnum::PENDING->value;
    public const APPROVED = PurchaseOrderStatusEnum::APPROVED->value;
    public const INVOICED = PurchaseOrderStatusEnum::INVOICED->value;
    public const REJECTED = PurchaseOrderStatusEnum::REJECTED->value;

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return PurchaseOrderStatusEnum::values();
    }
}
