<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import GroupSelect from '@/components/GroupSelect.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue'; // Importar el nuevo componente SelectField
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { Area, Role, User } from '@/domain/Settings'; // Importar los tipos centralizados
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit' | 'view';
    userData: Partial<User>;
    roles: Role[]; // Recibir roles desde el backend
    areas: Area[]; // Catálogo de áreas (tabla areas)
}>();

const emit = defineEmits<{
    (event: 'close'): void;
    (event: 'submit', payload: Partial<User>): void;
    (event: 'update:isOpen', value: boolean): void;
}>();

const internalIsOpen = ref(props.isOpen);

watch(
    () => props.isOpen,
    (newValue) => {
        internalIsOpen.value = newValue;
    },
);

watch(
    () => internalIsOpen.value,
    (newValue) => {
        emit('update:isOpen', newValue);
        if (!newValue) emit('close');
    },
);

const form = ref<Partial<User>>({ ...props.userData });
const confirmPassword = ref('');
const errorMessage = ref('');
const isView = computed(() => props.dialogType === 'view');

// Solo áreas activas; se conserva la actual del usuario aunque esté desactivada para no perder su asignación.
const areaOptions = computed(() =>
    props.areas.filter((area) => area.is_active || area.id === form.value.area_id).map((area) => ({ id: area.id, name: area.name })),
);
const getRoleKey = (role: Partial<Role>) => {
    if (role.id !== undefined && role.id !== null) return role.id;
    const areaId = (role as { area_id?: number | string | null }).area_id;
    if (areaId !== undefined && areaId !== null && role.name) {
        return `${areaId}-${role.name}`;
    }
    return role.name ?? '';
};

const roleOptions = computed(() =>
    props.roles.map((role) => {
        return { id: getRoleKey(role), name: role.name };
    }),
);
const selectedRoles = ref<Array<number | string>>(form.value.roles?.map((role) => getRoleKey(role)).filter(Boolean) || []);

// Propiedades para mostrar/ocultar contraseñas
const showPassword = ref(false);
const showConfirmPassword = ref(false);

watch(
    () => props.userData,
    (newData) => {
        form.value = { ...newData };
        confirmPassword.value = '';
        errorMessage.value = '';
        selectedRoles.value = newData.roles?.map((role) => getRoleKey(role)).filter(Boolean) || [];
    },
    { deep: true },
);

function closeDialog() {
    internalIsOpen.value = false;
}

function submitForm() {
    if (props.dialogType === 'view') {
        closeDialog();
        return;
    }
    const passwordValue = String(form.value.password ?? '');
    if (props.dialogType === 'create' && passwordValue.length < 8) {
        errorMessage.value = 'La contraseña debe tener al menos 8 caracteres.';
        return;
    }
    if (props.dialogType === 'edit' && passwordValue && passwordValue.length < 8) {
        errorMessage.value = 'La contraseña debe tener al menos 8 caracteres.';
        return;
    }
    if (!selectedRoles.value.length) {
        errorMessage.value = 'Selecciona al menos un rol.';
        return;
    }
    if (form.value.password && form.value.password !== confirmPassword.value) {
        errorMessage.value = 'Las contraseñas no coinciden.';
        return;
    }

    const selectedRoleObjects = selectedRoles.value
        .map((selectedId) => props.roles.find((role) => getRoleKey(role) == selectedId))
        .filter((role): role is Role => Boolean(role));

    if (!selectedRoleObjects.length) {
        errorMessage.value = 'Selecciona al menos un rol válido.';
        return;
    }

    // Envía los objetos Role completos
    emit('submit', { ...form.value, roles: selectedRoleObjects });
    errorMessage.value = '';
}
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-2xl flex-col px-2 py-2">
            <TooltipProvider>
                <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle>{{
                        dialogType === 'create' ? 'Crear Usuario' : dialogType === 'edit' ? 'Editar Usuario' : 'Ver Usuario'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            dialogType === 'create'
                                ? 'Completa los campos para crear un nuevo usuario.'
                                : dialogType === 'edit'
                                  ? 'Completa los campos para editar el usuario.'
                                  : 'Consulta la información del usuario.'
                        }}
                    </DialogDescription>
                </DialogHeader>
                <div class="min-h-0 flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                    <div class="space-y-4">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Nombre<span class="text-destructive">*</span> </Label>
                                <HelpTooltip text="Nombre completo del usuario que tendrá acceso al sistema." aria-label="Ayuda sobre nombre" />
                            </div>
                            <InputField v-model="form.name" data-no-uppercase label="" placeholder="Nombre del usuario" required :readonly="isView" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Email<span class="text-destructive">*</span> </Label>
                                <HelpTooltip
                                    text="Correo electrónico que se usará como nombre de usuario para iniciar sesión."
                                    aria-label="Ayuda sobre email"
                                />
                            </div>
                            <InputField v-model="form.email" type="email" label="" placeholder="Correo electrónico" required :readonly="isView" />
                        </div>
                        <!-- Campo para contraseña en modo "crear" -->
                        <div v-if="dialogType === 'create'" class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Contraseña<span class="text-destructive">*</span> </Label>
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
                                :readonly="isView"
                            />
                        </div>
                        <!-- Campo para contraseña en modo "editar" -->
                        <div v-if="dialogType === 'edit'" class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Nueva Contraseña </Label>
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
                                :readonly="isView"
                            />
                        </div>
                        <!-- Campo para confirmar contraseña -->
                        <div v-if="form.password" class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Confirmar Contraseña<span class="text-destructive">*</span> </Label>
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
                                :readonly="isView"
                            />
                        </div>
                        <!-- Select para área organizacional -->
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Área<span class="text-destructive">*</span> </Label>
                                <HelpTooltip
                                    text="Área organizacional a la que pertenece el usuario. Solo organiza, no autoriza acciones."
                                    aria-label="Ayuda sobre área"
                                />
                            </div>
                            <SelectField label="" :options="areaOptions" v-model="form.area_id" required :disabled="isView" />
                        </div>
                        <!-- Select para roles -->
                        <GroupSelect
                            v-model="selectedRoles"
                            label="Roles"
                            :options="roleOptions"
                            placeholder="Selecciona roles"
                            required
                            :searchable="true"
                            :disabled="isView"
                        />
                        <!-- Mensaje de error -->
                        <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
                    </div>
                </div>
            </TooltipProvider>
            <DialogFooter class="flex flex-shrink-0 flex-col gap-2 border-t border-border px-5 py-4 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="closeDialog">
                        {{ isView ? 'Cerrar' : 'Cancelar' }}
                    </Button>
                </div>
                <div v-if="!isView" class="w-full md:w-auto">
                    <Button
                        type="button"
                        size="pill"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="submitForm"
                    >
                        {{ dialogType === 'create' ? 'Crear' : 'Guardar' }}
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>

\n
