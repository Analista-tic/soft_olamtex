<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import type { ManualRollProgrammingLine } from '@/domain/Textil';
import AppLayout from '@/layouts/AppLayout.vue';
import ManualRollDischargeDialog from '@/pages/Textil/FabricRollOrders/ManualRollDischargeDialog.vue';
import ManualRollProgramDialog from '@/pages/Textil/FabricRollOrders/ManualRollProgramDialog.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    manualOrderCode: string;
}>();

const breadcrumbs = [
    { title: 'Kardex de Telas', href: '/kardex-telas' },
    { title: 'Descargue de tela por orden', href: '/kardex-telas/descargue-orden' },
    { title: props.manualOrderCode, href: '' },
];

const { successMessage, errorMessage, error, success, clear } = useGlobalAlert();

const lines = ref<ManualRollProgrammingLine[]>([]);
const isLoading = ref(false);
const isProgramDialogOpen = ref(false);
const isDischargeDialogOpen = ref(false);
const selectedLine = ref<ManualRollProgrammingLine | null>(null);

const numFmt = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const fmtMeters = (v: number) => `${numFmt.format(v)} m`;

const totals = computed(() => ({
    programmed: lines.value.reduce((sum, l) => sum + l.programmed_meters, 0),
    consumed: lines.value.reduce((sum, l) => sum + l.consumed_meters, 0),
    remaining: lines.value.reduce((sum, l) => sum + l.remaining_meters, 0),
}));

async function fetchLines() {
    isLoading.value = true;
    try {
        const { data } = await axios.get(`/kardex-telas/descargue-orden/${encodeURIComponent(props.manualOrderCode)}/lineas`);
        lines.value = Array.isArray(data?.data) ? data.data : [];
    } catch {
        lines.value = [];
        error('No se pudo cargar el detalle de este código de orden.');
    } finally {
        isLoading.value = false;
    }
}

function openDischargeDialog(line: ManualRollProgrammingLine) {
    selectedLine.value = line;
    isDischargeDialogOpen.value = true;
}

function handleProgramSuccess() {
    success('Programación registrada correctamente.');
    void fetchLines();
}

function handleDischargeSuccess() {
    success('Descargue registrado correctamente.');
    void fetchLines();
}

async function removeLine(line: ManualRollProgrammingLine) {
    if (line.consumed_meters > 0) return;
    try {
        await axios.delete(`/kardex-telas/descargue-orden/manual/${line.id}`);
        success('Programación eliminada correctamente.');
        void fetchLines();
    } catch (err: any) {
        error(err?.response?.data?.message || 'No se pudo eliminar la programación.');
    }
}

onMounted(fetchLines);
</script>

<template>
    <Head :title="`Descargue de tela por orden — ${props.manualOrderCode}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack :success-message="successMessage" :error-message="errorMessage" @close-success="clear" @close-error="clear" />

            <div class="flex items-center justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Código de orden: {{ props.manualOrderCode }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Programado {{ fmtMeters(totals.programmed) }} · Consumido {{ fmtMeters(totals.consumed) }} · Pendiente
                        {{ fmtMeters(totals.remaining) }}
                    </p>
                </div>
                <Button variant="default" class="shrink-0 gap-1.5 rounded-full" @click="isProgramDialogOpen = true">
                    <Plus class="h-4 w-4" />
                    Agregar rollo
                </Button>
            </div>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 bg-card md:min-h-min">
                <div class="overflow-x-auto">
                    <Table class="min-w-full text-[0.9rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Rollo</TableHead>
                                <TableHead>Ubicación</TableHead>
                                <TableHead class="text-right">Programado</TableHead>
                                <TableHead class="text-right">Consumido</TableHead>
                                <TableHead class="text-right">Pendiente</TableHead>
                                <TableHead>Notas</TableHead>
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="!isLoading && lines.length === 0">
                                <TableCell colspan="7" class="py-8 text-center text-sm text-muted-foreground">
                                    Todavía no hay rollos programados en este código.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="line in lines" v-else :key="line.id" class="border-b">
                                <TableCell class="font-medium">
                                    {{ line.roll_number ?? '-' }}
                                    <Badge v-if="!line.has_item" variant="outline" class="ml-1 text-[0.65rem]" title="Este rollo aún no tiene un ítem vinculado: el consumo no se refleja en el inventario del ítem hasta que se vincule.">
                                        Sin ítem
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ line.location ?? '-' }}</TableCell>
                                <TableCell class="text-right">{{ fmtMeters(line.programmed_meters) }}</TableCell>
                                <TableCell class="text-right">{{ fmtMeters(line.consumed_meters) }}</TableCell>
                                <TableCell class="text-right">{{ fmtMeters(line.remaining_meters) }}</TableCell>
                                <TableCell class="max-w-[180px] truncate text-sm text-muted-foreground" :title="line.notes ?? ''">
                                    {{ line.notes ?? '-' }}
                                </TableCell>
                                <TableCell class="flex justify-end gap-2">
                                    <Button variant="outline" size="sm" :disabled="line.remaining_meters <= 0" @click="openDischargeDialog(line)">
                                        Descargar
                                    </Button>
                                    <button
                                        v-if="line.consumed_meters <= 0"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                                        title="Eliminar programación"
                                        type="button"
                                        @click="removeLine(line)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <ManualRollProgramDialog v-model="isProgramDialogOpen" :manual-order-code="props.manualOrderCode" @success="handleProgramSuccess" />
        <ManualRollDischargeDialog v-model="isDischargeDialogOpen" :line="selectedLine" @success="handleDischargeSuccess" />
    </AppLayout>
</template>
