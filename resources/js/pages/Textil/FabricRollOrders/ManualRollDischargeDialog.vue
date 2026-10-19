<script setup lang="ts">
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';

import AlertBox from '@/components/AlertBox.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import type { ManualRollProgrammingHistoryEntry, ManualRollProgrammingLine } from '@/domain/Textil';
import { useAuthStore } from '@/stores/auth';
import { History, Pencil, TriangleAlert } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        line?: ManualRollProgrammingLine | null;
    }>(),
    { modelValue: false, line: null },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void;
    (event: 'success', message: string): void;
}>();

const EPSILON = 0.001;

const authStore = useAuthStore();
const canCorrectDischarge = computed(() => authStore.can('corregir descargue telas orden'));

const form = reactive({ quantity: '', reason: '', finishRoll: false });
const errors = reactive({ quantity: '', reason: '' });
const alertMessage = ref('');
const isSubmitting = ref(false);
const history = ref<ManualRollProgrammingHistoryEntry[]>([]);
const isLoadingHistory = ref(false);

const editingEntryId = ref<number | null>(null);
const correction = reactive({ quantity: '', reason: '' });
const correctionErrors = reactive({ quantity: '', reason: '' });
const isCorrecting = ref(false);

const numFmt = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const fmt = (v: number | null | undefined) => (v === null || v === undefined ? '-' : `${numFmt.format(v)} m`);
const round2 = (v: number) => Math.round(v * 100) / 100;

const programmed = computed(() => props.line?.programmed_meters ?? 0);
const consumed = computed(() => props.line?.consumed_meters ?? 0);
const pending = computed(() => props.line?.remaining_meters ?? 0);
const overconsumed = computed(() => props.line?.overconsumed_meters ?? 0);
const rollReal = computed(() => props.line?.roll_real_meters ?? 0);
const rollFree = computed(() => props.line?.roll_free_meters ?? 0);
const progressPercent = computed(() => (programmed.value > 0 ? Math.min(100, Math.round((consumed.value / programmed.value) * 100)) : 0));

/**
 * Vista previa del descargue, con las mismas reglas del servidor (FabricRollService::dischargeManual):
 * lo pendiente sale de la reserva de la línea, el exceso del disponible libre del rollo y, si
 * no alcanza, lo que falta es metraje que el rollo trae de más (sobrante).
 */
const preview = computed(() => {
    const quantity = Number(form.quantity);
    const valid = form.quantity !== '' && !Number.isNaN(quantity) && quantity >= 0;
    const qty = valid ? round2(quantity) : 0;
    const excess = round2(Math.max(0, qty - pending.value));
    const surplus = round2(Math.max(0, excess - rollFree.value));
    const shortage = form.finishRoll ? round2(Math.max(0, rollReal.value + surplus - qty)) : 0;

    return { qty, excess, surplus, shortage };
});

const reasonRequired = computed(() => preview.value.excess > 0 || form.finishRoll);

/**
 * Misma regla del servidor (FabricRollService::correctManualDischarge): la corrección exige motivo
 * cuando deja el consumo de la línea por encima de lo programado o supera el metraje del rollo.
 * Se calcula en pantalla para pedir el motivo antes de guardar y no después de un error.
 */
function correctionNeedsReason(entry: ManualRollProgrammingHistoryEntry): boolean {
    const line = props.line;
    const next = Number(correction.quantity);
    if (!line || correction.quantity === '' || Number.isNaN(next)) return false;

    const delta = round2(next - Number(entry.quantity ?? 0));
    if (delta <= EPSILON) return false;

    const consumedAfter = consumed.value + delta;
    const overBefore = Math.max(0, consumed.value - programmed.value);
    const overAfter = Math.max(0, consumedAfter - programmed.value);
    if (overAfter > overBefore + EPSILON) return true;

    const reservedAfter = Math.max(0, programmed.value - consumedAfter - (line.released_meters ?? 0));
    const needFromFree = delta - Math.max(0, pending.value - reservedAfter);
    return needFromFree - rollFree.value > EPSILON;
}

