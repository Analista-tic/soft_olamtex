<?php

namespace App\Models\Expenses;

use App\Contracts\HasAuditLabel;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseOrder;
use App\Models\Inventory\FabricRoll;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PurchaseInvoice extends PurchaseDocument implements HasAuditLabel
{
    public static function auditLabel(): string
    {
        return 'Factura de compra';
    }

    protected $attributes = [
        'document_type' => self::TYPE_INVOICE,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('invoice_type', function (Builder $builder) {
            $builder->where('document_type', self::TYPE_INVOICE);
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'parent_document_id');
    }

    public function getPurchaseOrderIdAttribute(): ?int
    {
        return $this->attributes['parent_document_id'] ?? null;
    }

    public function setPurchaseOrderIdAttribute($value): void
    {
        $this->attributes['parent_document_id'] = $value;
    }

    public function rolls(): HasManyThrough
    {
        return $this->hasManyThrough(
            FabricRoll::class,
            PurchaseDocumentItem::class,
            'purchase_document_id',
            'purchase_document_item_id',
            'id',
            'id'
        );
    }
}
