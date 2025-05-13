<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import Button from '@/components/ui/button/Button.vue';
import { PlusCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    branches: { name: string; address: string; phone: string }[];
}>();

const emit = defineEmits<{
    (e: 'update:branches', updatedBranches: { name: string; address: string; phone: string }[]): void;
}>();

// Estado interno para las sucursales
const internalBranches = ref([...props.branches]);

// Sincronizar cambios con el padre
watch(
    internalBranches,
    (newBranches) => {
        emit('update:branches', newBranches);
    },
    { deep: true },
);

// Agregar una nueva sucursal
const addBranch = () => {
    internalBranches.value.push({ name: '', address: '', phone: '' });
};

// Eliminar una sucursal
const removeBranch = (index: number) => {
    internalBranches.value.splice(index, 1);
};
</script>

<template>
    <div class="mt-2">
        <div class="flex justify-end">
            <Button @click="addBranch" variant="default" size="sm"><PlusCircle class="h-4 w-4" />Agregar sucursal</Button>
        </div>
        <div v-for="(branch, index) in internalBranches" :key="index" class="p-2">
            <div class="flex items-center gap-4">
                <InputField v-model="branch.name" label="Nombre de la sucursal" placeholder="Nombre" required />
                <InputField v-model="branch.address" label="Dirección" placeholder="Dirección" required />
                <InputField v-model="branch.phone" label="Teléfono" placeholder="Teléfono" required />
                <div class="text-right">
                    <DeleteButton @click="removeBranch(index)">Eliminar contacto</DeleteButton>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Agregar estilos específicos si es necesario */
</style>
