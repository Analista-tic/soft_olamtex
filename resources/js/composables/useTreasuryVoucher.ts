import { computed, type Ref } from 'vue';
import type { TreasuryApplicationLine, TreasuryOpenDocument, TreasuryVoucherSummary } from '@/domain/Finance';

export function useTreasuryVoucher(
    documents: Ref<TreasuryOpenDocument[]>,
    applicationAmounts: Ref<Record<number, string>>,
) {
    const applicationErrors = computed<Record<number, string | null>>(() => {
        const errors: Record<number, string | null> = {};

        documents.value.forEach((doc) => {
            const rawValue = String(applicationAmounts.value[doc.id] ?? '').trim();

            if (rawValue === '') {
                errors[doc.id] = null;
                return;
            }

            const parsed = Number(rawValue);
            if (!Number.isFinite(parsed) || parsed < 0) {
                errors[doc.id] = 'Ingresa un monto válido.';
                return;
            }

            if (parsed > Number(doc.payable_balance ?? doc.pending_balance ?? 0)) {
                errors[doc.id] = 'El monto supera el valor por pagar.';
                return;
            }

            errors[doc.id] = null;
        });

        return errors;
    });

    const hasApplicationErrors = computed(() =>
        Object.values(applicationErrors.value).some((error) => Boolean(error)),
    );

    const applications = computed<TreasuryApplicationLine[]>(() =>
        documents.value
            .map((doc) => {
                const raw = Number(applicationAmounts.value[doc.id] ?? 0);
                const hasError = Boolean(applicationErrors.value[doc.id]);
                const normalized = !hasError && Number.isFinite(raw) && raw > 0 ? raw : 0;
                return {
                    document_id: doc.id,
                    amount: normalized,
                };
            })
            .filter((line) => line.amount > 0),
    );

    const summary = computed<TreasuryVoucherSummary>(() => {
        const pendingTotal = documents.value.reduce((acc, doc) => acc + Number(doc.pending_balance || 0), 0);
        const appliedTotal = applications.value.reduce((acc, line) => acc + Number(line.amount || 0), 0);

        return {
            pending_total: pendingTotal,
            applied_total: appliedTotal,
            remaining_total: pendingTotal - appliedTotal,
            documents_count: documents.value.length,
        };
    });

    return {
        applications,
        summary,
        applicationErrors,
        hasApplicationErrors,
    };
}
