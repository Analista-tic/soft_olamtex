<?php

namespace App\Services\Finance;

use App\Models\Finance\AccountingAccount;
use App\Models\MasterData\Contact;
use App\Services\Expenses\PurchaseDocumentInvoiceService;
use App\Services\Settings\SettingService;

class TreasuryVoucherService
{
    public function __construct(
        private PurchaseDocumentInvoiceService $purchaseInvoiceService,
        private SettingService $settingService,
    ) {
    }

    public function searchSuppliers(string $search = '', int $limit = 30): array
    {
        $query = Contact::query()
            ->select(['id', 'full_name', 'id_number'])
            ->orderBy('full_name');

        $term = trim($search);
        if ($term !== '') {
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('full_name', 'like', "%{$term}%")
                    ->orWhere('id_number', 'like', "%{$term}%");
            });
        }

        return $query
            ->limit(max(1, min($limit, 200)))
            ->get()
            ->map(fn (Contact $contact) => [
                'id' => (int) $contact->id,
                'full_name' => $contact->full_name,
                'id_number' => $contact->id_number,
                'party_type' => 'supplier',
            ])
            ->values()
            ->all();
    }

    public function searchCustomers(string $search = '', int $limit = 30): array
    {
        $query = Contact::query()
            ->select(['id', 'full_name', 'id_number'])
            ->orderBy('full_name');

        $term = trim($search);
        if ($term !== '') {
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('full_name', 'like', "%{$term}%")
                    ->orWhere('id_number', 'like', "%{$term}%");
            });
        }

        return $query
            ->limit(max(1, min($limit, 200)))
            ->get()
            ->map(fn (Contact $contact) => [
                'id' => (int) $contact->id,
                'full_name' => $contact->full_name,
                'id_number' => $contact->id_number,
                'party_type' => 'customer',
            ])
            ->values()
            ->all();
    }

    public function getPendingPayableDocuments(int $contactId, int $perPage = 200): array
    {
        if ($contactId <= 0) {
            return [];
        }

        $paginator = $this->purchaseInvoiceService->paginateAccountsPayable([
            'provider_id' => $contactId,
            'include_settled' => false,
        ], max(1, min($perPage, 500)));

        return collect($paginator->items())
            ->map(function ($row) {
                $data = is_array($row) ? $row : (array) $row;

                return [
                    'id' => (int) ($data['invoice_id'] ?? $data['id'] ?? 0),
                    'document_number' => $data['document_number'] ?? null,
                    'invoice_date' => $data['invoice_date'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'invoice_total' => (float) ($data['invoice_total'] ?? 0),
                    'withholdings_total' => (float) ($data['withholdings_total'] ?? 0),
                    'credit_notes_total' => (float) ($data['credit_notes_total'] ?? 0),
                    'debit_notes_total' => (float) ($data['debit_notes_total'] ?? 0),
                    'payments_total' => (float) ($data['payments_total'] ?? 0),
                    'current_balance' => (float) ($data['current_balance'] ?? 0),
                    'disputed_amount' => (float) ($data['disputed_amount'] ?? 0),
                    'disputed_rolls_count' => (int) ($data['disputed_rolls_count'] ?? 0),
                    'payable_balance' => (float) ($data['payable_balance'] ?? $data['current_balance'] ?? 0),
                    'debt_status' => $data['debt_status'] ?? null,
                    'debt_status_label' => $data['debt_status_label'] ?? null,
                ];
            })
            ->filter(fn (array $row) => (int) $row['id'] > 0)
            ->values()
            ->all();
    }

    public function getPendingReceivableDocuments(int $contactId, int $perPage = 200): array
    {
        // Fase 1: base preparada para Recibo de caja.
        // Se completará con origen real de cuentas por cobrar en Fase 3.
        return [];
    }

    public function getAccountOptions(): array
    {
        // Obtener cuentas de caja y banco activas para selección en comprobantes
        return AccountingAccount::query()
            ->where('is_active', true)
            ->where('allows_entries', true)
            ->whereIn('special_type', [
                AccountingAccount::SPECIAL_CASH,
                AccountingAccount::SPECIAL_BANK,
            ])
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (AccountingAccount $acc) => [
                'id' => $acc->id,
                'name' => "{$acc->code} - {$acc->name}",
            ])
            ->values()
            ->all();
    }

    public function getPaymentMethodOptions(): array
    {
        $options = $this->settingService
            ->getCatalogOptions('payment_methods')
            ->map(static fn (array $item) => [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
            ])
            ->filter(static fn (array $item) => $item['id'] !== '' && $item['name'] !== '')
            ->values()
            ->all();

        if (!empty($options)) {
            return $options;
        }

        return [
            ['id' => 'cash', 'name' => 'Efectivo'],
            ['id' => 'bank_deposit', 'name' => 'Consignacion bancaria'],
            ['id' => 'bank_transfer', 'name' => 'Transferencia bancaria'],
            ['id' => 'check', 'name' => 'Cheque'],
            ['id' => 'credit_card', 'name' => 'Tarjeta credito'],
            ['id' => 'debit_card', 'name' => 'Tarjeta debito'],
        ];
    }
}
