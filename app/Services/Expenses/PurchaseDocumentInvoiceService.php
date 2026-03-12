<?php

namespace App\Services\Expenses;

use App\Enums\PurchaseInvoiceStatus;
use App\Models\Expenses\PurchaseDocument;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseDocumentInvoiceService extends PurchaseDocumentService
{
    /**
     * Estado oficial para que una nota impacte saldo en CxP.
     */
    private const NOTE_BALANCE_IMPACT_STATUS = 'approved';

    public function paginateAccountsPayable(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $rawDebtStatus = trim((string) ($filters['debt_status'] ?? 'all'));
        $debtStatus = in_array($rawDebtStatus, ['all', 'pending', 'overdue', 'settled'], true)
            ? $rawDebtStatus
            : 'all';
        $includeSettled = filter_var($filters['include_settled'] ?? false, FILTER_VALIDATE_BOOLEAN) || $debtStatus === 'settled';
        $today = CarbonImmutable::today()->toDateString();

        $invoiceTotalSql = "(SELECT COALESCE(SUM(COALESCE(pdi.total, 0) + COALESCE(pdi.total_tax, 0)), 0)
            FROM purchase_document_items pdi
            WHERE pdi.purchase_document_id = purchase_documents.id)";

        $creditNotesTotalSql = "(SELECT COALESCE(SUM(COALESCE(pdni.total, 0) + COALESCE(pdni.total_tax, 0)), 0)
            FROM purchase_documents pdn
            INNER JOIN purchase_document_items pdni ON pdni.purchase_document_id = pdn.id
            WHERE pdn.parent_document_id = purchase_documents.id
              AND pdn.document_type = '" . PurchaseDocument::TYPE_CREDIT_NOTE . "'
              AND pdn.status = '" . self::NOTE_BALANCE_IMPACT_STATUS . "'
              AND pdn.deleted_at IS NULL)";

        $debitNotesTotalSql = "(SELECT COALESCE(SUM(COALESCE(pdni.total, 0) + COALESCE(pdni.total_tax, 0)), 0)
            FROM purchase_documents pdn
            INNER JOIN purchase_document_items pdni ON pdni.purchase_document_id = pdn.id
            WHERE pdn.parent_document_id = purchase_documents.id
              AND pdn.document_type = '" . PurchaseDocument::TYPE_DEBIT_NOTE . "'
              AND pdn.status = '" . self::NOTE_BALANCE_IMPACT_STATUS . "'
              AND pdn.deleted_at IS NULL)";
        $paymentsTotalSql = "(SELECT COALESCE(SUM(COALESCE(pp.amount, 0)), 0)
            FROM purchase_payments pp
            WHERE pp.purchase_invoice_id = purchase_documents.id)";
        $balanceSql = "(({$invoiceTotalSql}) + ({$debitNotesTotalSql}) - ({$creditNotesTotalSql}) - ({$paymentsTotalSql}))";
        $dueDateSql = "COALESCE(purchase_documents.delivery_date, purchase_documents.date)";

        $query = PurchaseDocument::query()
            ->with([
                'supplier:id,full_name,id_number,phone,email',
                'items:id,purchase_document_id,total,total_tax',
                'payments:id,purchase_invoice_id,amount',
                'children' => fn ($childQuery) => $childQuery
                    ->whereIn('document_type', [PurchaseDocument::TYPE_CREDIT_NOTE, PurchaseDocument::TYPE_DEBIT_NOTE])
                    ->with([
                        'items:id,purchase_document_id,total,total_tax',
                        'parent:id,document_type',
                    ]),
            ])
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->whereIn('status', [
                PurchaseInvoiceStatus::PENDING_RECEIPT->value,
                PurchaseInvoiceStatus::RECEIVED->value,
            ]);

        if (!$includeSettled) {
            $query->whereRaw("{$balanceSql} > 0");
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('document_number', 'like', "%{$search}%")
                    ->orWhere('supplier_invoice_number', 'like', "%{$search}%")
                    ->orWhere('provider_name', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplierQuery) =>
                        $supplierQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('id_number', 'like', "%{$search}%")
                    );
            });
        }

        $contactId = (int) ($filters['contact_id'] ?? ($filters['provider_id'] ?? 0));
        if ($contactId > 0) {
            $query->where('contact_id', $contactId);
        }

        if (!empty($filters['due_date_from'])) {
            $query->whereRaw("{$dueDateSql} >= ?", [$filters['due_date_from']]);
        }

        if (!empty($filters['due_date_to'])) {
            $query->whereRaw("{$dueDateSql} <= ?", [$filters['due_date_to']]);
        }

        if ($debtStatus === 'settled') {
            $query->whereRaw("{$balanceSql} <= 0");
        } elseif ($debtStatus === 'overdue') {
            $query->whereRaw("{$balanceSql} > 0")
                ->whereRaw("{$dueDateSql} < ?", [$today]);
        } elseif ($debtStatus === 'pending') {
            $query->whereRaw("{$balanceSql} > 0")
                ->whereRaw("{$dueDateSql} >= ?", [$today]);
        }

        $paginator = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(max(1, $perPage));

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (PurchaseDocument $invoice) => $this->mapAccountsPayableRow($invoice))
                ->values()
        );

        return $paginator;
    }

    public function getInvoiceNotesSummary(PurchaseDocument $invoice): array
    {
        $creditTotal = 0.0;
        $debitTotal = 0.0;

        $notes = $this->resolveInvoiceNotes($invoice);

        foreach ($notes as $note) {
            if (!$this->noteAffectsBalance($note, (int) $invoice->id)) {
                continue;
            }

            $noteTotal = $this->noteApplicableAmount($note);

            if ($note->document_type === PurchaseDocument::TYPE_DEBIT_NOTE) {
                $debitTotal += $noteTotal;
                continue;
            }

            $creditTotal += $noteTotal;
        }

        $invoiceTotal = $this->invoiceTotal($invoice);
        $netAdjustment = $debitTotal - $creditTotal;
        $balanceAfterNotes = $invoiceTotal + $netAdjustment;

        return [
            'credit_total' => round($creditTotal, 2),
            'debit_total' => round($debitTotal, 2),
            'net_adjustment' => round($netAdjustment, 2),
            'invoice_total' => round($invoiceTotal, 2),
            'balance_after_notes' => round($balanceAfterNotes, 2),
        ];
    }

    public function getLinkedCreditDebitNotes(PurchaseDocument $invoice): Collection
    {
        return $this->resolveInvoiceNotes($invoice)
            ->sortByDesc(fn (PurchaseDocument $note) => sprintf('%s#%010d', $note->date?->format('Y-m-d') ?? '0000-00-00', (int) $note->id))
            ->map(function (PurchaseDocument $note) {
                $applicableAmount = $this->noteApplicableAmount($note);
                $affectsBalance = $this->noteAffectsBalance($note, (int) ($note->parent_document_id ?? 0));
                $signedApplicableAmount = $note->document_type === PurchaseDocument::TYPE_CREDIT_NOTE
                    ? -$applicableAmount
                    : $applicableAmount;

                return [
                    'id' => $note->id,
                    'document_number' => $note->document_number,
                    'document_type' => $note->document_type,
                    'date' => $note->date?->format('Y-m-d'),
                    'reason' => $note->reason,
                    'status' => $note->status,
                    'total' => round($applicableAmount, 2),
                    'applicable_amount' => round($applicableAmount, 2),
                    'signed_applicable_amount' => round($signedApplicableAmount, 2),
                    'affects_balance' => $affectsBalance,
                ];
            })
            ->values();
    }

    public function createInvoice(array $data, array $items = [], ?array $withholdings = null): PurchaseDocument
    {
        $data = $this->normalizeInvoicePayload($data);

        return DB::transaction(function () use ($data, $items, $withholdings) {
            // Aceptar parent_document_id o purchase_order_id (frontend histórico)
            $parentId = $data['parent_document_id'] ?? $data['purchase_order_id'] ?? null;
            $parentId = $parentId ? (int) $parentId : null;
            
            // Validar que no exista ya una factura para esta orden
            if (!empty($parentId)) {
                $parentOrder = PurchaseDocument::find($parentId);
                if (!$parentOrder) {
                    throw new \RuntimeException('La orden de compra especificada no existe.');
                }
                
                if ($parentOrder->document_type !== PurchaseDocument::TYPE_ORDER) {
                    throw new \RuntimeException('El documento padre debe ser una orden de compra.');
                }
                
                // Verificar si ya existe una factura para esta orden
                $existingInvoice = PurchaseDocument::where('parent_document_id', $parentId)
                    ->where('document_type', PurchaseDocument::TYPE_INVOICE)
                    ->whereNull('deleted_at')
                    ->first();
                
                if ($existingInvoice) {
                    throw new \RuntimeException('Ya existe una factura de compra para esta orden. No se pueden crear múltiples facturas desde la misma orden.');
                }
                
                // Validar que la orden esté aprobada
                if (!$parentOrder->isApproved()) {
                    throw new \RuntimeException('Solo se pueden crear facturas desde órdenes aprobadas.');
                }
            }

            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateInvoiceNumber();
            }
            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_INVOICE);

            $invoice = PurchaseDocument::create([
                ...$base,
                'parent_document_id'        => $parentId,
                'status'                    => $data['status'] ?? 'pending_receipt',
                'approval_status'           => $data['approval_status'] ?? 'pending',
                'supplier_invoice_number'   => $data['supplier_invoice_number'] ?? null,
                'withholdings'              => $withholdings ?? $data['withholdings'] ?? [],
                'invoiced_at'               => $data['invoiced_at'] ?? now(),
                'rejection_reason'          => $data['rejection_reason'] ?? null,
                'rejection_evidence_image'  => $data['rejection_evidence_image'] ?? null,
                'rejection_notes'           => $data['rejection_notes'] ?? null,
                'rejected_by_user_id'       => $data['rejected_by_user_id'] ?? null,
                'rejected_at'               => $data['rejected_at'] ?? null,
            ]);

            $this->syncItems($invoice, $items);

            // Actualizar el estado de la orden a "invoiced" cuando se crea una factura
            if (!empty($parentId)) {
                $parentOrder = PurchaseDocument::find($parentId);
                if ($parentOrder && $parentOrder->document_type === PurchaseDocument::TYPE_ORDER) {
                    $parentOrder->update([
                        'status' => \App\Enums\PurchaseOrderStatus::INVOICED->value,
                    ]);
                }
            }

            return $this->loadWithRelations($invoice);
        });
    }

    protected function resolveInvoiceNotes(PurchaseDocument $invoice): Collection
    {
        if ($invoice->relationLoaded('children')) {
            $notes = $invoice->children
                ->filter(fn (PurchaseDocument $document) =>
                    in_array($document->document_type, [PurchaseDocument::TYPE_CREDIT_NOTE, PurchaseDocument::TYPE_DEBIT_NOTE], true)
                )
                ->values();

            if ($notes->isNotEmpty()) {
                $notes->loadMissing([
                    'items:id,purchase_document_id,total,total_tax',
                    'parent:id,document_type',
                ]);
            }

            return $notes;
        }

        return $invoice->children()
            ->whereIn('document_type', [PurchaseDocument::TYPE_CREDIT_NOTE, PurchaseDocument::TYPE_DEBIT_NOTE])
            ->with([
                'items:id,purchase_document_id,total,total_tax',
                'parent:id,document_type',
            ])
            ->get(['id', 'document_number', 'document_type', 'date', 'reason', 'status', 'parent_document_id']);
    }

    protected function noteAffectsBalance(PurchaseDocument $note, int $invoiceId): bool
    {
        if (!in_array($note->document_type, [PurchaseDocument::TYPE_CREDIT_NOTE, PurchaseDocument::TYPE_DEBIT_NOTE], true)) {
            return false;
        }

        if ($note->status !== self::NOTE_BALANCE_IMPACT_STATUS) {
            return false;
        }

        if ((int) ($note->parent_document_id ?? 0) !== $invoiceId) {
            return false;
        }

        if ($note->trashed()) {
            return false;
        }

        $parentType = $note->parent?->document_type;
        if ($parentType !== null && $parentType !== PurchaseDocument::TYPE_INVOICE) {
            return false;
        }

        return true;
    }

    protected function noteApplicableAmount(PurchaseDocument $note): float
    {
        if ($note->relationLoaded('items')) {
            $amount = (float) $note->items->sum(function ($item) {
                return ((float) ($item->total ?? 0)) + ((float) ($item->total_tax ?? 0));
            });

            return round($amount, 2);
        }

        $amount = (float) $note->items()->sum(DB::raw('COALESCE(total, 0) + COALESCE(total_tax, 0)'));
        return round($amount, 2);
    }

    protected function invoiceTotal(PurchaseDocument $invoice): float
    {
        if ($invoice->relationLoaded('items')) {
            $amount = (float) $invoice->items->sum(function ($item) {
                return ((float) ($item->total ?? 0)) + ((float) ($item->total_tax ?? 0));
            });

            return round($amount, 2);
        }

        $amount = (float) $invoice->items()->sum(DB::raw('COALESCE(total, 0) + COALESCE(total_tax, 0)'));
        return round($amount, 2);
    }

    protected function mapAccountsPayableRow(PurchaseDocument $invoice): array
    {
        $summary = $this->getInvoiceNotesSummary($invoice);
        $paymentsTotal = $this->invoicePaymentsTotal($invoice);
        $currentBalance = round(((float) ($summary['balance_after_notes'] ?? 0)) - $paymentsTotal, 2);
        $dueDate = $invoice->delivery_date?->format('Y-m-d') ?? $invoice->date?->format('Y-m-d');
        $debtStatus = $this->resolveDebtStatus($currentBalance, $dueDate);

        return [
            'id' => $invoice->id,
            'invoice_id' => $invoice->id,
            'document_number' => $invoice->document_number,
            'supplier_name' => $invoice->supplier?->full_name ?? $invoice->provider_name ?? 'Sin proveedor',
            'contact_id' => $invoice->contact_id,
            'invoice_date' => $invoice->date?->format('Y-m-d'),
            'due_date' => $dueDate,
            'invoice_total' => (float) ($summary['invoice_total'] ?? 0),
            'credit_notes_total' => (float) ($summary['credit_total'] ?? 0),
            'debit_notes_total' => (float) ($summary['debit_total'] ?? 0),
            'payments_total' => $paymentsTotal,
            'current_balance' => $currentBalance,
            'debt_status' => $debtStatus['code'],
            'debt_status_label' => $debtStatus['label'],
            'debt_status_tone' => $debtStatus['tone'],
            'invoice_status' => $invoice->status,
            'invoice_status_label' => $this->resolveInvoiceStatusLabel($invoice->status),
        ];
    }

    protected function invoicePaymentsTotal(PurchaseDocument $invoice): float
    {
        if ($invoice->relationLoaded('payments')) {
            $amount = (float) $invoice->payments->sum(function ($payment) {
                return (float) ($payment->amount ?? 0);
            });

            return round($amount, 2);
        }

        $amount = (float) $invoice->payments()->sum(DB::raw('COALESCE(amount, 0)'));
        return round($amount, 2);
    }

    protected function resolveDebtStatus(float $balance, ?string $dueDate): array
    {
        if ($balance <= 0) {
            return [
                'code' => 'settled',
                'label' => 'Saldada',
                'tone' => 'green',
            ];
        }

        if ($dueDate !== null) {
            try {
                $due = CarbonImmutable::parse($dueDate)->startOfDay();
                if ($due->lt(CarbonImmutable::today())) {
                    return [
                        'code' => 'overdue',
                        'label' => 'Vencida',
                        'tone' => 'red',
                    ];
                }
            } catch (\Throwable) {
                // Si la fecha no es parseable, se mantiene como pendiente sin bloquear CxP.
            }
        }

        return [
            'code' => 'pending',
            'label' => 'Pendiente',
            'tone' => 'yellow',
        ];
    }

    protected function resolveInvoiceStatusLabel(?string $status): string
    {
        if (!is_string($status) || trim($status) === '') {
            return 'Sin estado';
        }

        $enum = PurchaseInvoiceStatus::tryFrom($status);
        return $enum?->label() ?? $status;
    }
}
