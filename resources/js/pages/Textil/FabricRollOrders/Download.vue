<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { FabricRollView } from '@/domain/Textil';
import AppLayout from '@/layouts/AppLayout.vue';
import RollDownloadFormDialog from '@/pages/Textil/FabricRollOrders/RollDownloadFormDialog.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Search } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type BreadcrumbItem = { title: string; href: string };
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kardex de Telas', href: '/kardex-telas' },
    { title: 'Descargue de tela por orden', href: '/kardex-telas/descargue-orden' },
    { title: 'Descarga detallada', href: '' },
];

const props = defineProps<{
    roll?: FabricRollView | null;
    lotName?: string | null;
    sizes?: string[] | Record<string, boolean> | null;
}>();

const pageTitle = computed(() => {
    return props.lotName ? `Descarga por rollo de ${props.lotName}` : 'Descarga por rollo';
});

const selectedRoll = ref<FabricRollView | null>(props.roll ?? null);
const availableRolls = ref<FabricRollView[]>([]);
const searchQuery = ref('');
const currentPage = ref(1);
const lastPage = ref(1);
const totalRolls = ref(0);
const isLoadingRolls = ref(false);
const itemsPerPage = ref(100);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const activeRoll = computed(() => selectedRoll.value ?? null);
const displayedRolls = computed(() => {
    if (!selectedRoll.value) {
        return availableRolls.value;
    }

    return availableRolls.value.some((roll) => roll.id === selectedRoll.value?.id)
        ? availableRolls.value
        : [selectedRoll.value, ...availableRolls.value];
});

watch(
    () => props.roll,
    (value) => {
        selectedRoll.value = value ?? null;
    },
);

watch(
    availableRolls,
    (list) => {
        if (!selectedRoll.value && list.length > 0) {
            selectedRoll.value = list[0];
        }
    },
    { immediate: true },
);

async function fetchAvailableRolls(page = 1) {
    isLoadingRolls.value = true;

    try {
        const params = new URLSearchParams();
        if (searchQuery.value.trim()) params.set('search', searchQuery.value.trim());
        params.set('page', String(page));
        params.set('per_page', String(itemsPerPage.value));
        params.set('sort_by', 'entry_date');
        params.set('sort_direction', 'desc');

        const response = await axios.get(`/kardex-telas/inventario-rollos/data?${params.toString()}`);
        const payload = response.data;

        availableRolls.value = Array.isArray(payload?.data) ? payload.data : [];
        currentPage.value = payload?.meta?.current_page ?? 1;
        lastPage.value = payload?.meta?.last_page ?? 1;
        totalRolls.value = payload?.meta?.total ?? availableRolls.value.length;
        errorMessage.value = '';
    } catch {
        availableRolls.value = [];
        errorMessage.value = 'No se pudieron cargar los rollos disponibles.';
    } finally {
        isLoadingRolls.value = false;
    }
}

function scheduleRollFetch() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        void fetchAvailableRolls(1);
    }, 350);
}

function openDownloadDialog(roll: FabricRollView) {
    selectedRoll.value = roll;
    isDialogOpen.value = true;
}

const toNumber = (value: string | number | null | undefined): number => {
    if (value === null || value === undefined) return 0;
    const n = typeof value === 'number' ? value : Number(String(value).replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
};

const sizeLabels = computed(() => {
    // Si props.sizes no existe o es null, usar tallas por defecto
    if (!props.sizes) {
        return ['S', 'M', 'L', 'XL'];
    }

    let extractedSizes: string[] = [];

    try {
        if (typeof props.sizes === 'object' && !Array.isArray(props.sizes)) {
            // Extraer solo las claves donde el valor sea estrictamente true (booleano)
            extractedSizes = Object.entries(props.sizes)
                .filter(([, isEnabled]) => isEnabled === true) // Filtrar solo valores true
                .map(([sizeName]) => String(sizeName).trim()) // Tomar solo la clave
                .filter((size) => {
                    // Filtrar claves válidas (no vacías y que no sean "false" o "true")
                    return size.length > 0 && size !== 'false' && size !== 'true';
                })
                .sort((a, b) => {
                    // Ordenar: primero numéricos ascendente, luego alfabéticos
                    const aNum = Number(a);
                    const bNum = Number(b);
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return aNum - bNum;
                    }
                    return a.localeCompare(b);
                });
        }
        // Si es un array
        else if (Array.isArray(props.sizes)) {
            extractedSizes = props.sizes
                .map((s) => String(s).trim())
                .filter((s) => s.length > 0 && s !== 'false' && s !== 'true')
                .sort((a, b) => {
                    const aNum = Number(a);
                    const bNum = Number(b);
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return aNum - bNum;
                    }
                    return a.localeCompare(b);
                });
        }
    } catch (error) {
        console.error('Error al procesar las tallas:', error);
        return ['S', 'M', 'L', 'XL'];
    }

    // Si se extrajeron tallas válidas, usarlas; si no, usar por defecto
    return extractedSizes.length > 0 ? extractedSizes : ['S', 'M', 'L', 'XL'];
});
type SizeKey = string;
const fallbackQuantity = 18;

