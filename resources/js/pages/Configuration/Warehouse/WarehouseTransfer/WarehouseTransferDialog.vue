<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import AlertBox from '@/components/AlertBox.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { Option } from '@/domain/shared';
import type { Item } from '@/domain/inventory';
import axios from 'axios';

function getTodayDate() {
	return new Date().toISOString().split('T')[0];
}

type WarehouseOption = { id: number; name: string };

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

const props = defineProps<{
	open: boolean;
	warehouses: WarehouseOption[];
	saving?: boolean;
	errorMessage?: string;
}>();

const emit = defineEmits<{
	(e: 'update:open', value: boolean): void;
	(e: 'save', value: TransferForm): void;
	(e: 'cancel'): void;
	(e: 'clear-error'): void;
}>();

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

const transferForm = ref<TransferForm>(createEmptyForm());
const catalogItems = ref<Item[]>([]);
const isLoadingItems = ref(false);

watch(
	() => transferForm.value.originId,
	(originId) => {
		if (originId && originId === transferForm.value.destinationId) {
			transferForm.value.destinationId = null;
		}
	},
);

const destinationWarehouses = computed(() =>
	props.warehouses.filter((warehouse) => warehouse.id !== transferForm.value.originId),
);

const warehouseOptions = computed<Option[]>(() =>
	props.warehouses.map((warehouse) => ({ id: warehouse.id, name: warehouse.name })),
);

const destinationOptions = computed<Option[]>(() =>
	destinationWarehouses.value.map((warehouse) => ({ id: warehouse.id, name: warehouse.name })),
);

const dateValue = computed({
	get: () => transferForm.value.date,
	set: (value: string | null) => {
		transferForm.value.date = value ?? getTodayDate();
	},
});

const totalTransferred = computed(() =>
	transferForm.value.items.reduce((sum, item) => sum + (Number(item.transferQty) || 0), 0),
);

const hasSameWarehouses = computed(
	() => !!transferForm.value.originId && transferForm.value.originId === transferForm.value.destinationId,
);

const hasValidItems = computed(() =>
	transferForm.value.items.some(
		(item) => Number(item.transferQty) > 0 && Number(item.itemId) > 0,
	),
);

const hasMissingItemId = computed(() =>
	transferForm.value.items.some(
		(item) => Number(item.transferQty) > 0 && !(Number(item.itemId) > 0),
	),
);

const isSaveDisabled = computed(
	() =>
		!transferForm.value.originId ||
		!transferForm.value.destinationId ||
		hasSameWarehouses.value ||
		hasMissingItemId.value ||
		!hasValidItems.value,
);

const isSaving = computed(() => props.saving ?? false);

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
	validationAlert,
	(value) => {
		validationAlertVisible.value = Boolean(value);
	},
	{ immediate: true },
);

watch(
	() => props.open,
	(isOpen) => {
		if (isOpen) {
			transferForm.value = createEmptyForm();
			void fetchItems();
			validationAlertVisible.value = Boolean(validationAlert.value);
		}
	},
);
const itemOptions = computed<Option[]>(() =>
	catalogItems.value.map((item) => ({
		id: item.id,
		name: item.reference ? `${item.name} - ${item.reference}` : item.name,
	})),
);

async function fetchItems() {
	if (isLoadingItems.value) return;
	isLoadingItems.value = true;
	try {
		const response = await axios.get('/item/data', { params: { per_page: 1000 } });
		catalogItems.value = Array.isArray(response?.data?.data) ? response.data.data : [];
	} catch (error) {
		console.error('Error al cargar ítems', error);
		catalogItems.value = [];
	} finally {
		isLoadingItems.value = false;
	}
}

function addTransferItem() {
	transferForm.value.items.push(createEmptyItem());
}

function removeTransferItem(index: number) {
	if (transferForm.value.items.length > 1) {
		transferForm.value.items.splice(index, 1);
	}
}

