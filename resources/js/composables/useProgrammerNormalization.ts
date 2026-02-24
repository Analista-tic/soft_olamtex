import type { Option } from '@/domain/shared/types';
import { computed, type ComputedRef } from 'vue';

export function useProgrammerNormalization(months: ComputedRef<Option[]> | Option[]) {
    const monthOptions = computed<Option[]>(() => (Array.isArray(months) ? months : months.value));

    function normalizeOptionToName(value: string | number | null | undefined, options: Option[]): string | null {
        if (value === null || value === undefined || value === '') return null;

        const byId = options.find((opt) => opt.id === value || String(opt.id) === String(value));
        if (byId) return String(byId.name).toUpperCase();

        const byName = options.find((opt) => String(opt.name).toUpperCase() === String(value).toUpperCase());
        if (byName) return String(byName.name).toUpperCase();

        return String(value).toUpperCase();
    }

    function normalizeSeasonValue(season: string | number | null | undefined): string | null {
        if (season === null || season === undefined || season === '') return null;

        const numericText = String(season).trim();
        if (/^\d+$/.test(numericText)) {
            const matched = monthOptions.value.find((m) => Number(m.id) === Number(numericText));
            if (matched) return String(matched.name).toUpperCase();
        } else {
            const matched = monthOptions.value.find((m) => String(m.id) === numericText);
            if (matched) return String(matched.name).toUpperCase();
        }

        const upper = numericText.toUpperCase();
        const byName = monthOptions.value.find((m) => String(m.name).toUpperCase() === upper);
        if (byName) return String(byName.name).toUpperCase();

        return upper;
    }

    function normalizeSeasonForApi(season: string | number | null | undefined): string | null {
        if (season === null || season === undefined || season === '') return null;

        const byId = monthOptions.value.find((m) => String(m.id) === String(season));
        if (byId) return String(byId.name).toUpperCase();

        const text = String(season).trim();
        if (text === '') return null;

        if (/^\d+$/.test(text)) {
            const numMatch = monthOptions.value.find((m) => Number(m.id) === Number(text));
            if (numMatch) return String(numMatch.name).toUpperCase();
        }

        const byName = monthOptions.value.find((m) => m.name.toUpperCase() === text.toUpperCase());
        if (byName) return String(byName.name).toUpperCase();

        return text.toUpperCase();
    }

    const PRIORITY_VALUES = ['A', 'B', 'C', 'D'] as const;
    type PriorityValue = (typeof PRIORITY_VALUES)[number];

    function normalizePriorityForApi(priority: string | number | null | undefined): PriorityValue | null {
        if (priority === null || priority === undefined || priority === '') return null;

        const normalized = String(priority).trim().toUpperCase();
        if (normalized === '') return null;

        if (PRIORITY_VALUES.includes(normalized as PriorityValue)) {
            return normalized as PriorityValue;
        }

        return null;
    }

    function normalizeIsoDate(raw: unknown): string | null {
        if (raw === null || raw === undefined) return null;

        const text = String(raw).trim();
        if (text === '' || text === '-' || text.toUpperCase() === 'N/A') return null;

        const isoMatch = text.match(/^(\d{4}-\d{2}-\d{2})/);
        if (isoMatch) return isoMatch[1];

        const parts = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (parts) {
            const [, dd, mm, yyyy] = parts;
            return `${yyyy}-${mm}-${dd}`;
        }

        return null;
    }

    function normalizeSeason(value: string | number | null | undefined): string | null {
        return normalizeOptionToName(value, monthOptions.value);
    }

    function normalizePriority(value: string | number | null | undefined): string | null {
        return normalizeOptionToName(
            value,
            PRIORITY_VALUES.map((p) => ({ id: p, name: p })),
        );
    }

    return {
        normalizeSeasonValue,
        normalizeSeasonForApi,
        normalizePriorityForApi,
        normalizeIsoDate,
        normalizeSeason,
        normalizePriority,
        normalizeOptionToName,
        PRIORITY_VALUES,
    };
}
