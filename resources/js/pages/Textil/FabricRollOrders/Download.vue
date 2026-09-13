<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import type { ManualRollProgrammingLine } from '@/domain/Textil';
import AppLayout from '@/layouts/AppLayout.vue';
import ManualRollDischargeDialog from '@/pages/Textil/FabricRollOrders/ManualRollDischargeDialog.vue';
import ManualRollProgramDialog from '@/pages/Textil/FabricRollOrders/ManualRollProgramDialog.vue';
import ManualRollProgramEditDialog from '@/pages/Textil/FabricRollOrders/ManualRollProgramEditDialog.vue';
import { useAuthStore } from '@/stores/auth';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    manualOrderCode: string;
}>();

const breadcrumbs = [
    { title: 'Kardex de Telas', href: '/kardex-telas' },
    { title: 'Descargue de tela por orden', href: '/kardex-telas/descargue-orden' },
    { title: props.manualOrderCode, href: '' },
];

const authStore = useAuthStore();
const canCreate = computed(() => authStore.can('crear descargue telas orden'));
const canEdit = computed(() => authStore.can('editar descargue telas orden'));
const canDelete = computed(() => authStore.can('eliminar descargue telas orden'));
const canDischarge = computed(() => authStore.can('descargar telas orden'));

const { successMessage, errorMessage, error, success, clear } = useGlobalAlert();

const lines = ref<ManualRollProgrammingLine[]>([]);
const isLoading = ref(false);
const isProgramDialogOpen = ref(false);
const isDischargeDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const selectedLine = ref<ManualRollProgrammingLine | null>(null);
const lineToEdit = ref<ManualRollProgrammingLine | null>(null);
const lineToDelete = ref<ManualRollProgrammingLine | null>(null);

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
        error('No se pudo cargar el detalle de esta orden de producción.');
    } finally {
        isLoading.value = false;
    }
}

function openDischargeDialog(line: ManualRollProgrammingLine) {
    selectedLine.value = line;
    isDischargeDialogOpen.value = true;
}

function openEditDialog(line: ManualRollProgrammingLine) {
    lineToEdit.value = line;
    isEditDialogOpen.value = true;
}

function confirmDeleteLine(line: ManualRollProgrammingLine) {
    lineToDelete.value = line;
    isDeleteDialogOpen.value = true;
}

function handleProgramSuccess() {
    success('Programación registrada correctamente.');
    void fetchLines();
}

function handleEditSuccess() {
    success('Programación actualizada correctamente.');
    void fetchLines();
}

function handleDischargeSuccess() {
    success('Descargue registrado correctamente.');
    void fetchLines();
}

async function deleteLine() {
    if (!lineToDelete.value) return;
    try {
        await axios.delete(`/kardex-telas/descargue-orden/manual/${lineToDelete.value.id}`);
        success('Programación eliminada correctamente.');
        void fetchLines();
    } catch (err: any) {
        error(err?.response?.data?.message || 'No se pudo eliminar la programación.');
    } finally {
        lineToDelete.value = null;
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
                    <h1 class="text-lg font-bold">Orden de producción: {{ props.manualOrderCode }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Programado {{ fmtMeters(totals.programmed) }} · Consumido {{ fmtMeters(totals.consumed) }} · Pendiente
                        {{ fmtMeters(totals.remaining) }}
                    </p>
                </div>
                <Button v-if="canCreate" variant="default" class="shrink-0 gap-1.5 rounded-full" @click="isProgramDialogOpen = true">
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
                                <TableHead>Tela</TableHead>
                                <TableHead>Color</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Lote</TableHead>
                                <TableHead>Programado</TableHead>
                                <TableHead>Consumido</TableHead>
                                <TableHead>Pendiente</TableHead>
                                <TableHead>Notas</TableHead>
                                <TableHead>Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow v-for="i in 5" :key="`manual-line-skeleton-${i}`">
                                    <TableCell v-for="col in 11" :key="`manual-line-skeleton-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <TableRow v-else-if="lines.length === 0">
                                <TableCell colspan="11" class="py-8 text-center text-sm text-muted-foreground">
                                    Todavía no hay rollos programados en esta orden.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="line in lines" v-else :key="line.id" class="border-b">
                                <TableCell class="text-center text-sm font-medium">
                                    {{ line.roll_number ?? '-' }}
                                    <Badge
                                        v-if="!line.has_item"
                                        variant="outline"
                                        class="ml-1 text-[0.65rem]"
                                        title="Este rollo aún no tiene un ítem vinculado: el consumo no se refleja en el inventario del ítem hasta que se vincule."
                                    >
                                        Sin ítem
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-center text-sm">{{ line.location ?? '-' }}</TableCell>
                                <TableCell class="max-w-[160px] truncate text-center text-sm" :title="line.fabric_name ?? ''">
                                    {{ line.fabric_name ?? '-' }}
                                </TableCell>
                                <TableCell class="text-center text-sm">{{ line.color ?? '-' }}</TableCell>
                                <TableCell class="text-center text-sm">{{ line.type ?? '-' }}</TableCell>
                                <TableCell class="text-center text-sm">{{ line.batch ?? '-' }}</TableCell>
                                <TableCell class="text-center text-sm">{{ fmtMeters(line.programmed_meters) }}</TableCell>
                                <TableCell class="text-center text-sm">{{ fmtMeters(line.consumed_meters) }}</TableCell>
                                <TableCell class="text-center text-sm">{{ fmtMeters(line.remaining_meters) }}</TableCell>
                                <TableCell class="max-w-[180px] truncate text-sm text-muted-foreground" :title="line.notes ?? ''">
                                    {{ line.notes ?? '-' }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <Button
                                            v-if="canDischarge"
                                            variant="outline"
                                            size="sm"
                                            :disabled="line.remaining_meters <= 0"
                                            @click="openDischargeDialog(line)"
                                        >
                                            Descargar
                                        </Button>
                                        <EditButton v-if="canEdit" title="Editar programación" @click="openEditDialog(line)" />
                                        <DeleteButton
                                            v-if="canDelete && line.consumed_meters <= 0"
                                            title="Eliminar programación"
                                            @click="confirmDeleteLine(line)"
                                        />
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <ManualRollProgramDialog v-model="isProgramDialogOpen" :manual-order-code="props.manualOrderCode" @success="handleProgramSuccess" />
        <ManualRollDischargeDialog v-model="isDischargeDialogOpen" :line="selectedLine" @success="handleDischargeSuccess" />
        <ManualRollProgramEditDialog v-model="isEditDialogOpen" :line="lineToEdit" @success="handleEditSuccess" />

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Estás seguro de que deseas eliminar esta programación? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteLine"
        />
    </AppLayout>
</template>
