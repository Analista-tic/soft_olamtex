import type { AccountingAccountListItem } from '@/domain/Finance';
import type { Option } from '@/domain/shared';

export type HierarchicalAccountOption = Option & {
    code: string;
    level: number;
    allows_entries: boolean;
};

export function compareAccountingAccountCode(left: AccountingAccountListItem, right: AccountingAccountListItem): number {
    return left.code.localeCompare(right.code, 'es-CO', { numeric: true, sensitivity: 'base' });
}

export function buildRelativeAccountOptions(
    rows: AccountingAccountListItem[],
    rootAccount: AccountingAccountListItem,
): HierarchicalAccountOption[] {
    const childrenByParent = new Map<number | null, AccountingAccountListItem[]>();

    rows.forEach((row) => {
        const siblings = childrenByParent.get(row.parent_id) ?? [];
        siblings.push(row);
        childrenByParent.set(row.parent_id, siblings);
    });

    for (const siblings of childrenByParent.values()) {
        siblings.sort(compareAccountingAccountCode);
    }

    const subtree: AccountingAccountListItem[] = [];
    const visited = new Set<number>();

    const appendBranch = (current: AccountingAccountListItem) => {
        if (visited.has(current.id)) return;

        visited.add(current.id);
        subtree.push(current);

        for (const child of childrenByParent.get(current.id) ?? []) {
            appendBranch(child);
        }
    };

    appendBranch(rows.find((row) => row.id === rootAccount.id) ?? rootAccount);

    return subtree.map((row) => ({
        id: row.id,
        name: `${row.code} - ${row.name}`,
        code: row.code,
        level: Math.max(1, row.level - rootAccount.level + 1),
        allows_entries: row.allows_entries,
    }));
}

export function buildParentAccountOptions(
    rows: AccountingAccountListItem[],
    excludeId: number | null = null,
): HierarchicalAccountOption[] {
    return [
        {
            id: '',
            name: 'Sin cuenta padre',
            code: '',
            level: 1,
            allows_entries: false,
        },
        ...rows
            .filter((row) => row.id !== excludeId)
            .sort(compareAccountingAccountCode)
            .map((row) => ({
                id: row.id,
                name: `${row.code} - ${row.name}`,
                code: row.code,
                level: row.level,
                allows_entries: row.allows_entries,
            })),
    ];
}
