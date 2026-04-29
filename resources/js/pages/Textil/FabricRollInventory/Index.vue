<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import Input from '@/components/ui/input/Input.vue';
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
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { FabricRollView } from '@/domain/Textil';
import { formatMeters, formatWidthToMeters, toUnitNumber } from '@/domain/Textil';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowDown, ArrowDownToLine, ArrowUp, Download, Eye, Search, SlidersHorizontal, Upload } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import FabricRollAdjustDialog from './FabricRollAdjustDialog.vue';
import FabricRollDownloadDialog from './FabricRollDownloadDialog.vue';
import FabricRollImportDialog from './FabricRollImportDialog.vue';
import FabricRollInventoryDialog from './FabricRollInventoryDialog.vue';
import { uiPatterns } from '@/lib/ui-patterns';

const breadcrumbs = [{ title: 'Inventario Telas por Rollo', href: '/kardex-telas/inventario-rollos' }];
const authStore = useAuthStore();

// ── Filtros ────────────────────────────────────────────────────────────────
const searchQuery = ref('');
const baseFilter = ref('');
const providerFilter = ref('');
const showDownloadedOnly = ref(false);

// ── Datos ──────────────────────────────────────────────────────────────────
const rolls = ref<FabricRollView[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const totalRolls = ref(0);
const PER_PAGE = 100;

// ── Sorting ────────────────────────────────────────────────────────────────
type SortColumn = 'roll_number' | 'location' | 'fabric' | 'color' | 'width' | 'quantity' | 'partial_output' | 'actual_output' | 'provider' | 'entry_date';
const sortColumn = ref<SortColumn>('entry_date');
const sortDirection = ref<'asc' | 'desc'>('desc');

function setSort(column: SortColumn) {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
    fetchFabricRolls(1);
}

// ── Estado UI ──────────────────────────────────────────────────────────────
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
const isImportDialogOpen = ref(false);

const canImportRolls = computed(() => authStore.can('fabric_rolls.import'));

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

// ── Fetch ──────────────────────────────────────────────────────────────────
async function fetchFabricRolls(page = 1) {
    isLoadingRolls.value = true;
    errorMessage.value = '';
    try {
        const params = new URLSearchParams();
        if (searchQuery.value.trim()) params.set('search', searchQuery.value.trim());
        if (baseFilter.value.trim()) params.set('base_fabric', baseFilter.value.trim());
        if (providerFilter.value.trim()) params.set('provider', providerFilter.value.trim());
        if (showDownloadedOnly.value) params.set('with_output', '1');
        params.set('page', String(page));
        params.set('per_page', String(PER_PAGE));
        params.set('sort_by', sortColumn.value);
        params.set('sort_direction', sortDirection.value);

        const response = await axios.get(`/kardex-telas/inventario-rollos/data?${params.toString()}`);
        const payload = response.data;
        rolls.value = Array.isArray(payload?.data) ? payload.data : [];
        currentPage.value = payload?.meta?.current_page ?? 1;
        lastPage.value = payload?.meta?.last_page ?? 1;
        totalRolls.value = payload?.meta?.total ?? rolls.value.length;
    } catch {
        rolls.value = [];
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

function handlePageChange(page: number) {
    if (!page || page === currentPage.value) return;
    currentPage.value = page;
    fetchFabricRolls(page);
}

watch([searchQuery, baseFilter, providerFilter, showDownloadedOnly], scheduleRefetch);

onMounted(() => {
    authStore.fetchUser().catch(() => undefined);
    fetchFabricRolls(1);
});

// ── Acciones ───────────────────────────────────────────────────────────────
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
        totalRolls.value = Math.max(0, totalRolls.value - 1);
        successMessage.value = 'El rollo se eliminó correctamente.';
        errorMessage.value = '';
    }
    isDeleteDialogOpen.value = false;
}

function exportFabricRollsExcel() {
    window.open('/kardex-telas/exportar-rollos', '_blank');
}

function exportImportTemplateCsv() {
    const headers = [
        'roll_number',
        'location',
        'base_tela',
        'tela',
        'internal_color_name',
        'internal_color_code',
        'roll_width',
        'quantity',
        'partial_output',
        'actual_output',
        'proveedor',
        'purchase_order_ref',
        'entry_date',
        'item_referencia',
    ];

    const csvContent = `${headers.join(',')}\n`;
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = 'plantilla_importacion_rollos.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function openImportDialog() {
    isImportDialogOpen.value = true;
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
    const exitQuantityValue = toUnitNumber(roll.exit_quantity) ?? 0;
    const partialOutputValue = toUnitNumber(roll.partial_output) ?? 0;
    const actualOutput = payload.data.actualOutput ? Number(payload.data.actualOutput) : exitQuantityValue;
    const partialOutput = payload.data.partialOutput ? Number(payload.data.partialOutput) : partialOutputValue;
    try {
        await axios.patch(`/rollos-tela/${roll.id}/update-output`, {
            actual_output: actualOutput,
            partial_output: partialOutput,
        });
        successMessage.value = `Salida actualizada para el rollo ${roll.roll}.`;
        errorMessage.value = '';
        await fetchFabricRolls(currentPage.value);
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message || 'No se pudo actualizar la salida del rollo.';
    } finally {
        isAdjustDialogOpen.value = false;
        rollToAdjust.value = null;
    }
}

function setDownloadDialogOpen(value: boolean) {
    isDownloadDialogOpen.value = value;
    if (!value) rollToDownload.value = null;
}

async function handleImportCompleted(payload: { inserted: number; skipped: number; errors: string[] }) {
    successMessage.value = `Importación completada. Insertados: ${payload.inserted}. Omitidos: ${payload.skipped}.`;
    errorMessage.value = payload.errors.length > 0
        ? payload.errors.slice(0, 3).join(' | ')
        : '';

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
                                class="flex h-10 w-full items-center justify-center rounded-full px-5 py-2 text-sm font-semibold md:w-auto"
                                type="button"
                            >
                                Otras acciones
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuItem v-if="canImportRolls" class="cursor-pointer gap-2" @click="openImportDialog">
                                <Upload class="h-4 w-4" /> Importar rollos
                            </DropdownMenuItem>
                            <DropdownMenuItem class="cursor-pointer gap-2" @click="exportFabricRollsExcel">
                                <Download class="h-4 w-4" /> Exportar inventario
                            </DropdownMenuItem>
                            <DropdownMenuItem class="cursor-pointer gap-2" @click="exportImportTemplateCsv">
                                <Download class="h-4 w-4" /> Exportar plantilla
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
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
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <div class="overflow-x-auto">
                    <Table class="min-w-full text-[0.9rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="cursor-pointer select-none hover:bg-muted/50" @click="setSort('roll_number')">
                                    <span class="inline-flex items-center gap-0.5">
                                        Rollo
                                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'roll_number'" class="h-3 w-3" />
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none hover:bg-muted/50" @click="setSort('location')">
                                    <span class="inline-flex items-center gap-0.5">
                                        Ubicación
                                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'location'" class="h-3 w-3" />
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none hover:bg-muted/50" @click="setSort('fabric')">
                                    <span class="inline-flex items-center gap-0.5">
                                        Tela
                                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'fabric'" class="h-3 w-3" />
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none hover:bg-muted/50" @click="setSort('color')">
                                    <span class="inline-flex items-center gap-0.5">
                                        Color
                                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'color'" class="h-3 w-3" />
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none hover:bg-muted/50" @click="setSort('width')">
                                    <span class="inline-flex items-center gap-0.5">
                                        Ancho
                                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'width'" class="h-3 w-3" />
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none hover:bg-muted/50" @click="setSort('quantity')">
                                    <span class="inline-flex items-center gap-0.5">
                                        Cant. rollo
                                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'quantity'" class="h-3 w-3" />
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none hover:bg-muted/50" @click="setSort('partial_output')">
                                    <span class="inline-flex items-center gap-0.5">
                                        Salida parcial
                                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'partial_output'" class="h-3 w-3" />
                                    </span>
                                </TableHead>
                                <TableHead>Disp. parcial</TableHead>
                                <TableHead class="cursor-pointer select-none hover:bg-muted/50" @click="setSort('actual_output')">
                                    <span class="inline-flex items-center gap-0.5">
                                        Cant. salida
                                        <component :is="sortDirection === 'asc' ? ArrowUp : ArrowDown" v-if="sortColumn === 'actual_output'" class="h-3 w-3" />
                                    </span>
                                </TableHead>
                                <TableHead>Disp. real</TableHead>
                                <TableHead>Acciones</TableHead>
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
                            <TableRow v-else-if="rolls.length === 0">
                                <TableCell colspan="11" class="py-8 text-center text-sm text-muted-foreground">No hay rollos disponibles.</TableCell>
                            </TableRow>
                            <TableRow v-for="roll in rolls" v-else :key="roll.id" class="border-b">
                                <TableCell class="text-center text-sm">{{ roll.roll }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.location }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.fabric }}</TableCell>
                                <TableCell class="text-center text-sm">{{ roll.color }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatWidthToMeters(roll.width) }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatMeters(roll.roll_qty) }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatMeters(roll.partial_output) }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatMeters(roll.partial_availability) }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatMeters(roll.exit_quantity) }}</TableCell>
                                <TableCell class="text-center text-sm text-foreground/90">{{ formatMeters(roll.real_availability) }}</TableCell>
                                <TableCell class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                            title="Ver rollo"
                                            type="button"
                                            @click="selectRoll(roll)"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </button>
                                        <DeleteButton @click="confirmDeleteRoll(roll)" />
                                        <button
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                            title="Descargar rollo"
                                            type="button"
                                            @click="openDownloadDialog(roll)"
                                        >
                                            <ArrowDownToLine class="h-4 w-4" />
                                        </button>
                                        <button
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-foreground/80 transition hover:bg-muted/60 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                            title="Ajustar salida"
                                            type="button"
                                            @click="openAdjustDialog(roll)"
                                        >
                                            <SlidersHorizontal class="h-4 w-4" />
                                        </button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Paginación -->
                <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <p class="text-xs text-muted-foreground">
                        Página {{ currentPage }} de {{ lastPage }} · Mostrando {{ rolls.length }} de {{ totalRolls }} registros
                    </p>

                    <Pagination
                        v-if="lastPage > 1"
                        class="flex justify-center"
                        :items-per-page="PER_PAGE"
                        :total="totalRolls"
                        :sibling-count="1"
                        show-edges
                        :page="currentPage"
                        @update:page="handlePageChange"
                    >
                        <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                            <PaginationFirst />
                            <PaginationPrev />

                            <template v-for="(item, index) in items" :key="index">
                                <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                    <Button class="h-6 w-6 p-0 text-xs" :variant="item.value === currentPage ? 'default' : 'outline'">
                                        {{ item.value }}
                                    </Button>
                                </PaginationListItem>
                                <PaginationEllipsis v-else :index="index" />
                            </template>

                            <PaginationNext />
                            <PaginationLast />
                        </PaginationList>
                    </Pagination>
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

        <FabricRollImportDialog
            :modelValue="isImportDialogOpen"
            @update:modelValue="(value) => (isImportDialogOpen = value)"
            @imported="handleImportCompleted"
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
