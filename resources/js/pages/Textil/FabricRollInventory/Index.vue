<script setup lang="ts">
import ActionIconButton from '@/components/common/ActionIconButton.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import PillTabs from '@/components/common/PillTabs.vue';
import { Tabs } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import TableRowActions from '@/components/common/TableRowActions.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { FabricRollView, FabricRollViewDto } from '@/domain/Textil';
import { formatMeters, formatWidthToMeters, mapFabricRollViewDto } from '@/domain/Textil';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { useAuthStore } from '@/stores/auth';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowDownToLine, Download, Eye, FileSpreadsheet, Link, Search, Upload } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import FabricRollDownloadDialog from './FabricRollDownloadDialog.vue';
import FabricRollInventoryDialog from './FabricRollInventoryDialog.vue';
import FabricRollLinkItemDialog from './FabricRollLinkItemDialog.vue';
import FabricRollQuantityImportDialog from './FabricRollQuantityImportDialog.vue';
import FabricRollCsvImportDialog from './FabricRollCsvImportDialog.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';

const breadcrumbs = [{ title: 'Inventario Telas por Rollo', href: '/kardex-telas/inventario-rollos' }];
const authStore = useAuthStore();

// Filtros
const searchQuery = ref('');
const baseFilter = ref('');
const providerFilter = ref('');
const filterNoItem = ref(false);
// 'available' = stock disponible (aprobado); 'restricted' = rollos rechazados con nota crédito pendiente.
const viewMode = ref<'available' | 'restricted'>('available');
const isRestrictedMode = computed(() => viewMode.value === 'restricted');

