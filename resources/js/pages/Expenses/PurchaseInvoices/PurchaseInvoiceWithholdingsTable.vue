<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { CirclePlus } from 'lucide-vue-next';
import type { PurchaseInvoiceWithholding } from '@/types'; // Importa el tipo correcto
import DeleteButton from '@/components/common/DeleteButton.vue';

defineOptions({ name: 'WithholdingTable' });

const props = defineProps<{
    modelValue: PurchaseInvoiceWithholding[];
}>();
const emit = defineEmits(['update:modelValue']);

const localWithholdings = ref<PurchaseInvoiceWithholding[]>(
    Array.isArray(props.modelValue)
        ? props.modelValue.map(w => ({ ...w }))
        : []
);

const retentions = ref<{ id: number; name: string; percentage: number }[]>([]);

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
        localWithholdings.value = Array.isArray(val)
            ? val.map(w => ({ ...w }))
            : [];
    },
    { immediate: true }
);

function addWithholding() {
    emit('update:modelValue', [...localWithholdings.value, { name: '', percentage: 0, base_amount: 0 }]);
}
function removeWithholding(idx: number) {
    const newList = localWithholdings.value.slice();
    newList.splice(idx, 1);
    emit('update:modelValue', newList);
}
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between">
            <span class="font-semibold text-xs">Retenciones</span>
            <Button type="button" variant="default" size="sm" @click="addWithholding" class="text-xs">
                <CirclePlus /> Añadir Retención
            </Button>
        </div>
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
                            :options="retentions.map(r => ({ id: r.name, name: `${r.name} (${r.percentage}%)` }))"
                            label=""
                            placeholder="Retención"
                            size="xs"
                            class="text-xs"
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
                        <DeleteButton type="button" size="sm" variant="destructive" @click="removeWithholding(idx)"></DeleteButton>
                    </td>
                </tr>
                <tr v-if="localWithholdings.length === 0">
                    <td colspan="5" class="text-center text-gray-400">No hay retenciones.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>