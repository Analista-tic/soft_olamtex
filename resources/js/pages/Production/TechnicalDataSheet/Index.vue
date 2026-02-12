<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SelectField from '@/components/SelectField.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import type { BreadcrumbItem, Option } from '@/domain/shared';
import type { OpBasicInfo } from '@/domain/Production/TechnicalDataSheet';
// TODO: descomentar cuando el service esté conectado al backend real
// import { fetchOpOptions, fetchOpBasicInfo } from '@/domain/Production/TechnicalDataSheet';

// ============================================================================
// Breadcrumbs
// ============================================================================
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Producción', href: '/produccion' },
    { title: 'Ficha Técnica', href: '/produccion/ficha-tecnica' },
];

// ============================================================================
// State
// ============================================================================
const selectedOpId = ref<string | number | null>(null);
const opOptions = ref<Option[]>([]);
const selectedOp = ref<OpBasicInfo | null>(null);
const isLoadingOptions = ref(false);
const isLoadingDetail = ref(false);

// ============================================================================
// Mock data (temporal hasta conectar con backend)
// ============================================================================
const MOCK_OP_OPTIONS: Option[] = [
    { id: 1, name: 'MF-0001 — REF-PRODUCTO-001', groupLabel: 'Productos' },
    { id: 2, name: 'MF-0002 — REF-PRODUCTO-002', groupLabel: 'Productos' },
    { id: 3, name: 'MFT-0003 — REF-PRODUCTO-TELA-001', groupLabel: 'Productos' },
    { id: 4, name: 'MU-0001 — REF-MUESTRA-001', groupLabel: 'Muestras' },
    { id: 5, name: 'MUT-0002 — REF-MUESTRA-TELA-001', groupLabel: 'Muestras' },
];

const MOCK_OP_DETAILS: Record<number, OpBasicInfo> = {
    1: {
        id: 1,
        opCode: 'MF-0001',
        reference: 'REF-PRODUCTO-001',
        type: 'MF',
        status: 'En proceso',
        season: 'Primavera 2026',
        destination: 'Nacional',
        units: 500,
        deliveryDate: '2026-03-15',
        section: 'Confección',
        group: 'Camisetas',
        world: 'Casual',
        base: 'Algodón',
        brand: 'Olamtex',
        sizes: ['S', 'M', 'L', 'XL'],
        priceBilling: 25000,
        priceSale: 35000,
        ecommerce: true,
        priority: 1,
    },
    2: {
        id: 2,
        opCode: 'MF-0002',
        reference: 'REF-PRODUCTO-002',
        type: 'MF',
        status: 'Pendiente',
        season: 'Verano 2026',
        destination: 'Exportación',
        units: 1000,
        deliveryDate: '2026-04-20',
        section: 'Tejido',
        group: 'Polos',
        world: 'Deportivo',
        base: 'Poliéster',
        brand: 'Olamtex Sport',
        sizes: ['M', 'L', 'XL'],
        priceBilling: 30000,
        priceSale: 42000,
        ecommerce: false,
        priority: 2,
    },
    3: {
        id: 3,
        opCode: 'MFT-0003',
        reference: 'REF-PRODUCTO-TELA-001',
        type: 'MFT',
        status: 'En proceso',
        season: 'Otoño 2026',
        destination: 'Nacional',
        units: 300,
        deliveryDate: '2026-05-10',
        section: 'Corte',
        group: 'Pantalones',
        world: 'Formal',
        base: 'Lino',
        brand: 'Olamtex Premium',
        sizes: ['28', '30', '32', '34'],
        priceBilling: 45000,
        priceSale: 65000,
        ecommerce: true,
        priority: 1,
    },
    4: {
        id: 4,
        opCode: 'MU-0001',
        reference: 'REF-MUESTRA-001',
        type: 'MU',
        status: 'Aprobada',
        season: 'Primavera 2026',
        destination: 'Showroom',
        units: 5,
        deliveryDate: '2026-02-28',
        section: 'Diseño',
        group: 'Vestidos',
        world: 'Elegante',
        base: 'Seda',
        brand: 'Olamtex Couture',
        sizes: ['S', 'M'],
        priceBilling: null,
        priceSale: null,
        ecommerce: false,
        priority: 3,
    },
    5: {
        id: 5,
        opCode: 'MUT-0002',
        reference: 'REF-MUESTRA-TELA-001',
        type: 'MUT',
        status: 'En revisión',
        season: 'Invierno 2026',
        destination: 'Cliente',
        units: 3,
        deliveryDate: '2026-03-05',
        section: 'Prototipo',
        group: 'Abrigos',
        world: 'Outdoor',
        base: 'Nylon',
        brand: 'Olamtex Adventure',
        sizes: ['L', 'XL'],
        priceBilling: null,
        priceSale: null,
        ecommerce: false,
        priority: 2,
    },
};

