<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import SelectField from '@/components/SelectField.vue';
import ProcessesTab from './tabs/ProcessesTab.vue';
import CostsTab from './tabs/CostsTab.vue';
import SuppliesTab from './tabs/SuppliesTab.vue';
import RollsTab from './tabs/RollsTab.vue';
import ThreadsTab from './tabs/ThreadsTab.vue';
import RawMaterialTab from './tabs/RawMaterialTab.vue';
import AdditionalInfoTab from './tabs/AdditionalInfoTab/index.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Box, ChevronDown, ChevronUp, ClipboardList, DollarSign, FilePlus2, Layers, Package, Volleyball, Workflow } from 'lucide-vue-next';
import type { BreadcrumbItem, Option } from '@/domain/shared';
import type { OpBasicInfo, OperationalList } from '@/domain/Production/TechnicalDataSheet';
import {
    assignOperationalListToOp,
    fetchOpBasicInfo,
    fetchOperationalListOptions,
    fetchOpOperationalList,
    fetchOpOptions,
} from '@/domain/Production/TechnicalDataSheet';

const props = defineProps<{
    initialOpId: string | number | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Produccion', href: '/produccion' },
    { title: 'Ficha Tecnica', href: '/produccion/ficha-tecnica' },
];

const selectedOpId = ref<string | number | null>(props.initialOpId ?? null);
const opOptions = ref<Option[]>([]);
const selectedOp = ref<OpBasicInfo | null>(null);
const isLoadingOptions = ref(false);
const isLoadingDetail = ref(false);
const isDrawingOpen = ref(false);
const operationalList = ref<OperationalList | null>(null);
const hasOperationalList = ref(false);
const isLoadingOperationalList = ref(false);
const operationalListOptions = ref<Option[]>([]);
const selectedOperationalListId = ref<string | number | null>(null);
const isAssigningOperationalList = ref(false);
const operationalListMessage = ref<string | null>(null);
const isBasicInfoCollapsed = ref(false);
type RawMaterialsInfoRow = {
    uid: string;
    tela: string;
    ancho: number | null;
    promTrazo: number;
    largoTrazo: number;
    largoLiq: number;
    consumo: number;
    precioTela: number | null;
    proveedor: string | null;
};
const rawMaterialsInfo = ref<RawMaterialsInfoRow[]>([]);

const drawingUrl = computed(() => {
    const op = selectedOp.value as (OpBasicInfo & { drawingUrl?: string | null; imageUrl?: string | null }) | null;
    return op?.flatDrawingUrl ?? op?.drawingUrl ?? op?.imageUrl ?? null;
});
const hasDrawing = computed(() => Boolean(drawingUrl.value));
const isSampleOp = computed(() => String(selectedOp.value?.type ?? selectedOp.value?.opCode ?? '').toUpperCase().startsWith('MU'));
const displayedSecondReference = computed(() => {
    const op = selectedOp.value as (OpBasicInfo & {
        sampleReference?: string | null;
        productReference?: string | null;
        sample_reference?: string | null;
        product_reference?: string | null;
    }) | null;
    if (!op) return '-';

    const sampleFromField = (op.sampleReference ?? op.sample_reference ?? '').trim();
    const productFromField = (op.productReference ?? op.product_reference ?? '').trim();
    if (isSampleOp.value && sampleFromField) return sampleFromField;
    if (!isSampleOp.value && productFromField) return productFromField;

    const reference = String(op.reference ?? '').trim();
    if (!reference) return '-';

    const parts = reference.split(' - ').map((part) => part.trim()).filter(Boolean);
    const sampleRef = parts[0] ?? reference;
    const productRef = parts[parts.length - 1] ?? reference;
    return isSampleOp.value ? sampleRef : productRef;
});
const opHeaderLabel = computed(() => {
    const opCode = String(selectedOp.value?.opCode ?? '').trim() || '-';
    const ref = displayedSecondReference.value || '-';
    return `${opCode} — ${ref}`;
});

const tabs = [
    { value: 'lista_operacional', label: 'Lista operacional', icon: ClipboardList },
    { value: 'procesos', label: 'Procesos', icon: Workflow },
    { value: 'materia_prima', label: 'Materia prima', icon: Box },
    { value: 'rollos', label: 'Rollos', icon: Layers },
    { value: 'hilos', label: 'Hilos', icon: Volleyball },
    { value: 'insumos', label: 'Insumos', icon: Package },
    { value: 'informacion_adicional', label: 'Informacion adicional', icon: FilePlus2 },
    { value: 'costos', label: 'Costos', icon: DollarSign },
];

