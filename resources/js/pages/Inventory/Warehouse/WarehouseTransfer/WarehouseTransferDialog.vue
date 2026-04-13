<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useItemSearch } from '@/composables/useItemSearch';
import type { Option } from '@/domain/shared';
import axios from 'axios';
import { Trash } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

function getTodayDate() {
    return new Date().toISOString().split('T')[0];
}

type WarehouseOption = { id: number; name: string };

type TransferDialogMode = 'create' | 'edit' | 'view';

type TransferItem = {
    itemId: number | null;
    currentQty: number;
    transferQty: number;
};

type TransferForm = {
    originId: number | null;
    destinationId: number | null;
    date: string;
    observation: string;
    items: TransferItem[];
};

type ExistingTransfer = {
    id: number;
    originId: number | null;
    destinationId: number | null;
    date: string;
    observation?: string | null;
    items: Array<{ itemId: number | null; quantity: number }>;
};

const props = defineProps<{
    open: boolean;
    warehouses: WarehouseOption[];
    saving?: boolean;
    errorMessage?: string;
    mode?: TransferDialogMode;
    transfer?: ExistingTransfer | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'save', value: TransferForm): void;
    (e: 'cancel'): void;
    (e: 'clear-error'): void;
}>();

const dialogMode = computed<TransferDialogMode>(() => props.mode ?? 'create');
const isEditMode = computed(() => dialogMode.value === 'edit');
const isViewMode = computed(() => dialogMode.value === 'view');

const createEmptyItem = (): TransferItem => ({
    itemId: null,
    currentQty: 0,
    transferQty: 0,
});

const createEmptyForm = (): TransferForm => ({
    originId: null,
    destinationId: null,
    date: getTodayDate(),
    observation: '',
    items: [createEmptyItem()],
});

const {
    options: itemSearchOptions,
    isLoading: isLoadingItems,
    search: searchItems,
    loadInitial: loadItemsInitial,
} = useItemSearch();

const transferForm = ref<TransferForm>(createEmptyForm());
const itemRowRefs = ref<Array<HTMLElement | null>>([]);
const itemWarehouseStocks = ref<Record<number, Array<{ warehouse_id: number; quantity: number }>>>({});

const destinationWarehouses = computed(() => props.warehouses.filter((warehouse) => warehouse.id !== transferForm.value.originId));

const warehouseOptions = computed<Option[]>(() => props.warehouses.map((warehouse) => ({ id: warehouse.id, name: warehouse.name })));

const destinationOptions = computed<Option[]>(() => destinationWarehouses.value.map((warehouse) => ({ id: warehouse.id, name: warehouse.name })));

const availableOriginWarehouseIds = computed<Set<number>>(() => {
    const ids = new Set<number>();
    transferForm.value.items.forEach((item) => {
        if (!item.itemId) return;
        (itemWarehouseStocks.value[item.itemId] ?? []).forEach((row) => {
            if (Number(row.quantity) > 0) {
                ids.add(Number(row.warehouse_id));
            }
        });
    });
    return ids;
});

const filteredOriginOptions = computed<Option[]>(() => {
    if (!availableOriginWarehouseIds.value.size) {
        return warehouseOptions.value;
    }
    return warehouseOptions.value.filter((option) => availableOriginWarehouseIds.value.has(Number(option.id)));
});

watch(filteredOriginOptions, (options) => {
    if (transferForm.value.originId && !options.some((option) => Number(option.id) === Number(transferForm.value.originId))) {
        transferForm.value.originId = null;
    }
});

const dateValue = computed({
    get: () => transferForm.value.date,
    set: (value: string | null) => {
        transferForm.value.date = value ?? getTodayDate();
    },
});

const totalTransferred = computed(() => transferForm.value.items.reduce((sum, item) => sum + (Number(item.transferQty) || 0), 0));

const hasSameWarehouses = computed(() => !!transferForm.value.originId && transferForm.value.originId === transferForm.value.destinationId);

const hasValidItems = computed(() => transferForm.value.items.some((item) => Number(item.transferQty) > 0 && Number(item.itemId) > 0));

const hasMissingItemId = computed(() => transferForm.value.items.some((item) => Number(item.transferQty) > 0 && !(Number(item.itemId) > 0)));