// ============================================================================
// Funciones de carga
// ============================================================================

/**
 * Carga las opciones de OP para el selector
 * TODO: Conectar con fetchOpOptions del service cuando el backend esté listo
 */
async function loadOpOptions(searchTerm = '') {
    isLoadingOptions.value = true;
    try {
        // TODO: Reemplazar mock por llamada real al service:
        // const options = await fetchOpOptions({
        //     includeSamples: true,
        //     includeProducts: true,
        //     search: searchTerm,
        // });
        // opOptions.value = options.map(op => ({
        //     id: op.id,
        //     name: op.name,
        //     groupLabel: op.groupLabel,
        // }));

        // Mock temporal: filtrar opciones localmente
        await new Promise((resolve) => setTimeout(resolve, 200)); // Simular latencia
        const term = searchTerm.toLowerCase().trim();
        opOptions.value = term
            ? MOCK_OP_OPTIONS.filter((op) => op.name.toLowerCase().includes(term))
            : MOCK_OP_OPTIONS;
    } finally {
        isLoadingOptions.value = false;
    }
}

/**
 * Carga el detalle de una OP seleccionada
 * TODO: Conectar con fetchOpBasicInfo del service cuando el backend esté listo
 */
async function loadOpDetail(opId: string | number) {
    isLoadingDetail.value = true;
    try {
        // TODO: Reemplazar mock por llamada real al service:
        // selectedOp.value = await fetchOpBasicInfo(opId);

        // Mock temporal
        await new Promise((resolve) => setTimeout(resolve, 300)); // Simular latencia
        selectedOp.value = MOCK_OP_DETAILS[Number(opId)] ?? null;
    } finally {
        isLoadingDetail.value = false;
    }
}

// ============================================================================
// Watchers
// ============================================================================

watch(selectedOpId, async (newId) => {
    if (newId !== null && newId !== undefined) {
        await loadOpDetail(newId);
    } else {
        selectedOp.value = null;
    }
});

// ============================================================================
// Computed: campos de información básica
// ============================================================================

/**
 * Formatea una fecha ISO a dd/MM/yyyy
 */
