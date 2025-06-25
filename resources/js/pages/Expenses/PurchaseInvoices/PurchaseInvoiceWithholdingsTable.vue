<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';

// Cambia el nombre del componente y asegúrate de que los props y emits sean genéricos y reutilizables
defineOptions({ name: 'WithholdingTable' });

const props = defineProps<{
    modelValue: {
        name: string;
        percentage: number;
        base_amount: number;
    }[];
}>();
const emit = defineEmits(['update:modelValue']);

const localWithholdings = ref(
    Array.isArray(props.modelValue)
        ? props.modelValue.map(w => ({ ...w }))
        : []
);

const retentions = ref<{ id: number; name: string; percentage: number }[]>([]);

async function fetchRetentions() {
    try {
        const { data } = await axios.get('/retenciones/data');
        let retentionsArray = [];
        if (Array.isArray(data?.data)) {
            retentionsArray = data.data;
        } else if (Array.isArray(data)) {
            retentionsArray = data;
        } else {
            retentionsArray = [];
        }
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

// Elimino el watcher de localWithholdings

function addWithholding() {
    const newList = [...localWithholdings.value, { name: '', percentage: 0, base_amount: 0 }];
    emit('update:modelValue', newList);
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
            <span class="font-semibold text-sm">Retenciones</span>
            <Button type="button" variant="outline" size="sm" @click="addWithholding">+</Button>
        </div>
        <table class="min-w-full text-xs border rounded">
            <thead>
                <tr>
                    <th class="p-1">Nombre</th>
                    <th class="p-1 w-20">%</th>
                    <th class="p-1 w-24">Base</th>
                    <th class="p-1 w-10"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(withholding, idx) in localWithholdings" :key="idx">
                    <td class="p-1">
                        <SelectField
                            v-model="withholding.name"
                            :options="retentions.map(r => ({ id: r.name, name: r.name }))"
                            label=""
                            placeholder="Retención"
                            size="sm"
                            @update:modelValue="(val) => {
                                const found = retentions.find(r => r.name === val);
                                if (found) withholding.percentage = found.percentage;
                            }"
                        />
                    </td>
                    <td class="p-1">
                        <InputField v-model="withholding.percentage" type="number" label="" min="0" step="0.01" readonly size="sm" />
                    </td>
                    <td class="p-1">
                        <InputField v-model="withholding.base_amount" type="number" label="" min="0" step="0.01" size="sm" />
                    </td>
                    <td class="p-1">
                        <Button type="button" size="sm" variant="destructive" @click="removeWithholding(idx)">×</Button>
                    </td>
                </tr>
                <tr v-if="localWithholdings.length === 0">
                    <td colspan="4" class="text-center text-gray-400">No hay retenciones.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>