function startCorrection(entry: ManualRollProgrammingHistoryEntry) {
    editingEntryId.value = entry.id;
    correction.quantity = String(entry.quantity ?? '');
    correction.reason = '';
    correctionErrors.quantity = '';
    correctionErrors.reason = '';
}

function cancelCorrection() {
    editingEntryId.value = null;
    correction.quantity = '';
    correction.reason = '';
    correctionErrors.quantity = '';
    correctionErrors.reason = '';
}

async function saveCorrection(entry: ManualRollProgrammingHistoryEntry) {
    if (!props.line || isCorrecting.value) return;

    const quantity = Number(correction.quantity);
    correctionErrors.quantity =
        correction.quantity === '' || Number.isNaN(quantity) || quantity < 0 ? 'Ingresa una cantidad válida (0 o mayor).' : '';
    correctionErrors.reason =
        correctionNeedsReason(entry) && !correction.reason.trim() ? 'Indica el motivo: la corrección supera lo programado.' : '';
    if (correctionErrors.quantity || correctionErrors.reason) return;

    isCorrecting.value = true;

    try {
        const { data } = await axios.patch(`/kardex-telas/descargue-orden/manual/${props.line.id}/descargues/${entry.id}`, {
            quantity,
            reason: correction.reason.trim() || null,
        });
        cancelCorrection();
        await fetchHistory();
        emit('success', data?.message || 'Descargue corregido correctamente.');
    } catch (error: any) {
        const fieldErrors = error?.response?.data?.errors ?? {};
        correctionErrors.quantity = fieldErrors.quantity?.[0] ?? '';
        correctionErrors.reason = fieldErrors.reason?.[0] ?? '';
        if (!correctionErrors.quantity && !correctionErrors.reason) {
            correctionErrors.quantity = error?.response?.data?.message ?? 'No se pudo corregir el descargue.';
        }
    } finally {
        isCorrecting.value = false;
    }
}

async function fetchHistory() {
    if (!props.line) return;
    isLoadingHistory.value = true;
    try {
        const { data } = await axios.get(`/kardex-telas/descargue-orden/manual/${props.line.id}/historial`);
        history.value = Array.isArray(data?.data) ? data.data : [];
    } catch {
        history.value = [];
        alertMessage.value = 'No se pudo cargar el historial de descargues.';
    } finally {
        isLoadingHistory.value = false;
    }
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            form.quantity = '';
            form.reason = '';
            form.finishRoll = false;
            errors.quantity = '';
            errors.reason = '';
            alertMessage.value = '';
            cancelCorrection();
            void fetchHistory();
        }
    },
);

function handleOpenChange(open: boolean) {
    emit('update:modelValue', open);
}

function closeDialog() {
    emit('update:modelValue', false);
}

function validateForm(): boolean {
    const quantity = Number(form.quantity);
    const minimum = form.finishRoll ? 0 : 0.01;

    errors.quantity =
        form.quantity === '' || Number.isNaN(quantity) || quantity < minimum
            ? form.finishRoll
                ? 'Ingresa la cantidad descargada (0 si solo das el rollo por terminado).'
                : 'Ingresa una cantidad mayor a 0.'
            : '';
    errors.reason =
        reasonRequired.value && !form.reason.trim() ? 'Indica el motivo: es obligatorio al superar lo programado o dar el rollo por terminado.' : '';

    return !errors.quantity && !errors.reason;
}

