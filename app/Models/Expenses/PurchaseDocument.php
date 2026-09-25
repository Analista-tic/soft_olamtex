<?php

namespace App\Models\Expenses;

use App\Contracts\HasAuditLabel;
use App\Models\MasterData\Contact;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseDocument extends Model implements HasAuditLabel
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_documents';

    public array $auditIgnore = ['created_at', 'updated_at', 'deleted_at'];

    public static function auditLabel(): string
    {
        return 'Documento de compra';
    }

    protected $fillable = [
        'document_number',
        'document_type',
        'parent_document_id',
        'purchase_request_id',
        'contact_id',
        'provider_name',
        'provider_phone',
        'date',
        'delivery_date',
        'description',
        'created_by_user_id',
        'executed_by_user_id',
        'buyer_user_id',
        'status',
        'approval_status',
        'approval_notes',
        'has_difference',
        'difference_status',
        'closure_reason',
        'supplier_order_number',
        'supplier_invoice_number',
        'withholdings',
        'rejection_reason',
        'rejection_evidence_image',
        'rejection_notes',
        'rejected_by_user_id',
        'rejected_at',
        'approved_by_user_id',
        'approved_at',
        'sent_at',
        'requested_at',
        'ordered_at',
        'invoiced_at',
        'received_at',
    ];

    protected $casts = [
        'received_at'   => 'datetime',
        'date'          => 'date',
        'delivery_date' => 'date',

        'requested_at'  => 'datetime',
        'ordered_at'    => 'datetime',
        'invoiced_at'   => 'datetime',
        'approved_at'   => 'datetime',
        'rejected_at'   => 'datetime',
        'sent_at'       => 'datetime',

        'withholdings'  => 'array',
        'has_difference' => 'boolean',
    ];

    public const TYPE_REQUEST = 'purchase_request';
    public const TYPE_ORDER   = 'purchase_order';
    public const TYPE_INVOICE = 'purchase_invoice';
    public const TYPE_CREDIT_NOTE = 'purchase_credit_note';
    public const TYPE_DEBIT_NOTE  = 'purchase_debit_note';

    public const APPROVAL_PENDING            = 'pending';
    public const APPROVAL_PENDING_DIFFERENCE  = 'pending_difference_approval';
    public const APPROVAL_DIFFERENCE_REJECTED = 'difference_rejected';
    public const APPROVAL_APPROVED            = 'approved';

    // Detalles (ítems) del documento
    public function items()
    {
        // foreign key explícita porque en la tabla se llama purchase_document_id
        return $this->hasMany(PurchaseDocumentItem::class, 'purchase_document_id');
    }

    // Documento padre (Solicitud -> Orden -> Factura)
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_document_id');
    }

    // Documentos hijos (desde una solicitud, ver su orden; desde una orden, ver sus facturas)
    public function children()
    {
        return $this->hasMany(self::class, 'parent_document_id');
    }

    // Pagos aplicados a la factura
    public function payments()
    {
        return $this->hasMany(PurchasePayment::class, 'purchase_invoice_id');
    }

    // Solicitud de compra relacionada (para órdenes creadas desde solicitudes)
    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    // Proveedor
    public function supplier()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    // Alias para mantener compatibilidad con vistas que esperan `contact`
    public function contact()
    {
        return $this->supplier();
    }

    // Usuario que creó el documento
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Usuario que ejecutó / aprobó / procesó
    public function executor()
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    // Encargado de compra (buyer asignado desde la solicitud)
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    // Usuario que rechazó (si aplica)
    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    // Usuario que aprobó (si aplica)
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isRequest(): bool
    {
        return $this->document_type === self::TYPE_REQUEST;
    }

    public function isOrder(): bool
    {
        return $this->document_type === self::TYPE_ORDER;
    }

    public function isInvoice(): bool
    {
        return $this->document_type === self::TYPE_INVOICE;
    }

    public function isApproved(): bool
    {
        if ($this->isOrder()) {
            return $this->status === \App\Enums\PurchaseOrderStatus::APPROVED->value;
        }
        return false;
    }

    public function scopeRequests($query)
    {
        return $query->where('document_type', self::TYPE_REQUEST);
    }

    public function scopeOrders($query)
    {
        return $query->where('document_type', self::TYPE_ORDER);
    }

    public function scopeInvoices($query)
    {
        return $query->where('document_type', self::TYPE_INVOICE);
    }

    public function scopeCreditNotes($query)
    {
        return $query->where('document_type', self::TYPE_CREDIT_NOTE);
    }

    public function scopeDebitNotes($query)
    {
        return $query->where('document_type', self::TYPE_DEBIT_NOTE);
    }

    // Métodos de estado para órdenes
    public function isOrderPending(): bool
    {
        return $this->isOrder() && $this->status === 'pending';
    }

    public function isOrderApproved(): bool
    {
        return $this->isOrder() && $this->status === 'approved';
    }

    public function isOrderRejected(): bool
    {
        return $this->isOrder() && $this->status === 'rejected';
    }

    public function isOrderInvoiced(): bool
    {
        return $this->isOrder() && $this->status === 'invoiced';
    }

    public function canEditOrder(): bool
    {
        return $this->isOrder() && $this->status === 'pending';
    }

    public function canApproveOrder(): bool
    {
        return $this->isOrder() && $this->status === 'pending';
    }

    public function canApprove(): bool
    {
        if ($this->isOrder()) {
            return $this->status === \App\Enums\PurchaseOrderStatus::PENDING->value;
        }
        return false;
    }

    public function canRejectOrder(): bool
    {
        return $this->isOrder() && $this->status === 'pending';
    }

    public function canInvoiceOrder(): bool
    {
        return $this->isOrder() && in_array($this->status, ['approved', 'partially_invoiced']);
    }

    // Métodos de estado para facturas
    public function isInvoicePendingReceipt(): bool
    {
        return $this->isInvoice() && $this->status === 'pending_receipt';
    }

    public function isInvoiceReceived(): bool
    {
        return $this->isInvoice() && $this->status === 'received';
    }

    public function isInvoiceRejected(): bool
    {
        return $this->isInvoice() && $this->status === 'rejected';
    }

    public function canEditInvoice(): bool
    {
        return $this->isInvoice() && $this->status === 'pending_receipt';
    }

    public function canRejectInvoice(): bool
    {
        return $this->isInvoice() && $this->status === 'pending_receipt';
    }
}
