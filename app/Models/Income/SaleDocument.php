<?php

namespace App\Models\Income;

use App\Contracts\HasAuditLabel;
use App\Enums\Income\QuoteStatus;
use App\Enums\Income\SaleNoteStatus;
use App\Enums\Income\SalesInvoiceStatus;
use App\Enums\Income\SalesOrderStatus;
use App\Models\MasterData\Contact;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class SaleDocument extends Model implements HasAuditLabel
{
    use HasFactory, SoftDeletes;

    public const TYPE_QUOTE = 'quote';
    public const TYPE_SALES_ORDER = 'sales_order';
    public const TYPE_INVOICE = 'sales_invoice';
    public const TYPE_CREDIT_NOTE = 'sale_credit_note';
    public const TYPE_DEBIT_NOTE = 'sale_debit_note';
    public const TYPE_CASH_RECEIPT = 'cash_receipt';

    protected $table = 'sale_documents';

    public array $auditIgnore = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'document_number',
        'document_type',
        'parent_document_id',
        'quote_id',
        'contact_id',
        'date',
        'due_date',
        'delivery_date',
        'description',
        'payment_terms',
        'payment_days',
        'payment_status',
        'amount_paid',
        'customer_order_number',
        'status',
        'withholdings',
        'invoiced_at',
        'created_by_user_id',
        'executed_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'delivery_date' => 'date',
        'payment_days' => 'integer',
        'amount_paid' => 'decimal:2',
        'withholdings' => 'array',
        'invoiced_at' => 'datetime',
    ];

    public static function auditLabel(): string
    {
        return 'Documento de venta';
    }

    public static function formatDocumentNumber(string $prefix, int $number): string
    {
        return $prefix . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    public function items()
    {
        return $this->hasMany(SaleDocumentItem::class, 'sale_document_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_document_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_document_id');
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class, 'sale_invoice_id');
    }

    public function quote()
    {
        return $this->belongsTo(self::class, 'quote_id');
    }

    public function client()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function contact()
    {
        return $this->client();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function executor()
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    public function scopeQuotes($query)
    {
        return $query->where('document_type', self::TYPE_QUOTE);
    }

    public function scopeSalesOrders($query)
    {
        return $query->where('document_type', self::TYPE_SALES_ORDER);
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

    public function scopeCashReceipts($query)
    {
        return $query->where('document_type', self::TYPE_CASH_RECEIPT);
    }

    public function isQuote(): bool
    {
        return $this->document_type === self::TYPE_QUOTE;
    }

    public function isQuoteDraft(): bool
    {
        return $this->isQuote() && $this->status === QuoteStatus::DRAFT->value;
    }

    public function isQuoteSent(): bool
    {
        return $this->isQuote() && $this->status === QuoteStatus::SENT->value;
    }

    public function isQuoteAccepted(): bool
    {
        return $this->isQuote() && $this->status === QuoteStatus::ACCEPTED->value;
    }

    public function isQuoteRejected(): bool
    {
        return $this->isQuote() && $this->status === QuoteStatus::REJECTED->value;
    }

    public function isQuoteExpired(): bool
    {
        return $this->isQuote() && $this->status === QuoteStatus::EXPIRED->value;
    }

    public function isQuoteConverted(): bool
    {
        return $this->isQuote() && $this->status === QuoteStatus::CONVERTED->value;
    }

    public function canEditQuote(): bool
    {
        return $this->isQuote() && !in_array($this->status, [QuoteStatus::ACCEPTED->value, QuoteStatus::CONVERTED->value], true);
    }

    public function canDeleteQuote(): bool
    {
        return $this->isQuote() && $this->status === QuoteStatus::DRAFT->value;
    }

    public function canConvertQuote(): bool
    {
        return $this->isQuote() && in_array($this->status, [QuoteStatus::SENT->value, QuoteStatus::ACCEPTED->value], true);
    }

    public function isSalesOrder(): bool
    {
        return $this->document_type === self::TYPE_SALES_ORDER;
    }

    public function isSalesOrderDraft(): bool
    {
        return $this->isSalesOrder() && $this->status === SalesOrderStatus::DRAFT->value;
    }

    public function isSalesOrderConfirmed(): bool
    {
        return $this->isSalesOrder() && $this->status === SalesOrderStatus::CONFIRMED->value;
    }

    public function isSalesOrderInProcess(): bool
    {
        return $this->isSalesOrder() && $this->status === SalesOrderStatus::IN_PROCESS->value;
    }

    public function isSalesOrderInvoiced(): bool
    {
        return $this->isSalesOrder() && $this->status === SalesOrderStatus::INVOICED->value;
    }

    public function isSalesOrderCancelled(): bool
    {
        return $this->isSalesOrder() && $this->status === SalesOrderStatus::CANCELLED->value;
    }

    public function canEditSalesOrder(): bool
    {
        return $this->isSalesOrder() && in_array($this->status, [SalesOrderStatus::DRAFT->value, SalesOrderStatus::CONFIRMED->value], true);
    }

    public function canDeleteSalesOrder(): bool
    {
        return $this->isSalesOrder() && $this->status === SalesOrderStatus::DRAFT->value;
    }

    public function canConvertSalesOrder(): bool
    {
        return $this->isSalesOrder() && in_array($this->status, [SalesOrderStatus::CONFIRMED->value, SalesOrderStatus::IN_PROCESS->value], true);
    }

    public function isInvoice(): bool
    {
        return $this->document_type === self::TYPE_INVOICE;
    }

    public function isInvoiceDraft(): bool
    {
        return $this->isInvoice() && $this->status === SalesInvoiceStatus::DRAFT->value;
    }

    public function isInvoiceIssued(): bool
    {
        return $this->isInvoice() && $this->status === SalesInvoiceStatus::ISSUED->value;
    }

    public function isInvoicePartial(): bool
    {
        return $this->isInvoice() && $this->status === SalesInvoiceStatus::PARTIAL->value;
    }

    public function isInvoicePaid(): bool
    {
        return $this->isInvoice() && $this->status === SalesInvoiceStatus::PAID->value;
    }

    public function isInvoiceVoided(): bool
    {
        return $this->isInvoice() && $this->status === SalesInvoiceStatus::VOIDED->value;
    }

    public function canEditInvoice(): bool
    {
        return $this->isInvoice() && $this->status === SalesInvoiceStatus::DRAFT->value;
    }

    public function canDeleteInvoice(): bool
    {
        return $this->isInvoice() && $this->status === SalesInvoiceStatus::DRAFT->value;
    }

    public function canEmitInvoice(): bool
    {
        return $this->isInvoice() && $this->status === SalesInvoiceStatus::DRAFT->value;
    }

    public function canVoidInvoice(): bool
    {
        return $this->isInvoice() && in_array($this->status, [SalesInvoiceStatus::ISSUED->value, SalesInvoiceStatus::PARTIAL->value], true);
    }

    public function isCreditNote(): bool
    {
        return $this->document_type === self::TYPE_CREDIT_NOTE;
    }

    public function isDebitNote(): bool
    {
        return $this->document_type === self::TYPE_DEBIT_NOTE;
    }

    public function isCreditDebitNote(): bool
    {
        return $this->isCreditNote() || $this->isDebitNote();
    }

    public function isNoteDraft(): bool
    {
        return $this->isCreditDebitNote() && $this->status === SaleNoteStatus::DRAFT->value;
    }

    public function isNoteIssued(): bool
    {
        return $this->isCreditDebitNote() && $this->status === SaleNoteStatus::ISSUED->value;
    }

    public function isNoteVoided(): bool
    {
        return $this->isCreditDebitNote() && $this->status === SaleNoteStatus::VOIDED->value;
    }

    public function canEditNote(): bool
    {
        return $this->isCreditDebitNote() && $this->status === SaleNoteStatus::DRAFT->value;
    }

    public function canDeleteNote(): bool
    {
        return $this->isCreditDebitNote() && $this->status === SaleNoteStatus::DRAFT->value;
    }

    public function canEmitNote(): bool
    {
        return $this->isCreditDebitNote() && $this->status === SaleNoteStatus::DRAFT->value;
    }

    public function canVoidNote(): bool
    {
        return $this->isCreditDebitNote() && $this->status === SaleNoteStatus::ISSUED->value;
    }

    public function totalNet(): float
    {
        /** @var Collection<int, SaleDocumentItem> $items */
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $total = '0.00';

        foreach ($items as $item) {
            $quantity = number_format(max((float) ($item->quantity ?? 0), 0), 3, '.', '');
            $price = number_format(max((float) ($item->price ?? 0), 0), 2, '.', '');
            $lineBase = bcmul($quantity, $price, 2);

            $discountPercentage = number_format(
                min(max((float) ($item->discount ?? 0), 0), 100),
                2,
                '.',
                ''
            );
            $discountAmount = bcmul($lineBase, bcdiv($discountPercentage, '100', 4), 2);
            $taxAmount = number_format(max((float) ($item->total_tax ?? 0), 0), 2, '.', '');

            $lineNet = bcadd(
                bcsub($lineBase, $discountAmount, 2),
                $taxAmount,
                2
            );

            $total = bcadd($total, $lineNet, 2);
        }

        return (float) $total;
    }

    public function isCashReceipt(): bool
    {
        return $this->document_type === self::TYPE_CASH_RECEIPT;
    }
}
