<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { FabricItemOption, FabricRollView } from '@/domain/Textil';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const currencyFormatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 2 });

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        roll?: FabricRollView | null;
        canEditMainFabric?: boolean;
        canAdjustRolls?: boolean;
    }>(),
    { modelValue: false, roll: null, canEditMainFabric: false, canAdjustRolls: false },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void;
    (event: 'main-fabric-updated', payload: { rollId: number; itemId: number; itemName: string; message: string }): void;
    (event: 'quantity-corrected', payload: { rollId: number; newQuantity: number }): void;
    (event: 'price-updated', payload: { rollId: number; pricePerMeter: number }): void;
}>();

// ── Corrección de cantidad ──────────────────────────────────────
const newQuantity    = ref('');
const quantityReason = ref('');
const isSavingQty   = ref(false);
const qtyError       = ref('');

// ── Precio por metro ────────────────────────────────────────────
const newPrice      = ref('');
const isSavingPrice = ref(false);
const priceError    = ref('');

watch(
    () => props.roll,
    (roll) => {
        newQuantity.value    = roll?.roll_qty_value != null ? String(roll.roll_qty_value) : '';
        quantityReason.value = '';
        qtyError.value       = '';
        newPrice.value       = roll?.price_per_meter != null ? String(roll.price_per_meter) : '';
        priceError.value     = '';
    },
    { immediate: true },
);

async function saveQuantityCorrection() {
    if (!props.roll?.id) return;
    const qty = parseFloat(newQuantity.value.replace(',', '.'));
    if (isNaN(qty) || qty <= 0) {
        qtyError.value = 'Ingresa una cantidad válida mayor a 0.';
        return;
    }
    if (!quantityReason.value.trim()) {
        qtyError.value = 'El motivo es obligatorio.';
        return;
    }
    qtyError.value = '';
    isSavingQty.value = true;
    try {
        const res = await axios.patch(`/rollos-tela/${props.roll.id}/corregir-cantidad`, {
            new_quantity: qty,
            reason: quantityReason.value.trim(),
        });
        emit('quantity-corrected', { rollId: props.roll.id, newQuantity: res.data.roll_qty_value });
        quantityReason.value = '';
        showAlert(res.data.message || 'Cantidad corregida correctamente.', 'success');
    } catch (err: any) {
        const msg = err?.response?.data?.errors?.new_quantity?.[0]
            ?? err?.response?.data?.message
            ?? 'No se pudo corregir la cantidad.';
        qtyError.value = msg;
    } finally {
        isSavingQty.value = false;
    }
}

async function savePrice() {
    if (!props.roll?.id) return;
    const price = parseFloat(newPrice.value.replace(',', '.'));
    if (isNaN(price) || price < 0) {
        priceError.value = 'Ingresa un precio válido (0 o mayor).';
        return;
    }
    priceError.value = '';
    isSavingPrice.value = true;
    try {
        const res = await axios.patch(`/rollos-tela/${props.roll.id}/actualizar-precio`, {
            price_per_meter: price,
        });
        emit('price-updated', { rollId: props.roll.id, pricePerMeter: res.data.price_per_meter });
        showAlert(res.data.message || 'Precio actualizado correctamente.', 'success');
    } catch (err: any) {
        priceError.value = err?.response?.data?.message ?? 'No se pudo actualizar el precio.';
    } finally {
        isSavingPrice.value = false;
    }
}

const fabricSearch = ref('');
const fabricOptions = ref<FabricItemOption[]>([]);
const selectedFabricItemId = ref<string>('');
const isLoadingFabricOptions = ref(false);
const isSavingMainFabric = ref(false);
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | 'warning' | 'info'>('error');

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

function handleOpenChange(open: boolean) {
    emit('update:modelValue', open);
}

function showAlert(message: string, type: 'success' | 'error' | 'warning' | 'info' = 'error') {
    alertMessage.value = message;
    alertType.value = type;
}