const baseQuantity = computed(() => {
    const raw = Math.max(0, Math.round(toNumber(activeRoll.value?.quantity_value)));
    return raw || fallbackQuantity;
});

const createCurve = (distribution: number[]) => {
    return sizeLabels.value.map((size, index) => {
        const portion = distribution[index] ?? 0;
        const value = Math.max(1, Math.round(baseQuantity.value * portion));
        return { size, value };
    });
};

const getDefaultDistribution = (numSizes: number) => {
    // Distribución equitativa si no coincide con las distribuciones predefinidas
    if (numSizes === 4) return [0.25, 0.35, 0.25, 0.15];
    const portion = 1 / numSizes;
    return Array(numSizes).fill(portion);
};

const primaryCurve = computed(() => {
    const numSizes = sizeLabels.value.length;
    const distribution = numSizes === 4 ? [0.25, 0.35, 0.25, 0.15] : getDefaultDistribution(numSizes);
    return createCurve(distribution);
});

const liquidationCurve = computed(() => {
    const numSizes = sizeLabels.value.length;
    const distribution = numSizes === 4 ? [0.2, 0.35, 0.25, 0.2] : getDefaultDistribution(numSizes);
    return createCurve(distribution);
});

type CurveEntry = { size: SizeKey; value: number; manual: boolean };

const createManualCurveTemplate = (): Record<SizeKey, string> => {
    const template: Record<string, string> = {};
    sizeLabels.value.forEach((size) => {
        template[size] = '';
    });
    return template;
};

const manualPrimaryCurveValues = reactive<Record<SizeKey, string>>(createManualCurveTemplate());
const manualLiquidationCurveValues = reactive<Record<SizeKey, string>>(createManualCurveTemplate());

const createDisplayCurve = (manualValues: Record<SizeKey, string>, fallbackEntries: { size: string; value: number }[]): CurveEntry[] => {
    return sizeLabels.value.map((size, index) => {
        const key = size as SizeKey;
        const manualValue = manualValues[key];
        const hasManual = manualValue?.trim() !== '';
        const fallbackValue = fallbackEntries[index]?.value ?? 0;
        const displayValue = hasManual ? Math.max(0, toNumber(manualValue)) : fallbackValue;
        return { size: key, value: displayValue, manual: hasManual };
    });
};

const primaryCurveDisplay = computed(() => createDisplayCurve(manualPrimaryCurveValues, primaryCurve.value));
const liquidationCurveDisplay = computed(() => createDisplayCurve(manualLiquidationCurveValues, liquidationCurve.value));

const resetManualCurves = () => {
    Object.assign(manualPrimaryCurveValues, createManualCurveTemplate());
    Object.assign(manualLiquidationCurveValues, createManualCurveTemplate());
};

watch(() => activeRoll.value?.id, resetManualCurves);
watch(() => props.sizes, resetManualCurves, { deep: true });

const colorBaseWeights = [0.5, 0.3, 0.15, 0.05];

const colorBaseSizes = computed(() => {
    const numSizes = sizeLabels.value.length;
    const weights = numSizes === 4 ? colorBaseWeights : Array(numSizes).fill(1 / numSizes);

    return sizeLabels.value.map((_, idx) => {
        const weight = weights[idx] ?? 0;
        return Math.max(0, Math.round(baseQuantity.value * weight));
    });
});

const manualColorSizes = computed(() => {
    return sizeLabels.value.map((size, index) => {
        const primaryValue = manualPrimaryCurveValues[size];
        const liquidationValue = manualLiquidationCurveValues[size];
        const hasManual = (primaryValue ?? '').trim() !== '' || (liquidationValue ?? '').trim() !== '';

        if (!hasManual) {
            return colorBaseSizes.value[index] ?? 0;
        }

        const manualTotal = toNumber(primaryValue) + toNumber(liquidationValue);
        return Math.max(0, manualTotal);
    });
});