async function submitDischarge() {
    if (!props.line || isSubmitting.value || !validateForm()) return;

    isSubmitting.value = true;
    alertMessage.value = '';

    try {
        const { data } = await axios.patch(`/kardex-telas/descargue-orden/manual/${props.line.id}/descargar`, {
            quantity: Number(form.quantity),
            reason: form.reason.trim() || null,
            finish_roll: form.finishRoll,
        });

        emit('success', data?.message || 'Descargue registrado correctamente.');
        closeDialog();
    } catch (error: any) {
        const fieldErrors = error?.response?.data?.errors ?? {};
        errors.quantity = fieldErrors.quantity?.[0] ?? '';
        errors.reason = fieldErrors.reason?.[0] ?? '';
        alertMessage.value = error?.response?.data?.message || 'No se pudo registrar el descargue.';
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog :open="props.modelValue" modal @update:open="handleOpenChange">
        <FormDialogContent size="md" class="flex max-h-[90vh] flex-col px-2 py-2">
            <AlertBox v-if="alertMessage" type="error" :message="alertMessage" @close="alertMessage = ''" />

            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Descargar rollo {{ props.line?.roll_number ?? '' }}</DialogTitle>
                <DialogDescription>
                    Orden {{ props.line?.manual_order_code ?? '' }}: registra los metros que realmente se consumieron. Puedes descargar en varias
                    veces.
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-5 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                <!-- Resumen de la programación -->
                <div v-if="props.line" class="space-y-3 rounded-2xl border border-border bg-muted/20 p-4">
                    <div class="grid grid-cols-3 gap-4 text-center text-sm">
                        <div>
                            <p class="text-xs text-muted-foreground">Programado</p>
                            <p class="text-base font-semibold">{{ fmt(programmed) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Consumido</p>
                            <p class="text-base font-semibold">{{ fmt(consumed) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Pendiente</p>
                            <p class="text-base font-semibold">{{ fmt(pending) }}</p>
                        </div>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="overconsumed > 0 ? 'bg-amber-500' : 'bg-primary'"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                        <span
                            >Disponible en el rollo: <span class="font-semibold text-foreground">{{ fmt(rollReal) }}</span></span
                        >
                        <Badge v-if="overconsumed > 0" variant="outline" class="border-amber-400 text-amber-700 dark:text-amber-300">
                            Sobreconsumo {{ fmt(overconsumed) }}
                        </Badge>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Nuevo descargue -->
                    <section class="space-y-4">
                        <h3 class="text-sm font-semibold">Nuevo descargue</h3>

                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-muted-foreground">
                                Cantidad a descargar (m) <span class="text-destructive">*</span>
                            </p>
                            <Input
                                v-model="form.quantity"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Ej. 10"
                                :invalid="Boolean(errors.quantity)"
                                :error-message="errors.quantity"
                            />
                        </div>

                        <label class="flex cursor-pointer items-start gap-2 rounded-xl border border-border px-3 py-2.5 text-sm">
                            <Checkbox
                                :model-value="form.finishRoll"
                                class="mt-0.5"
                                @update:model-value="(v: boolean | 'indeterminate') => (form.finishRoll = v === true)"
                            />
                            <span>
                                <span class="font-medium">El rollo se terminó</span>
                                <span class="block text-xs text-muted-foreground"
                                    >Registra lo que sobre como faltante y libera las reservas restantes.</span
                                >
                            </span>
                        </label>

                        <div
                            v-if="preview.excess > 0 || form.finishRoll"
                            class="space-y-1 rounded-xl border border-amber-300/70 bg-amber-50 px-3 py-2.5 text-xs text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                        >
                            <p class="flex items-center gap-1.5 font-semibold"><TriangleAlert class="h-3.5 w-3.5" /> Se registrará</p>
                            <p v-if="preview.excess > 0">Sobreconsumo de {{ fmt(preview.excess) }} sobre lo programado.</p>
                            <p v-if="preview.surplus > 0">Sobrante de metraje de {{ fmt(preview.surplus) }}, pendiente de revisión.</p>
                            <p v-if="form.finishRoll && preview.shortage > 0">
                                Faltante de metraje de {{ fmt(preview.shortage) }}, pendiente de revisión.
                            </p>
                            <p v-if="form.finishRoll && props.line && props.line.other_reservations_count > 0">
                                Se liberan {{ props.line.other_reservations_count }} reserva(s) de otras órdenes ({{
                                    fmt(props.line.other_reservations_meters)
                                }}).
                            </p>
                        </div>

                        <div v-if="reasonRequired || form.reason" class="space-y-1">
                            <p class="text-xs font-semibold text-muted-foreground">
                                Motivo <span v-if="reasonRequired" class="text-destructive">*</span>
                            </p>
                            <Input
                                v-model="form.reason"
                                data-no-uppercase
                                :placeholder="reasonRequired ? 'Obligatorio' : 'Opcional'"
                                :invalid="Boolean(errors.reason)"
                                :error-message="errors.reason"
                            />
                        </div>
                    </section>

                    <!-- Historial -->
                    <section class="space-y-2">
                        <h3 class="flex items-center gap-1.5 text-sm font-semibold">
                            <History class="h-4 w-4 text-muted-foreground" /> Historial de descargues
                        </h3>
                        <div v-if="isLoadingHistory" class="text-xs text-muted-foreground">Cargando...</div>
                        <p
                            v-else-if="history.length === 0"
                            class="rounded-xl border border-dashed border-border px-3 py-6 text-center text-xs text-muted-foreground"
                        >
                            Aún no hay descargues registrados.
                        </p>
                        <div v-else class="max-h-72 divide-y divide-border overflow-y-auto rounded-xl border border-border text-sm">
                            <div v-for="entry in history" :key="entry.id" class="px-3 py-2">
                                <div v-if="editingEntryId === entry.id" class="space-y-2">
                                    <div class="space-y-1">
                                        <p class="text-xs font-semibold text-muted-foreground">Cantidad corregida (m)</p>
                                        <Input
                                            v-model="correction.quantity"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :invalid="Boolean(correctionErrors.quantity)"
                                            :error-message="correctionErrors.quantity"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-xs font-semibold text-muted-foreground">
                                            Motivo
                                            <span v-if="correctionNeedsReason(entry)" class="text-destructive">*</span>
                                        </p>
                                        <Input
                                            v-model="correction.reason"
                                            data-no-uppercase
                                            :placeholder="correctionNeedsReason(entry) ? 'Obligatorio: supera lo programado' : 'Opcional'"
                                            :invalid="Boolean(correctionErrors.reason)"
                                            :error-message="correctionErrors.reason"
                                        />
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <Button size="sm" variant="outline" class="h-8" :disabled="isCorrecting" @click="cancelCorrection"
                                            >Cancelar</Button
                                        >
                                        <Button size="sm" class="h-8" :disabled="isCorrecting" @click="saveCorrection(entry)">Guardar</Button>
                                    </div>
                                </div>
                                <div v-else class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate">{{ entry.reason || 'Sin motivo' }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ entry.user_name ?? '-' }} ·
                                            {{ entry.created_at ? new Date(entry.created_at).toLocaleString('es-CO') : '-' }}
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <Badge
                                            v-if="entry.corrections_count > 0"
                                            variant="outline"
                                            class="text-[0.65rem]"
                                            title="Cantidad vigente tras sus correcciones"
                                        >
                                            Corregido {{ entry.corrections_count }}×
                                        </Badge>
                                        <span class="font-semibold">{{ fmt(entry.quantity) }}</span>
                                        <button
                                            v-if="canCorrectDischarge"
                                            type="button"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                            title="Corregir cantidad de este descargue"
                                            @click="startCorrection(entry)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <Button variant="outline" size="pill" @click="closeDialog">Cancelar</Button>
                <Button variant="default" type="button" :disabled="isSubmitting" class="rounded-full px-5" @click="submitDischarge">
                    {{ isSubmitting ? 'Guardando...' : form.finishRoll ? 'Descargar y terminar rollo' : 'Descargar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
