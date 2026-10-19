<script setup lang="ts" generic="T extends Record<string, any>">
import ServerPagination from '@/components/common/ServerPagination.vue';
import SortableTableHead from '@/components/common/SortableTableHead.vue';
import TableSkeletonRows from '@/components/common/TableSkeletonRows.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

export interface ListColumn {
    /** Identifica el slot `cell-<key>` y el campo de orden por defecto. */
    key: string;
    label: string;
    /** Campo enviado al backend; si se define la columna es ordenable. */
    sortField?: string;
    /** Alineación; por defecto centrado (texto largo → 'start'). */
    align?: 'start' | 'center' | 'end';
    /** Clases extra (anchos, truncado, etc.). */
    class?: string;
}

const props = withDefaults(
    defineProps<{
        columns: ListColumn[];
        rows: T[];
        rowKey?: keyof T | ((row: T) => string | number);
        loading?: boolean;
        emptyText?: string;
        sortBy?: string;
        sortDir?: 'asc' | 'desc';
        currentPage: number;
        pageSize: number;
        totalItems: number;
    }>(),
    { rowKey: 'id', loading: false, emptyText: 'No se encontraron registros.', sortBy: '', sortDir: 'desc' },
);

const emit = defineEmits<{
    (e: 'sort', field: string): void;
    (e: 'update:currentPage', page: number): void;
    (e: 'update:pageSize', size: number): void;
    (e: 'fetch-data', page?: number): void;
}>();

const alignClass: Record<NonNullable<ListColumn['align']>, string> = {
    start: 'text-start',
    center: 'text-center',
    end: 'text-end',
};

function colClass(col: ListColumn) {
    return cn(alignClass[col.align ?? 'center'], col.class);
}

function keyOf(row: T, index: number) {
    const key = typeof props.rowKey === 'function' ? props.rowKey(row) : row[props.rowKey];
    return key ?? index;
}

// +1 por la columna fija de acciones
const totalColumns = () => props.columns.length + 1;
</script>

<template>
    <div class="relative overflow-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
        <Table>
            <TableHeader>
                <TableRow>
                    <template v-for="col in columns" :key="col.key">
                        <SortableTableHead
                            v-if="col.sortField"
                            :label="col.label"
                            :field="col.sortField"
                            :sort-by="sortBy"
                            :sort-dir="sortDir"
                            :class="colClass(col)"
                            @sort="emit('sort', $event)"
                        />
                        <TableHead v-else :class="cn('whitespace-nowrap', colClass(col))">{{ col.label }}</TableHead>
                    </template>
                    <TableHead class="w-[1%] whitespace-nowrap text-center">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableSkeletonRows v-if="loading" :columns="totalColumns()" />
                <TableRow v-else-if="!rows.length">
                    <TableCell :colspan="totalColumns()">
                        <div class="py-6 text-center text-sm text-muted-foreground">{{ emptyText }}</div>
                    </TableCell>
                </TableRow>
                <template v-else>
                    <TableRow v-for="(row, index) in rows" :key="keyOf(row, index)" :data-row-id="keyOf(row, index)">
                        <TableCell v-for="col in columns" :key="col.key" :class="colClass(col)">
                            <slot :name="`cell-${col.key}`" :row="row">{{ row[col.key] ?? '-' }}</slot>
                        </TableCell>
                        <TableCell class="text-center">
                            <slot name="actions" :row="row" />
                        </TableCell>
                    </TableRow>
                </template>
            </TableBody>
        </Table>
        <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
            <ServerPagination
                :current-page="currentPage"
                :page-size="pageSize"
                :total-items="totalItems"
                @update:current-page="emit('update:currentPage', $event)"
                @update:page-size="emit('update:pageSize', $event)"
                @fetch-data="emit('fetch-data', $event)"
            />
        </div>
    </div>
</template>