function closeDialog() {
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

function getItemSelectionValue(item: TransferItem) {
	return item.itemId ?? null;
}

function handleItemSelection(item: TransferItem, value: Option['id'] | null | undefined) {
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
}

</script>

<template>
	<Dialog :open="open" @update:open="val => (val ? emit('update:open', val) : closeDialog())">
		<DialogContent class="max-w-[200vh] max-h-[90vh] flex flex-col p-0">
			<DialogHeader class="flex-shrink-0">
				<DialogTitle>Nueva transferencia entre bodegas, almacenes o depósitos</DialogTitle>
				<DialogDescription class="pb-2">
					Completa los datos para registrar el traslado de productos entre bodegas.
				</DialogDescription>
			</DialogHeader>
			<div class="flex-1 overflow-y-auto pr-2 relative">
				<div
					class="absolute left-1/2 top-5 z-30 -translate-x-1/2 flex flex-col gap-2 pointer-events-auto"
					style="max-width: 400px;"
				>
					<AlertBox
						v-if="errorMessage"
						type="error"
						title="Error al guardar"
						:message="errorMessage"
						class="px-2 shadow-lg"
						:autoCloseMs="2000"
						@close="emit('clear-error')"
					/>
					<AlertBox
						v-if="validationAlert && validationAlertVisible"
						:type="validationAlert.type"
						:title="validationAlert.title"
						:message="validationAlert.message"
						class="px-2 shadow-lg"
						:autoCloseMs="2000"
						@close="validationAlertVisible = false"
					/>
				</div>
				<TooltipProvider>
					<div class="space-y-4 pt-10">
						<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
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
									:options="warehouseOptions"
									:required="true"
									placeholder="Seleccionar"
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
									:disabled="!transferForm.originId"
									placeholder="Seleccionar"
								/>
							</div>
							<div class="space-y-1">
								<div class="flex items-center gap-1 text-sm font-semibold text-foreground">
									<span>
										Fecha
										<span class="text-red-500">*</span>
									</span>
									<HelpTooltip
										text="Selecciona la fecha del traslado o deja la actual."
										ariaLabel="Ayuda sobre fecha"
									/>
								</div>
								<InputDate v-model="dateValue" label="" :required="true" />
							</div>
							<div class="space-y-1">
								<div class="flex items-center gap-1 text-sm font-semibold text-foreground">
									<span>Observaciones</span>
									<HelpTooltip
										text="Agrega información adicional sobre el traslado."
										ariaLabel="Ayuda sobre observaciones"
									/>
								</div>
								<TextareaField
									v-model="transferForm.observation"
									label=""
									placeholder="Observaciones adicionales"
									:rows="3"
								/>
							</div>
						</div>
						<InventoryDetailTableLayout
							title="Ítems de venta"
							button-label="Agregar ítem"
							@create-line="addTransferItem"
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
								<tr v-for="(row, idx) in transferForm.items" :key="idx">
									<td class="p-2">
										<SelectField
											:modelValue="getItemSelectionValue(row)"
											:options="itemOptions"
											label=""
											placeholder="Selecciona un ítem"
											:searchable="true"
											:disabled="isLoadingItems"
											class="w-full"
											@update:modelValue="value => handleItemSelection(row, value)"
										/>
									</td>
									<td class="p-2">
										<InputField
											v-model="row.currentQty"
											label=""
											type="number"
											:disabled="true"
										/>
									</td>
									<td class="p-2">
										<InputField
											v-model="row.transferQty"
											label=""
											type="number"
										/>
									</td>
									<td class="p-2">
										<Button
											variant="ghost"
											size="icon"
											@click="removeTransferItem(idx)"
											:disabled="transferForm.items.length === 1"
										>
											×
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
			<DialogFooter class="flex-shrink-0 border-t pt-4 mt-4">
				<div class="flex justify-end gap-3 w-full">
					<button
						type="button"
						class="inline-flex h-10 items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
						@click="closeDialog"
					>
						Cancelar
					</button>
					<button
						type="button"
						:disabled="isSaveDisabled || isSaving"
						class="inline-flex h-10 items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-60"
						@click="saveDialog"
					>
						{{ isSaving ? 'Guardando...' : 'Guardar' }}
					</button>
				</div>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
