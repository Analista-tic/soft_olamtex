<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Pagination,
    PaginationEllipsis,
    PaginationFirst,
    PaginationLast,
    PaginationList,
    PaginationListItem,
    PaginationNext,
    PaginationPrev,
} from '@/components/ui/pagination';

// 1. Definimos qué datos recibe este componente
const props = withDefaults(
    defineProps<{
        currentPage: number;
        totalItems: number; 
        pageSize: number;
        pageSizeOptions?: number[];
    }>(),
    {
        pageSizeOptions: () => [10, 25, 50, 100],
    },
);

// 2. Definimos los eventos
const emit = defineEmits<{
    (e: 'update:currentPage', page: number): void;
    (e: 'update:pageSize', size: number): void;
    (e: 'fetch-data', page?: number): void;
}>();

// 3. Lógica para calcular el rango visible (Ej: 1 - 10)
const rangeText = computed(() => {
    if (props.totalItems === 0) return '0';
    const start = (props.currentPage - 1) * props.pageSize + 1;
    const end = Math.min(props.currentPage * props.pageSize, props.totalItems);
    return `${start} - ${end}`;
});

// 4. Lógica para cambiar la cantidad de registros por página
function onPageSizeChange(event: Event) {
    const selectElement = event.target as HTMLSelectElement;
    const newSize = Number(selectElement.value);

    if (newSize === props.pageSize) return;

    emit('update:pageSize', newSize);
    emit('update:currentPage', 1);
    emit('fetch-data', 1);
}

// 5. Lógica para cuando cambia de página
function handlePageChange(page: number) {
    if (page === props.currentPage) return;

    // Evitar navegar a páginas inexistentes
    const lastPage = Math.ceil(props.totalItems / props.pageSize);
    if (page < 1 || page > lastPage) return;

    emit('update:currentPage', page);
    emit('fetch-data', page);
}
</script>

<template>
    <div class="mt-5 flex w-full flex-col items-center justify-between gap-4 px-2 sm:flex-row">
        
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <span>Mostrar</span>
            <select
                :value="pageSize"
                @change="onPageSizeChange"
                class="h-7 w-12 rounded-md border border-input bg-background px-1 py-0 text-xs ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
            >
                <option v-for="size in pageSizeOptions" :key="size" :value="size">
                    {{ size }}
                </option>
            </select>
            <span>registros ({{ rangeText }} de {{ totalItems }})</span>
        </div>

        <Pagination
            v-slot="{ page }"
            :total="totalItems"
            :items-per-page="pageSize"
            :sibling-count="1"
            show-edges
            :page="currentPage"
            @update:page="handlePageChange"
        >
            <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                <PaginationFirst />
                <PaginationPrev />

                <template v-for="(item, index) in items">
                    <PaginationListItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                        <Button 
                            class="h-8 w-8 p-0" 
                            :variant="item.value === page ? 'default' : 'outline'"
                        >
                            {{ item.value }}
                        </Button>
                    </PaginationListItem>
                    <PaginationEllipsis v-else :key="item.type" :index="index" />
                </template>

                <PaginationNext />
                <PaginationLast />
            </PaginationList>
        </Pagination>
    </div>
</template>