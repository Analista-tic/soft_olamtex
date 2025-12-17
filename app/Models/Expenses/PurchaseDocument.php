<?php

namespace App\Models\Expenses;

use App\Contracts\HasAuditLabel;
use App\Models\Common\Contact;
use App\Models\User;
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
        'contact_id',
        'date',
        'delivery_date',
        'description',
        'created_by_user_id',
        'executed_by_user_id',
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
        'date'          => 'date',
        'delivery_date' => 'date',

        'requested_at'  => 'datetime',
        'ordered_at'    => 'datetime',
        'invoiced_at'   => 'datetime',
        'approved_at'   => 'datetime',
        'rejected_at'   => 'datetime',

        'withholdings'  => 'array',
    ];

    public const TYPE_REQUEST = 'purchase_request';
    public const TYPE_ORDER   = 'purchase_order';
    public const TYPE_INVOICE = 'purchase_invoice';

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

    // Usuario que rechazó (si aplica)
    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
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
}
