<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import Button from '@/components/ui/button/Button.vue';
import AlertBox from '@/components/AlertBox.vue';
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

// Mensaje de alerta
const alertMessage = ref<string>('');

// Validar que todos los campos de la sucursal estén llenos
const validateBranch = (branch: { name: string; address: string; phone: string }) => {
    const errors = {
        name: !branch.name.trim() ? 'El nombre es obligatorio.' : '',
        address: !branch.address.trim() ? 'La dirección es obligatoria.' : '',
        phone: !branch.phone.trim() ? 'El teléfono es obligatorio.' : '',
    };
    return errors;
};

// Errores por sucursal
const branchErrors = ref(internalBranches.value.map((branch) => validateBranch(branch)));

// Detectar sucursales duplicadas
const hasDuplicateBranch = (
    branches: { name: string; address: string; phone: string }[],
): boolean => {
    const seen = new Set<string>();
    for (const b of branches) {
        const key = `${b.name.trim().toLowerCase()}|${b.address.trim().toLowerCase()}|${b.phone.trim()}`;
        if (seen.has(key)) return true;
        seen.add(key);
    }
    return false;
};

// Sincronizar cambios con el padre
watch(
    internalBranches,
    (newBranches) => {
        branchErrors.value = newBranches.map((branch) => validateBranch(branch));

        if (hasDuplicateBranch(newBranches)) {
            alertMessage.value = 'Ya existe una sucursal con el mismo nombre, dirección y teléfono.';
        } else if (
            alertMessage.value === 'Ya existe una sucursal con el mismo nombre, dirección y teléfono.'
        ) {
            alertMessage.value = '';
        }

        emit('update:branches', newBranches);
    },
    { deep: true },
);

// Validar que todos los campos estén llenos
const areBranchesValid = () => {
    return internalBranches.value.every(
        (branch) =>
            branch.name.trim() &&
            branch.address.trim() &&
            branch.phone.trim(),
    );
};

// Agregar una nueva sucursal
const addBranch = () => {
    // Primero: todos los campos actuales llenos
    if (!areBranchesValid()) {
        alertMessage.value = 'Por favor, complete todos los campos antes de agregar una nueva sucursal.';
        branchErrors.value = internalBranches.value.map((branch) => validateBranch(branch));
        return;
    }

    // Segundo: que no haya duplicados
    if (hasDuplicateBranch(internalBranches.value)) {
        alertMessage.value = 'Ya existe una sucursal con el mismo nombre, dirección y teléfono.';
        return;
    }

    internalBranches.value.push({ name: '', address: '', phone: '' });
    branchErrors.value.push(validateBranch({ name: '', address: '', phone: '' }));
    alertMessage.value = ''; // limpiamos alerta si había
};

// Eliminar una sucursal
const removeBranch = (index: number) => {
    internalBranches.value.splice(index, 1);
    branchErrors.value.splice(index, 1);
};

const showDuplicateAlertFn = () => {
    alertMessage.value = 'Ya existe una sucursal con el mismo nombre, dirección y teléfono.';
};

defineExpose({
    showDuplicateAlert: showDuplicateAlertFn,
});
</script>

<template>
    <div class="mt-2 relative">
        <transition name="fade">
            <div
                v-if="alertMessage"
                class="absolute -top-26 left-1/2 z-20 w-full max-w-xl -translate-x-1/2"
            >
                <AlertBox
                    type="error"
                    title="Error"
                    :message="alertMessage"
                    @close="alertMessage = ''"
                />
            </div>
        </transition>

        <div class="flex justify-end mb-2">
            <Button @click="addBranch" variant="default" size="sm">
                <PlusCircle class="h-4 w-4" />Agregar sucursal
            </Button>
        </div>

        <div v-for="(branch, index) in internalBranches" :key="index" class="p-2">
            <div class="flex items-center gap-4">
                <InputField
                    v-model="branch.name"
                    label="Nombre de la sucursal"
                    placeholder="Nombre"
                    required
                    :error="branchErrors[index]?.name"
                />
                <InputField
                    v-model="branch.address"
                    label="Dirección"
                    placeholder="Dirección"
                    required
                    :error="branchErrors[index]?.address"
                />
                <InputField
                    v-model="branch.phone"
                    label="Teléfono"
                    placeholder="Teléfono"
                    required
                    :error="branchErrors[index]?.phone"
                />
                <div class="text-right">
                    <DeleteButton @click="removeBranch(index)">Eliminar sucursal</DeleteButton>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Agregar estilos específicos si es necesario */
</style>