const isSaveDisabled = computed(
    () =>
        !transferForm.value.originId ||
        !transferForm.value.destinationId ||
        hasSameWarehouses.value ||
        hasMissingItemId.value ||
        !hasValidItems.value,
);

const isSaving = computed(() => props.saving ?? false);

const dialogTitle = computed(() => {
    if (isViewMode.value) {
        return 'Ver transferencia entre bodegas';
    }
    if (isEditMode.value) {
        return 'Editar transferencia entre bodegas';
    }
    return 'Nueva transferencia entre bodegas';
});

const dialogDescription = computed(() => {
    if (isViewMode.value) {
        return 'Consulta los datos del traslado registrado.';
    }
    if (isEditMode.value) {
        return 'Actualiza los datos del traslado y las cantidades correspondientes.';
    }
    return 'Completa los datos para registrar el traslado de productos entre bodegas.';
});

const cancelButtonLabel = computed(() => (isViewMode.value ? 'Cerrar' : 'Cancelar'));

const validationAlertVisible = ref(false);
const validationAlert = computed(() => {
    if (hasSameWarehouses.value) {
        return {
            type: 'error' as const,
            title: 'Bodegas inválidas',
            message: 'La bodega de origen y destino no pueden ser la misma.',
        };
    }
    if (hasMissingItemId.value) {
        return {
            type: 'warning' as const,
            title: 'Ítem faltante',
            message: 'Debes seleccionar un ítem para cada línea con cantidad.',
        };
    }
    if (!hasValidItems.value) {
        return {
            type: 'warning' as const,
            title: 'Faltan ítems',
            message: 'Agrega al menos un ítem con cantidad a transferir mayor a cero.',
        };
    }
    return null;
});

watch(
    () => transferForm.value.originId,
    (originId) => {
        if (originId && originId === transferForm.value.destinationId) {
            transferForm.value.destinationId = null;
        }
        updateRowsCurrentQty();
    },
);

watch(
    validationAlert,
    (value) => {
        validationAlertVisible.value = Boolean(value);
    },
    { immediate: true },
);

const localErrorMessage = computed(() =>
    props.errorMessage || (validationAlertVisible.value && validationAlert.value ? validationAlert.value.message : ''),
);
const localErrorType = computed<'error' | 'warning' | 'info'>(
    () => (props.errorMessage ? 'error' : ((validationAlert.value?.type ?? 'error') as 'error' | 'warning' | 'info')),
);

function handleCloseError() {
    if (props.errorMessage) emit('clear-error');
    else validationAlertVisible.value = false;
}

function initializeForm() {
    itemRowRefs.value = [];
    const activeTransfer = props.transfer;
    const hasItems = Array.isArray(activeTransfer?.items) && activeTransfer.items.length > 0;
    const items = hasItems
        ? activeTransfer!.items.map((item) => ({
              itemId: item.itemId ?? null,
              currentQty: 0,
              transferQty: Number(item.quantity) || 0,
          }))
        : [createEmptyItem()];

    transferForm.value = {
        originId: activeTransfer?.originId ?? null,
        destinationId: activeTransfer?.destinationId ?? null,
        date: activeTransfer?.date ?? getTodayDate(),
        observation: activeTransfer?.observation ?? '',
        items,
    };

    if (hasItems) {
        activeTransfer!.items.forEach((item) => {
            if (item.itemId) {
                void ensureItemWarehouseStock(item.itemId);
            }
        });
    }

    validationAlertVisible.value = Boolean(validationAlert.value);
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            initializeForm();
            loadItemsInitial();
        }
    },
);

watch(
    () => props.transfer,
    () => {
        if (props.open && (isEditMode.value || isViewMode.value)) {
            initializeForm();
        }
    },
);

const itemOptions = computed<Option[]>(() =>
    itemSearchOptions.value.map((item) => ({
        id: item.id,
        name: item.label,
    })),
);

