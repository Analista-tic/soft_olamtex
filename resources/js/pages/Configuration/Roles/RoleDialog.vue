<script setup lang="ts">
import InputField from '@/components/InputField.vue';
// ...existing code...
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { ref, watch } from 'vue';

const emit = defineEmits(['submit', 'close']);

const props = defineProps({
    isOpen: Boolean,
    role: { type: Object as () => Record<string, any> | null, default: null },
    permissions: { type: Array as () => Array<{ name: string; module: string }>, default: () => [] },
});

const permissionsByModule: Record<string, string[]> = {
    Usuarios: ['ver usuarios', 'crear usuarios', 'editar usuarios', 'eliminar usuarios'],
    Telas: ['ver telas', 'crear telas', 'editar telas', 'eliminar telas'],
    Programador: ['ver programador', 'crear programador', 'editar programador', 'eliminar programador'],
    'Sábana Productos': ['ver sabana productos', 'crear sabana productos', 'editar sabana productos', 'eliminar sabana productos'],
};

const form = ref<{
    id: number | null;
    name: string;
    permissions: string[];
}>({
    id: null,
    name: '',
    permissions: [],
});

// ...existing code...

// ...existing code...

const localIsOpen = ref(props.isOpen);
const globalSelectAll = ref(false);

watch(
    () => props.isOpen,
    (newValue) => {
        localIsOpen.value = newValue;
    },
    { immediate: true },
);

watch(
    () => localIsOpen.value,
    (newValue) => {
        if (!newValue) emit('close');
    },
);

watch(
    () => props.role,
    (newRole) => {
        if (newRole) {
            form.value = {
                id: newRole.id,
                name: newRole.name,
                permissions: [...(newRole.permissions || []).map((p: { name: string }) => p.name)],
            };
            // ...existing code...
            setTimeout(() => {
                for (const module in permissionsByModule) {
                    const modulePermissions = permissionsByModule[module];
                    const allSelected = modulePermissions.every((permission) => form.value.permissions.includes(permission));
                    const checkbox = document.getElementById(`select-all-${module}`) as HTMLInputElement;
                    if (checkbox) {
                        checkbox.checked = allSelected;
                        if (checkbox.labels && checkbox.labels[0]) {
                            checkbox.labels[0].textContent = allSelected ? 'Deseleccionar todos' : 'Seleccionar todos';
                        }
                    }
                }
            }, 0);
        } else {
            form.value = { id: null, name: '', permissions: [] };
        }
    },
    { immediate: true },
);

// ...eliminado watch de área...

watch(
    () => form.value.permissions,
    (newPermissions) => {
        for (const module in permissionsByModule) {
            const modulePermissions = permissionsByModule[module];
            const allSelected = modulePermissions.every((permission) => newPermissions.includes(permission));
            const checkbox = document.getElementById(`select-all-${module}`) as HTMLInputElement;
            if (checkbox) {
                checkbox.checked = allSelected;
                if (checkbox.labels && checkbox.labels[0]) {
                    checkbox.labels[0].textContent = allSelected ? 'Deseleccionar todos' : 'Seleccionar todos';
                }
            }
        }
    },
    { immediate: true },
);

watch(
    () => globalSelectAll.value,
    (isChecked) => {
        if (isChecked) {
            form.value.permissions = Object.values(permissionsByModule).flat();
        } else {
            form.value.permissions = [];
        }

        // Actualizar el estado de los checkboxes de cada módulo
        for (const module in permissionsByModule) {
            const checkbox = document.getElementById(`select-all-${module}`) as HTMLInputElement;
            if (checkbox) {
                checkbox.checked = isChecked;
                if (checkbox.labels && checkbox.labels[0]) {
                    checkbox.labels[0].textContent = isChecked ? 'Deseleccionar todos' : 'Seleccionar todos';
                }
            }
        }
    },
);

function submit() {
    const roleData: Record<string, unknown> = {
        id: form.value.id,
        name: form.value.name,
        permissions: form.value.permissions,
    };
    emit('submit', roleData);
}

function closeDialog() {
    emit('close');
}

