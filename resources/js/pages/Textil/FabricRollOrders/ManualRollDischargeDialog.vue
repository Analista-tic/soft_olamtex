<script setup lang="ts">
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';

import AlertBox from '@/components/AlertBox.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import type { ManualRollProgrammingHistoryEntry, ManualRollProgrammingLine } from '@/domain/Textil';
import { useAuthStore } from '@/stores/auth';
import { Pencil } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        line?: ManualRollProgrammingLine | null;
    }>(),
    { modelValue: false, line: null },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void;
    (event: 'success'): void;
}>();

const authStore = useAuthStore();
const canCorrectDischarge = computed(() => authStore.can('corregir descargue telas orden'));

const form = reactive({ quantity: '', reason: '' });
const errors = reactive({ quantity: '', reason: '' });
const alertMessage = ref('');
const isSubmitting = ref(false);
const history = ref<ManualRollProgrammingHistoryEntry[]>([]);
const isLoadingHistory = ref(false);

const editingEntryId = ref<number | null>(null);
const correctionQuantity = ref('');
const correctionError = ref('');
const isCorrecting = ref(false);

const numFmt = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const fmt = (v: number | null | undefined) => (v === null || v === undefined ? '-' : `${numFmt.format(v)} m`);

const remaining = computed(() => props.line?.remaining_meters ?? 0);

function startCorrection(entry: ManualRollProgrammingHistoryEntry) {
    editingEntryId.value = entry.id;
    correctionQuantity.value = String(entry.quantity ?? '');
    correctionError.value = '';
}

function cancelCorrection() {
    editingEntryId.value = null;
    correctionQuantity.value = '';
    correctionError.value = '';
}

async function saveCorrection(entry: ManualRollProgrammingHistoryEntry) {
    if (!props.line || isCorrecting.value) return;

    const quantity = Number(correctionQuantity.value);
    if (!correctionQuantity.value || Number.isNaN(quantity) || quantity < 0) {
        correctionError.value = 'Ingresa una cantidad válida (0 o mayor).';
        return;
    }

    isCorrecting.value = true;
    correctionError.value = '';

    try {
        await axios.patch(`/kardex-telas/descargue-orden/manual/${props.line.id}/descargues/${entry.id}`, { quantity });
        editingEntryId.value = null;
        await fetchHistory();
        emit('success');
    } catch (error: any) {
        correctionError.value = error?.response?.data?.errors?.quantity?.[0] ?? error?.response?.data?.message ?? 'No se pudo corregir el descargue.';
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
            errors.quantity = '';
            errors.reason = '';
            alertMessage.value = '';
            editingEntryId.value = null;
            correctionQuantity.value = '';
            correctionError.value = '';
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
    errors.quantity =
        !form.quantity || Number.isNaN(quantity) || quantity <= 0
            ? 'Ingresa una cantidad mayor a 0.'
            : quantity > remaining.value
              ? `No puede superar lo pendiente (${fmt(remaining.value)}).`
              : '';

    return !errors.quantity;
}

async function submitDischarge() {
    if (!props.line || isSubmitting.value || !validateForm()) return;

    isSubmitting.value = true;
    alertMessage.value = '';

    try {
        await axios.patch(`/kardex-telas/descargue-orden/manual/${props.line.id}/descargar`, {
            quantity: Number(form.quantity),
            reason: form.reason.trim() || null,
        });

        emit('success');
        closeDialog();
    } catch (error: any) {
        errors.quantity = error?.response?.data?.errors?.quantity?.[0] ?? '';
        alertMessage.value = error?.response?.data?.message || 'No se pudo registrar el descargue.';
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog :open="props.modelValue" modal @update:open="handleOpenChange">
        <FormDialogContent class="flex max-h-[90vh] max-w-lg flex-col px-2 py-2">
            <AlertBox v-if="alertMessage" type="error" :message="alertMessage" @close="alertMessage = ''" />

            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Descargar rollo</DialogTitle>
                <DialogDescription>Registra el consumo real de esta línea. Puedes descargar en varias veces hasta agotar lo programado.</DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-5 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                <div v-if="props.line" class="rounded-2xl border border-border bg-muted/20 p-4">
                    <div class="grid grid-cols-3 gap-4 text-center text-sm">
                        <div>
                            <p class="text-xs text-muted-foreground">Programado</p>
                            <p class="font-semibold">{{ fmt(props.line.programmed_meters) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Consumido</p>
                            <p class="font-semibold">{{ fmt(props.line.consumed_meters) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Pendiente</p>
                            <p class="font-semibold">{{ fmt(remaining) }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-semibold text-muted-foreground">Cantidad a descargar (m) <span class="text-destructive">*</span></p>
                    <Input
                        v-model="form.quantity"
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder="Ej. 10"
                        :invalid="Boolean(errors.quantity)"
                        :error-message="errors.quantity"
                    />
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-semibold text-muted-foreground">Motivo</p>
                    <Input data-no-uppercase v-model="form.reason" placeholder="Opcional" />
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-semibold text-muted-foreground">Historial de descargues</p>
                    <div v-if="isLoadingHistory" class="text-xs text-muted-foreground">Cargando...</div>
                    <p v-else-if="history.length === 0" class="text-xs text-muted-foreground">Sin descargues registrados aún.</p>
                    <div v-else class="max-h-52 divide-y divide-border overflow-y-auto rounded-md border border-border text-sm">
                        <div v-for="entry in history" :key="entry.id" class="px-3 py-2">
                            <div v-if="editingEntryId === entry.id" class="flex items-center gap-2">
                                <Input
                                    v-model="correctionQuantity"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="h-8 w-24"
                                    :invalid="Boolean(correctionError)"
                                />
                                <Button size="sm" class="h-8" :disabled="isCorrecting" @click="saveCorrection(entry)">Guardar</Button>
                                <Button size="sm" variant="outline" class="h-8" :disabled="isCorrecting" @click="cancelCorrection">Cancelar</Button>
                            </div>
                            <div v-else class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate">{{ entry.reason || 'Sin motivo' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ entry.user_name ?? '-' }} · {{ entry.created_at ? new Date(entry.created_at).toLocaleString('es-CO') : '-' }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <span class="font-semibold">{{ fmt(entry.quantity) }}</span>
                                    <button
                                        v-if="canCorrectDischarge"
                                        type="button"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                        title="Corregir cantidad de este descargue"
                                        @click="startCorrection(entry)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                            <p v-if="editingEntryId === entry.id && correctionError" class="mt-1 text-xs text-destructive">{{ correctionError }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <Button variant="outline" size="pill" @click="closeDialog">Cancelar</Button>
                <Button variant="default" type="button" :disabled="isSubmitting" class="rounded-full px-5" @click="submitDischarge">
                    {{ isSubmitting ? 'Guardando...' : 'Descargar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