async function ensureItemWarehouseStock(itemId: number | null) {
    if (!itemId) return;
    if (itemWarehouseStocks.value[itemId]) return;

    try {
        const response = await axios.get('/warehouse-items', { params: { item_id: itemId } });
        const payload = Array.isArray(response?.data?.data) ? response.data.data : [];
        itemWarehouseStocks.value = {
            ...itemWarehouseStocks.value,
            [itemId]: payload.map((row: { warehouse_id: number | string; quantity: number | string }) => ({
                warehouse_id: Number(row.warehouse_id),
                quantity: Number(row.quantity),
            })),
        };
        updateRowsCurrentQty(itemId);
    } catch (error) {
        console.error('No se pudieron cargar los stocks del ítem', error);
        itemWarehouseStocks.value[itemId] = [];
    }
}

function updateRowCurrentQty(row: TransferItem) {
    if (!row.itemId || !transferForm.value.originId) {
        row.currentQty = 0;
        return;
    }
    const stock = itemWarehouseStocks.value[row.itemId] ?? [];
    const found = stock.find((entry) => Number(entry.warehouse_id) === Number(transferForm.value.originId));
    row.currentQty = found ? Number(found.quantity) : 0;
}

function updateRowsCurrentQty(itemId: number | null = null) {
    transferForm.value.items.forEach((row: TransferItem) => {
        if (itemId === null || row.itemId === itemId) {
            updateRowCurrentQty(row);
        }
    });
}

import type { ComponentPublicInstance } from 'vue';
function setItemRowRef(idx: number) {
    return (el: Element | ComponentPublicInstance | null) => {
        // Solo guarda la referencia si es un elemento HTML
        itemRowRefs.value[idx] = el instanceof HTMLElement ? el : null;
    };
}