const manualColorTotal = computed(() => manualColorSizes.value.reduce((sum, value) => sum + value, 0));

const colorReport = computed(() => {
    const manualRow = {
        label: activeRoll.value?.color ?? 'Rojo',
        sizes: manualColorSizes.value,
        total: manualColorTotal.value,
    };

    const distributions = [
        { label: 'Verde', weights: [0.25, 0.25, 0.25, 0.25] },
        { label: 'Azul', weights: [0.15, 0.35, 0.35, 0.15] },
    ];

    const otherRows = distributions.map(({ label, weights }) => {
        const sizes = sizeLabels.value.map((_, idx) => {
            const weight = weights[idx] ?? 1 / sizeLabels.value.length;
            return Math.max(0, Math.round(baseQuantity.value * weight));
        });
        const total = sizes.reduce((sum, value) => sum + value, 0);
        return { label, sizes, total };
    });

    const rows = [manualRow, ...otherRows];
    const totals = sizeLabels.value.map((_, idx) => rows.reduce((sum, row) => sum + row.sizes[idx], 0));
    const grandTotal = totals.reduce((sum, value) => sum + value, 0);

    return [...rows, { label: 'Total', sizes: totals, total: grandTotal }];
});

function formatMeters(value?: string | number | null) {
    const n = toNumber(value);
    return `${n.toFixed(2)} m`;
}

const isDialogOpen = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

function handleDownloadSubmit(payload: { roll: FabricRollView | null; data: Record<string, unknown> }) {
    const rollLabel = payload.roll?.roll ? ` para el rollo ${payload.roll.roll}` : '';
    successMessage.value = `Descarga registrada${rollLabel}.`;
    errorMessage.value = '';
}

