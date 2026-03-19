import type { HierarchicalAccountOption } from '@/domain/Finance/accountHierarchy';
import type { Option } from '@/domain/shared';

type HierarchyOptionState = {
    active: boolean;
    selected: boolean;
};

export function getHierarchicalAccountOptionRowClass(option: Option, state?: HierarchyOptionState): string {
    const account = option as HierarchicalAccountOption;

    if (typeof account.id === 'string') {
        return state?.selected && !state.active ? 'ring-1 ring-primary/15 py-2.5 pl-3' : 'py-2.5 pl-3';
    }

    const levelPadding = account.level <= 1 ? 'pl-3' : account.level === 2 ? 'pl-6' : account.level === 3 ? 'pl-9' : 'pl-12';
    const levelWeight = account.level === 1 ? 'py-3' : 'py-2.5';
    const stateClass = state?.selected && !state.active ? 'ring-1 ring-primary/15' : '';

    return [levelPadding, levelWeight, stateClass].filter(Boolean).join(' ');
}

export function getHierarchicalAccountOptionLabelClass(option: Option, state?: HierarchyOptionState): string {
    const account = option as HierarchicalAccountOption;

    if (typeof account.id === 'string') {
        return state?.selected ? 'text-primary font-semibold' : 'text-foreground font-medium';
    }

    const levelClass = account.level === 1
        ? 'text-foreground font-semibold'
        : account.level === 2
            ? 'text-foreground/90 font-medium'
            : 'text-muted-foreground';

    const selectedClass = state?.selected ? 'text-primary font-semibold' : '';

    return [levelClass, selectedClass].filter(Boolean).join(' ');
}
