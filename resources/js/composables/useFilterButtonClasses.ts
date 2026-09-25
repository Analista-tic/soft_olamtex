import type { Ref } from 'vue';
import { computed } from 'vue';

const DEFAULT_BASE_CLASSES =
    'h-10 w-full rounded-full border px-3 text-sm font-normal flex items-center justify-between transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background';

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
        const activeClasses = options?.activeClasses ?? 'border-primary/60 bg-accent/20 text-foreground shadow';
        const inactiveClasses =
            options?.inactiveClasses ?? 'border-border bg-background text-foreground shadow-sm hover:bg-accent/20 hover:text-foreground';

        return `${baseClasses} ${active ? activeClasses : inactiveClasses}`.trim();
    });
}
