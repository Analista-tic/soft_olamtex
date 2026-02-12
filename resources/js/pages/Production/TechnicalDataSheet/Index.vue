<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import SelectField from '@/components/SelectField.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ClipboardList, DollarSign, FilePlus2, Layers, Package, Volleyball, Workflow } from 'lucide-vue-next';
import type { BreadcrumbItem, Option } from '@/domain/shared';
import type { OpBasicInfo } from '@/domain/Production/TechnicalDataSheet';
import { fetchOpOptions, fetchOpBasicInfo } from '@/domain/Production/TechnicalDataSheet';

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
    { value: 'costos', label: 'Costos', icon: DollarSign },
    { value: 'insumos', label: 'Insumos', icon: Package },
    { value: 'rollos', label: 'Rollos', icon: Layers },
    { value: 'hilos', label: 'Hilos', icon: Volleyball },
    { value: 'informacion_adicional', label: 'Informacion adicional', icon: FilePlus2 },
];

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
            return;
        }

        await loadOpDetail(value);
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
        { key: 'prioridad', label: 'Prioridad', value: selectedOp.value.priority?.toString() ?? '-' },
        { key: 'marca', label: 'Marca', value: selectedOp.value.brand ?? '-' },
        { key: 'mundo', label: 'Mundo', value: selectedOp.value.world ?? '-' },
        { key: 'temporada', label: 'Temporada', value: selectedOp.value.season ?? '-' },
    ];
});

onMounted(() => {
    void loadOpOptions();
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
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div class="lg:col-span-2">
                            <div class="mb-3">
                                <p class="text-sm font-semibold text-foreground">Informacion basica</p>
                                <p class="text-xs text-muted-foreground">{{ opHeaderLabel }}</p>
                            </div>
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
                </div>

                <Tabs default-value="lista_operacional" class="w-full">
                    <div class="mb-3 flex justify-center">
                        <TabsList class="inline-flex w-fit items-center gap-1 rounded-lg border border-border bg-muted/40 p-1">
                            <TabsTrigger
                                v-for="tab in tabs"
                                :key="tab.value"
                                :value="tab.value"
                                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground data-[state=active]:border data-[state=active]:border-primary/20 data-[state=active]:bg-primary/10 data-[state=active]:text-primary data-[state=active]:shadow-sm"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <component :is="tab.icon" class="h-4 w-4 shrink-0" />
                                    <span>{{ tab.label }}</span>
                                </span>
                            </TabsTrigger>
                        </TabsList>
                    </div>
                    <TabsContent v-for="tab in tabs" :key="tab.value" :value="tab.value" class="mt-0">
                        <div class="rounded-lg border border-dashed border-border px-4 py-6 text-sm text-muted-foreground">
                            En construccion
                        </div>
                    </TabsContent>
                </Tabs>
            </template>
        </div>
    </AppLayout>
</template>


