import { computed } from 'vue';
import type { Ref } from 'vue';

const DEFAULT_BASE_CLASSES =
    'h-10 w-full rounded-full border px-3 text-sm font-normal flex items-center justify-between transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2';

export function useFilterButtonClasses(
    isActive: Ref<boolean>,
    options?: {
        baseClasses?: string;
        activeClasses?: string;
        inactiveClasses?: string;
    },
) {
    return computed(() => {
        const active = isActive.value;
        const baseClasses = options?.baseClasses ?? DEFAULT_BASE_CLASSES;
        const activeClasses = options?.activeClasses ?? 'border-blue-500/80 bg-blue-50 text-blue-700 shadow';
        const inactiveClasses =
            options?.inactiveClasses ?? 'border-border bg-white text-foreground shadow-sm hover:border-blue-300';

        return `${baseClasses} ${active ? activeClasses : inactiveClasses}`.trim();
    });
}
