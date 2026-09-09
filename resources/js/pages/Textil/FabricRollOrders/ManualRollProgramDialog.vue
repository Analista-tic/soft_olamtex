<script setup lang="ts">
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';

import AlertBox from '@/components/AlertBox.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { FabricRollView } from '@/domain/Textil';
import { X } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        manualOrderCode?: string | null;
    }>(),
    { modelValue: false, manualOrderCode: null },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void;
    (event: 'success', payload: { manualOrderCode: string }): void;
}>();

type SelectedLine = {
    roll: FabricRollView;
    quantity: string;
    error: string;
};

const form = reactive({
    manualOrderCode: '',
    notes: '',
});

const errors = reactive({
    manual_order_code: '',
    lines: '',
});

const rollSearch = ref('');
const rollOptions = ref<FabricRollView[]>([]);
const isLoadingRolls = ref(false);
const isSubmitting = ref(false);
const alertMessage = ref('');
const selectedLines = ref<SelectedLine[]>([]);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

const selectedIds = computed(() => new Set(selectedLines.value.map((line) => line.roll.id)));

function resetForm() {
    form.manualOrderCode = props.manualOrderCode ?? '';
    form.notes = '';
    rollSearch.value = '';
    rollOptions.value = [];
    selectedLines.value = [];
    alertMessage.value = '';
    errors.manual_order_code = '';
    errors.lines = '';
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) resetForm();
    },
);

async function searchRolls() {
    isLoadingRolls.value = true;
    try {
        const { data } = await axios.get('/kardex-telas/inventario-rollos/data', {
            params: { search: rollSearch.value.trim() || undefined, per_page: 15, sort_by: 'roll_number', sort_direction: 'asc' },
        });
        rollOptions.value = Array.isArray(data?.data) ? data.data : [];
    } catch {
        rollOptions.value = [];
    } finally {
        isLoadingRolls.value = false;
    }
}

function scheduleSearch() {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(searchRolls, 300);
}

watch(rollSearch, scheduleSearch);

function addRoll(roll: FabricRollView) {
    if (selectedIds.value.has(roll.id)) return;
    selectedLines.value.push({ roll, quantity: '', error: '' });
}

function removeRoll(rollId: number) {
    selectedLines.value = selectedLines.value.filter((line) => line.roll.id !== rollId);
}

function handleOpenChange(open: boolean) {
    emit('update:modelValue', open);
}

function closeDialog() {
    emit('update:modelValue', false);
}

function validateForm(): boolean {
    errors.manual_order_code = form.manualOrderCode.trim() ? '' : 'El código de orden es obligatorio.';
    errors.lines = selectedLines.value.length > 0 ? '' : 'Agrega al menos un rollo.';

    let linesOk = true;
    selectedLines.value.forEach((line) => {
        const qty = Number(line.quantity);
        const available = Number(line.roll.real_availability_value ?? 0);
        if (!line.quantity || Number.isNaN(qty) || qty <= 0) {
            line.error = 'Ingresa una cantidad mayor a 0.';
            linesOk = false;
        } else if (qty > available) {
            line.error = `No puede superar la disponibilidad real (${line.roll.real_availability}).`;
            linesOk = false;
        } else {
            line.error = '';
        }
    });

    return !errors.manual_order_code && !errors.lines && linesOk;
}

