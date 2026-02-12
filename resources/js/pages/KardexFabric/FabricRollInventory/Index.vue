<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { FabricRollView } from '@/domain/fabric';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowDownToLine, Download, Eye, Search, SlidersHorizontal } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import FabricRollAdjustDialog from './FabricRollAdjustDialog.vue';
import FabricRollDownloadDialog from './FabricRollDownloadDialog.vue';
import FabricRollInventoryDialog from './FabricRollInventoryDialog.vue';

const breadcrumbs = [{ title: 'Inventario Telas por Rollo', href: '/kardex-telas/inventario-rollos' }];

const searchQuery = ref('');
const baseFilter = ref('all');
const providerFilter = ref('all');

const baseOptions = ref([
    { id: 'all', name: 'Todas las bases' },
    { id: 'seda', name: 'Seda lavada' },
    { id: 'lino', name: 'Lino stretch' },
    { id: 'algodon', name: 'Algodón peinado' },
]);

const providerOptions = ref([
    { id: 'all', name: 'Todos los proveedores' },
    { id: 'foru', name: 'ForU Tex' },
    { id: 'bogota', name: 'Textiles Bogotá' },
]);

const rolls = ref<FabricRollView[]>([]);
const selectedRoll = ref<FabricRollView | null>(null);
const isDialogOpen = ref(false);
const rollToDelete = ref<FabricRollView | null>(null);
const isDownloadDialogOpen = ref(false);
const rollToDownload = ref<FabricRollView | null>(null);
const isDeleteDialogOpen = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const isLoadingRolls = ref(false);
const isAdjustDialogOpen = ref(false);
const rollToAdjust = ref<FabricRollView | null>(null);
const showDownloadedOnly = ref(false);

const isBaseFilterActive = computed(() => baseFilter.value !== 'all');
const isProviderFilterActive = computed(() => providerFilter.value !== 'all');
const baseFilterButtonClasses = useFilterButtonClasses(isBaseFilterActive);
const providerFilterButtonClasses = useFilterButtonClasses(isProviderFilterActive);

const normalizedValue = (value?: string | null) =>
    (value ?? '')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

