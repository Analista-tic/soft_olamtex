<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import type { FabricRollLengthDifference } from '@/domain/Textil';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { useAuthStore } from '@/stores/auth';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckCheck } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const breadcrumbs = [
    { title: 'Kardex de Telas', href: '/kardex-telas' },
    { title: 'Diferencias de metraje', href: '/kardex-telas/diferencias-metraje' },
];

const authStore = useAuthStore();
const canReview = computed(() => authStore.can('revisar diferencias metraje telas'));

const { successMessage, errorMessage, errorType, success, error, clear } = useGlobalAlert();

const rows = ref<FabricRollLengthDifference[]>([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(50);
const isLoading = ref(false);

const status = ref<'pending' | 'reviewed' | 'all'>('pending');
const type = ref<'all' | 'sobrante' | 'faltante'>('all');
const rollNumber = ref('');

const reviewTarget = ref<FabricRollLengthDifference | null>(null);
const reviewNotes = ref('');
const reviewError = ref('');
const isReviewing = ref(false);

const numFmt = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const fmtMeters = (v: number | null | undefined) => (v === null || v === undefined ? '-' : `${numFmt.format(v)} m`);
const fmtDate = (v: string | null) => (v ? new Date(v).toLocaleString('es-CO') : '-');

async function fetchRows(targetPage = 1) {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/kardex-telas/diferencias-metraje/data', {
            params: {
                page: targetPage,
                per_page: perPage.value,
                status: status.value,
                type: type.value === 'all' ? undefined : type.value,
                search: rollNumber.value.trim() || undefined,
            },
        });
        rows.value = Array.isArray(data?.data) ? data.data : [];
        total.value = data?.meta?.total ?? rows.value.length;
        page.value = data?.meta?.current_page ?? targetPage;
    } catch {
        rows.value = [];
        error('No se pudieron cargar las diferencias de metraje.');
    } finally {
        isLoading.value = false;
    }
}

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
watch([status, type], () => fetchRows(1));
watch(rollNumber, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchRows(1), 350);
});

function openReview(row: FabricRollLengthDifference) {
    reviewTarget.value = row;
    reviewNotes.value = '';
    reviewError.value = '';
}

function closeReview() {
    reviewTarget.value = null;
}

async function submitReview() {
    if (!reviewTarget.value || isReviewing.value) return;

    isReviewing.value = true;
    reviewError.value = '';
    try {
        const { data } = await axios.patch(`/kardex-telas/diferencias-metraje/${reviewTarget.value.id}/revisar`, {
            review_notes: reviewNotes.value.trim() || null,
        });
        success(data?.message || 'Diferencia de metraje marcada como revisada.');
        closeReview();
        void fetchRows(page.value);
    } catch (err: any) {
        reviewError.value = err?.response?.data?.message || 'No se pudo marcar la diferencia como revisada.';
    } finally {
        isReviewing.value = false;
    }
}

onMounted(() => fetchRows(1));
</script>