const defaultTabValue = computed(() => (
    tabs.some((tab) => tab.value === 'lista_operacional') ? 'lista_operacional' : 'procesos'
));
const activeTab = ref<string>(defaultTabValue.value);

function formatDate(value: string | null): string {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function loadRawMaterialsInfo(opId: string | number | null): void {
    if (typeof window === 'undefined' || opId === null || opId === undefined || opId === '') {
        rawMaterialsInfo.value = [];
        return;
    }

    try {
        const raw = window.localStorage.getItem(`tDS_rawMaterial_${opId}`);
        if (!raw) {
            rawMaterialsInfo.value = [];
            return;
        }

        const parsed = JSON.parse(raw) as Array<Record<string, unknown>>;
        if (!Array.isArray(parsed)) {
            rawMaterialsInfo.value = [];
            return;
        }

        rawMaterialsInfo.value = parsed
            .map((row, index) => {
                const toNumberOrNull = (value: unknown): number | null => {
                    const parsedNumber = Number(value);
                    return Number.isFinite(parsedNumber) ? parsedNumber : null;
                };
                const toNumber = (value: unknown): number => {
                    const parsedNumber = Number(value);
                    return Number.isFinite(parsedNumber) ? parsedNumber : 0;
                };

                return {
                    uid: String(row.uid ?? `raw_${index}`),
                    tela: String(row.tela ?? '').trim(),
                    ancho: toNumberOrNull(row.ancho),
                    promTrazo: toNumber(row.promTrazo),
                    largoTrazo: toNumber(row.largoTrazo),
                    largoLiq: toNumber(row.largoLiq),
                    consumo: toNumber(row.consumo),
                    precioTela: toNumberOrNull(row.lastUnitPriceCop ?? row.precioTela),
                    proveedor: String(row.lastSupplierName ?? row.proveedor ?? '').trim() || null,
                } satisfies RawMaterialsInfoRow;
            })
            .filter((row) => row.tela !== '');
    } catch (error) {
        console.error('Error al leer materia prima para informacion adicional:', error);
        rawMaterialsInfo.value = [];
    }
}

function handleRawMaterialSaved(event: Event): void {
    const customEvent = event as CustomEvent<{ opId?: string | number }>;
    const opId = customEvent.detail?.opId;
    if (opId === undefined || opId === null || String(opId) !== String(selectedOpId.value ?? '')) return;
    loadRawMaterialsInfo(selectedOpId.value);
}

async function loadOpOptions(searchTerm = ''): Promise<void> {
    isLoadingOptions.value = true;
    try {
        opOptions.value = await fetchOpOptions({ search: searchTerm || undefined });
    } catch (error) {
        console.error('Error al cargar opciones de OP:', error);
        opOptions.value = [];
    } finally {
        isLoadingOptions.value = false;
    }
}

async function loadOpDetail(opId: string | number): Promise<void> {
    isLoadingDetail.value = true;
    try {
        selectedOp.value = await fetchOpBasicInfo(opId);
    } catch (error) {
        console.error('Error al cargar detalle de OP:', error);
        selectedOp.value = null;
    } finally {
        isLoadingDetail.value = false;
    }
}

async function loadOperationalList(opId: string | number): Promise<void> {
    isLoadingOperationalList.value = true;
    operationalListMessage.value = null;
    try {
        const response = await fetchOpOperationalList(opId);
        hasOperationalList.value = response.hasList;
        operationalList.value = response.list;

        if (!response.hasList) {
            await loadOperationalListOptions();
        }
    } catch (error) {
        console.error('Error al cargar listado operacional:', error);
        hasOperationalList.value = false;
        operationalList.value = null;
        operationalListMessage.value = 'No se pudo cargar el listado operacional.';
    } finally {
        isLoadingOperationalList.value = false;
    }
}

async function loadOperationalListOptions(searchTerm = ''): Promise<void> {
    try {
        operationalListOptions.value = await fetchOperationalListOptions({ search: searchTerm || undefined });
    } catch (error) {
        console.error('Error al cargar opciones de listados operacionales:', error);
        operationalListOptions.value = [];
    }
}

async function assignOperationalList(): Promise<void> {
    if (!selectedOpId.value || selectedOperationalListId.value === null || selectedOperationalListId.value === undefined || selectedOperationalListId.value === '') {
        return;
    }

    isAssigningOperationalList.value = true;
    operationalListMessage.value = null;
    try {
        await assignOperationalListToOp(selectedOpId.value, selectedOperationalListId.value);
        selectedOperationalListId.value = null;
        await loadOperationalList(selectedOpId.value);
    } catch (error) {
        console.error('Error al asignar listado operacional:', error);
        operationalListMessage.value = 'No se pudo asignar el listado operacional.';
    } finally {
        isAssigningOperationalList.value = false;
    }
}

function resetOperationalListState(): void {
    operationalList.value = null;
    hasOperationalList.value = false;
    isLoadingOperationalList.value = false;
    selectedOperationalListId.value = null;
    operationalListMessage.value = null;
}

function openDrawing() {
    if (!drawingUrl.value) return;
    isDrawingOpen.value = true;
}

function formatOpOptionLabel(option: Option): string {
    const raw = option.name ?? '';
    const separator = raw.includes(' — ') ? ' — ' : null;
    if (!separator) return raw;
    const [left, ...rest] = raw.split(separator);
    const right = rest.join(separator).trim();
    if (!left || !right) return raw;

    const opCode = left;
    const parts = right.split(' - ').map((segment) => segment.trim()).filter(Boolean);
    const isSample = opCode.toUpperCase().startsWith('MU');
    const sampleRef = parts[0] ?? right;
    const productRef = parts[parts.length - 1] ?? right;
    const chosen = isSample ? sampleRef : productRef;

    return `${opCode} — ${chosen}`;
}

watch(
    () => props.initialOpId,
    (value) => {
        selectedOpId.value = value ?? null;
    },
);

watch(
    selectedOpId,
    async (value) => {
        if (value === null || value === undefined || value === '') {
            selectedOp.value = null;
            resetOperationalListState();
            rawMaterialsInfo.value = [];
            return;
        }

        await loadOpDetail(value);
        await loadOperationalList(value);
        loadRawMaterialsInfo(value);
    },
    { immediate: true },
);

const basicInfoFields = computed(() => {
    if (!selectedOp.value) return [];

    return [
        { key: 'fecha', label: 'Fecha', value: formatDate(selectedOp.value.deliveryDate) },
        { key: 'destino', label: 'Destino', value: selectedOp.value.destination ?? '-' },
        { key: 'seccion', label: 'Seccion', value: selectedOp.value.section ?? '-' },
        { key: 'grupo', label: 'Grupo', value: selectedOp.value.group ?? '-' },
        { key: 'prioridad', label: 'Prioridad', value: selectedOp.value.priority ?? '-' },
        { key: 'marca', label: 'Marca', value: selectedOp.value.brand ?? '-' },
        { key: 'mundo', label: 'Mundo', value: selectedOp.value.world ?? '-' },
        { key: 'temporada', label: 'Temporada', value: selectedOp.value.season ?? '-' },
    ];
});

onMounted(() => {
    void loadOpOptions();
    if (typeof window !== 'undefined') {
        window.addEventListener('tds-raw-material-saved', handleRawMaterialSaved as EventListener);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('tds-raw-material-saved', handleRawMaterialSaved as EventListener);
    }
});
</script>

<template>
    <Head title="Ficha Tecnica" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl px-2 py-3">
            <h1 class="text-lg font-bold">Ficha Tecnica</h1>
            <p class="max-w-4xl text-sm text-muted-foreground">
                Selecciona una orden de produccion para consultar su informacion basica y revisar el dibujo plano de referencia en vista ampliada.
            </p>

            <div class="max-w-md">
                <SelectField
                    v-model="selectedOpId"
                    label="Orden de produccion"
                    :options="opOptions"
                    :get-option-label="formatOpOptionLabel"
                    placeholder="Selecciona una OP"
                    :searchable="true"
                    search-placeholder="Buscar OP..."
                    :allow-custom="true"
                    @search="loadOpOptions"
                />
                <p v-if="isLoadingOptions" class="mt-2 text-sm text-muted-foreground">Cargando opciones...</p>
                <p v-else-if="opOptions.length === 0" class="mt-2 text-sm text-muted-foreground">No hay resultados</p>
            </div>

            <div v-if="isLoadingDetail" class="rounded-lg border border-border px-4 py-8 text-center text-sm text-muted-foreground">
                Cargando informacion...
            </div>

            <div
                v-else-if="!selectedOpId"
                class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Selecciona una OP
            </div>

            <div
                v-else-if="!selectedOp"
                class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
            >
                No hay resultados
            </div>

            <template v-else>
                <div class="rounded-lg border border-border bg-background p-4">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-foreground">Informacion basica</p>
                            <p class="text-xs text-muted-foreground">{{ opHeaderLabel }}</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-transparent text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            :aria-label="isBasicInfoCollapsed ? 'Expandir información' : 'Minimizar información'"
                            @click="isBasicInfoCollapsed = !isBasicInfoCollapsed"
                        >
                            <ChevronDown v-if="isBasicInfoCollapsed" class="h-4 w-4" />
                            <ChevronUp v-else class="h-4 w-4" />
                        </button>
                    </div>

                    <transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="max-h-0 opacity-0"
                        enter-to-class="max-h-[700px] opacity-100"
                        leave-active-class="transition-all duration-150 ease-in"
                        leave-from-class="max-h-[700px] opacity-100"
                        leave-to-class="max-h-0 opacity-0"
                    >
                        <div v-show="!isBasicInfoCollapsed" class="grid grid-cols-1 gap-4 overflow-hidden lg:grid-cols-3">
                            <div class="lg:col-span-2">
                                <div class="grid grid-cols-1 gap-y-4 gap-x-8 sm:grid-cols-2 xl:grid-cols-4">
                                    <div v-for="field in basicInfoFields" :key="field.key" class="space-y-1">
                                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ field.label }}</p>
                                        <p class="text-sm font-medium text-foreground">{{ field.value }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-1 lg:border-l lg:border-border lg:pl-4">
                                <div class="mb-3">
                                    <p class="text-sm font-semibold text-foreground">Dibujo plano</p>
                                    <p class="text-xs text-muted-foreground">Referencia visual del producto</p>
                                </div>

                                <button
                                    v-if="hasDrawing"
                                    type="button"
                                    class="mt-2 flex h-[110px] w-full cursor-pointer items-center justify-center overflow-hidden rounded-md border border-dashed border-muted-foreground/25 bg-muted/10 transition hover:border-muted-foreground/40 hover:bg-muted/20 focus:outline-none focus:ring-2 focus:ring-primary/30"
                                    @click="openDrawing"
                                    :disabled="!drawingUrl"
                                >
                                    <img
                                        :src="drawingUrl!"
                                        class="h-full w-auto max-w-full object-contain"
                                        alt="Dibujo plano"
                                    />
                                </button>
                                <div v-else class="mt-2 flex h-[110px] items-center justify-center overflow-hidden rounded-md border border-dashed border-muted-foreground/25 bg-muted/10">
                                    <p class="text-sm text-muted-foreground">Sin dibujo</p>
                                </div>

                                <ImagePreviewDialog
                                    v-model:open="isDrawingOpen"
                                    title="Dibujo plano"
                                    :src="drawingUrl"
                                    alt="Dibujo plano"
                                />
                            </div>
                        </div>
                    </transition>
                </div>

                <Tabs v-model="activeTab" :default-value="defaultTabValue" class="w-full">
                    <div class="mb-3 flex justify-center">
                        <TabsList class="inline-flex w-fit items-center gap-1 rounded-full border border-border bg-muted/40 p-1">
                            <TabsTrigger
                                v-for="tab in tabs"
                                :key="tab.value"
                                :value="tab.value"
                                class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground data-[state=active]:border data-[state=active]:border-primary/20 data-[state=active]:bg-primary/10 data-[state=active]:text-primary data-[state=active]:shadow-sm"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                                    <span>{{ tab.label }}</span>
                                </span>
                            </TabsTrigger>
                        </TabsList>
                    </div>
                    <TabsContent value="lista_operacional" class="mt-0">
                        <div v-if="isLoadingOperationalList" class="rounded-lg border border-border px-4 py-6 text-sm text-muted-foreground">
                            Cargando listado operacional...
                        </div>

                        <div
                            v-else-if="!hasOperationalList"
                            class="rounded-lg border border-dashed border-border bg-background px-4 py-6"
                        >
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm font-semibold text-foreground">Lista operacional</p>
                                    <p class="text-sm text-muted-foreground">Esta OP no tiene listado operacional asignado.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-3 md:max-w-lg">
                                    <SelectField
                                        v-model="selectedOperationalListId"
                                        label="Listado operacional"
                                        :options="operationalListOptions"
                                        placeholder="Selecciona un listado"
                                        :searchable="true"
                                        search-placeholder="Buscar listado..."
                                        @search="loadOperationalListOptions"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex w-fit items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="isAssigningOperationalList || !selectedOperationalListId"
                                        @click="assignOperationalList"
                                    >
                                        {{ isAssigningOperationalList ? 'Asignando...' : 'Asignar listado' }}
                                    </button>
                                    <p v-if="operationalListMessage" class="text-sm text-destructive">{{ operationalListMessage }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-else class="rounded-lg border border-border bg-background p-4">
                            <div class="mb-4 border-b border-border pb-3">
                                <p class="text-sm font-semibold text-foreground">Lista operacional</p>
                                <p class="text-xs text-muted-foreground">Listado asignado a la OP</p>
                                <p class="mt-1 text-sm font-medium text-foreground">
                                    {{ operationalList?.reference ?? 'Sin referencia' }}{{ operationalList?.name ? ` — ${operationalList.name}` : '' }}
                                </p>
                            </div>

                            <div
                                v-if="!operationalList?.items?.length"
                                class="rounded-md border border-dashed border-border px-3 py-4 text-sm text-muted-foreground"
                            >
                                Listado asignado (sin detalle)
                            </div>
                            <div v-else class="overflow-x-auto">
                                <div class="min-w-[760px] rounded-md border border-border">
                                    <div class="grid grid-cols-[90px_220px_1fr_120px_170px] border-b border-border bg-muted/30 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        <p># / Orden</p>
                                        <p>Proceso / Operacion</p>
                                        <p>Descripcion</p>
                                        <p>Tiempo (min)</p>
                                        <p>Tipo</p>
                                    </div>
                                    <div
                                        v-for="(item, index) in operationalList?.items"
                                        :key="`${item.order ?? index}-${item.process ?? 'sin-proceso'}-${index}`"
                                        class="grid grid-cols-[90px_220px_1fr_120px_170px] border-b border-border px-3 py-2 text-sm last:border-b-0"
                                    >
                                        <p class="text-foreground">{{ item.order ?? '-' }}</p>
                                        <p class="text-foreground">{{ item.process ?? '-' }}</p>
                                        <p class="text-muted-foreground">{{ item.description ?? '-' }}</p>
                                        <p class="text-foreground">{{ item.minutes ?? '-' }}</p>
                                        <p class="text-foreground">{{ item.type ?? '-' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </TabsContent>

                    <TabsContent value="procesos" class="mt-0">
                        <ProcessesTab
                            :op-id="selectedOpId"
                            :selected-op="selectedOp"
                            @go-to-operational-list="activeTab = 'lista_operacional'"
                        />
                    </TabsContent>

                    <TabsContent value="materia_prima" class="mt-0">
                        <RawMaterialTab
                            v-if="selectedOpId"
                            :op-id="selectedOpId"
                        />
                    </TabsContent>

                    <TabsContent value="rollos" class="mt-0">
                        <RollsTab
                            v-if="selectedOpId"
                            :op-id="selectedOpId"
                            :units="selectedOp?.units ?? null"
                        />
                    </TabsContent>

                    <TabsContent value="hilos" class="mt-0">
                        <ThreadsTab
                            v-if="selectedOpId"
                            :op-id="selectedOpId"
                            :units="selectedOp?.units ?? null"
                        />
                    </TabsContent>

                    <TabsContent value="insumos" class="mt-0">
                        <SuppliesTab
                            v-if="selectedOpId"
                            :op-id="selectedOpId"
                            :units="selectedOp?.units ?? null"
                            :has-operational-list="hasOperationalList"
                            :op-code="selectedOp?.opCode ?? null"
                            :op-group="selectedOp?.group ?? null"
                        />
                    </TabsContent>

                    <TabsContent value="informacion_adicional" class="mt-0">
                        <AdditionalInfoTab
                            v-if="selectedOpId"
                            :op-id="selectedOpId"
                            :sizes="selectedOp?.sizes ?? null"
                            :total-units="selectedOp?.units ?? null"
                            :raw-materials-info="rawMaterialsInfo"
                            :drawing-url="drawingUrl"
                            :op-basic-info="selectedOp"
                        />
                    </TabsContent>

                    <TabsContent value="costos" class="mt-0">
                        <CostsTab :op-id="selectedOpId" :selected-op="selectedOp" />
                    </TabsContent>
                </Tabs>
            </template>
        </div>
    </AppLayout>
</template>
