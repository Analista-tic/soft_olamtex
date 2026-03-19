<script setup lang="ts">
import AccountChartDialog from '@/pages/Finance/AccountChart/AccountChartDialog.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import TableActionsMenu from '@/components/common/TableActionsMenu.vue';
import Button from '@/components/ui/button/Button.vue';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import {
    fetchAccountChartPage,
    toggleAccountingAccountStatus,
    type AccountingAccountListItem,
} from '@/domain/Finance';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Eye, Pencil, Plus, Search, ToggleLeft, ToggleRight, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { compareAccountingAccountCode } from '@/domain/Finance/accountHierarchy';

const ROUTES = {
    index: '/finanzas/catalogo-cuentas',
    movements: (id: number) => `/finanzas/catalogo-cuentas/${id}/movimientos`,
} as const;

type AccountDialogMode = 'create' | 'edit';

const FETCH_BATCH_SIZE = 200;
const TREE_GRID_TEMPLATE = 'minmax(0, 1.05fr) minmax(240px, 0.95fr) 120px 56px';
const TREE_INDENT_STEP = 12;
const breadcrumbs = [{ title: 'Catalogo de cuentas', href: ROUTES.index }];

const accountTypeOptions = [
    { id: 'all', name: 'Todos los tipos' },
    { id: 'asset', name: 'Activo' },
    { id: 'liability', name: 'Pasivo' },
    { id: 'equity', name: 'Patrimonio' },
    { id: 'income', name: 'Ingreso' },
    { id: 'cost', name: 'Costo' },
    { id: 'expense', name: 'Gasto' },
];

const statusOptions = [
    { id: 'all', name: 'Todos los estados' },
    { id: '1', name: 'Activas' },
    { id: '0', name: 'Inactivas' },
];

const allowsEntriesOptions = [
    { id: 'all', name: 'Todos' },
    { id: '1', name: 'Si permite' },
    { id: '0', name: 'No permite' },
];

const { successMessage, errorMessage, errorType, success, error, clear } = useGlobalAlert();

const rows = ref<AccountingAccountListItem[]>([]);
const isLoading = ref(false);

function createAccountDialogState(
    mode: AccountDialogMode = 'create',
    account: AccountingAccountListItem | null = null,
    parentAccountId: number | null = null,
) {
    return {
        open: false,
        mode,
        account,
        accountId: account?.id ?? null,
        parentAccountId,
    };
}

const accountDialog = ref<{
    open: boolean;
    mode: AccountDialogMode;
    account: AccountingAccountListItem | null;
    accountId: number | null;
    parentAccountId: number | null;
}>(createAccountDialogState());

async function fetchData(): Promise<void> {
    isLoading.value = true;
    try {
        const collected: AccountingAccountListItem[] = [];
        let page = 1;
        let total = 0;

        do {
            const response = await fetchAccountChartPage({
                page,
                per_page: FETCH_BATCH_SIZE,
            });

            collected.push(...response.data);
            total = response.total;
            page += 1;
        } while (collected.length < total);

        rows.value = collected;
    } catch (err) {
        console.error('Error al cargar catalogo de cuentas', err);
        rows.value = [];
    } finally {
        isLoading.value = false;
    }
}

const searchQuery = ref('');
const accountTypeFilter = ref<string>('all');
const statusFilter = ref<string>('all');
const allowsEntriesFilter = ref<string>('all');

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isAccountTypeActive = computed(() => accountTypeFilter.value !== 'all');
const isStatusActive = computed(() => statusFilter.value !== 'all');
const isAllowsEntriesActive = computed(() => allowsEntriesFilter.value !== 'all');

const accountTypeButtonClasses = useFilterButtonClasses(isAccountTypeActive);
const statusButtonClasses = useFilterButtonClasses(isStatusActive);
const allowsEntriesButtonClasses = useFilterButtonClasses(isAllowsEntriesActive);

const collapsedParents = ref<Set<number>>(new Set());

const rowMap = computed(() => {
    const map = new Map<number, AccountingAccountListItem>();
    rows.value.forEach((row) => map.set(row.id, row));
    return map;
});

