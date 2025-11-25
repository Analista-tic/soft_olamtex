<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue';
import axios from 'axios';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { CirclePlus } from 'lucide-vue-next';
import type { PurchaseInvoiceWithholding } from '@/types';
import DeleteButton from '@/components/common/DeleteButton.vue';
import RetentionDialog from '@/pages/Configuration/Retention/RetentionDialog.vue';
import AlertBox from '@/components/AlertBox.vue';

defineOptions({ name: 'WithholdingTable' });

const props = defineProps<{
    modelValue: PurchaseInvoiceWithholding[];
    isReadOnly?: boolean;
}>();
const emit = defineEmits(['update:modelValue']);

const localWithholdings = ref<PurchaseInvoiceWithholding[]>(
    Array.isArray(props.modelValue) ? props.modelValue.map((w) => ({ ...w })) : []
);

const retentions = ref<{ id: number; name: string; percentage: number }[]>([]);
const isReadOnly = computed(() => !!props.isReadOnly);
const retentionOptions = computed(() =>
    retentions.value.map((r) => ({ id: r.name, name: `${r.name} (${r.percentage}%)` })),
);

const retentionDialogOpen = ref(false);
const retentionDialogLoading = ref(false);
const retentionDialogType = ref<'create' | 'edit'>('create');
const retentionDialogForm = ref<any>({
    name: '',
    percent: '',
    type: '',
    description: '',
    status: 'active',
});
const successMessage = ref('');

async function fetchRetentions() {
    try {
        const { data } = await axios.get('/retenciones/data');
        const retentionsArray = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : [];
        retentions.value = retentionsArray.map((r: any) => ({
            id: r.id,
            name: r.name,
            percentage: Number(r.percent ?? r.percentage ?? 0),
        }));
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

function addWithholding() {
    if (isReadOnly.value) return;
    emit('update:modelValue', [...localWithholdings.value, { name: '', percentage: 0, base_amount: 0 }]);
}

function removeWithholding(idx: number) {
    if (isReadOnly.value) return;
    if (localWithholdings.value.length <= 1) return;
    const newList = localWithholdings.value.slice();
    newList.splice(idx, 1);
    emit('update:modelValue', newList);
}

function openRetentionDialog() {
    if (isReadOnly.value) return;
    retentionDialogForm.value = {
        name: '',
        percent: '',
        type: '',
        description: '',
        status: 'active',
    };
    retentionDialogOpen.value = true;
}

async function handleRetentionSubmit(formData: any) {
    if (isReadOnly.value) return;
    retentionDialogLoading.value = true;
    try {
        await axios.post('/retenciones', {
            name: formData.name,
            percent: Number(formData.percent) || 0,
            type: formData.type || null,
            description: formData.description || null,
            status: formData.status || 'active',
        });
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
                <Button
                    v-if="!isReadOnly"
                    type="button"
                    variant="default"
                    size="sm"
                    @click="addWithholding"
                    class="text-xs"
                >
                    <CirclePlus /> Añadir Retención
                </Button>
                <Button
                    v-if="!isReadOnly"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="text-xs"
                    @click="openRetentionDialog"
                >
                    Nueva retención
                </Button>
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
                                :disabled="isReadOnly"
                                @update:modelValue="(val) => {
                                    const found = retentions.find(r => r.name === val);
                                    if (found) withholding.percentage = found.percentage;
                                    emit('update:modelValue', localWithholdings);
                                }"
                            />
                        </td>
                        <td class="px-1">
                            <InputField
                                v-model="withholding.base_amount"
                                type="currency"
                                label=""
                                size="xs"
                                class="text-xs"
                                :readonly="isReadOnly"
                                :disabled="isReadOnly"
                                @update:modelValue="() => emit('update:modelValue', localWithholdings)"
                            />
                        </td>
                        <td class="px-1">
                            <InputField
                                :modelValue="((Number(withholding.base_amount) || 0) * (Number(withholding.percentage) || 0) / 100)"
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
                                v-if="!isReadOnly && localWithholdings.length > 1"
                                type="button"
                                size="sm"
                                variant="destructive"
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

