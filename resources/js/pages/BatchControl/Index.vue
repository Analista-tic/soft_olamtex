<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAlertStack } from '@/composables/useAlert';
import type { BreadcrumbItem } from '@/domain/shared/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowRightLeft } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type BatchControlRow = {
    id: number;
    op_code: string;
    current_process: string | null;
    referencia: string;
    temporada: string;
    prioridad: string;
    cant_programadas: number;
    destino: string;
    seccion: string;
    grupo: string;
    fecha: string;
};

const props = defineProps<{
    processes: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Control de lotes', href: '/control-de-lotes' }];

const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();

const allRows = ref<BatchControlRow[]>([]);
const isLoading = ref(false);
const activeTab = ref(props.processes[0] ?? '');
const changeProcessDialogOpen = ref(false);
const selectedRow = ref<BatchControlRow | null>(null);
const selectedNewProcess = ref<string>('');

const TAB_COLORS = [
    'bg-primary/10 text-primary data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:border-primary',
    'bg-sky-100 text-sky-800 data-[state=active]:bg-sky-200 data-[state=active]:border-sky-300 dark:bg-sky-950/30 dark:text-sky-400 dark:data-[state=active]:bg-sky-900/40 dark:data-[state=active]:border-sky-700',
    'bg-teal-100 text-teal-800 data-[state=active]:bg-teal-200 data-[state=active]:border-teal-300 dark:bg-teal-950/30 dark:text-teal-400 dark:data-[state=active]:bg-teal-900/40 dark:data-[state=active]:border-teal-700',
    'bg-emerald-100 text-emerald-800 data-[state=active]:bg-emerald-200 data-[state=active]:border-emerald-300 dark:bg-emerald-950/30 dark:text-emerald-400 dark:data-[state=active]:bg-emerald-900/40 dark:data-[state=active]:border-emerald-700',
    'bg-green-100 text-green-800 data-[state=active]:bg-green-200 data-[state=active]:border-green-300 dark:bg-green-950/30 dark:text-green-400 dark:data-[state=active]:bg-green-900/40 dark:data-[state=active]:border-green-700',
    'bg-lime-100 text-lime-800 data-[state=active]:bg-lime-200 data-[state=active]:border-lime-300 dark:bg-lime-950/30 dark:text-lime-400 dark:data-[state=active]:bg-lime-900/40 dark:data-[state=active]:border-lime-700',
    'bg-amber-100 text-amber-800 data-[state=active]:bg-amber-200 data-[state=active]:border-amber-300 dark:bg-amber-950/30 dark:text-amber-400 dark:data-[state=active]:bg-amber-900/40 dark:data-[state=active]:border-amber-700',
    'bg-orange-100 text-orange-800 data-[state=active]:bg-orange-200 data-[state=active]:border-orange-300 dark:bg-orange-950/30 dark:text-orange-400 dark:data-[state=active]:bg-orange-900/40 dark:data-[state=active]:border-orange-700',
    'bg-rose-100 text-rose-800 data-[state=active]:bg-rose-200 data-[state=active]:border-rose-300 dark:bg-rose-950/30 dark:text-rose-400 dark:data-[state=active]:bg-rose-900/40 dark:data-[state=active]:border-rose-700',
    'bg-pink-100 text-pink-800 data-[state=active]:bg-pink-200 data-[state=active]:border-pink-300 dark:bg-pink-950/30 dark:text-pink-400 dark:data-[state=active]:bg-pink-900/40 dark:data-[state=active]:border-pink-700',
    'bg-violet-100 text-violet-800 data-[state=active]:bg-violet-200 data-[state=active]:border-violet-300 dark:bg-violet-950/30 dark:text-violet-400 dark:data-[state=active]:bg-violet-900/40 dark:data-[state=active]:border-violet-700',
    'bg-indigo-100 text-indigo-800 data-[state=active]:bg-indigo-200 data-[state=active]:border-indigo-300 dark:bg-indigo-950/30 dark:text-indigo-400 dark:data-[state=active]:bg-indigo-900/40 dark:data-[state=active]:border-indigo-700',
    'bg-slate-100 text-slate-800 data-[state=active]:bg-slate-200 data-[state=active]:border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:data-[state=active]:bg-slate-700 dark:data-[state=active]:border-slate-600',
];

const tabColor = (index: number) => TAB_COLORS[index % TAB_COLORS.length];

const filteredRows = computed(() => {
    const process = activeTab.value;
    return allRows.value.filter((r) => (r.current_process ?? '') === process);
});

const otherProcesses = computed(() => {
    const current = selectedRow.value?.current_process;
    return props.processes.filter((p) => p !== current);
});

async function fetchData() {
    isLoading.value = true;
    clearError();
    try {
        const { data } = await axios.get(route('control-de-lotes.data'));
        allRows.value = Array.isArray(data?.data) ? data.data : [];
    } catch {
        allRows.value = [];
        showError('No se pudo cargar el listado de órdenes.');
    } finally {
        isLoading.value = false;
    }
}

function openChangeProcess(row: BatchControlRow) {
    selectedRow.value = row;
    selectedNewProcess.value = '';
    changeProcessDialogOpen.value = true;
}

function closeChangeProcessDialog() {
    changeProcessDialogOpen.value = false;
    selectedRow.value = null;
    selectedNewProcess.value = '';
}

async function confirmChangeProcess() {
    if (!selectedRow.value || !selectedNewProcess.value) return;

    try {
        await axios.patch(route('control-de-lotes.update-process', { programmer: selectedRow.value.id }), {
            current_process: selectedNewProcess.value,
        });
        showSuccess('Proceso actualizado correctamente.');
        closeChangeProcessDialog();
        await fetchData();
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        showError(msg ?? 'No se pudo actualizar el proceso.');
    }
}

onMounted(() => {
    void fetchData();
});
</script>

<template>
    <Head title="Control de lotes" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            success-title="Éxito"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Control de lotes</h1>
            </div>

            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="mb-4 flex h-auto flex-wrap gap-1 rounded-lg border bg-muted/30 p-1">
                    <TabsTrigger
                        v-for="(process, idx) in processes"
                        :key="process"
                        :value="process"
                        :class="['rounded-md border px-3 py-2 text-sm font-medium transition', tabColor(idx)]"
                    >
                        {{ process }}
                    </TabsTrigger>
                </TabsList>

                <TabsContent v-for="process in processes" :key="process" :value="process" class="mt-0">
                    <div class="rounded-lg border bg-card">
                        <div v-if="isLoading" class="p-6">
                            <Skeleton class="h-64 w-full" />
                        </div>
                        <div v-else class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Referencia</TableHead>
                                        <TableHead>Temporada</TableHead>
                                        <TableHead>Prioridad</TableHead>
                                        <TableHead class="text-right">Cant. Programadas</TableHead>
                                        <TableHead>Destino</TableHead>
                                        <TableHead>Sección</TableHead>
                                        <TableHead>Grupo</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Lote</TableHead>
                                        <TableHead class="w-[100px]">Acción</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="filteredRows.length === 0">
                                        <TableCell colspan="10" class="py-8 text-center text-muted-foreground">
                                            No hay órdenes en este proceso.
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-for="row in filteredRows" :key="row.id">
                                        <TableCell class="font-medium">{{ row.referencia }}</TableCell>
                                        <TableCell>{{ row.temporada }}</TableCell>
                                        <TableCell>{{ row.prioridad }}</TableCell>
                                        <TableCell class="text-right">{{ row.cant_programadas }}</TableCell>
                                        <TableCell>{{ row.destino }}</TableCell>
                                        <TableCell>{{ row.seccion }}</TableCell>
                                        <TableCell>{{ row.grupo }}</TableCell>
                                        <TableCell>{{ row.fecha }}</TableCell>
                                        <TableCell class="font-mono text-sm">{{ row.op_code }}</TableCell>
                                        <TableCell>
                                            <Button variant="ghost" size="sm" class="h-8 gap-1" @click="openChangeProcess(row)">
                                                <ArrowRightLeft class="h-3.5 w-3.5" />
                                                Cambiar
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <Dialog :open="changeProcessDialogOpen" @update:open="changeProcessDialogOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Cambiar proceso</DialogTitle>
                    <DialogDescription>
                        Orden <span class="font-mono font-medium">{{ selectedRow?.op_code }}</span> — {{ selectedRow?.referencia }}. Selecciona el
                        nuevo proceso y confirma.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Nuevo proceso</label>
                        <Select v-model="selectedNewProcess" required>
                            <SelectTrigger>
                                <SelectValue placeholder="Selecciona un proceso" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in otherProcesses" :key="p" :value="p">
                                    {{ p }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="closeChangeProcessDialog"> Cancelar </Button>
                    <Button :disabled="!selectedNewProcess" @click="confirmChangeProcess"> Confirmar cambio </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