watch(
    () => props.roll,
    (roll) => {
        selectedFabricItemId.value = roll?.fabric_item_id != null ? String(roll.fabric_item_id) : '';
        if (roll?.fabric_item_id && roll.fabric) {
            fabricOptions.value = [
                {
                    id: roll.fabric_item_id,
                    name: roll.fabric,
                    reference: null,
                },
            ];
        } else {
            fabricOptions.value = [];
        }
    },
    { immediate: true },
);

watch(
    () => props.modelValue,
    (open) => {
        if (open && props.canEditMainFabric) {
            fetchFabricOptions();
        }
    },
);

watch(fabricSearch, () => {
    if (!props.canEditMainFabric || !props.modelValue) {
        return;
    }

    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }

    searchDebounceTimer = setTimeout(() => {
        fetchFabricOptions();
    }, 300);
});

async function fetchFabricOptions() {
    if (!props.canEditMainFabric) {
        return;
    }

    isLoadingFabricOptions.value = true;

    try {
        const response = await axios.get('/item/data', {
            params: {
                search: fabricSearch.value.trim() || undefined,
                per_page: 20,
                status: 1,
                item_type: 'product',
            },
        });

        const rawOptions = Array.isArray(response?.data?.data) ? response.data.data : [];
        fabricOptions.value = rawOptions.map((item: { id: number; name: string; reference?: string | null }) => ({
            id: item.id,
            name: item.name,
            reference: item.reference ?? null,
        }));

        if (
            props.roll?.fabric_item_id &&
            !fabricOptions.value.some((option) => option.id === props.roll?.fabric_item_id)
        ) {
            fabricOptions.value.unshift({
                id: props.roll.fabric_item_id,
                name: props.roll.fabric || 'Tela actual',
                reference: null,
            });
        }
    } catch {
        showAlert('No se pudieron cargar las telas para relink.', 'error');
    } finally {
        isLoadingFabricOptions.value = false;
    }
}

async function saveMainFabricRelation() {
    if (!props.canEditMainFabric || !props.roll?.id) {
        showAlert('Solo superadmin puede modificar la tela principal.', 'warning');
        return;
    }

    if (!selectedFabricItemId.value) {
        showAlert('Selecciona una tela principal válida.', 'warning');
        return;
    }

    isSavingMainFabric.value = true;

    try {
        const response = await axios.patch(`/kardex-telas/inventario-rollos/${props.roll.id}/vincular-tela-principal`, {
            item_id: Number(selectedFabricItemId.value),
        });

        const selectedOption = fabricOptions.value.find((option) => String(option.id) === selectedFabricItemId.value);
        const message = response?.data?.message || 'Tela principal actualizada correctamente.';
        emit('main-fabric-updated', {
            rollId: props.roll.id,
            itemId: Number(selectedFabricItemId.value),
            itemName: selectedOption?.name || props.roll.fabric,
            message,
        });

        showAlert(message, 'success');
    } catch (error: any) {
        showAlert(error?.response?.data?.message || 'No se pudo actualizar la tela principal del rollo.', 'error');
    } finally {
        isSavingMainFabric.value = false;
    }
}

const avatarInitials = computed(() => {
    if (!props.roll?.roll) return 'RL';
    const letters = props.roll.roll.replace(/[^a-zA-Z]/g, '');
    return letters.slice(0, 2).toUpperCase() || 'RL';
});

const statusLabel = computed(() => (props.roll?.status === 'available' ? 'Disponible' : 'Agotado'));
const statusVariant = computed(() => (props.roll?.status === 'available' ? 'default' : 'destructive'));

const infoRows = computed(() => {
    if (!props.roll) return [];
    return [
        { label: 'N. Pedido', value: props.roll.order_number || '—' },
        { label: 'Proveedor', value: props.roll.provider || '—' },
        { label: 'Color', value: props.roll.color || '—' },
        { label: 'Ubicación', value: props.roll.location || '—' },
        { label: 'Fecha de creación', value: props.roll.created_at || '—' },
        { label: 'Usuario creador', value: props.roll.creator || '—' },
    ];
});

