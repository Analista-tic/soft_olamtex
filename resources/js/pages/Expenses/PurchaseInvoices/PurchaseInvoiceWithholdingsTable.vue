<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { CirclePlus } from 'lucide-vue-next';
import type { PurchaseInvoiceWithholding } from '@/domain/Expenses';
import DeleteButton from '@/components/common/DeleteButton.vue';
import RetentionDialog from '@/pages/Finance/Retention/RetentionDialog.vue';
import AlertBox from '@/components/AlertBox.vue';
import {
    createRetention as createRetentionRequest,
    createRetentionForm,
    fetchRetentionsPage,
    mapRetentionFormToPayload,
    type Retention,
    type RetentionForm,
} from '@/domain/Finance';

defineOptions({ name: 'WithholdingTable' });

const props = withDefaults(
    defineProps<{
        modelValue: PurchaseInvoiceWithholding[];
        isReadOnly?: boolean;
        /** Subtotal de la factura; cuando base_amount es null se usa como base (asumida). */
        invoiceSubtotal?: number;
    }>(),
    { invoiceSubtotal: 0 },
);
const emit = defineEmits<{
    (e: 'update:modelValue', value: PurchaseInvoiceWithholding[]): void;
}>();

const localWithholdings = ref<PurchaseInvoiceWithholding[]>(
    Array.isArray(props.modelValue) ? props.modelValue.map((w) => ({ ...w })) : []
);

const retentions = ref<Retention[]>([]);
const isReadOnly = computed(() => !!props.isReadOnly);
const retentionOptions = computed(() =>
    retentions.value.map((r) => ({ id: r.name, name: `${r.name} (${r.percent}%)` })),
);

const retentionDialogOpen = ref(false);
const retentionDialogLoading = ref(false);
const retentionDialogType = ref<'create' | 'edit'>('create');
const retentionDialogForm = ref<RetentionForm>(createRetentionForm());
const successMessage = ref('');
const retentionSelectKey = ref(0);

async function fetchRetentions() {
    try {
        const data = await fetchRetentionsPage({ per_page: 1000, status: 'active' });
        retentions.value = data.data;
    } catch {
        retentions.value = [];
    }
}

onMounted(fetchRetentions);

watch(
    () => props.modelValue,
    (val) => {
        localWithholdings.value = Array.isArray(val) ? val.map((w) => ({ ...w })) : [];
    },
    { immediate: true },
);

/** Base efectiva: si base_amount es null/omitido se usa el subtotal de la factura (asumida). */
function getEffectiveBase(w: PurchaseInvoiceWithholding): number {
    const b = w.base_amount;
    if (b != null && Number.isFinite(Number(b))) return Number(b);
    return Number(props.invoiceSubtotal) || 0;
}

function getWithholdingValue(w: PurchaseInvoiceWithholding): number {
    return (getEffectiveBase(w) * (Number(w.percentage) || 0)) / 100;
}

function getBaseAmountInputValue(w: PurchaseInvoiceWithholding): string | number | undefined {
    return w.base_amount == null ? undefined : Number(w.base_amount);
}

function updateBaseAmount(w: PurchaseInvoiceWithholding, value: string | number | undefined) {
    if (value === '' || value == null) {
        w.base_amount = null;
        emit('update:modelValue', [...localWithholdings.value]);
        return;
    }

    const numericValue = Number(value);
    w.base_amount = Number.isFinite(numericValue) ? numericValue : null;
    emit('update:modelValue', [...localWithholdings.value]);
}

function addWithholding() {
    if (isReadOnly.value) return;
    // Base null implica "usar subtotal" sin persistir un valor redundante.
    emit('update:modelValue', [...localWithholdings.value, { name: '', percentage: 0, base_amount: null }]);
}

function removeWithholding(idx: number) {
    if (isReadOnly.value) return;
    const newList = localWithholdings.value.slice();
    newList.splice(idx, 1);
    emit('update:modelValue', newList);
}