function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    try {
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return dateStr;
        return date.toLocaleDateString('es-CO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
}

const basicInfoFields = computed(() => [
    { key: 'fecha', label: 'Fecha', value: formatDate(selectedOp.value?.deliveryDate ?? null) },
    { key: 'destino', label: 'Destino', value: selectedOp.value?.destination ?? '-' },
    { key: 'seccion', label: 'Sección', value: selectedOp.value?.section ?? '-' },
    { key: 'grupo', label: 'Grupo', value: selectedOp.value?.group ?? '-' },
    { key: 'prioridad', label: 'Prioridad', value: selectedOp.value?.priority?.toString() ?? '-' },
    { key: 'marca', label: 'Marca', value: selectedOp.value?.brand ?? '-' },
    { key: 'mundo', label: 'Mundo', value: selectedOp.value?.world ?? '-' },
    { key: 'temporada', label: 'Temporada', value: selectedOp.value?.season ?? '-' },
]);

const opSubtitle = computed(() => {
    if (!selectedOp.value) return '';
    return `${selectedOp.value.opCode} — ${selectedOp.value.reference}`;
});

// ============================================================================
// Tabs config
// ============================================================================
const tabs = [
    { value: 'insumos', label: 'Insumos' },
    { value: 'procesos', label: 'Procesos' },
    { value: 'rollos', label: 'Rollos' },
    { value: 'hilos', label: 'Hilos' },
    { value: 'costos', label: 'Costos' },
];

// ============================================================================
// Lifecycle
// ============================================================================
onMounted(() => {
    loadOpOptions();
});
</script>

<template>
    <Head title="Ficha Técnica" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl px-2 py-3">
            <!-- Header -->
            <div class="flex flex-col gap-1">
                <h1 class="text-lg font-bold">Ficha Técnica</h1>
                <p class="text-sm text-muted-foreground">
                    Consulta y gestiona la información técnica de las órdenes de producción
                </p>
            </div>

            <!-- Selector de OP -->
            <div class="max-w-md">
                <SelectField
                    v-model="selectedOpId"
                    label="Orden de producción"
                    placeholder="Selecciona una OP"
                    :options="opOptions"
                    :searchable="true"
                    search-placeholder="Buscar por código o referencia..."
                    @search="loadOpOptions"
                />
            </div>

            <!-- Empty state: cuando no hay OP seleccionada -->
            <template v-if="!selectedOp && !isLoadingDetail">
                <Card class="border-dashed">
                    <CardContent class="flex flex-col items-center justify-center py-12">
                        <div class="text-center">
                            <p class="text-muted-foreground">
                                Selecciona una orden de producción para ver su ficha técnica
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <!-- Loading state -->
            <template v-else-if="isLoadingDetail">
                <Card>
                    <CardContent class="flex items-center justify-center py-12">
                        <p class="text-muted-foreground">Cargando información...</p>
                    </CardContent>
                </Card>
            </template>

            <!-- Contenido cuando hay OP seleccionada -->
            <template v-else-if="selectedOp">
                <!-- Grid de 2 columnas: Info básica + Dibujo plano -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- Card izquierda: Información básica -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base">Ficha técnica</CardTitle>
                            <CardDescription>{{ opSubtitle }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div
                                    v-for="field in basicInfoFields"
                                    :key="field.key"
                                    class="flex flex-col gap-0.5"
                                >
                                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                        {{ field.label }}
                                    </span>
                                    <span class="text-sm font-medium">
                                        {{ field.value }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Card derecha: Dibujo plano (placeholder) -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base">Dibujo plano</CardTitle>
                            <CardDescription>Referencia visual del producto</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                class="flex min-h-[200px] items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 bg-muted/20"
                            >
                                <p class="text-sm text-muted-foreground">
                                    Próximamente: plano/imagen de referencia
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Tabs -->
                <Card class="mt-2">
                    <CardContent class="pt-4">
                        <Tabs default-value="insumos" class="w-full">
                            <TabsList class="mb-4 flex w-full justify-start gap-1 border-b bg-transparent p-0">
                                <TabsTrigger
                                    v-for="tab in tabs"
                                    :key="tab.value"
                                    :value="tab.value"
                                    class="rounded-none border-b-2 border-transparent bg-transparent px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground data-[state=active]:border-primary data-[state=active]:text-foreground data-[state=active]:shadow-none"
                                >
                                    {{ tab.label }}
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent
                                v-for="tab in tabs"
                                :key="tab.value"
                                :value="tab.value"
                                class="mt-0 rounded-lg border p-4"
                            >
                                <div class="flex min-h-[150px] items-center justify-center">
                                    <p class="text-muted-foreground">
                                        {{ tab.label }} — En construcción
                                    </p>
                                </div>
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
