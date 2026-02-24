<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
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
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('error');

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
const hasDuplicateBranch = (branches: { name: string; address: string; phone: string }[]): boolean => {
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
            alertType.value = 'error';
            alertMessage.value = 'Ya existe una sucursal con el mismo nombre, dirección y teléfono.';
        } else if (alertMessage.value === 'Ya existe una sucursal con el mismo nombre, dirección y teléfono.') {
            alertMessage.value = '';
        }

        emit('update:branches', newBranches);
    },
    { deep: true },
);

// Validar que todos los campos estén llenos
const areBranchesValid = () => {
    return internalBranches.value.every((branch) => branch.name.trim() && branch.address.trim() && branch.phone.trim());
};

// Agregar una nueva sucursal
const addBranch = () => {
    // Primero: todos los campos actuales llenos
    if (!areBranchesValid()) {
        alertType.value = 'warning';
        alertMessage.value = 'Por favor, complete todos los campos antes de agregar una nueva sucursal.';
        branchErrors.value = internalBranches.value.map((branch) => validateBranch(branch));
        return;
    }

    // Segundo: que no haya duplicados
    if (hasDuplicateBranch(internalBranches.value)) {
        alertType.value = 'error';
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
    alertType.value = 'error';
    alertMessage.value = 'Ya existe una sucursal con el mismo nombre, dirección y teléfono.';
};

defineExpose({
    showDuplicateAlert: showDuplicateAlertFn,
});
</script>

<template>
    <div class="relative mt-2">
        <transition name="fade">
            <div v-if="alertMessage" class="-top-26 absolute left-1/2 z-20 w-full max-w-xl -translate-x-1/2">
                <AlertBox
                    :type="alertType"
                    :title="alertType === 'warning' ? 'Advertencia' : 'Error'"
                    :message="alertMessage"
                    @close="alertMessage = ''"
                />
            </div>
        </transition>

        <div class="mb-2 flex justify-end">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                @click="addBranch"
            >
                <PlusCircle class="h-4 w-4" />
                Agregar sucursal
            </button>
        </div>

        <div v-for="(branch, index) in internalBranches" :key="index" class="p-2">
            <div class="flex items-center gap-4">
                <InputField v-model="branch.name" label="Nombre de la sucursal" placeholder="Nombre" required :error="branchErrors[index]?.name" />
                <InputField v-model="branch.address" label="Dirección" placeholder="Dirección" required :error="branchErrors[index]?.address" />
                <InputField v-model="branch.phone" label="Teléfono" placeholder="Teléfono" required :error="branchErrors[index]?.phone" />
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