function openRetentionDialog() {
    if (isReadOnly.value) return;
    retentionDialogForm.value = createRetentionForm();
    retentionDialogOpen.value = true;
}

function openRetentionDialogFromDropdown() {
    openRetentionDialog();
    // Forzar el cierre/redibujo del select
    retentionSelectKey.value += 1;
}

async function handleRetentionSubmit(formData: RetentionForm) {
    if (isReadOnly.value) return;
    retentionDialogLoading.value = true;
    try {
        await createRetentionRequest(mapRetentionFormToPayload(formData));
        await fetchRetentions();
        retentionDialogOpen.value = false;
        successMessage.value = 'Retención guardada correctamente.';
    } catch (e) {
        console.error('Error al crear retención', e);
    } finally {
        retentionDialogLoading.value = false;
    }
}
</script>

<template>
    <div class="relative">
        <div
            v-if="successMessage"
            class="pointer-events-none absolute left-1/2 top-2 z-10 w-full max-w-xl -translate-x-1/2"
        >
            <AlertBox
                type="success"
                :message="successMessage"
                class="pointer-events-auto shadow-lg"
                @close="successMessage = ''"
            />
        </div>
        <div class="mb-2 flex items-center justify-between">
            <span class="font-semibold text-xs">Retenciones</span>
            <div class="flex items-center gap-2">
                <button
                    v-if="!isReadOnly"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="addWithholding"
                >
                    <CirclePlus class="h-4 w-4" />
                    Añadir Retención
                </button>
            </div>
        </div>
        <div class="min-w-full text-xs rounded" v-if="localWithholdings.length">
            <table class="min-w-full text-xs rounded">
                <thead>
                    <tr>
                        <th class="p-1 w-32">Nombre</th>
                        <th class="p-1 w-24">Base</th>
                        <th class="p-1 w-24">Valor</th>
                        <th class="p-1 w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(withholding, idx) in localWithholdings" :key="idx">
                        <td class="px-1">
                            <SelectField
                                v-model="withholding.name"
                                :options="retentionOptions"
                                label=""
                                placeholder="Retención"
                                size="xs"
                                class="text-xs flex-1"
                                :key="`${retentionSelectKey}-${idx}`"
                                :disabled="isReadOnly"
                                :showPlaceholderOption="false"
                                :action-label="!isReadOnly ? 'Nueva retención' : undefined"
                                :action-disabled="isReadOnly"
                                @action="openRetentionDialogFromDropdown"
                                @update:modelValue="(val) => {
                                    const found = retentions.find(r => r.name === val);
                                    if (found) withholding.percentage = found.percent;
                                    emit('update:modelValue', localWithholdings);
                                }"
                            />
                        </td>
                        <td class="px-1">
                            <InputField
                                :modelValue="getBaseAmountInputValue(withholding)"
                                type="currency"
                                label=""
                                size="xs"
                                class="text-xs"
                                :placeholder="withholding.base_amount == null ? 'Subtotal' : undefined"
                                :readonly="isReadOnly"
                                :disabled="isReadOnly"
                                @update:modelValue="(val) => updateBaseAmount(withholding, val)"
                            />
                        </td>
                        <td class="px-1">
                            <InputField
                                :modelValue="getWithholdingValue(withholding)"
                                type="currency"
                                label=""
                                size="xs"
                                class="text-xs"
                                readonly
                                tabindex="-1"
                            />
                        </td>
                        <td class="px-1">
                            <DeleteButton
                                v-if="!isReadOnly"
                                type="button"
                                @click="removeWithholding(idx)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="text-xs text-gray-500 text-center py-4">
            No hay retenciones.
        </div>

        <RetentionDialog
            :open="retentionDialogOpen"
            :dialogType="retentionDialogType"
            :form="retentionDialogForm"
            :loading="retentionDialogLoading"
            @update:open="(val: boolean) => (retentionDialogOpen = val)"
            @submit="handleRetentionSubmit"
            @cancel="retentionDialogOpen = false"
        />
    </div>
</template>