const childIdsByParent = computed(() => {
    const map = new Map<number, number[]>();

    rows.value.forEach((row) => {
        if (row.parent_id === null) return;

        const siblings = map.get(row.parent_id) ?? [];
        siblings.push(row.id);
        map.set(row.parent_id, siblings);
    });

    return map;
});

const filteredRows = computed(() => {
    const search = searchQuery.value.trim().toLowerCase();
    const matched = new Set<number>();

    rows.value.forEach((row) => {
        const matchesSearch = !search
            || row.code.toLowerCase().includes(search)
            || row.name.toLowerCase().includes(search);
        const matchesAccountType = accountTypeFilter.value === 'all' || row.account_type === accountTypeFilter.value;
        const matchesStatus = statusFilter.value === 'all' || String(Number(row.is_active)) === statusFilter.value;
        const matchesAllowsEntries = allowsEntriesFilter.value === 'all' || String(Number(row.allows_entries)) === allowsEntriesFilter.value;

        if (matchesSearch && matchesAccountType && matchesStatus && matchesAllowsEntries) {
            matched.add(row.id);
        }
    });

    if (matched.size === 0) {
        return [] as AccountingAccountListItem[];
    }

    const included = new Set<number>(matched);

    for (const rowId of matched) {
        let current = rowMap.value.get(rowId) ?? null;
        while (current?.parent_id != null) {
            const parent = rowMap.value.get(current.parent_id) ?? null;
            if (!parent || included.has(parent.id)) break;
            included.add(parent.id);
            current = parent;
        }
    }

    return rows.value.filter((row) => included.has(row.id));
});

const orderedRows = computed(() => {
    const childrenByParent = new Map<number | null, AccountingAccountListItem[]>();
    const ids = new Set(filteredRows.value.map((row) => row.id));

    filteredRows.value.forEach((row) => {
        const parentKey = row.parent_id !== null && ids.has(row.parent_id) ? row.parent_id : null;
        const siblings = childrenByParent.get(parentKey) ?? [];
        siblings.push(row);
        childrenByParent.set(parentKey, siblings);
    });

    for (const siblings of childrenByParent.values()) {
        siblings.sort(compareAccountingAccountCode);
    }

    const ordered: AccountingAccountListItem[] = [];

    const appendBranch = (parentId: number | null) => {
        const branch = childrenByParent.get(parentId) ?? [];
        branch.forEach((row) => {
            ordered.push(row);
            appendBranch(row.id);
        });
    };

    appendBranch(null);

    return ordered;
});

function toggleCollapse(id: number): void {
    if (collapsedParents.value.has(id)) {
        collapsedParents.value.delete(id);
    } else {
        collapsedParents.value.add(id);
    }
    collapsedParents.value = new Set(collapsedParents.value);
}

function isRowVisible(row: AccountingAccountListItem): boolean {
    if (isSearchActive.value || isAccountTypeActive.value || isStatusActive.value || isAllowsEntriesActive.value) {
        return true;
    }
    if (row.parent_id === null) return true;
    const parent = rowMap.value.get(row.parent_id) ?? null;
    if (parent) {
        if (collapsedParents.value.has(parent.id)) return false;
        return isRowVisible(parent);
    }
    return true;
}

function hasChildren(id: number): boolean {
    return (childIdsByParent.value.get(id)?.length ?? 0) > 0;
}

function getIndentWidth(level: number): string {
    return `${Math.max(0, level - 1) * TREE_INDENT_STEP}px`;
}

const visibleRows = computed(() => orderedRows.value.filter(isRowVisible));

function openCreate(): void {
    accountDialog.value = { ...createAccountDialogState(), open: true };
}

function openEdit(row: AccountingAccountListItem): void {
    accountDialog.value = { ...createAccountDialogState('edit', row), open: true };
}

function openSubcreate(row: AccountingAccountListItem): void {
    accountDialog.value = { ...createAccountDialogState('create', null, row.id), open: true };
}

function openMovements(row: AccountingAccountListItem): void {
    router.visit(ROUTES.movements(row.id));
}

function canDelete(row: AccountingAccountListItem): boolean {
    if (row.level === 1) return false;
    return !hasChildren(row.id);
}

async function handleDelete(row: AccountingAccountListItem): Promise<void> {
    if (!canDelete(row)) return;
    console.warn('handleDelete pendiente de implementar', row.id);
}

