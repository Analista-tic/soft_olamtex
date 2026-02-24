import type { Tax } from '@/domain/Finance';

export const DEFAULT_TAX_LABEL = 'Ninguno (0%)';
export const DEFAULT_TAX_PERCENTAGE = 0;

export function isDefaultTax(tax?: Tax | null): tax is Tax {
    if (!tax) {
        return false;
    }

    const name = (tax.name ?? '').trim();
    const percentage = Number(tax.percentage ?? 0);
    return name === DEFAULT_TAX_LABEL && Number.isFinite(percentage) && Math.trunc(percentage) === DEFAULT_TAX_PERCENTAGE;
}

export function formatTaxOptionLabel(tax: Tax): string {
    const percentage = Number(tax.percentage ?? 0);
    const truncated = Number.isFinite(percentage) ? Math.trunc(percentage) : 0;
    return `${tax.name} (${truncated}%)`;
}

