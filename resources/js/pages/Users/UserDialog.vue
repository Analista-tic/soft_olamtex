<script setup lang="ts">
import type { Role, User } from '@/domain/auth'; // Importar los tipos centralizados
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue'; // Importar el nuevo componente SelectField
import { ref, watch } from 'vue';
import FormHeader from '@/components/FormHeader.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';

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
        <DialogContent class="max-h-[90vh] flex flex-col bg-card text-card-foreground w-full max-w-4xl">
            <TooltipProvider>
            <FormHeader
                class="flex-shrink-0"
                :title="dialogType === 'create' ? 'Crear Usuario' : 'Editar Usuario'"
                :description="dialogType === 'create' ? 'Completa los campos para crear un nuevo usuario.' : 'Completa los campos para editar el usuario.'"
            />
            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <div class="space-y-4">
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Nombre<span class="text-red-500">*</span>
                        </Label>
                        <HelpTooltip
                            text="Nombre completo del usuario que tendrá acceso al sistema."
                            aria-label="Ayuda sobre nombre"
                        />
                    </div>
                    <InputField v-model="form.name" label="" placeholder="Nombre del usuario" required />
                </div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Email<span class="text-red-500">*</span>
                        </Label>
                        <HelpTooltip
                            text="Correo electrónico que se usará como nombre de usuario para iniciar sesión."
                            aria-label="Ayuda sobre email"
                        />
                    </div>
                    <InputField v-model="form.email" label="" placeholder="Correo electrónico" required />
                </div>
                <!-- Campo para contraseña en modo "crear" -->
                <div v-if="dialogType === 'create'" class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Contraseña<span class="text-red-500">*</span>
                        </Label>
                        <HelpTooltip
                            text="Contraseña para acceder al sistema. Debe ser segura y única."
                            aria-label="Ayuda sobre contraseña"
                        />
                    </div>
                    <InputField
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        label=""
                        placeholder="Contraseña"
                        required
                    />
                </div>
                <!-- Campo para contraseña en modo "editar" -->
                <div v-if="dialogType === 'edit'" class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Nueva Contraseña
                        </Label>
                        <HelpTooltip
                            text="Dejar en blanco si no deseas cambiar la contraseña actual del usuario."
                            aria-label="Ayuda sobre nueva contraseña"
                        />
                    </div>
                    <InputField
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        label=""
                        placeholder="Dejar en blanco para no cambiar"
                    />
                </div>
                <!-- Campo para confirmar contraseña -->
                <div v-if="form.password" class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Confirmar Contraseña<span class="text-red-500">*</span>
                        </Label>
                        <HelpTooltip
                            text="Confirma la contraseña ingresada para evitar errores."
                            aria-label="Ayuda sobre confirmar contraseña"
                        />
                    </div>
                    <InputField
                        v-model="confirmPassword"
                        :type="showConfirmPassword ? 'text' : 'password'"
                        label=""
                        placeholder="Repite la contraseña"
                        required
                    />
                </div>
                <!-- Select para roles usando SelectField -->
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Roles<span class="text-red-500">*</span>
                        </Label>
                        <HelpTooltip
                            text="Asigna uno o más roles al usuario para definir sus permisos en el sistema."
                            aria-label="Ayuda sobre roles"
                        />
                    </div>
                    <SelectField
                        label=""
                        :options="props.roles.map(role => ({ id: role.name, name: role.name }))"
                        v-model="selectedRoles"
                        multiple
                        required
                    />
                </div>
                <!-- Mensaje de error -->
                <p v-if="errorMessage" class="text-red-500 text-sm">{{ errorMessage }}</p>
            </div>
            </div>
            </TooltipProvider>
            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="closeDialog"
                    >
                        Cancelar
                    </button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="submitForm"
                    >
                        {{ dialogType === 'create' ? 'Crear' : 'Guardar' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