function scrollItemRowIntoView(idx: number) {
    const row = itemRowRefs.value[idx];
    if (!row) return;
    row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function addTransferItem() {
    transferForm.value.items.push(createEmptyItem());
}

function handleAddLine() {
    if (isViewMode.value) return;
    addTransferItem();
}

function removeTransferItem(index: number) {
    if (transferForm.value.items.length > 1) {
        transferForm.value.items.splice(index, 1);
    }
}

function closeDialog() {
    validationAlertVisible.value = false;
    emit('update:open', false);
    emit('cancel');
}

function saveDialog() {
    if (isSaveDisabled.value || isSaving.value) {
        return;
    }

    emit('save', {
        ...transferForm.value,
        items: transferForm.value.items.map((item) => ({ ...item })),
    });
}

function getItemSelectionValue(item: TransferItem): number | null {
    // Garantiza que nunca retorna un array
    return typeof item.itemId === 'number' ? item.itemId : null;
}

function handleItemSelection(item: TransferItem, value: Option['id'] | null | undefined) {
    // Si value es un array, lo ignoramos (no soportamos selección múltiple)
    if (Array.isArray(value)) {
        item.itemId = null;
        item.currentQty = 0;
        return;
    }
    if (value === null || value === undefined || value === '') {
        item.itemId = null;
        item.currentQty = 0;
        return;
    }

    const parsedId = Number(value);
    if (Number.isNaN(parsedId)) {
        item.itemId = null;
        item.currentQty = 0;
        return;
    }

    item.itemId = parsedId;
    item.currentQty = item.currentQty || 0;
    void ensureItemWarehouseStock(parsedId);
    updateRowCurrentQty(item);
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => (val ? emit('update:open', val) : closeDialog())">
        <FormDialogContent class="flex max-h-[98vh] max-w-[150vh] flex-col px-2 py-2">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription class="pb-2">
                    {{ dialogDescription }}
                </DialogDescription>
            </DialogHeader>
            <div class="relative flex-1 overflow-y-auto px-1 pr-2">
                <AlertStack
                    :error-message="localErrorMessage"
                    :error-type="localErrorType"
                    @close-error="handleCloseError"
                />
                <TooltipProvider>
                    <div class="space-y-4 pt-4">
                        <div>
                            <div class="grid items-start gap-6 lg:grid-cols-[1.15fr,1fr]">
                                <div class="space-y-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1 text-sm font-semibold text-foreground">
                                            <span>
                                                Bodega origen
                                                <span class="text-red-500">*</span>
                                            </span>
                                            <HelpTooltip
                                                text="Elige la bodega, almacén o depósito desde donde saldrán los productos."
                                                ariaLabel="Ayuda sobre bodega origen"
                                            />
                                        </div>
                                        <SelectField
                                            v-model="transferForm.originId"
                                            label=""
                                            :options="filteredOriginOptions"
                                            :required="true"
                                            placeholder="Seleccionar"
                                            :disabled="isViewMode || !filteredOriginOptions.length"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1 text-sm font-semibold text-foreground">
                                            <span>
                                                Bodega destino
                                                <span class="text-red-500">*</span>
                                            </span>
                                            <HelpTooltip
                                                text="Elige la bodega, almacén o depósito donde ingresarán los productos."
                                                ariaLabel="Ayuda sobre bodega destino"
                                            />
                                        </div>
                                        <SelectField
                                            v-model="transferForm.destinationId"
                                            label=""
                                            :options="destinationOptions"
                                            :required="true"
                                            :disabled="isViewMode || !transferForm.originId"
                                            placeholder="Seleccionar"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1 text-sm font-semibold text-foreground">
                                            <span>
                                                Fecha
                                                <span class="text-red-500">*</span>
                                            </span>
                                            <HelpTooltip text="Selecciona la fecha del traslado o deja la actual." ariaLabel="Ayuda sobre fecha" />
                                        </div>
                                        <DatePickerField
                                            v-model="dateValue"
                                            label=""
                                            helpText="Selecciona la fecha del traslado o mantén la actual."
                                            :required="true"
                                            class="w-full"
                                            :disabled="isViewMode"
                                        />
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1 text-sm font-semibold text-foreground">
                                        <span>Observaciones</span>
                                        <HelpTooltip text="Agrega información adicional sobre el traslado." ariaLabel="Ayuda sobre observaciones" />
                                    </div>
                                    <TextareaField
                                        v-model="transferForm.observation"
                                        label=""
                                        placeholder="Observaciones adicionales"
                                        :rows="7"
                                        class="min-h-[140px]"
                                        :disabled="isViewMode"
                                    />
                                </div>
                            </div>
                        </div>
                        <InventoryDetailTableLayout
                            title="Ítems de venta"
                            button-label="Agregar ítem"
                            :show-create-line-button="!isViewMode"
                            :create-line-button-disabled="isViewMode"
                            @create-line="handleAddLine"
                        >
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-muted">
                                        <th class="p-2 font-normal">Ítem</th>
                                        <th class="p-2 font-normal">Cantidad actual</th>
                                        <th class="p-2 font-normal">Cantidad a transferir</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, idx) in transferForm.items" :key="idx" :ref="setItemRowRef(idx)">
                                        <td class="p-2">
                                            <SelectField
                                                :modelValue="getItemSelectionValue(row)"
                                                :options="itemOptions"
                                                label=""
                                                placeholder="Buscar ítem..."
                                                :searchable="true"
                                                :loading="isLoadingItems"
                                                :disabled="isViewMode"
                                                class="w-full"
                                                @update:modelValue="
                                                    (value) => handleItemSelection(row, Array.isArray(value) ? (value[0] ?? null) : value)
                                                "
                                                @search="searchItems"
                                                @focus="() => scrollItemRowIntoView(idx)"
                                            />
                                        </td>
                                        <td class="p-2">
                                            <InputField v-model="row.currentQty" label="" type="number" :disabled="true" />
                                        </td>
                                        <td class="p-2">
                                            <InputField v-model="row.transferQty" label="" type="number" :disabled="isViewMode" />
                                        </td>
                                        <td class="p-2">
                                            <Button
                                                v-if="transferForm.items.length > 1 && !isViewMode"
                                                variant="ghost"
                                                size="icon"
                                                @click="removeTransferItem(idx)"
                                            >
                                                <Trash class="h-4 w-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </InventoryDetailTableLayout>
                        <div class="mt-2 text-right font-normal">Total transferido: {{ totalTransferred }}</div>
                    </div>
                </TooltipProvider>
            </div>
            <DialogFooter class="mt-4 flex-shrink-0 border-t pt-4">
                <div class="flex w-full justify-end gap-3">
                    <Button variant="outline" size="pill" @click="closeDialog">
                        {{ cancelButtonLabel }}
                    </Button>
                    <Button v-if="!isViewMode" variant="default" size="pill" class="disabled:opacity-60" :disabled="isSaveDisabled || isSaving" @click="saveDialog">
                        {{ isSaving ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