function toggleModulePermissions(module: string, isChecked: boolean): void {
    const permissions = permissionsByModule[module] || [];

    if (isChecked) {
        form.value.permissions = Array.from(new Set([...form.value.permissions, ...permissions]));
    } else {
        form.value.permissions = form.value.permissions.filter((permission) => !permissions.includes(permission));
    }

    // Actualizar el estado del checkbox general
    const checkbox = document.getElementById(`select-all-${module}`) as HTMLInputElement;
    if (checkbox) {
        const allSelected = permissions.every((permission) => form.value.permissions.includes(permission));
        checkbox.checked = allSelected;
        if (checkbox.labels && checkbox.labels[0]) {
            checkbox.labels[0].textContent = allSelected ? 'Deseleccionar todos' : 'Seleccionar todos';
        }
    }
}

// Corregí el acceso a la propiedad 'checked' asegurando el tipo correcto.
function handleCheckboxChange(event: Event, module: string): void {
    const target = event.target as HTMLInputElement;
    toggleModulePermissions(module, target.checked);
}
</script>

<template>
    <Dialog v-model:open="localIsOpen">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-2xl px-2 py-2">
            <TooltipProvider>
                <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle>{{ role?.id ? 'Editar Rol' : 'Crear Rol' }}</DialogTitle>
                    <DialogDescription>
                        {{ role?.id ? 'Edita la información del rol.' : 'Completa la información para crear un nuevo rol.' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="flex-1 overflow-y-auto scroll-smooth px-2" style="scrollbar-width: thin">
                    <form @submit.prevent="submit">
                        <!-- Eliminado campo de área -->
                        <div class="mb-4 flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium"> Nombre del Rol<span class="text-red-500">*</span> </Label>
                                <HelpTooltip
                                    text="Nombre descriptivo para el rol que agrupa permisos específicos (ej: Administrador, Vendedor, etc.)."
                                    aria-label="Ayuda sobre nombre del rol"
                                />
                            </div>
                            <InputField label="" v-model="form.name" placeholder="Nombre del rol" required />
                        </div>

                        <div class="mb-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <label class="mb-2 block text-sm font-medium text-foreground">Permisos</label>
                                    <HelpTooltip
                                        text="Selecciona los permisos que tendrá este rol. Los usuarios con este rol podrán realizar solo las acciones permitidas."
                                        aria-label="Ayuda sobre permisos"
                                    />
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="global-select-all" v-model="globalSelectAll" class="mr-1" />
                                    <label for="global-select-all" class="text-xs text-foreground">Seleccionar todos los módulos</label>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-5">
                                <Card v-for="(permissions, module) in permissionsByModule" :key="module" class="rounded-lg shadow-md">
                                    <CardHeader class="p-2">
                                        <CardDescription class="flex items-center justify-between">
                                            <CardTitle class="text-center text-base font-semibold text-muted-foreground">{{ module }}</CardTitle>
                                            <div class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    :id="`select-all-${module}`"
                                                    @change="handleCheckboxChange($event, module)"
                                                    class="mr-1"
                                                />
                                                <label :for="`select-all-${module}`" class="text-xs text-foreground">Seleccionar todos</label>
                                            </div>
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="px-3">
                                        <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                                            <div v-for="permission in permissions" :key="permission" class="flex items-center">
                                                <input type="checkbox" :id="permission" :value="permission" v-model="form.permissions" class="mr-1" />
                                                <label :for="permission" class="text-sm text-muted-foreground">{{ permission }}</label>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <DialogFooter class="mt-4 flex flex-shrink-0 flex-col gap-2 border-t pt-4 md:flex-row md:justify-end">
                            <div class="w-full md:w-auto">
                                <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="closeDialog">
                                    Cancelar
                                </Button>
                            </div>
                            <div class="w-full md:w-auto">
                                <Button
                                    type="submit"
                                    class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                                >
                                    {{ role?.id ? 'Guardar Cambios' : 'Crear Rol' }}
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                </div>
            </TooltipProvider>
        </FormDialogContent>
    </Dialog>
</template>

<style scoped>
/* Eliminé los estilos personalizados */
</style>
