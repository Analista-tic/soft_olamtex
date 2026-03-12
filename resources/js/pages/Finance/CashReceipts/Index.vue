<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs = [{ title: 'Recibo de caja', href: '/finanzas/recibos-caja' }];

const searchQuery = ref('');
const dateFromFilter = ref<string | null>(null);
const dateToFilter = ref<string | null>(null);

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isDateFromActive = computed(() => Boolean(dateFromFilter.value));
const isDateToActive = computed(() => Boolean(dateToFilter.value));

function openCreate(): void {
    router.visit('/finanzas/recibos-caja/crear');
}
</script>

<template>
    <Head title="Recibos de caja" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="uiPatterns.pageIndex.page">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div :class="uiPatterns.pageIndex.header">
                    <h1 :class="uiPatterns.pageIndex.title">Recibos de caja</h1>
                    <p :class="uiPatterns.pageIndex.subtitle">
                        Listado operativo para ingresos de tesorería. El guardado quedará activo en Fase 3.
                    </p>
                </div>
                <Button type="button" size="pill" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    Nuevo recibo
                </Button>
            </div>

            <div :class="uiPatterns.pageIndex.filtersWrap">
                <FilterBar no-margin>
                    <FilterField label="Buscar" :active="isSearchActive" class="flex-1 min-w-[260px] p-0">
                        <div class="relative">
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar recibo"
                                class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm focus:border-primary focus:ring-0"
                            />
                            <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                                <Search :size="20" class="text-muted-foreground" />
                            </span>
                        </div>
                    </FilterField>

                    <FilterField label="Desde" :active="isDateFromActive" class="min-w-[190px]">
                        <InputDate v-model="dateFromFilter" placeholder="Fecha desde" clearable />
                    </FilterField>

                    <FilterField label="Hasta" :active="isDateToActive" class="min-w-[190px]">
                        <InputDate v-model="dateToFilter" placeholder="Fecha hasta" clearable />
                    </FilterField>
                </FilterBar>
            </div>

            <div :class="uiPatterns.pageIndex.tableWrap">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead">Número</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Fecha</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Cliente</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Referencia</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Monto</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Estado</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow>
                            <TableCell colspan="6" class="py-8 text-center text-sm text-muted-foreground">
                                No hay recibos cargados en esta fase. Usa "Nuevo recibo" para validar el flujo base Treasury.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