// Datos
const rolls = ref<FabricRollView[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const totalRolls = ref(0);
const perPage = ref(100);

// Sorting
type SortColumn =
    | 'roll_number'
    | 'location'
    | 'width'
    | 'quantity'
    | 'partial_output'
    | 'partial_availability'
    | 'actual_output'
    | 'real_availability'
    | 'provider'
    | 'entry_date';
const sortColumn = ref<SortColumn>('roll_number');
const sortDirection = ref<'asc' | 'desc'>('asc');

function setSort(column: SortColumn) {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
    if (column !== 'partial_availability' && column !== 'real_availability') {
        fetchFabricRolls(1);
    }
}

const sortedRolls = computed(() => {
    if (sortColumn.value !== 'partial_availability' && sortColumn.value !== 'real_availability') {
        return rolls.value;
    }

    const sorted = [...rolls.value];

    sorted.sort((a, b) => {
        const aValue =
            sortColumn.value === 'partial_availability'
                ? (a.partial_availability_value ?? 0)
                : (a.real_availability_value ?? 0);

        const bValue =
            sortColumn.value === 'partial_availability'
                ? (b.partial_availability_value ?? 0)
                : (b.real_availability_value ?? 0);

        return sortDirection.value === 'asc' ? aValue - bValue : bValue - aValue;
    });

    return sorted;
});

// Estado UI
const selectedRoll = ref<FabricRollView | null>(null);
const isDialogOpen = ref(false);
const rollToDelete = ref<FabricRollView | null>(null);
const isDownloadDialogOpen = ref(false);
const rollToDownload = ref<FabricRollView | null>(null);
const isDeleteDialogOpen = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'error' | 'warning' | 'info'>('error');
const isLoadingRolls = ref(false);

const isLinkItemDialogOpen = ref(false);
const rollToLink = ref<FabricRollView | null>(null);
const isQuantityImportDialogOpen = ref(false);
const isCsvImportDialogOpen = ref(false);

const canSuperadminManageChanges = computed(() => authStore.hasRole('superadmin'));
const canEditRolls = computed(() => authStore.can('editar telas rollos'));
const canAdjustRolls = computed(() => authStore.can('ajustar telas rollos'));

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

// Fetch
async function fetchFabricRolls(page = 1) {
    isLoadingRolls.value = true;
    errorMessage.value = '';
    try {
        const params = new URLSearchParams();
        if (searchQuery.value.trim()) params.set('search', searchQuery.value.trim());
        if (baseFilter.value.trim()) params.set('base_fabric', baseFilter.value.trim());
        if (providerFilter.value.trim()) params.set('provider', providerFilter.value.trim());
        if (filterNoItem.value) params.set('no_item', '1');
        if (isRestrictedMode.value) params.set('restricted', '1');
        params.set('page', String(page));
        params.set('per_page', String(perPage.value));
        params.set('sort_by', sortColumn.value);
        params.set('sort_direction', sortDirection.value);

        const response = await axios.get(`/kardex-telas/inventario-rollos/data?${params.toString()}`);
        const payload = response.data;
        const rows = Array.isArray(payload?.data) ? payload.data : [];
        rolls.value = rows.map((row: FabricRollViewDto) => mapFabricRollViewDto(row));
        currentPage.value = payload?.meta?.current_page ?? 1;
        lastPage.value = payload?.meta?.last_page ?? 1;
        totalRolls.value = payload?.meta?.total ?? rolls.value.length;
    } catch {
        rolls.value = [];
        errorType.value = 'error';
        errorMessage.value = 'No se pudo cargar el inventario de rollos.';
    } finally {
        isLoadingRolls.value = false;
    }
}

function scheduleRefetch() {
    if (debounceTimer) clearTimeout(debounceTimer);
    currentPage.value = 1;
    debounceTimer = setTimeout(() => fetchFabricRolls(1), 350);
}

watch([searchQuery, baseFilter, providerFilter, filterNoItem, viewMode], scheduleRefetch);

function restrictedBadgeLabel(roll: FabricRollView): string {
    return roll.credit_note_number ? `Restringido · Nota N° ${roll.credit_note_number}` : 'Restringido · pendiente nota crédito';
}

function restrictedTooltip(roll: FabricRollView): string {
    const reason = roll.restriction_reason?.trim() ? roll.restriction_reason.trim() : 'Sin motivo registrado';
    return `Motivo del rechazo: ${reason}`;
}

onMounted(() => {
    authStore.fetchUser().catch(() => undefined);
    fetchFabricRolls(1);
});

// Acciones
function selectRoll(roll: FabricRollView) {
    selectedRoll.value = roll;
    isDialogOpen.value = true;
}

function confirmDeleteRoll(roll: FabricRollView) {
    if (!canSuperadminManageChanges.value) {
        errorType.value = 'warning';
        errorMessage.value = 'Solo superadmin puede eliminar rollos del inventario.';
        return;
    }

    rollToDelete.value = roll;
    isDeleteDialogOpen.value = true;
}

function deleteRoll() {
    if (!rollToDelete.value) return;
    const index = rolls.value.findIndex((r) => r.id === rollToDelete.value?.id);
    if (index !== -1) {
        rolls.value.splice(index, 1);
        totalRolls.value = Math.max(0, totalRolls.value - 1);
        successMessage.value = 'El rollo se eliminó correctamente.';
        errorMessage.value = '';
    }
    isDeleteDialogOpen.value = false;
}

function exportFabricRollsExcel() {
    window.open('/kardex-telas/exportar-rollos', '_blank');
}

function exportTemplate() {
    window.open('/fabric-rolls/descargar-plantilla', '_blank');
}

function openDownloadDialog(roll: FabricRollView) {
    rollToDownload.value = roll;
    isDownloadDialogOpen.value = true;
}

function handleDischargeSuccess(payload: {
    roll: FabricRollView | null;
    partialOutputValue: number;
    partialAvailabilityValue: number;
    realAvailabilityValue: number;
    rollQtyValue: number;
    exitQuantityValue: number;
    hasDischarges: boolean;
}) {
    if (!payload.roll) {
        return;
    }

    const index = rolls.value.findIndex((roll) => roll.id === payload.roll?.id);

    if (index !== -1) {
        const currentRoll = rolls.value[index];
        rolls.value[index] = {
            ...currentRoll,
            roll_qty_value: payload.rollQtyValue,
            roll_qty: formatMeters(payload.rollQtyValue),
            partial_output_value: payload.partialOutputValue,
            partial_output: formatMeters(payload.partialOutputValue),
            partial_availability_value: payload.partialAvailabilityValue,
            partial_availability: formatMeters(payload.partialAvailabilityValue),
            exit_quantity_value: payload.exitQuantityValue,
            exit_quantity: formatMeters(payload.exitQuantityValue),
            real_availability_value: payload.realAvailabilityValue,
            real_availability: formatMeters(payload.realAvailabilityValue),
            status: payload.realAvailabilityValue <= 0 ? 'depleted' : 'available',
            has_discharges: payload.hasDischarges,
        };
    }

    successMessage.value = `Ajuste registrado para el rollo ${payload.roll.roll}.`;
    errorMessage.value = '';
    rollToDownload.value = null;
}

function handleDischargeError(payload: { message: string }) {
    errorType.value = 'error';
    errorMessage.value = payload.message;
}

function setDownloadDialogOpen(value: boolean) {
    isDownloadDialogOpen.value = value;
    if (!value) rollToDownload.value = null;
}

function handleQuantityCorrected(payload: { rollId: number; newQuantity: number }) {
    const idx = rolls.value.findIndex((r) => r.id === payload.rollId);
    if (idx !== -1) {
        const roll = rolls.value[idx];
        const newQty = payload.newQuantity;
        const actualOutput = roll.exit_quantity_value ?? 0;
        const partialOutput = roll.partial_output_value ?? 0;
        const realAvail = newQty + (roll.length_adjustment_meters ?? 0) - actualOutput;
        const partialAvail = Math.max(0, realAvail - partialOutput);
        rolls.value[idx] = {
            ...roll,
            roll_qty_value: newQty,
            roll_qty: formatMeters(newQty),
            real_availability_value: realAvail,
            real_availability: formatMeters(realAvail),
            partial_availability_value: partialAvail,
            partial_availability: formatMeters(partialAvail),
            status: realAvail <= 0 ? 'depleted' : 'available',
        };
        if (selectedRoll.value?.id === payload.rollId) {
            selectedRoll.value = rolls.value[idx];
        }
    }
    successMessage.value = 'Cantidad del rollo corregida correctamente.';
    errorMessage.value = '';
}

function handlePriceUpdated(payload: { rollId: number; pricePerMeter: number }) {
    const idx = rolls.value.findIndex((r) => r.id === payload.rollId);
    if (idx !== -1) {
        rolls.value[idx] = { ...rolls.value[idx], price_per_meter: payload.pricePerMeter };
        if (selectedRoll.value?.id === payload.rollId) {
            selectedRoll.value = { ...selectedRoll.value, price_per_meter: payload.pricePerMeter };
        }
    }
    successMessage.value = 'Precio por metro actualizado correctamente.';
    errorMessage.value = '';
}

async function handleMainFabricUpdated(payload: { rollId: number; itemId: number; itemName: string; message: string }) {
    const rollIndex = rolls.value.findIndex((roll) => roll.id === payload.rollId);

    if (rollIndex !== -1) {
        rolls.value[rollIndex] = {
            ...rolls.value[rollIndex],
            fabric_item_id: payload.itemId,
            fabric: payload.itemName,
        };
    }

    successMessage.value = payload.message;
    errorMessage.value = '';
    errorType.value = 'error';

    await fetchFabricRolls(currentPage.value);
}

function openLinkItemDialog(roll: FabricRollView) {
    rollToLink.value = roll;
    isLinkItemDialogOpen.value = true;
}

async function handleLinkItemSuccess(payload: { rollId: number; itemId: number; itemName: string; message: string }) {
    const rollIndex = rolls.value.findIndex((r) => r.id === payload.rollId);
    if (rollIndex !== -1) {
        rolls.value[rollIndex] = {
            ...rolls.value[rollIndex],
            fabric_item_id: payload.itemId,
            fabric: payload.itemName,
        };
    }
    successMessage.value = payload.message;
    errorMessage.value = '';
    isLinkItemDialogOpen.value = false;
    rollToLink.value = null;
}

async function handleQuantityImportCompleted(payload: { message: string; updated: number; skipped: number; errors: string[] }) {
    successMessage.value = `Importación completada. Actualizados: ${payload.updated}. Omitidos: ${payload.skipped}.`;
    if (payload.errors.length > 0) {
        errorType.value = 'warning';
        errorMessage.value = payload.errors.slice(0, 3).join(' | ');
    } else {
        errorMessage.value = '';
    }
    await fetchFabricRolls(1);
}

async function handleCsvImported(payload: { inserted: number; skipped: number; errors: { row: number | null; message: string }[] }) {
    isCsvImportDialogOpen.value = false;
    successMessage.value = `Archivo importado. Insertados: ${payload.inserted}. Omitidos: ${payload.skipped}.`;
    if (payload.errors.length > 0) {
        errorType.value = 'warning';
        errorMessage.value = payload.errors.slice(0, 3).map((e) => e.message).join(' | ');
    } else {
        errorMessage.value = '';
    }
    await fetchFabricRolls(1);
}
</script>

<template>
    <Head title="Inventario Telas por Rollo" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                success-title="Éxito"
                error-title="Error"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Inventario telas por rollo</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">Mantén el control preciso de cada rollo.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="outline"
                                size="default"
                                class="flex h-10 items-center justify-center rounded-full px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10"
                                type="button"
                            >
                                <span>Otras acciones</span>
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            align="end"
                            side="bottom"
                            :side-offset="1"
                            :avoid-collisions="false"
                            :collision-padding="0"
                            class="w-56"
                        >
                            <DropdownMenuItem class="cursor-pointer gap-2" @click="exportTemplate">
                                <FileSpreadsheet class="h-4 w-4" /> Exportar plantilla
                            </DropdownMenuItem>

                            <DropdownMenuItem v-if="canEditRolls" class="cursor-pointer gap-2" @click="isQuantityImportDialogOpen = true">
                                <Upload class="h-4 w-4" /> Importar cantidades
                            </DropdownMenuItem>

                            <DropdownMenuItem v-if="canEditRolls" class="cursor-pointer gap-2" @click="isCsvImportDialogOpen = true">
                                <Upload class="h-4 w-4" /> Importar histórico de rollos
                            </DropdownMenuItem>

                            <DropdownMenuItem class="cursor-pointer gap-2" @click="exportFabricRollsExcel">
                                <Download class="h-4 w-4" /> Exportar inventario
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <Tabs v-model="viewMode">
                <PillTabs
                    :tabs="[
                        { value: 'available', label: 'Disponibles' },
                        { value: 'restricted', label: 'Restringidos' },
                    ]"
                />
            </Tabs>

            <FilterBar>
                <FilterField label="Buscar rollo" :active="searchQuery.trim().length > 0" class="min-w-[220px] flex-1 p-0">
                    <div class="relative">
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar rollo"
                            class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm focus:border-primary focus:ring-0"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Base tela" :active="baseFilter.trim().length > 0" class="min-w-[180px] p-0">
                    <div class="relative">
                        <Input
                            v-model="baseFilter"
                            placeholder="Filtrar por base tela"
                            class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search class="h-4 w-4 text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Proveedor" :active="providerFilter.trim().length > 0" class="min-w-[180px] p-0">
                    <div class="relative">
                        <Input
                            v-model="providerFilter"
                            placeholder="Filtrar por proveedor"
                            class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search class="h-4 w-4 text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
                <FilterField label="Sin ítem" :active="filterNoItem" class="p-0">
                    <button
                        type="button"
                        class="flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm transition hover:bg-muted/60"
                        :class="{ 'border-primary bg-primary/5 font-medium text-primary': filterNoItem }"
                        @click="filterNoItem = !filterNoItem"
                    >
                        <Link class="h-4 w-4" />
                        Sin ítem vinculado
                    </button>
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <div class="overflow-x-auto">
                    <Table class="min-w-full text-[0.9rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="setSort('roll_number')">
                                    <span class="inline-flex items-center gap-1">
                                        Rollo
                                        <span class="text-muted-foreground">
                                            <template v-if="sortColumn === 'roll_number'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>

                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="setSort('location')">
                                    <span class="inline-flex items-center gap-1">
                                        Ubicación
                                        <span class="text-muted-foreground">
                                            <template v-if="sortColumn === 'location'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>

                                <TableHead>Tela</TableHead>

                                <TableHead>Color</TableHead>

                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="setSort('width')">
                                    <span class="inline-flex items-center gap-1">
                                        Ancho
                                        <span class="text-muted-foreground">
                                            <template v-if="sortColumn === 'width'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>

                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="setSort('quantity')">
                                    <span class="inline-flex items-center gap-1">
                                        Cant. rollo
                                        <span class="text-muted-foreground">
                                            <template v-if="sortColumn === 'quantity'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>

                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="setSort('partial_output')">
                                    <span class="inline-flex items-center gap-1">
                                        Salida parcial
                                        <span class="text-muted-foreground">
                                            <template v-if="sortColumn === 'partial_output'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>

                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="setSort('partial_availability')">
                                    <span class="inline-flex items-center gap-1">
                                        Disp. parcial
                                        <span class="text-muted-foreground">
                                            <template v-if="sortColumn === 'partial_availability'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>

                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="setSort('actual_output')">
                                    <span class="inline-flex items-center gap-1">
                                        Cant. salida
                                        <span class="text-muted-foreground">
                                            <template v-if="sortColumn === 'actual_output'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>

                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="setSort('real_availability')">
                                    <span class="inline-flex items-center gap-1">
                                        Disp. real
                                        <span class="text-muted-foreground">
                                            <template v-if="sortColumn === 'real_availability'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>

                                <TableHead>{{ isRestrictedMode ? 'Restricción' : 'Acciones' }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoadingRolls">
                                <TableRow v-for="i in 5" :key="`fabric-roll-skeleton-${i}`">
                                    <TableCell v-for="col in 11" :key="`fabric-roll-skeleton-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <TableRow v-else-if="sortedRolls.length === 0">
                                <TableCell colspan="11" class="py-8 text-center text-sm text-muted-foreground">{{ isRestrictedMode ? 'No hay rollos restringidos.' : 'No hay rollos disponibles.' }}</TableCell>
                            </TableRow>
                            <TableRow v-for="roll in sortedRolls" v-else :key="roll.id" class="border-b">
                                <TableCell class="text-center text-sm">{{ roll.roll }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.location }}</TableCell>
                                <TableCell class="text-center text-sm">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span>{{ roll.fabric }}</span>
                                        <span
                                            v-if="!roll.fabric_item_id"
                                            class="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                            title="Este rollo no está vinculado a un ítem del inventario"
                                        >
                                            Sin ítem
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center text-sm">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span
                                            v-if="roll.color_code"
                                            class="inline-block h-3.5 w-3.5 shrink-0 rounded-full border border-black/10"
                                            :style="{ backgroundColor: roll.color_code }"
                                            :title="roll.color"
                                        />
                                        <span>{{ roll.color !== 'N/A' ? roll.color : '-' }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatWidthToMeters(roll.width) }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatMeters(roll.roll_qty) }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatMeters(roll.partial_output) }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatMeters(roll.partial_availability) }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatMeters(roll.exit_quantity) }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatMeters(roll.real_availability) }}</TableCell>
                                <TableCell v-if="isRestrictedMode" class="text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span>
                                                        <TableStatusBadge :label="restrictedBadgeLabel(roll)" tone="warning" />
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent>{{ restrictedTooltip(roll) }}</TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <span v-if="roll.invoice_number" class="text-xs text-muted-foreground">Factura {{ roll.invoice_number }}</span>
                                    </div>
                                </TableCell>
                                <TableCell v-else class="text-center">
                                    <TableRowActions>
                                        <ActionIconButton :icon="Eye" tooltip="Ver rollo" @click="selectRoll(roll)" />
                                        <ActionIconButton
                                            v-if="canAdjustRolls"
                                            :icon="ArrowDownToLine"
                                            tooltip="Ajustar rollo (entrada/salida trazable)"
                                            @click="openDownloadDialog(roll)"
                                        />
                                        <ActionIconButton
                                            v-if="canEditRolls && (!roll.fabric_item_id || !roll.has_discharges)"
                                            :icon="Link"
                                            :tooltip="roll.fabric_item_id ? 'Cambiar ítem vinculado' : 'Vincular ítem (sin vínculo)'"
                                            :class="roll.fabric_item_id ? '' : 'text-amber-600 hover:text-amber-700'"
                                            @click="openLinkItemDialog(roll)"
                                        />
                                        <DeleteButton v-if="canSuperadminManageChanges && !roll.has_discharges" @click="confirmDeleteRoll(roll)" />
                                    </TableRowActions>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Paginación -->
                <div class="mt-4">
                  <ServerPagination 
                    v-model:current-page="currentPage"
                                        v-model:page-size="perPage"
                    :total-items="totalRolls"
                    @fetch-data="fetchFabricRolls"
                  />
                </div>
            </div>
        </div>

        <FabricRollInventoryDialog
            :modelValue="isDialogOpen"
            :roll="selectedRoll"
            :can-edit-main-fabric="canSuperadminManageChanges"
            :can-adjust-rolls="canAdjustRolls"
            @update:modelValue="(value) => (isDialogOpen = value)"
            @main-fabric-updated="handleMainFabricUpdated"
            @quantity-corrected="handleQuantityCorrected"
            @price-updated="handlePriceUpdated"
        />


        <FabricRollDownloadDialog
            :modelValue="isDownloadDialogOpen"
            :roll="rollToDownload"
            @update:modelValue="setDownloadDialogOpen"
            @discharge-success="handleDischargeSuccess"
            @discharge-error="handleDischargeError"
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

        <FabricRollLinkItemDialog
            :model-value="isLinkItemDialogOpen"
            :roll="rollToLink"
            @update:model-value="(v) => { isLinkItemDialogOpen = v; if (!v) rollToLink = null; }"
            @linked="handleLinkItemSuccess"
        />

        <FabricRollQuantityImportDialog
            :model-value="isQuantityImportDialogOpen"
            @update:model-value="(v) => (isQuantityImportDialogOpen = v)"
            @imported="handleQuantityImportCompleted"
        />

        <FabricRollCsvImportDialog
            :open="isCsvImportDialogOpen"
            @update:open="(v) => (isCsvImportDialogOpen = v)"
            @imported="handleCsvImported"
        />
    </AppLayout>
</template>
