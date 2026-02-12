<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';

type HistoryEntry = {
    id: number;
    operator_efficiency_factor_anterior?: number;
    operator_efficiency_factor_nuevo?: number;
    machine_supplement_factor_anterior?: number;
    machine_supplement_factor_nuevo?: number;
    fabric_base_factor_anterior?: number;
    fabric_base_factor_nuevo?: number;
    sam_base_anterior?: number;
    sam_base_nuevo?: number;
    sam_total_unitario_anterior?: number;
    sam_total_unitario_nuevo?: number;
    sam_total_operacion_anterior?: number;
    sam_total_operacion_nuevo?: number;
    motivo?: string;
    created_at?: string;
    usuario?: { id: number; name: string };
};

const props = withDefaults(
    defineProps<{
        open: boolean;
        operationId: number | null;
        operationLabel?: string;
    }>(),
    {
        operationLabel: '',
    }
);

const emit = defineEmits<{
    (event: 'close'): void;
}>();

const localOpen = ref(props.open);
const history = ref<HistoryEntry[]>([]);
const isLoading = ref(false);

watch(() => props.open, (value) => {
    localOpen.value = value;
    if (value && props.operationId) fetchHistory();
    else history.value = [];
});

watch(localOpen, (value) => {
    if (!value) emit('close');
});

async function fetchHistory() {
    if (!props.operationId) return;
    isLoading.value = true;
    try {
        const { data } = await axios.get(
            `/produccion/listados-operacionales/${props.operationId}/history`
        );
        history.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        console.error('Error al cargar historial:', error);
        history.value = [];
    } finally {
        isLoading.value = false;
    }
}

function formatValue(v: number | undefined): string {
    if (v === undefined || v === null) return '-';
    return Number(v).toFixed(4);
}

function formatDate(d: string | undefined): string {
    if (!d) return '-';
    const date = new Date(d);
    return date.toLocaleString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getChangeDescription(entry: HistoryEntry): string {
    const parts: string[] = [];
    if (entry.operator_efficiency_factor_anterior !== entry.operator_efficiency_factor_nuevo) {
        parts.push('Factor operario');
    }
    if (entry.machine_supplement_factor_anterior !== entry.machine_supplement_factor_nuevo) {
        parts.push('Suplemento máquina');
    }
    if (entry.fabric_base_factor_anterior !== entry.fabric_base_factor_nuevo) {
        parts.push('Factor tela');
    }
    if (entry.sam_base_anterior !== entry.sam_base_nuevo) {
        parts.push('SAM base');
    }
    return parts.length > 0 ? parts.join(', ') : 'Cambios varios';
}
</script>

<template>
    <Dialog v-model:open="localOpen">
        <DialogContent class="max-w-3xl max-h-[85vh] flex flex-col">
            <DialogHeader>
                <DialogTitle>Historial de cambios</DialogTitle>
                <DialogDescription>
                    Cambios registrados para esta operación
                    <span v-if="operationLabel" class="font-medium text-foreground">{{ operationLabel }}</span>
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-hidden flex flex-col min-h-0">
                <div v-if="isLoading" class="space-y-2 p-4">
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                </div>
                <div
                    v-else-if="history.length === 0"
                    class="flex flex-1 items-center justify-center rounded-lg border border-dashed bg-muted/20 p-8 text-center text-sm text-muted-foreground"
                >
                    No hay registros de cambios para esta operación.
                </div>
                <div v-else class="overflow-y-auto rounded-lg border" style="scrollbar-width: thin;">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[140px]">Fecha</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Cambio</TableHead>
                                <TableHead class="text-right">Antes</TableHead>
                                <TableHead class="text-right">Después</TableHead>
                                <TableHead class="max-w-[160px]">Motivo</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="entry in history" :key="entry.id" class="text-sm">
                                <TableCell class="text-muted-foreground">
                                    {{ formatDate(entry.created_at) }}
                                </TableCell>
                                <TableCell>{{ entry.usuario?.name ?? '-' }}</TableCell>
                                <TableCell>{{ getChangeDescription(entry) }}</TableCell>
                                <TableCell class="text-right font-mono text-muted-foreground">
                                    {{ formatValue(entry.operator_efficiency_factor_anterior ?? entry.machine_supplement_factor_anterior ?? entry.fabric_base_factor_anterior ?? entry.sam_base_anterior) }}
                                </TableCell>
                                <TableCell class="text-right font-mono font-medium">
                                    {{ formatValue(entry.operator_efficiency_factor_nuevo ?? entry.machine_supplement_factor_nuevo ?? entry.fabric_base_factor_nuevo ?? entry.sam_base_nuevo) }}
                                </TableCell>
                                <TableCell class="max-w-[160px] truncate text-muted-foreground" :title="entry.motivo ?? ''">
                                    {{ entry.motivo ?? '-' }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <DialogFooter class="mt-4 flex-shrink-0 border-t pt-4">
                <Button variant="outline" class="rounded-full px-5 py-2 text-sm font-semibold" @click="localOpen = false">
                    Cerrar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