// Función helper para limpiar el formato de las tallas
function cleanSizeLabel(size: string | number): string {
    // Convertir a string y eliminar comillas, corchetes y espacios extra
    return String(size)
        .replace(/"/g, '') // Eliminar todas las comillas dobles
        .replace(/'/g, '') // Eliminar todas las comillas simples
        .replace(/\[|\]/g, '') // Eliminar todos los corchetes
        .trim();
}

watch(searchQuery, scheduleRollFetch);

onMounted(() => {
    void fetchAvailableRolls(1);
});
</script>

<template>
    <Head title="Descarga detallada" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
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
                    <h1 class="text-lg font-bold">{{ pageTitle }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Registra el descargue con la información estructurada para curvas y reportes de colores.
                    </p>
                </div>
            </div>

            <FilterBar>
                <FilterField label="Buscar rollo" :active="searchQuery.trim().length > 0" class="min-w-[220px] flex-1 p-0">
                    <div class="relative">
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar rollo o tela"
                            class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm focus:border-primary focus:ring-0"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
            </FilterBar>

            <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Rollo</TableHead>
                            <TableHead>Ubicación</TableHead>
                            <TableHead>Tela</TableHead>
                            <TableHead>Color</TableHead>
                            <TableHead>Ancho</TableHead>
                            <TableHead>Cantidad real</TableHead>
                            <TableHead>Metros a usar</TableHead>
                            <TableHead class="w-[140px]">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-if="isLoadingRolls">
                            <TableCell colspan="8" class="py-8 text-center text-sm text-muted-foreground">Cargando rollos disponibles...</TableCell>
                        </TableRow>
                        <TableRow v-else-if="displayedRolls.length === 0">
                            <TableCell colspan="8" class="py-8 text-center text-sm text-muted-foreground">No hay rollos disponibles.</TableCell>
                        </TableRow>
                        <TableRow 
                          v-else 
                          v-for="roll in displayedRolls.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage)" 
                          :key="roll.id" 
                          :class="roll.id === selectedRoll?.id ? 'bg-primary/5' : ''"
                        >
                            <TableCell class="font-medium">{{ roll.roll ?? '-' }}</TableCell>
                            <TableCell>{{ roll.location ?? '-' }}</TableCell>
                            <TableCell>{{ roll.fabric ?? '-' }}</TableCell>
                            <TableCell>{{ roll.color ?? 'Color' }}</TableCell>
                            <TableCell>{{ roll.width ?? '0 m' }}</TableCell>
                            <TableCell>{{ roll.exit_quantity ?? '0 m' }}</TableCell>
                            <TableCell>{{ formatMeters(Math.max(0, toNumber(roll.quantity_value) - toNumber(roll.exit_quantity))) }}</TableCell>
                            <TableCell class="flex justify-center">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-9 w-full rounded-full px-4 py-2 text-xs font-semibold shadow-sm md:w-auto"
                                    @click="openDownloadDialog(roll)"
                                >
                                    Descargar
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                  <ServerPagination 
                    v-model:current-page="currentPage"
                    v-model:page-size="itemsPerPage"
                    :total-items="totalRolls"
                    @fetch-data="() => fetchAvailableRolls(currentPage)" 
                  />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <article class="rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-card-foreground">Curva principal</p>
                        <span class="text-xs font-medium text-muted-foreground">Distribución</span>
                    </div>

                    <div class="mt-4 grid gap-4 text-center text-sm sm:grid-cols-2">
                        <div
                            v-for="entry in primaryCurveDisplay"
                            :key="`primary-${entry.size}`"
                            class="flex flex-col items-center gap-1 rounded-xl border border-dashed border-sidebar-border/70 bg-card px-4 py-4 shadow-sm transition hover:border-primary hover:shadow-md"
                        >
                            <span class="text-xs font-medium text-muted-foreground">{{ cleanSizeLabel(entry.size) }}</span>
                            <input
                                v-model="manualPrimaryCurveValues[entry.size]"
                                type="number"
                                min="0"
                                step="1"
                                inputmode="numeric"
                                placeholder="Cantidad"
                                :aria-label="'Valor manual ' + entry.size"
                                :class="[
                                    'mt-2 h-9 w-24 rounded-full border px-2 text-center text-xs focus:outline-none focus-visible:ring-2',
                                    entry.manual
                                        ? 'border-green-400 text-card-foreground focus:border-green-500 focus-visible:ring-green-400'
                                        : 'border-dashed border-primary text-muted-foreground focus:border-primary focus-visible:ring-ring',
                                ]"
                            />
                            <span class="text-[10px] text-muted-foreground">{{ entry.manual ? 'Manual' : 'Unidades' }}</span>
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-card-foreground">Curva liquidación</p>
                        <span class="text-xs font-medium text-muted-foreground">Distribución</span>
                    </div>

                    <div class="mt-4 grid gap-4 text-center text-sm sm:grid-cols-2">
                        <div
                            v-for="entry in liquidationCurveDisplay"
                            :key="`liquid-${entry.size}`"
                            class="flex flex-col items-center gap-1 rounded-xl border border-dashed border-sidebar-border/70 bg-card px-4 py-4 shadow-sm transition hover:border-primary hover:shadow-md"
                        >
                            <span class="text-xs font-medium text-muted-foreground">{{ cleanSizeLabel(entry.size) }}</span>
                            <input
                                v-model="manualLiquidationCurveValues[entry.size]"
                                type="number"
                                min="0"
                                step="1"
                                inputmode="numeric"
                                placeholder="Cantidad"
                                :aria-label="'Valor manual ' + entry.size"
                                :class="[
                                    'mt-2 h-9 w-24 rounded-full border px-2 text-center text-xs focus:outline-none focus-visible:ring-2',
                                    entry.manual
                                        ? 'border-green-400 text-card-foreground focus:border-green-500 focus-visible:ring-green-400'
                                        : 'border-dashed border-primary text-muted-foreground focus:border-primary focus-visible:ring-ring',
                                ]"
                            />
                            <span class="text-[10px] text-muted-foreground">{{ entry.manual ? 'Manual' : 'Unidades' }}</span>
                        </div>
                    </div>
                </article>
            </div>

            <section class="rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
                <p class="text-sm font-semibold text-card-foreground">Reporte de unidades reales</p>

                <div class="mt-3 overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="text-left">Colores</TableHead>
                                <TableHead v-for="label in sizeLabels" :key="`head-${label}`" class="text-center">
                                    {{ cleanSizeLabel(label) }}
                                </TableHead>
                                <TableHead class="text-center">Total</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow
                                v-for="row in colorReport"
                                :key="`color-row-${row.label}`"
                                :class="row.label === 'Total' ? 'font-semibold text-card-foreground' : ''"
                            >
                                <TableCell class="text-left">{{ row.label }}</TableCell>
                                <TableCell v-for="(value, idx) in row.sizes" :key="`cell-${row.label}-${idx}`" class="text-center">
                                    {{ value }}
                                </TableCell>
                                <TableCell class="text-center">{{ row.total }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </section>
        </div>

        <RollDownloadFormDialog
            :modelValue="isDialogOpen"
            :roll="activeRoll"
            @update:modelValue="(value) => (isDialogOpen = value)"
            @submit="handleDownloadSubmit"
        />
    </AppLayout>
</template>