async function handleToggleStatus(row: AccountingAccountListItem): Promise<void> {
    try {
        await toggleAccountingAccountStatus(row.id);
        success(row.is_active ? 'Cuenta desactivada correctamente' : 'Cuenta activada correctamente');
        await fetchData();
    } catch {
        error('Error al cambiar estado de la cuenta');
    }
}

function handleAccountDialogOpenChange(open: boolean): void {
    if (open) {
        accountDialog.value.open = true;
        return;
    }

    accountDialog.value = createAccountDialogState();
}

async function handleAccountDialogSaved(payload: { mode: 'create' | 'edit'; account: AccountingAccountListItem }): Promise<void> {
    success(payload.mode === 'create' ? 'Cuenta guardada correctamente' : 'Cuenta actualizada correctamente');
    handleAccountDialogOpenChange(false);
    await fetchData();
}

watch([searchQuery, accountTypeFilter, statusFilter, allowsEntriesFilter], () => {
    collapsedParents.value = new Set();
});

onMounted(() => void fetchData());
</script>

<template>
    <Head title="Catalogo de cuentas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="uiPatterns.pageIndex.page">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="clear"
                @close-error="clear"
            />

            <AccountChartDialog
                :open="accountDialog.open"
                :mode="accountDialog.mode"
                :account="accountDialog.account"
                :account-id="accountDialog.accountId"
                :parent-account-id="accountDialog.parentAccountId"
                @update:open="handleAccountDialogOpenChange"
                @saved="handleAccountDialogSaved"
            />

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div :class="uiPatterns.pageIndex.header">
                    <h1 :class="uiPatterns.pageIndex.title">Catálogo de cuentas</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">
                        Configura y administra la estructura de cuentas contables de la empresa.
                    </p>
                </div>
                <Button type="button" size="pill" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    Nueva cuenta
                </Button>
            </div>

            <div>
                <FilterBar no-margin>
                    <FilterField label="Buscar" :active="isSearchActive" class="min-w-[260px] flex-1 p-0">
                        <div class="relative">
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar por codigo o nombre"
                                class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm focus:border-primary focus:ring-0"
                            />
                            <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                                <Search :size="20" class="text-muted-foreground" />
                            </span>
                        </div>
                    </FilterField>

                    <FilterField label="Tipo de cuenta" :active="isAccountTypeActive" class="min-w-[200px]">
                        <SelectField
                            v-model="accountTypeFilter"
                            :options="accountTypeOptions"
                            placeholder="Todos los tipos"
                            :button-class="accountTypeButtonClasses"
                        />
                    </FilterField>

                    <FilterField label="Estado" :active="isStatusActive" class="min-w-[180px]">
                        <SelectField
                            v-model="statusFilter"
                            :options="statusOptions"
                            placeholder="Todos los estados"
                            :button-class="statusButtonClasses"
                        />
                    </FilterField>

                    <FilterField label="Permite movimientos" :active="isAllowsEntriesActive" class="min-w-[180px]">
                        <SelectField
                            v-model="allowsEntriesFilter"
                            :options="allowsEntriesOptions"
                            placeholder="Todos"
                            :button-class="allowsEntriesButtonClasses"
                        />
                    </FilterField>
                </FilterBar>
            </div>

            <div :class="uiPatterns.pageIndex.tableWrap">
                <div
                    class="account-tree-grid"
                    role="treegrid"
                    aria-label="Catalogo de cuentas"
                    :style="{ gridTemplateColumns: TREE_GRID_TEMPLATE }"
                >
                    <div
                        class="account-tree-grid__header"
                        role="row"
                        :style="{ gridTemplateColumns: TREE_GRID_TEMPLATE }"
                    >
                        <div class="account-tree-grid__header-cell account-tree-grid__header-cell--account" role="columnheader">
                            Cuenta
                        </div>
                        <div class="account-tree-grid__header-cell account-tree-grid__header-cell--description" role="columnheader">
                            Descripcion
                        </div>
                        <div class="account-tree-grid__header-cell" role="columnheader">Estado</div>
                        <div class="account-tree-grid__header-cell" role="columnheader" />
                    </div>

                    <div v-if="isLoading" class="account-tree-grid__feedback" role="row">
                        Cargando catalogo de cuentas...
                    </div>

                    <div v-else-if="visibleRows.length === 0" class="account-tree-grid__feedback" role="row">
                        <div class="flex flex-col items-center gap-2 py-10">
                            <p class="text-sm text-muted-foreground">
                                No se encontraron cuentas con los filtros actuales.
                            </p>
                            <Button variant="outline" size="sm" @click="openCreate">
                                <Plus class="mr-2 h-4 w-4" />
                                Crear primera cuenta
                            </Button>
                        </div>
                    </div>

                    <template v-else>
                        <template v-for="(row, index) in visibleRows" :key="row.id">
                            <div
                                v-if="row.level === 1 && index > 0"
                                class="account-tree-grid__separator"
                                aria-hidden="true"
                            />

                            <div
                                class="account-row account-tree-grid__row group"
                                role="row"
                                :aria-level="row.level"
                                :aria-expanded="hasChildren(row.id) ? !collapsedParents.has(row.id) : undefined"
                                :class="[
                                    row.level === 1 ? 'bg-muted/30 dark:bg-muted/10' : '',
                                    !row.is_active ? 'account-row--inactive' : '',
                                    row.level === 1 ? `accent-${row.account_type}` : '',
                                ]"
                                :style="{ gridTemplateColumns: TREE_GRID_TEMPLATE }"
                            >
                                <div class="account-tree-grid__cell account-tree-grid__cell--account" role="gridcell">
                                    <div class="account-node">
                                        <span class="account-node__indent" :style="{ width: getIndentWidth(row.level) }" />

                                        <span class="account-node__toggle-slot">
                                            <button
                                                v-if="hasChildren(row.id)"
                                                type="button"
                                                class="account-node__toggle"
                                                @click.stop="toggleCollapse(row.id)"
                                            >
                                                <ChevronDown v-if="!collapsedParents.has(row.id)" class="h-3 w-3" />
                                                <ChevronRight v-else class="h-3 w-3" />
                                            </button>
                                        </span>

                                        <div class="account-node__content">
                                            <span
                                                class="account-node__code"
                                                :class="row.level === 1
                                                    ? 'text-[13px] font-bold text-foreground'
                                                    : row.level === 2
                                                        ? 'text-xs font-semibold text-foreground/80'
                                                        : 'text-xs text-muted-foreground/75'"
                                            >{{ row.code }}</span>

                                            <span
                                                class="account-node__name"
                                                :class="row.level === 1
                                                    ? 'text-[13px] font-bold text-foreground'
                                                    : row.level === 2
                                                        ? 'text-sm font-medium text-foreground'
                                                        : 'text-sm text-foreground/75'"
                                            >{{ row.name }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="account-tree-grid__cell account-tree-grid__cell--description" role="gridcell">
                                    <span
                                        class="account-description"
                                        :class="row.description
                                            ? row.level === 1
                                                ? 'text-foreground/80'
                                                : 'text-muted-foreground'
                                            : 'text-muted-foreground/55'"
                                    >
                                        {{ row.description?.trim() || '—' }}
                                    </span>
                                </div>

                                <div class="account-tree-grid__cell account-tree-grid__cell--center" role="gridcell">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="row.is_active
                                            ? 'border-emerald-200 bg-emerald-50/80 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-950/30 dark:text-emerald-300'
                                            : 'border-red-200 bg-red-50/80 text-red-600 dark:border-red-400/30 dark:bg-red-950/30 dark:text-red-400'"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="row.is_active ? 'bg-emerald-500' : 'bg-red-400'"
                                        />
                                        {{ row.is_active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </div>

                                <div class="account-tree-grid__cell account-tree-grid__cell--actions" role="gridcell">
                                    <TableActionsMenu content-class="w-48">
                                        <DropdownMenuItem class="cursor-pointer gap-2" @select="openEdit(row)">
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="cursor-pointer gap-2" @select="openSubcreate(row)">
                                            <Plus class="h-3.5 w-3.5" />
                                            Agregar subcuenta
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="cursor-pointer gap-2" @select="openMovements(row)">
                                            <Eye class="h-3.5 w-3.5" />
                                            Ver movimientos
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            class="cursor-pointer gap-2"
                                            :class="row.is_active
                                                ? 'text-muted-foreground'
                                                : 'text-emerald-600 focus:text-emerald-700'"
                                            @select="handleToggleStatus(row)"
                                        >
                                            <ToggleRight v-if="row.is_active" class="h-3.5 w-3.5" />
                                            <ToggleLeft v-else class="h-3.5 w-3.5 text-emerald-600" />
                                            {{ row.is_active ? 'Desactivar' : 'Activar' }}
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            :disabled="!canDelete(row)"
                                            class="cursor-pointer gap-2"
                                            :class="canDelete(row)
                                                ? 'text-red-600 focus:text-red-700 focus:bg-red-50 dark:focus:bg-red-950/30'
                                                : 'cursor-not-allowed opacity-40'"
                                            @select="handleDelete(row)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                            Eliminar
                                        </DropdownMenuItem>
                                    </TableActionsMenu>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.account-tree-grid {
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.account-tree-grid__header,
.account-tree-grid__row {
    display: grid;
    align-items: stretch;
}

.account-tree-grid__header {
    border-bottom: 1px solid hsl(var(--border));
}

.account-tree-grid__header-cell {
    display: flex;
    min-width: 0;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: hsl(var(--muted-foreground));
}

.account-tree-grid__header-cell--account {
    justify-content: flex-start;
    padding-left: 0.75rem;
}

.account-tree-grid__header-cell--description {
    justify-content: flex-start;
}

.account-tree-grid__feedback {
    display: flex;
    justify-content: center;
    padding: 1.5rem 1rem;
    text-align: center;
    font-size: 0.875rem;
    color: hsl(var(--muted-foreground));
}

.account-tree-grid__separator {
    height: 5px;
    background: color-mix(in srgb, hsl(var(--sidebar-border)) 25%, transparent);
}

.account-tree-grid__row {
    position: relative;
    border-bottom: 1px solid color-mix(in srgb, hsl(var(--border)) 72%, transparent);
    transition: background-color 150ms ease;
}

.account-tree-grid__cell {
    display: flex;
    min-width: 0;
    align-items: center;
    padding: 0.5rem;
}

.account-tree-grid__cell--account {
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}

.account-tree-grid__cell--description {
    align-items: flex-start;
    padding-right: 0.75rem;
    padding-left: 0.5rem;
}

.account-tree-grid__cell--center {
    justify-content: center;
}

.account-tree-grid__cell--actions {
    justify-content: flex-end;
    padding-right: 0.5rem;
}

.account-node {
    display: flex;
    min-width: 0;
    width: 100%;
    align-items: flex-start;
}

.account-node__indent {
    flex: 0 0 auto;
}

.account-node__toggle-slot {
    display: flex;
    width: 16px;
    min-width: 16px;
    justify-content: center;
    padding-top: 2px;
}

.account-node__toggle {
    display: inline-flex;
    height: 16px;
    width: 16px;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: hsl(var(--muted-foreground));
}

.account-node__toggle:hover {
    background: hsl(var(--muted));
    color: hsl(var(--foreground));
}

.account-node__content {
    display: flex;
    min-width: 0;
    flex: 1 1 auto;
    align-items: flex-start;
    gap: 0.5rem;
    margin-left: 0.375rem;
}

.account-node__code {
    flex: 0 0 auto;
    white-space: nowrap;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
}

.account-node__name {
    display: -webkit-box;
    min-width: 0;
    overflow: hidden;
    line-height: 1.25rem;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    word-break: break-word;
}

.account-description {
    display: -webkit-box;
    min-width: 0;
    overflow: hidden;
    line-height: 1.25rem;
    font-size: 0.875rem;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    word-break: break-word;
}

.account-row--inactive .account-tree-grid__cell {
    opacity: 0.72;
}

.account-row::before {
    content: '';
    position: absolute;
    inset-block: 0;
    left: 0;
    width: 3px;
    pointer-events: none;
}

.accent-asset::before { background-color: #1d9e75; }
.accent-liability::before { background-color: #185fa5; }
.accent-equity::before { background-color: #ba7517; }
.accent-income::before { background-color: #3b6d11; }
.accent-cost::before { background-color: #5f5e5a; }
.accent-expense::before { background-color: #a32d2d; }
</style>