async function submitProgram() {
    if (isSubmitting.value || !validateForm()) return;

    isSubmitting.value = true;
    alertMessage.value = '';

    try {
        await axios.post('/kardex-telas/descargue-orden/manual/lote', {
            manual_order_code: form.manualOrderCode.trim(),
            notes: form.notes.trim() || null,
            lines: selectedLines.value.map((line) => ({
                fabric_roll_id: line.roll.id,
                programmed_meters: Number(line.quantity),
            })),
        });

        emit('success', { manualOrderCode: form.manualOrderCode.trim() });
        closeDialog();
    } catch (error: any) {
        errors.manual_order_code = error?.response?.data?.errors?.manual_order_code?.[0] ?? '';
        alertMessage.value = error?.response?.data?.message || 'No se pudo registrar la programación.';
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog :open="props.modelValue" modal @update:open="handleOpenChange">
        <FormDialogContent class="flex max-h-[90vh] max-w-4xl flex-col px-2 py-2">
            <AlertBox v-if="alertMessage" type="error" :message="alertMessage" @close="alertMessage = ''" />

            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Programar rollos por código de orden</DialogTitle>
                <DialogDescription>
                    Busca y agrega los rollos que necesites para este código, indicando cuántos metros usar de cada uno.
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-5 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Código de orden <span class="text-destructive">*</span></p>
                        <Input
                            v-model="form.manualOrderCode"
                            placeholder="Ej. OP-A-MANO-001"
                            :disabled="Boolean(props.manualOrderCode)"
                            :invalid="Boolean(errors.manual_order_code)"
                            :error-message="errors.manual_order_code"
                        />
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground">Notas</p>
                        <Input v-model="form.notes" placeholder="Opcional" />
                    </div>
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-semibold text-muted-foreground">Buscar rollo (por número, color o tela)</p>
                    <Input v-model="rollSearch" placeholder="Buscar..." />
                    <div v-if="isLoadingRolls" class="text-xs text-muted-foreground">Buscando...</div>
                    <div v-else-if="rollOptions.length > 0" class="max-h-40 divide-y divide-border overflow-y-auto rounded-md border border-border">
                        <button
                            v-for="roll in rollOptions"
                            :key="roll.id"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-muted/60 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="selectedIds.has(roll.id)"
                            @click="addRoll(roll)"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="font-medium">{{ roll.roll }}</span>
                                <span class="text-muted-foreground"> — {{ roll.fabric }}<template v-if="roll.color"> · {{ roll.color }}</template></span>
                                <Badge v-if="!roll.fabric_item_id" variant="outline" class="ml-1.5 text-[0.65rem]" title="Este rollo aún no tiene un ítem vinculado: el consumo no se reflejará en el inventario del ítem hasta que se vincule.">
                                    Sin ítem
                                </Badge>
                            </span>
                            <span class="shrink-0 text-xs text-muted-foreground">
                                {{ selectedIds.has(roll.id) ? 'Ya agregado' : `Disp: ${roll.real_availability}` }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-muted-foreground">Rollos seleccionados <span class="text-destructive">*</span></p>
                        <p v-if="errors.lines" class="text-xs text-destructive">{{ errors.lines }}</p>
                    </div>

                    <div v-if="selectedLines.length === 0" class="rounded-md border border-dashed border-border px-3 py-6 text-center text-xs text-muted-foreground">
                        Todavía no has agregado ningún rollo. Búscalo arriba y haz clic para agregarlo.
                    </div>

                    <div v-else class="overflow-x-auto rounded-md border border-border">
                        <Table class="min-w-full text-sm">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Rollo</TableHead>
                                    <TableHead>Tela</TableHead>
                                    <TableHead>Color</TableHead>
                                    <TableHead>Disponible</TableHead>
                                    <TableHead>Cantidad a usar</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="line in selectedLines" :key="line.roll.id">
                                    <TableCell class="font-medium">
                                        {{ line.roll.roll }}
                                        <Badge v-if="!line.roll.fabric_item_id" variant="outline" class="ml-1 text-[0.65rem]" title="Sin ítem vinculado todavía">
                                            Sin ítem
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="max-w-[160px] truncate text-muted-foreground" :title="line.roll.fabric">{{ line.roll.fabric }}</TableCell>
                                    <TableCell class="text-muted-foreground">{{ line.roll.color || '-' }}</TableCell>
                                    <TableCell class="text-muted-foreground">{{ line.roll.real_availability }}</TableCell>
                                    <TableCell>
                                        <Input
                                            v-model="line.quantity"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            placeholder="Ej. 25"
                                            class="w-28"
                                            :invalid="Boolean(line.error)"
                                            :error-message="line.error"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                                            title="Quitar rollo"
                                            @click="removeRoll(line.roll.id)"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0 justify-end gap-2 border-t border-border px-5 py-4">
                <Button variant="outline" size="pill" @click="closeDialog">Cancelar</Button>
                <Button variant="default" type="button" :disabled="isSubmitting" class="rounded-full px-5" @click="submitProgram">
                    {{ isSubmitting ? 'Guardando...' : `Programar ${selectedLines.length > 0 ? `(${selectedLines.length})` : ''}` }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
