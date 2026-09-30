import { computed, ref, watch } from 'vue';
import {
    fetchTreasuryAccounts,
    fetchTreasuryPaymentMethods,
    type TreasuryDirection,
    type TreasuryOpenDocument,
    type TreasuryPartyType,
    type TreasurySelectOption,
    type TreasuryThirdParty,
    type TreasuryVoucherDraft,
} from '@/domain/Finance';
import { useTreasuryVoucher } from './useTreasuryVoucher';

type PartyLoader = (search?: string, limit?: number) => Promise<TreasuryThirdParty[]>;
type DocumentsLoader = (contactId: number) => Promise<TreasuryOpenDocument[]>;

type TreasuryVoucherPageOptions = {
    direction: TreasuryDirection;
    partyType: TreasuryPartyType;
    loadParties: PartyLoader;
    loadDocuments: DocumentsLoader;
    initialDate?: string;
    onLoadDocumentsError?: (error: unknown) => void;
};

function todayYmd(): string {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export function useTreasuryVoucherPage(options: TreasuryVoucherPageOptions) {
    const loadingParties = ref(false);
    const loadingDocuments = ref(false);
    const submitting = ref(false);

    const partyOptions = ref<TreasurySelectOption[]>([]);
    const accountOptions = ref<TreasurySelectOption[]>([]);
    const paymentMethodOptions = ref<TreasurySelectOption[]>([]);
    const currencyOptions = ref<TreasurySelectOption[]>([
        { id: 'COP', name: 'COP - Colombia Peso' },
    ]);
    const voucherTypeOptions = ref<TreasurySelectOption[]>(
        options.direction === 'outflow'
            ? [{ id: 'supplier_invoice_payment', name: 'Pago a factura de proveedor' }]
            : [{ id: 'customer_invoice_payment', name: 'Pago a factura de cliente' }],
    );
    const documents = ref<TreasuryOpenDocument[]>([]);
    const applicationAmounts = ref<Record<number, string>>({});

    const form = ref<TreasuryVoucherDraft>({
        direction: options.direction,
        party_type: options.partyType,
        contact_id: null,
        date: options.initialDate || todayYmd(),
        account_id: null,
        payment_method: null,
        currency: 'COP',
        voucher_type: options.direction === 'outflow' ? 'supplier_invoice_payment' : 'customer_invoice_payment',
        reference: '',
        notes: '',
        applications: [],
    });

    const { applications, summary, applicationErrors, hasApplicationErrors } = useTreasuryVoucher(
        documents,
        applicationAmounts,
    );

    const baseCanSubmit = computed(() => {
        return (
            !submitting.value &&
            !loadingDocuments.value &&
            !!form.value.contact_id &&
            !!form.value.date &&
            !hasApplicationErrors.value &&
            applications.value.length > 0 &&
            applications.value.every((line) => Number(line.amount) > 0)
        );
    });

    async function loadInitialLookups(): Promise<void> {
        loadingParties.value = true;
        try {
            const [parties, accounts, methods] = await Promise.all([
                options.loadParties('', 150),
                fetchTreasuryAccounts(),
                fetchTreasuryPaymentMethods(),
            ]);

            partyOptions.value = parties.map((row) => ({ id: row.id, name: row.name }));
            accountOptions.value = accounts;
            paymentMethodOptions.value = methods;
        } finally {
            loadingParties.value = false;
        }
    }

    async function loadPendingDocuments(): Promise<void> {
        if (!form.value.contact_id) {
            documents.value = [];
            applicationAmounts.value = {};
            form.value.applications = [];
            return;
        }

        loadingDocuments.value = true;
        try {
            const rows = await options.loadDocuments(form.value.contact_id);
            documents.value = rows;
            const seeded: Record<number, string> = {};
            rows.forEach((doc) => {
                seeded[doc.id] = '';
            });
            applicationAmounts.value = seeded;
            form.value.applications = [...applications.value];
        } finally {
            loadingDocuments.value = false;
        }
    }

    function updateApplicationAmount(documentId: number, amount: string): void {
        applicationAmounts.value = {
            ...applicationAmounts.value,
            [documentId]: amount,
        };
        form.value.applications = [...applications.value];
    }

    function applyAllApplicationAmounts(): void {
        const next: Record<number, string> = {};

        documents.value.forEach((doc) => {
            next[doc.id] = String(Number(doc.payable_balance ?? doc.pending_balance ?? 0));
        });

        applicationAmounts.value = next;
        form.value.applications = [...applications.value];
    }

    function clearApplicationAmounts(): void {
        const next: Record<number, string> = {};

        documents.value.forEach((doc) => {
            next[doc.id] = '';
        });

        applicationAmounts.value = next;
        form.value.applications = [];
    }

    watch(
        () => form.value.contact_id,
        async () => {
            applicationAmounts.value = {};
            form.value.applications = [];
            try {
                await loadPendingDocuments();
            } catch (error) {
                options.onLoadDocumentsError?.(error);
            }
        },
    );

    return {
        loadingParties,
        loadingDocuments,
        submitting,
        partyOptions,
        accountOptions,
        paymentMethodOptions,
        currencyOptions,
        voucherTypeOptions,
        documents,
        applicationAmounts,
        form,
        applications,
        summary,
        applicationErrors,
        hasApplicationErrors,
        baseCanSubmit,
        loadInitialLookups,
        loadPendingDocuments,
        updateApplicationAmount,
        applyAllApplicationAmounts,
        clearApplicationAmounts,
    };
}
