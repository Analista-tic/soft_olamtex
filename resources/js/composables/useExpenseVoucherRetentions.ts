import { computed, ref, type Ref } from 'vue';
import type { TreasuryApplicationLine, TreasuryOpenDocument } from '@/domain/Finance';

type UseExpenseVoucherRetentionsOptions = {
    documents: Ref<TreasuryOpenDocument[]>;
    applicationAmounts: Ref<Record<number, string>>;
    applicationErrors: Ref<Record<number, string | null>>;
    applications: Ref<TreasuryApplicationLine[]>;
    updateApplicationAmount: (documentId: number, amount: string) => void;
};

type NumberByDocument = Record<number, number>;
type StringByDocument = Record<number, string>;
type ErrorByDocument = Record<number, string | null>;

function sanitizeNonNegativeAmount(value: unknown): number {
    const parsed = Number(value ?? 0);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return 0;
    }

    return Math.trunc(parsed);
}

export function parseIntegerAmountInput(raw: string): number | null {
    const cleaned = raw.trim();
    if (cleaned === '') return null;

    const digits = cleaned.replace(/[^\d]/g, '');
    if (digits === '') return null;

    const parsed = Number(digits);
    if (!Number.isFinite(parsed)) return null;

    return Math.max(0, Math.trunc(parsed));
}

export function useExpenseVoucherRetentions(options: UseExpenseVoucherRetentionsOptions) {
    const basePendingByDocumentId = ref<NumberByDocument>({});
    const baseWithholdingByDocumentId = ref<NumberByDocument>({});
    const editableWithholdingByDocumentId = ref<StringByDocument>({});

    function getBasePending(documentId: number): number {
        return Number(basePendingByDocumentId.value[documentId] ?? 0);
    }

    function getBaseWithholding(documentId: number): number {
        return Number(baseWithholdingByDocumentId.value[documentId] ?? 0);
    }

    function getWithholdingValue(documentId: number): number {
        const value = editableWithholdingByDocumentId.value[documentId];
        const parsed = Number(value ?? '');
        if (Number.isFinite(parsed) && parsed >= 0) {
            return parsed;
        }

        return getBaseWithholding(documentId);
    }

    function getWithholdingGrossLimit(documentId: number): number {
        return Math.max(0, getBasePending(documentId) + getBaseWithholding(documentId));
    }

    function getAdjustedPendingBalance(documentId: number): number {
        const basePending = getBasePending(documentId);
        const baseWithholding = getBaseWithholding(documentId);
        const currentWithholding = getWithholdingValue(documentId);
        return Math.max(0, basePending - (currentWithholding - baseWithholding));
    }

    function setWithholdingValue(documentId: number, value: number): void {
        const cappedValue = Math.min(sanitizeNonNegativeAmount(value), getWithholdingGrossLimit(documentId));

        editableWithholdingByDocumentId.value = {
            ...editableWithholdingByDocumentId.value,
            [documentId]: String(cappedValue),
        };
    }

    function applyAllWithAdjustedPending(): void {
        options.documents.value.forEach((doc) => {
            options.updateApplicationAmount(doc.id, String(getAdjustedPendingBalance(doc.id)));
        });
    }

    function initializeWithholdingMaps(): void {
        const nextBasePending: NumberByDocument = {};
        const nextBaseWithholding: NumberByDocument = {};
        const nextEditableWithholding: StringByDocument = {};

        options.documents.value.forEach((doc) => {
            const documentId = Number(doc.id);
            // Se parte del saldo pagable: excluye lo retenido por rollos rechazados sin nota crédito aprobada.
            const basePending = sanitizeNonNegativeAmount(doc.payable_balance ?? doc.pending_balance);
            const baseWithholding = sanitizeNonNegativeAmount(doc.withholdings_total);

            nextBasePending[documentId] = basePending;
            nextBaseWithholding[documentId] = baseWithholding;

            const existingEditable = editableWithholdingByDocumentId.value[documentId];
            nextEditableWithholding[documentId] = existingEditable ?? String(baseWithholding);
        });

        basePendingByDocumentId.value = nextBasePending;
        baseWithholdingByDocumentId.value = nextBaseWithholding;
        editableWithholdingByDocumentId.value = nextEditableWithholding;
    }

    const retentionAdjustedErrors = computed<ErrorByDocument>(() => {
        const errors: ErrorByDocument = {};

        options.documents.value.forEach((doc) => {
            const documentId = Number(doc.id);
            const rawAmount = String(options.applicationAmounts.value[documentId] ?? '').trim();

            if (rawAmount === '') {
                errors[documentId] = null;
                return;
            }

            const parsedAmount = Number(rawAmount);
            if (!Number.isFinite(parsedAmount) || parsedAmount < 0) {
                errors[documentId] = 'Ingresa un monto válido.';
                return;
            }

            if (parsedAmount > getAdjustedPendingBalance(documentId)) {
                errors[documentId] = 'El monto supera el valor por pagar después de retenciones.';
                return;
            }

            errors[documentId] = null;
        });

        return errors;
    });

    const hasRetentionAdjustedErrors = computed(() =>
        Object.values(retentionAdjustedErrors.value).some((error) => Boolean(error)),
    );

    function getAmountError(documentId: number): string | null {
        return retentionAdjustedErrors.value[documentId] ?? options.applicationErrors.value?.[documentId] ?? null;
    }

    const adjustedPendingTotal = computed(() =>
        options.documents.value.reduce((acc, doc) => acc + getAdjustedPendingBalance(Number(doc.id)), 0),
    );

    const appliedTotal = computed(() =>
        options.applications.value.reduce((acc, line) => acc + Number(line.amount || 0), 0),
    );

    const displaySummary = computed(() => ({
        pending_total: adjustedPendingTotal.value,
        applied_total: appliedTotal.value,
        remaining_total: adjustedPendingTotal.value - appliedTotal.value,
    }));

    return {
        editableWithholdingByDocumentId,
        hasRetentionAdjustedErrors,
        displaySummary,
        setWithholdingValue,
        getWithholdingGrossLimit,
        applyAllWithAdjustedPending,
        initializeWithholdingMaps,
        getWithholdingValue,
        getAdjustedPendingBalance,
        getAmountError,
    };
}