const metricCards = computed(() => {
    if (!props.roll) return [];
    return [
        { label: 'Ancho', value: props.roll.width },
        { label: 'Cant. rollo', value: props.roll.roll_qty },
        { label: 'Salida parcial', value: props.roll.partial_output },
        { label: 'Disp. parcial', value: props.roll.partial_availability },
        { label: 'Cant. salida', value: props.roll.exit_quantity },
        { label: 'Disp. real', value: props.roll.real_availability, highlight: true },
    ];
});
</script>

<template>
    <Dialog :open="props.modelValue" modal @update:open="handleOpenChange">
        <FormDialogContent class="flex max-h-[95vh] max-w-2xl flex-col px-2 py-2">
            <!-- Header -->
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <div class="flex items-start gap-4">
                    <!-- Avatar de iniciales -->
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base font-bold tracking-wide text-primary"
                        aria-hidden="true"
                    >
                        {{ avatarInitials }}
                    </div>

                    <!-- Nombre + tela + badge -->
                    <div class="min-w-0 flex-1 space-y-0.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <DialogTitle class="truncate text-base font-semibold leading-tight text-foreground">
                                {{ props.roll?.roll ?? '—' }}
                            </DialogTitle>
                            <Badge :variant="statusVariant" class="shrink-0 text-[11px]">
                                {{ statusLabel }}
                            </Badge>
                        </div>
                        <DialogDescription class="truncate text-xs text-muted-foreground">
                            {{ props.roll?.fabric ?? 'Sin tela asignada' }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- Cuerpo scrollable -->
            <div class="flex-1 overflow-y-auto scroll-smooth px-5 py-5" style="scrollbar-width: thin">
                <div
                    v-if="alertMessage"
                    class="pointer-events-none fixed left-1/2 top-6 z-[99999] flex -translate-x-1/2 justify-center px-4 sm:px-0"
                >
                    <AlertBox
                        :type="alertType"
                        :title="alertType === 'warning' ? 'Advertencia' : alertType === 'success' ? 'Éxito' : 'Error'"
                        :message="alertMessage"
                        :auto-close-ms="alertType === 'error' ? 0 : 4000"
                        class="pointer-events-auto"
                        @close="alertMessage = ''"
                    />
                </div>

                <div v-if="props.roll" class="space-y-5">
                    <!-- Información general -->
                    <section>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Información general</p>
                        <div class="rounded-xl border border-border bg-card shadow-sm">
                            <dl>
                                <div
                                    v-for="(row, index) in infoRows"
                                    :key="row.label"
                                    class="grid grid-cols-2 gap-x-4 px-4 py-2.5 text-sm"
                                    :class="index % 2 === 0 ? 'bg-muted/30' : 'bg-transparent'"
                                >
                                    <dt class="font-medium text-muted-foreground">{{ row.label }}</dt>
                                    <dd class="truncate text-right font-medium text-foreground">{{ row.value }}</dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    <!-- Medidas y disponibilidad -->
                    <section>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Medidas y disponibilidad</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div
                                v-for="card in metricCards"
                                :key="card.label"
                                class="flex flex-col items-center justify-center gap-1 rounded-xl border px-3 py-4 text-center shadow-sm"
                                :class="
                                    card.highlight
                                        ? 'border-primary/30 bg-primary/5 dark:bg-primary/10'
                                        : 'border-border bg-card'
                                "
                            >
                                <span class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{{ card.label }}</span>
                                <span
                                    class="text-xl font-bold leading-tight"
                                    :class="card.highlight ? 'text-primary' : 'text-foreground'"
                                >
                                    {{ card.value }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <section>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Tela principal del rollo</p>
                        <div class="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm text-muted-foreground">Tela actual</p>
                                <p class="max-w-[60%] truncate text-right text-sm font-semibold text-foreground">
                                    {{ props.roll.fabric || 'Sin tela asignada' }}
                                </p>
                            </div>

                            <div v-if="props.canEditMainFabric" class="space-y-3">
                                <div class="space-y-1">
                                    <p class="text-xs font-semibold text-muted-foreground">Buscar tela</p>
                                    <Input v-model="fabricSearch" placeholder="Referencia o nombre" class="h-9" />
                                </div>

                                <div class="space-y-1">
                                    <p class="text-xs font-semibold text-muted-foreground">Nueva tela principal</p>
                                    <Select v-model="selectedFabricItemId">
                                        <SelectTrigger class="h-9">
                                            <SelectValue
                                                :placeholder="isLoadingFabricOptions ? 'Cargando telas...' : 'Selecciona una tela'"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="option in fabricOptions" :key="option.id" :value="String(option.id)">
                                                {{ option.reference ? `${option.reference} · ${option.name}` : option.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="flex justify-end">
                                    <Button
                                        type="button"
                                        size="pill"
                                        :disabled="isSavingMainFabric || !selectedFabricItemId"
                                        @click="saveMainFabricRelation"
                                    >
                                        {{ isSavingMainFabric ? 'Guardando...' : 'Guardar tela principal' }}
                                    </Button>
                                </div>
                            </div>

                            <p v-else class="text-xs text-muted-foreground">
                                Solo superadmin puede actualizar la relación del rollo con su tela principal.
                            </p>
                        </div>
                    </section>

                    <!-- Corrección de cantidad (solo usuarios con permiso editar rollos) -->
                    <section v-if="props.canAdjustRolls">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Corrección de cantidad</p>
                        <div class="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-muted-foreground">Cantidad actual</span>
                                <span class="font-semibold text-foreground">{{ props.roll?.roll_qty ?? '—' }} mts</span>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-semibold text-muted-foreground">Nueva cantidad total (mts) <span class="text-destructive">*</span></p>
                                <Input
                                    v-model="newQuantity"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="h-9"
                                    placeholder="Ej: 48.5"
                                />
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-semibold text-muted-foreground">Motivo <span class="text-destructive">*</span></p>
                                <Input data-no-uppercase
                                    v-model="quantityReason"
                                    class="h-9"
                                    placeholder="Ej: Error de medición en recepción"
                                    maxlength="500"
                                />
                            </div>
                            <p v-if="qtyError" class="text-xs text-destructive">{{ qtyError }}</p>
                            <div class="flex justify-end">
                                <Button
                                    type="button"
                                    size="pill"
                                    :disabled="isSavingQty"
                                    @click="saveQuantityCorrection"
                                >
                                    {{ isSavingQty ? 'Guardando...' : 'Aplicar corrección' }}
                                </Button>
                            </div>
                        </div>
                    </section>

                    <!-- Precio por metro (solo usuarios con permiso editar rollos) -->
                    <section v-if="props.canAdjustRolls">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Precio por metro</p>
                        <div class="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-muted-foreground">Precio actual</span>
                                <span class="font-semibold text-foreground">
                                    {{ props.roll?.price_per_meter != null
                                        ? currencyFormatter.format(props.roll.price_per_meter)
                                        : 'Sin precio definido' }}
                                </span>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-semibold text-muted-foreground">Nuevo precio por metro (COP) <span class="text-destructive">*</span></p>
                                <Input
                                    v-model="newPrice"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="h-9"
                                    placeholder="Ej: 12500"
                                />
                                <p class="text-xs text-muted-foreground">Este valor se usa como costo unitario en los movimientos de inventario futuros.</p>
                            </div>
                            <p v-if="priceError" class="text-xs text-destructive">{{ priceError }}</p>
                            <div class="flex justify-end">
                                <Button
                                    type="button"
                                    size="pill"
                                    :disabled="isSavingPrice"
                                    @click="savePrice"
                                >
                                    {{ isSavingPrice ? 'Guardando...' : 'Guardar precio' }}
                                </Button>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Estado vacío -->
                <div v-else class="flex h-40 items-center justify-center text-sm text-muted-foreground">
                    No hay información disponible.
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="shrink-0 border-t border-border px-5 py-4">
                <DialogClose as-child>
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center">
                        Cerrar
                    </Button>
                </DialogClose>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
