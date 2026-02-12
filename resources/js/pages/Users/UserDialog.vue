<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import GroupSelect from '@/components/GroupSelect.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue'; // Importar el nuevo componente SelectField
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { Role, User } from '@/domain/auth'; // Importar los tipos centralizados
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit' | 'view';
    userData: Partial<User>;
    roles: Role[]; // Recibir roles desde el backend
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

type AreaOption = { id: number; name: string };
const areas = ref<AreaOption[]>([]);
const getRoleKey = (role: Partial<Role>) => {
    if (role.id !== undefined && role.id !== null) return role.id;
    const areaId = (role as { area_id?: number | string | null }).area_id;
    if (areaId !== undefined && areaId !== null && role.name) {
        return `${areaId}-${role.name}`;
    }
    return role.name ?? '';
};

function normalizeAreas(payload: unknown): AreaOption[] {
    if (typeof payload === 'string') {
        try {
            return normalizeAreas(JSON.parse(payload));
        } catch {
            return [];
        }
    }

    if (!Array.isArray(payload)) {
        if (payload && typeof payload === 'object') {
            const record = payload as Record<string, unknown>;
            if ('data' in record) return normalizeAreas(record.data);
            if ('valor' in record) return normalizeAreas(record.valor);
        }
        return [];
    }

    return payload
        .map((area) => {
            if (area && typeof area === 'object') {
                const record = area as Record<string, unknown>;
                const name = String(record.name ?? record.nombre ?? record.label ?? '').trim();
                if (!name) return null;
                const rawId = record.id;
                const parsedId = typeof rawId === 'number' ? rawId : Number(rawId);
                if (!Number.isInteger(parsedId)) return null;
                return { id: parsedId, name };
            }
            return null;
        })
        .filter((area): area is AreaOption => Boolean(area));
}

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

async function fetchAreas() {
    try {
        const { data } = await axios.get('/api/parameters/areas');
        areas.value = normalizeAreas(data);
    } catch (error) {
        console.error('Error al cargar áreas:', error);
        areas.value = [];
    }
}

onMounted(fetchAreas);

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
        <FormDialogContent class="flex max-h-[95vh] max-w-[85vh] flex-col px-2 py-2">
            <TooltipProvider>
                <FormHeader
                    class="flex-shrink-0 pb-2"
                    :title="dialogType === 'create' ? 'Crear Usuario' : dialogType === 'edit' ? 'Editar Usuario' : 'Ver Usuario'"
                    :description="
                        dialogType === 'create'
                            ? 'Completa los campos para crear un nuevo usuario.'
                            : dialogType === 'edit'
                              ? 'Completa los campos para editar el usuario.'
                              : 'Consulta la información del usuario.'
                    "
                />
                <div class="flex-1 overflow-y-auto scroll-smooth pr-2" style="scrollbar-width: thin">
                    <div class="space-y-4">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Nombre<span class="text-red-500">*</span> </Label>
                                <HelpTooltip text="Nombre completo del usuario que tendrá acceso al sistema." aria-label="Ayuda sobre nombre" />
                            </div>
                            <InputField v-model="form.name" label="" placeholder="Nombre del usuario" required :readonly="isView" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Email<span class="text-red-500">*</span> </Label>
                                <HelpTooltip
                                    text="Correo electrónico que se usará como nombre de usuario para iniciar sesión."
                                    aria-label="Ayuda sobre email"
                                />
                            </div>
                            <InputField v-model="form.email" label="" placeholder="Correo electrónico" required :readonly="isView" />
                        </div>
                        <!-- Campo para contraseña en modo "crear" -->
                        <div v-if="dialogType === 'create'" class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Contraseña<span class="text-red-500">*</span> </Label>
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
                                <Label class="text-sm font-medium"> Confirmar Contraseña<span class="text-red-500">*</span> </Label>
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
                                <Label class="text-sm font-medium"> Área<span class="text-red-500">*</span> </Label>
                                <HelpTooltip
                                    text="Área organizacional a la que pertenece el usuario. Solo organiza, no autoriza acciones."
                                    aria-label="Ayuda sobre área"
                                />
                            </div>
                            <SelectField
                                label=""
                                :options="areas.map((area) => ({ id: area.id, name: area.name }))"
                                v-model="form.area_id"
                                required
                                :disabled="isView"
                            />
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
                        <p v-if="errorMessage" class="text-sm text-red-500">{{ errorMessage }}</p>
                    </div>
                </div>
            </TooltipProvider>
            <DialogFooter class="mt-4 flex flex-shrink-0 flex-col gap-2 border-t pt-4 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="closeDialog"
                    >
                        {{ isView ? 'Cerrar' : 'Cancelar' }}
                    </button>
                </div>
                <div v-if="!isView" class="w-full md:w-auto">
                    <Button
                        type="button"
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
