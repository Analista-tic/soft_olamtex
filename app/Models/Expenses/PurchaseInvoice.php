<?php

namespace App\Models\Expenses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Common\Contact;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseOrder;

class PurchaseInvoice extends Model implements \App\Contracts\HasAuditLabel
{
    use HasFactory, SoftDeletes;

    public static function auditLabel(): string
    {
        return 'Factura de compra';
    }

    protected $table = 'purchase_documents';

    protected $attributes = [
        'document_type' => PurchaseDocument::TYPE_INVOICE,
    ];

    protected $fillable = [
        'document_number',
        'document_type',
        'parent_document_id',
        'contact_id',
        'provider_name',
        'provider_phone',
        'date',
        'delivery_date',
        'reason',
        'description',
        'status',
        'approval_status',
        'supplier_order_number',
        'supplier_invoice_number',
        'withholdings',
        'rejection_reason',
        'rejection_evidence_image',
        'rejection_notes',
        'rejected_by_user_id',
        'rejected_at',
        'requested_at',
        'ordered_at',
        'invoiced_at',
        'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'delivery_date' => 'date',
        'requested_at' => 'datetime',
        'ordered_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'withholdings' => 'array',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseDocumentItem::class, 'purchase_document_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function getPurchaseOrderIdAttribute(): ?int
    {
        return $this->attributes['parent_document_id'] ?? null;
    }

    public function setPurchaseOrderIdAttribute($value): void
    {
        $this->attributes['parent_document_id'] = $value;
    }
}
