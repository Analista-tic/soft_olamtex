<script setup lang="ts">
import { Role, User } from '@/types'; // Importar los tipos centralizados
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue'; // Importar el nuevo componente SelectField
import { ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    userData: Partial<User>;
    roles: Role[]; // Recibir roles desde el backend
}>();

const emit = defineEmits(['close', 'submit']);

const internalIsOpen = ref(props.isOpen);

watch(
    () => props.isOpen,
    (newValue) => {
        internalIsOpen.value = newValue;
    }
);

watch(
    () => internalIsOpen.value,
    (newValue) => {
        if (!newValue) {
            emit('close');
        }
    }
);

const form = ref<Partial<User>>({ ...props.userData });
const confirmPassword = ref('');
const errorMessage = ref('');
const selectedRoles = ref<string[]>(form.value.roles?.map(role => role.name) || []); // Manejar múltiples roles como nombres

// Propiedades para mostrar/ocultar contraseñas
const showPassword = ref(false);
const showConfirmPassword = ref(false);

watch(
    () => props.userData,
    (newData) => {
        form.value = { ...newData };
        confirmPassword.value = '';
        selectedRoles.value = newData.roles?.map(role => role.name) || []; // Sincronizar roles seleccionados
    },
    { deep: true }
);

function closeDialog() {
    internalIsOpen.value = false;
}

function submitForm() {
    if (form.value.password && form.value.password !== confirmPassword.value) {
        errorMessage.value = 'Las contraseñas no coinciden.';
        return;
    }

    form.value.roles = selectedRoles.value.map(name => ({ id: 0, name, permissions: [] })); // Convertir nombres a objetos con estructura esperada

    emit('submit', { ...form.value, roles: selectedRoles.value.map(name => ({ id: 0, name, permissions: [] })) }); // Emitir los datos correctamente
    errorMessage.value = '';
}
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ dialogType === 'create' ? 'Crear Usuario' : 'Editar Usuario' }}</DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ dialogType === 'create' ? 'crear un nuevo usuario' : 'editar el usuario' }}.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4">
                <InputField v-model="form.name" label="Nombre" placeholder="Nombre del usuario" required />
                <InputField v-model="form.email" label="Email" placeholder="Correo electrónico" required />
                <!-- Campo para contraseña en modo "crear" -->
                <div class="relative">
                    <InputField
                        v-if="dialogType === 'create'"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        label="Contraseña"
                        placeholder="Contraseña"
                        required
                    />
                </div>
                <!-- Campo para contraseña en modo "editar" -->
                <div class="relative">
                    <InputField
                        v-if="dialogType === 'edit'"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        label="Nueva Contraseña (opcional)"
                        placeholder="Dejar en blanco para no cambiar"
                    />
                </div>
                <!-- Campo para confirmar contraseña -->
                <div class="relative">
                    <InputField
                        v-if="form.password"
                        v-model="confirmPassword"
                        :type="showConfirmPassword ? 'text' : 'password'"
                        label="Confirmar Contraseña"
                        placeholder="Repite la contraseña"
                        required
                    />
                </div>
                <!-- Select para roles usando SelectField -->
                <SelectField
                    label="Roles"
                    :options="props.roles.map(role => ({ id: role.name, name: role.name }))"
                    v-model="selectedRoles"
                    multiple
                    required
                />
                <!-- Mensaje de error -->
                <p v-if="errorMessage" class="text-red-500 text-sm">{{ errorMessage }}</p>
            </div>
            <DialogFooter class="mt-4">
                <Button @click="submitForm">{{ dialogType === 'create' ? 'Crear' : 'Guardar' }}</Button>
                <Button variant="outline" @click="closeDialog">Cancelar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
