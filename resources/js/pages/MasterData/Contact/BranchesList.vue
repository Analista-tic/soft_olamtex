<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
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

const emptyBranch = () => ({ name: '', address: '', phone: '' });

const isBlankBranch = (branch: { name: string; address: string; phone: string }) =>
    !branch.name.trim() && !branch.address.trim() && !branch.phone.trim();

// Estado interno para las sucursales (siempre al menos una fila para indicar que se puede llenar)
const internalBranches = ref(props.branches.length ? [...props.branches] : [emptyBranch()]);

// Mensaje de alerta
const alertMessage = ref<string>('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('error');

// Validar que todos los campos de la sucursal estén llenos (una fila vacía no marca errores salvo que se fuerce)
const validateBranch = (branch: { name: string; address: string; phone: string }, force = false) => {
    if (!force && isBlankBranch(branch)) {
        return { name: '', address: '', phone: '' };
    }

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

        emit(
            'update:branches',
            newBranches.filter((branch) => !isBlankBranch(branch)),
        );
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
        branchErrors.value = internalBranches.value.map((branch) => validateBranch(branch, true));
        return;
    }

    // Segundo: que no haya duplicados
    if (hasDuplicateBranch(internalBranches.value)) {
        alertType.value = 'error';
        alertMessage.value = 'Ya existe una sucursal con el mismo nombre, dirección y teléfono.';
        return;
    }

    internalBranches.value.push(emptyBranch());
    branchErrors.value.push(validateBranch(emptyBranch()));
    alertMessage.value = ''; // limpiamos alerta si había
};

// Eliminar una sucursal (si queda vacía la lista, se deja una fila en blanco)
const removeBranch = (index: number) => {
    internalBranches.value.splice(index, 1);
    branchErrors.value.splice(index, 1);

    if (internalBranches.value.length === 0) {
        internalBranches.value.push(emptyBranch());
        branchErrors.value.push(validateBranch(emptyBranch()));
    }
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
        <AlertStack
            :error-message="alertMessage"
            :error-type="alertType"
            @close-error="alertMessage = ''"
        />

        <div class="mb-2 flex justify-end">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                @click="addBranch"
            >
                <PlusCircle class="h-4 w-4" />
                Agregar sucursal
            </button>
        </div>

        <div v-for="(branch, index) in internalBranches" :key="index" class="p-2">
            <div class="flex items-center gap-4">
                <InputField v-model="branch.name" data-no-uppercase label="Nombre de la sucursal" placeholder="Nombre" required :error="branchErrors[index]?.name" />
                <InputField v-model="branch.address" data-no-uppercase label="Dirección" placeholder="Dirección" required :error="branchErrors[index]?.address" />
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
