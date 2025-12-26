<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import DeleteButton from '@/components/common/DeleteButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import FabricRollInventoryDialog from '@/pages/KardexFabric/FabricRollInventory/FabricRollInventoryDialog.vue';
import { ArrowDownToLine, Eye, Search } from 'lucide-vue-next';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import type { FabricRollView } from '@/types';

const breadcrumbs = [
    { title: 'Kardex de Telas', href: '/kardex-telas' },
    { title: 'Descargue de tela por orden', href: '/kardex-telas/descargue-orden' },
];

const searchQuery = ref('');

const rolls = ref<FabricRollView[]>([]);
const selectedRoll = ref<FabricRollView | null>(null);
const isDialogOpen = ref(false);
const rollToDelete = ref<FabricRollView | null>(null);
const isDeleteDialogOpen = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const isLoadingRolls = ref(false);

const normalizedValue = (value?: string | null) =>
    (value ?? '')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

const filteredRolls = computed(() => {
    const query = normalizedValue(searchQuery.value);
    return rolls.value.filter((roll) => {
        const fieldsToSearch = [
            roll.roll,
            roll.location,
            roll.fabric,
            roll.color,
            roll.provider,
            roll.orderNumber,
        ];
        const matchesSearch =
            !query ||
            fieldsToSearch.some((field) => normalizedValue(field).includes(query));
        return matchesSearch;
    });
});

function selectRoll(roll: FabricRollView) {
    selectedRoll.value = roll;
    isDialogOpen.value = true;
}

function confirmDeleteRoll(roll: FabricRollView) {
    rollToDelete.value = roll;
    isDeleteDialogOpen.value = true;
}

function deleteRoll() {
    if (!rollToDelete.value) return;
    const index = rolls.value.findIndex((r) => r.id === rollToDelete.value?.id);
    if (index !== -1) {
        rolls.value.splice(index, 1);
        successMessage.value = 'El rollo se elimino correctamente.';
        errorMessage.value = '';
    }
    isDeleteDialogOpen.value = false;
}

function openDownloadWindow(roll: FabricRollView) {
    const url = new URL('/kardex-telas/descargue-orden/descarga', window.location.origin);
    if (roll?.id) {
        url.searchParams.set('roll', String(roll.id));
    }
    window.open(url.toString(), '_blank');
}

const getFieldValue = (roll: FabricRollView, key: string, fallback = '-') => {
    const record = roll as Record<string, unknown>;
    const value = record[key];
    if (value === undefined || value === null || value === '') {
        return fallback;
    }
    return String(value);
};

const getSeasonLabel = (roll: FabricRollView) => getFieldValue(roll, 'season', roll.fabric ?? '-');
const getSectionLabel = (roll: FabricRollView) => getFieldValue(roll, 'section', roll.location ?? '-');
const getGroupLabel = (roll: FabricRollView) => getFieldValue(roll, 'group', roll.provider ?? '-');
const getPriorityLabel = (roll: FabricRollView) =>
    getFieldValue(roll, 'priority', roll.status === 'depleted' ? 'Baja' : 'Alta');
const getScheduledUnitsLabel = (roll: FabricRollView) => getFieldValue(roll, 'scheduledUnits', roll.rollQty ?? '-');

async function fetchFabricRolls() {
    isLoadingRolls.value = true;
    errorMessage.value = '';
    try {
        const response = await axios.get('/kardex-telas/inventario-rollos/data');
        const payload = response.data?.data ?? response.data;
        rolls.value = Array.isArray(payload) ? payload : [];
    } catch {
        rolls.value = [];
        errorMessage.value = 'No se pudo cargar el inventario de rollos.';
    } finally {
        isLoadingRolls.value = false;
    }
}

onMounted(fetchFabricRolls);
</script>

<template>
    <Head title="Descargue de tela por orden" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Exito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Descargue de tela por orden</h1>
                    <p class="text-sm text-muted-foreground">
                        Registra el descargue de tela por orden de produccion, controlando el metraje utilizado y actualizando automaticamente el inventario con trazabilidad por rollo.
                    </p>
                </div>
            </div>

            <FilterBar>
                <FilterField label="Buscar lote" :active="searchQuery.trim().length > 0" class="flex-1 min-w-[220px] p-0">
                    <div class="relative">
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar lote"
                            class="h-10 w-full rounded-md border border-gray-200 bg-white pl-10 text-sm focus:border-primary focus:ring-0"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 bg-white md:min-h-min">
                <div class="overflow-x-auto">
                    <Table class="min-w-full text-[0.9rem]">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Lote</TableHead>
                            <TableHead>Referencia</TableHead>
                            <TableHead>Temporada</TableHead>
                            <TableHead>Seccion</TableHead>
                            <TableHead>Grupo</TableHead>
                            <TableHead>Prioridad</TableHead>
                            <TableHead>Unidades programadas</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="roll in filteredRolls" :key="roll.id" class="border-b">
                            <TableCell class="text-center text-sm ">{{ roll.roll }}</TableCell>
                            <TableCell class="text-center text-sm ">
                                {{ roll.orderNumber || '—' }}
                            </TableCell>
                            <TableCell class="text-center text-sm ">{{ getSeasonLabel(roll) }}</TableCell>
                            <TableCell class="text-center text-sm ">{{ getSectionLabel(roll) }}</TableCell>
                            <TableCell class="text-center text-sm ">{{ getGroupLabel(roll) }}</TableCell>
                            <TableCell class="text-center text-sm ">{{ getPriorityLabel(roll) }}</TableCell>
                            <TableCell class="text-center text-sm ">{{ getScheduledUnitsLabel(roll) }}</TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <button
                                    class="inline-flex items-center justify-center text-muted-foreground transition-colors hover:text-blue-600"
                                    title="Ver rollo"
                                    type="button"
                                    @click="selectRoll(roll)"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>
                                <DeleteButton @click="confirmDeleteRoll(roll)" />
                                <button
                                    class="inline-flex items-center justify-center text-gray-500 transition-colors hover:text-blue-600"
                                    title="Descargar rollo"
                                    type="button"
                                    @click="openDownloadWindow(roll)"
                                >
                                    <ArrowDownToLine class="h-4 w-4" />
                                </button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                    </Table>
                    <div v-if="isLoadingRolls" class="p-4 text-center text-sm text-muted-foreground">
                        Cargando rollos...
                    </div>
                    <div
                        v-else-if="!isLoadingRolls && filteredRolls.length === 0"
                        class="p-4 text-center text-sm text-muted-foreground"
                    >
                        No hay rollos disponibles.
                    </div>
                </div>
            </div>
        </div>

        <FabricRollInventoryDialog
            :modelValue="isDialogOpen"
            :roll="selectedRoll"
            @update:modelValue="(value) => (isDialogOpen = value)"
        />
        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminacion"
            description="Estas seguro de que deseas eliminar este rollo? Esta accion no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteRoll"
        />
    </AppLayout>
</template>