const filteredRolls = computed(() => {
    const query = normalizedValue(searchQuery.value);
    const baseValue = baseFilter.value;
    const providerValue = providerFilter.value;
    return rolls.value.filter((roll) => {
        const fieldsToSearch = [roll.roll, roll.location, roll.fabric, roll.color, roll.provider, roll.order_number];
        const matchesSearch = !query || fieldsToSearch.some((field) => normalizedValue(field).includes(query));
        const matchesBase = baseValue === 'all' || normalizedValue(roll.fabric).includes(baseValue);
        const matchesProvider = providerValue === 'all' || normalizedValue(roll.provider).includes(providerValue);
        if (!matchesSearch || !matchesBase || !matchesProvider) {
            return false;
        }

        if (!showDownloadedOnly.value) {
            return true;
        }

        const partialOutputValue = Number(String(roll.partial_output ?? '').replace(/[^\d.-]/g, '')) || 0;
        const exitQuantityValue = Number(String(roll.exit_quantity ?? '').replace(/[^\d.-]/g, '')) || 0;
        return partialOutputValue > 0 || exitQuantityValue > 0;
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
        successMessage.value = 'El rollo se eliminó correctamente.';
        errorMessage.value = '';
    }
    isDeleteDialogOpen.value = false;
}

function exportFabricRollsExcel() {
    window.open('/kardex-telas/exportar-rollos', '_blank');
}

function openDownloadDialog(roll: FabricRollView) {
    rollToDownload.value = roll;
    isDownloadDialogOpen.value = true;
}

function openAdjustDialog(roll?: FabricRollView | null) {
    const target = roll ?? selectedRoll.value ?? null;
    if (!target) {
        errorMessage.value = 'Selecciona un rollo antes de ajustar la salida.';
        return;
    }
    rollToAdjust.value = target;
    isAdjustDialogOpen.value = true;
}

function toggleDownloadedFilter() {
    showDownloadedOnly.value = !showDownloadedOnly.value;
}

function handleDownloadRequest(payload: { roll: FabricRollView | null; data: Record<string, string> }) {
    const { adjustmentType, quantity } = payload.data;
    const rollLabel = payload.roll ? ` (${payload.roll.roll})` : '';
    successMessage.value = `Solicitud de ${adjustmentType || 'descarga'} ${quantity ? `de ${quantity}` : ''}${rollLabel} registrada.`;
    errorMessage.value = '';
    rollToDownload.value = null;
}

async function handleAdjustRequest(payload: { roll: FabricRollView | null; data: Record<string, string> }) {
    const roll = payload.roll;
    if (!roll) {
        errorMessage.value = 'No se encontró el rollo seleccionado.';
        return;
    }
    const exitQuantityValue = Number(roll.exit_quantity ?? 0);
    const partialOutputValue = Number(roll.partial_output ?? 0);
    const actualOutput = payload.data.actualOutput ? Number(payload.data.actualOutput) : exitQuantityValue;
    const partialOutput = payload.data.partialOutput ? Number(payload.data.partialOutput) : partialOutputValue;
    try {
        await axios.patch(`/rollos-tela/${roll.id}/update-output`, {
            actual_output: actualOutput,
            partial_output: partialOutput,
        });
        successMessage.value = `Salida actualizada para el rollo ${roll.roll}.`;
        errorMessage.value = '';
        await fetchFabricRolls();
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message || 'No se pudo actualizar la salida del rollo.';
    } finally {
        isAdjustDialogOpen.value = false;
        rollToAdjust.value = null;
    }
}

function setDownloadDialogOpen(value: boolean) {
    isDownloadDialogOpen.value = value;
    if (!value) {
        rollToDownload.value = null;
    }
}

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
    <Head title="Inventario Telas por Rollo" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="Éxito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Inventario telas por rollo</h1>
                    <p class="text-sm text-muted-foreground">Mantén el control preciso de cada rollo.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        variant="secondary"
                        class="flex h-10 w-full items-center justify-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm md:w-auto"
                        type="button"
                        @click="exportFabricRollsExcel"
                    >
                        <Download class="h-4 w-4" />
                        Exportar inventario
                    </Button>
                    <Button
                        variant="secondary"
                        class="flex h-10 w-full items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm md:w-auto"
                        type="button"
                        @click="toggleDownloadedFilter"
                    >
                        {{ showDownloadedOnly ? 'Ver todos los rollos' : 'Ver descargue por rollo' }}
                    </Button>
                </div>
            </div>

            <FilterBar>
                <FilterField label="Buscar rollo" :active="searchQuery.trim().length > 0" class="min-w-[220px] flex-1 p-0">
                    <div class="relative">
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar rollo"
                            class="h-10 w-full rounded-md border border-gray-200 bg-white pl-10 text-sm focus:border-primary focus:ring-0"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Base tela" :active="baseFilter !== 'all'" class="min-w-[180px]">
                    <SelectField
                        v-model="baseFilter"
                        label=""
                        :options="baseOptions"
                        placeholder="Base tela"
                        :button-class="baseFilterButtonClasses"
                    />
                </FilterField>
                <FilterField label="Proveedor" :active="providerFilter !== 'all'" class="min-w-[180px]">
                    <SelectField
                        v-model="providerFilter"
                        label=""
                        :options="providerOptions"
                        placeholder="Proveedor"
                        :button-class="providerFilterButtonClasses"
                    />
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 bg-white md:min-h-min">
                <div class="overflow-x-auto">
                    <Table class="min-w-full text-[0.9rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Rollo</TableHead>
                                <TableHead>Ubicación</TableHead>
                                <TableHead>Tela</TableHead>
                                <TableHead>Color</TableHead>
                                <TableHead>Ancho</TableHead>
                                <TableHead>Cant. rollo</TableHead>
                                <TableHead>Salida parcial</TableHead>
                                <TableHead>Disponibilidad parcial</TableHead>
                                <TableHead>Cantidad salida</TableHead>
                                <TableHead>Disponibilidad real</TableHead>
                                <TableHead>Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="roll in filteredRolls" :key="roll.id" class="border-b">
                                <TableCell class="text-center text-sm">{{ roll.roll }}</TableCell>
                                <TableCell class="text-center text-sm">
                                    {{ roll.location }}
                                </TableCell>
                                <TableCell class="text-center text-sm">{{ roll.fabric }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.color }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.width }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.roll_qty }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.partial_output }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.partial_availability }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.exit_quantity }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.real_availability }}</TableCell>
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
                                        @click="openDownloadDialog(roll)"
                                    >
                                        <ArrowDownToLine class="h-4 w-4" />
                                    </button>
                                    <button
                                        class="inline-flex items-center justify-center text-gray-500 transition-colors hover:text-blue-600"
                                        title="Ajustar salida"
                                        type="button"
                                        @click="openAdjustDialog(roll)"
                                    >
                                        <SlidersHorizontal class="h-4 w-4" />
                                    </button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-if="isLoadingRolls" class="p-4 text-center text-sm text-muted-foreground">Cargando rollos...</div>
                    <div v-else-if="!isLoadingRolls && filteredRolls.length === 0" class="p-4 text-center text-sm text-muted-foreground">
                        No hay rollos disponibles.
                    </div>
                </div>
            </div>
        </div>

        <FabricRollInventoryDialog :modelValue="isDialogOpen" :roll="selectedRoll" @update:modelValue="(value) => (isDialogOpen = value)" />

        <FabricRollAdjustDialog
            :modelValue="isAdjustDialogOpen"
            :roll="rollToAdjust"
            @update:modelValue="(value) => (isAdjustDialogOpen = value)"
            @submit-adjust="handleAdjustRequest"
        />

        <FabricRollDownloadDialog
            :modelValue="isDownloadDialogOpen"
            :roll="rollToDownload"
            @update:modelValue="setDownloadDialogOpen"
            @submit-download="handleDownloadRequest"
        />

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Estás seguro de que deseas eliminar este rollo? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteRoll"
        />
    </AppLayout>
</template>