<template>
    <Head title="Diferencias de metraje" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="clear"
                @close-error="clear"
            />

            <div class="flex flex-col gap-1">
                <h1 :class="uiPatterns.typography.pageTitle">Diferencias de metraje</h1>
                <p :class="uiPatterns.typography.pageSubtitle">
                    Sobrantes (el rollo dio más de lo registrado) y faltantes (se acabó antes) detectados al descargar. Cada evento queda con su
                    movimiento de kardex; la revisión deja constancia de quién lo validó, por ejemplo para reclamar al proveedor.
                </p>
            </div>

            <FilterBar>
                <FilterField label="Estado" :active="status !== 'pending'" class="min-w-[180px] p-0">
                    <Select v-model="status">
                        <SelectTrigger class="h-10"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="pending">Pendientes de revisión</SelectItem>
                            <SelectItem value="reviewed">Revisadas</SelectItem>
                            <SelectItem value="all">Todas</SelectItem>
                        </SelectContent>
                    </Select>
                </FilterField>
                <FilterField label="Tipo" :active="type !== 'all'" class="min-w-[160px] p-0">
                    <Select v-model="type">
                        <SelectTrigger class="h-10"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Sobrantes y faltantes</SelectItem>
                            <SelectItem value="sobrante">Sobrantes</SelectItem>
                            <SelectItem value="faltante">Faltantes</SelectItem>
                        </SelectContent>
                    </Select>
                </FilterField>
                <FilterField label="N° Rollo" :active="rollNumber.trim().length > 0" class="min-w-[160px] p-0">
                    <input
                        v-model="rollNumber"
                        placeholder="N° Rollo"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus:border-primary focus:outline-none"
                    />
                </FilterField>
            </FilterBar>

            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="overflow-x-auto">
                    <Table class="min-w-full text-[0.9rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Rollo</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead class="text-right">Metros</TableHead>
                                <TableHead class="text-right">Recibido → vigente</TableHead>
                                <TableHead>Orden</TableHead>
                                <TableHead>Factura / proveedor</TableHead>
                                <TableHead>Motivo</TableHead>
                                <TableHead>Registró</TableHead>
                                <TableHead>Revisión</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow v-for="i in 5" :key="`frld-sk-${i}`">
                                    <TableCell v-for="col in 10" :key="`frld-sk-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <TableRow v-else-if="rows.length === 0">
                                <TableCell colspan="10" class="py-8 text-center text-sm text-muted-foreground">
                                    No hay diferencias de metraje con este filtro.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="row in rows" v-else :key="row.id" class="border-b align-top">
                                <TableCell class="whitespace-nowrap text-sm">{{ fmtDate(row.created_at) }}</TableCell>
                                <TableCell class="text-sm font-medium">
                                    {{ row.roll_number ?? '-' }}
                                    <p class="max-w-[160px] truncate text-xs font-normal text-muted-foreground" :title="row.fabric ?? ''">
                                        {{ row.fabric ?? '' }}
                                    </p>
                                </TableCell>
                                <TableCell class="text-sm">
                                    <Badge
                                        variant="outline"
                                        :class="
                                            row.type === 'sobrante'
                                                ? 'border-emerald-400 text-emerald-700 dark:text-emerald-300'
                                                : 'border-red-400 text-red-700 dark:text-red-300'
                                        "
                                    >
                                        {{ row.type === 'sobrante' ? 'Sobrante' : 'Faltante' }}
                                    </Badge>
                                    <p v-if="row.is_reversal" class="mt-1 text-xs text-muted-foreground">Revierte un sobrante</p>
                                </TableCell>
                                <TableCell class="text-right text-sm font-semibold">{{ fmtMeters(row.meters) }}</TableCell>
                                <TableCell class="whitespace-nowrap text-right text-sm text-muted-foreground">
                                    {{ fmtMeters(row.roll_received_quantity) }} → {{ fmtMeters(row.roll_quantity) }}
                                    <p v-if="Number(row.roll_length_adjustment_meters ?? 0) !== 0" class="text-xs">
                                        {{ Number(row.roll_length_adjustment_meters) > 0 ? 'Sobrante' : 'Faltante' }} de metraje:
                                        {{ fmtMeters(Math.abs(Number(row.roll_length_adjustment_meters))) }}
                                    </p>
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ row.order_code ?? '-' }}
                                    <p v-if="row.released_reservations.length > 0" class="text-xs text-muted-foreground">
                                        Liberó:
                                        {{ row.released_reservations.map((r) => `${r.code ?? '#' + r.id} (${fmtMeters(r.meters)})`).join(', ') }}
                                    </p>
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ row.invoice_number ?? '-' }}
                                    <p class="text-xs text-muted-foreground">{{ row.provider ?? '' }}</p>
                                </TableCell>
                                <TableCell class="max-w-[220px] text-sm">{{ row.reason }}</TableCell>
                                <TableCell class="text-sm">{{ row.created_by ?? '-' }}</TableCell>
                                <TableCell class="text-sm">
                                    <template v-if="row.reviewed_at">
                                        <p class="font-medium">{{ row.reviewed_by ?? '-' }}</p>
                                        <p class="text-xs text-muted-foreground">{{ fmtDate(row.reviewed_at) }}</p>
                                        <p v-if="row.review_notes" class="text-xs">{{ row.review_notes }}</p>
                                    </template>
                                    <Button v-else-if="canReview" variant="outline" class="gap-1.5 rounded-full" @click="openReview(row)">
                                        <CheckCheck class="h-4 w-4" />
                                        Revisar
                                    </Button>
                                    <span v-else class="text-xs text-muted-foreground">Pendiente</span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
                <div class="mt-4">
                    <ServerPagination v-model:current-page="page" v-model:page-size="perPage" :total-items="total" @fetch-data="fetchRows" />
                </div>
            </div>
        </div>

        <Dialog :open="reviewTarget !== null" modal @update:open="(open) => !open && closeReview()">
            <FormDialogContent class="flex max-h-[90vh] max-w-lg flex-col px-2 py-2">
                <AlertBox v-if="reviewError" type="error" :message="reviewError" @close="reviewError = ''" />

                <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle>Revisar diferencia de metraje</DialogTitle>
                    <DialogDescription>
                        {{ reviewTarget?.type === 'sobrante' ? 'Sobrante' : 'Faltante' }} de {{ fmtMeters(reviewTarget?.meters) }} en el rollo
                        {{ reviewTarget?.roll_number }}. La revisión no revierte nada: si hubo un error, corrija el descargue.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex-1 space-y-2 overflow-y-auto px-5 py-4">
                    <p class="text-xs font-semibold text-muted-foreground">Notas de la revisión</p>
                    <Textarea v-model="reviewNotes" data-no-uppercase rows="3" placeholder="Opcional: verificación, reclamo al proveedor, etc." />
                </div>

                <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                    <Button variant="outline" size="pill" @click="closeReview">Cancelar</Button>
                    <Button variant="default" class="rounded-full px-5" :disabled="isReviewing" @click="submitReview">
                        {{ isReviewing ? 'Guardando...' : 'Marcar como revisada' }}
                    </Button>
                </DialogFooter>
            </FormDialogContent>
        </Dialog>
    </AppLayout>
</template>
