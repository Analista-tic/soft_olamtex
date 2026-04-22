<script setup lang="ts">
import InputField from '@/components/InputField.vue';
// ...existing code...
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { PERMISSION_CATEGORIES, PERMISSION_MODULES, PERMISSIONS_BY_MODULE } from '@/lib/permissionModules';
import { computed, ref, watch } from 'vue';

const emit = defineEmits(['submit', 'close']);

const props = defineProps({
    isOpen: Boolean,
    role: { type: Object as () => Record<string, any> | null, default: null },
    permissions: { type: Array as () => Array<{ name: string; module: string }>, default: () => [] },
});

const permissionsByModule = PERMISSIONS_BY_MODULE;

const modulesByCategory = computed(() => {
    const result: Record<string, Record<string, string[]>> = {};

    for (const category of PERMISSION_CATEGORIES) {
        const modulesInCategory = PERMISSION_MODULES.filter((m) => m.category === category);
        if (modulesInCategory.length === 0) continue;

        const filtered: Record<string, string[]> = {};
        for (const mod of modulesInCategory) {
            if (!filteredPermissionModules.value[mod.label]) continue;
            filtered[mod.label] = filteredPermissionModules.value[mod.label];
        }
        if (Object.keys(filtered).length > 0) {
            result[category] = filtered;
        }
    }
    return result;
});

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
const moduleAllSelected = computed<Record<string, boolean>>(() => {
    const result: Record<string, boolean> = {};
    for (const module in permissionsByModule) {
        result[module] = permissionsByModule[module].every(
            (p) => form.value.permissions.includes(p)
        );
    }
    return result;
});

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
        } else {
            form.value = { id: null, name: '', permissions: [] };
        }
    },
    { immediate: true },
);

// ...eliminado watch de área...

watch(
    () => globalSelectAll.value,
    (isChecked) => {
        form.value.permissions = isChecked
            ? Object.values(permissionsByModule).flat()
            : [];
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
        form.value.permissions = form.value.permissions.filter((p) => !permissions.includes(p));
    }
}

// Corregí el acceso a la propiedad 'checked' asegurando el tipo correcto.
function handleCheckboxChange(event: Event, module: string): void {
    const target = event.target as HTMLInputElement;
    toggleModulePermissions(module, target.checked);
}

const searchPermissions = ref('');

const filteredPermissionModules = computed(() => {
    const query = searchPermissions.value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
    if (!query) return permissionsByModule;

    const result: Record<string, string[]> = {};
    for (const [module, perms] of Object.entries(permissionsByModule)) {
        const moduleNorm = module.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        const matchedPerms = perms.filter((p) => {
            const pNorm = p.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            return pNorm.includes(query) || moduleNorm.includes(query);
        });
        if (matchedPerms.length > 0) {
            result[module] = matchedPerms;
        }
    }
    return result;
});
</script>

<template>
    <Dialog v-model:open="localIsOpen">
        <FormDialogContent size="md" class="overflow-hidden p-0 !h-[90vh]">
            <TooltipProvider>

                <!-- Header fijo -->
                <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-6 py-4 dark:border-sidebar-border">
                    <DialogTitle>{{ role?.id ? 'Editar Rol' : 'Crear Rol' }}</DialogTitle>
                    <DialogDescription>
                        {{ role?.id ? 'Edita la información del rol.' : 'Completa la información para crear un nuevo rol.' }}
                    </DialogDescription>
                </DialogHeader>

                <!-- Área scrollable -->
                <div class="no-scrollbar min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    <form @submit.prevent="submit">

                        <!-- Nombre del rol -->
                        <div class="mb-6 flex flex-col gap-1.5">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">
                                    Nombre del Rol<span class="text-destructive">*</span>
                                </Label>
                                <HelpTooltip
                                    text="Nombre descriptivo para el rol que agrupa permisos específicos (ej: Administrador, Vendedor, etc.)."
                                    aria-label="Ayuda sobre nombre del rol"
                                />
                            </div>
                            <InputField label="" v-model="form.name" placeholder="Nombre del rol" required />
                        </div>

                        <!-- Permisos -->
                        <div class="mb-2">
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-foreground">Permisos</span>
                                    <HelpTooltip
                                        text="Selecciona los permisos que tendrá este rol."
                                        aria-label="Ayuda sobre permisos"
                                    />
                                </div>
                                <label class="flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        id="global-select-all"
                                        v-model="globalSelectAll"
                                        class="accent-primary h-3.5 w-3.5 cursor-pointer"
                                    />
                                    Seleccionar todos los módulos
                                </label>
                            </div>

                            <div class="mb-3">
                                <input
                                    v-model="searchPermissions"
                                    type="text"
                                    placeholder="Buscar permiso..."
                                    class="w-full rounded-md border border-border bg-background px-3 py-1.5 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                                />
                            </div>

                            <div v-if="Object.keys(modulesByCategory).length > 0" class="space-y-5">
                                <div
                                    v-for="(modules, category) in modulesByCategory"
                                    :key="category"
                                >
                                    <!-- Cabecera de categoría -->
                                    <div class="mb-3 flex items-center gap-2">
                                        <span class="text-xs font-bold uppercase tracking-widest text-primary">
                                            {{ category }}
                                        </span>
                                        <div class="h-px flex-1 bg-border" />
                                    </div>

                                    <!-- Módulos de la categoría -->
                                    <div class="grid grid-cols-2 gap-3">
                                        <div
                                            v-for="(permissions, module) in modules"
                                            :key="module"
                                            class="rounded-lg border border-border bg-muted/30 p-3"
                                        >
                                            <!-- Cabecera del módulo — igual que antes -->
                                            <div class="mb-2.5 flex items-center justify-between">
                                                <span class="text-xs font-semibold uppercase tracking-wide text-foreground">
                                                    {{ module }}
                                                </span>
                                                <label class="flex cursor-pointer items-center gap-1 text-xs text-muted-foreground">
                                                    <input
                                                        type="checkbox"
                                                        :id="`select-all-${module}`"
                                                        :checked="moduleAllSelected[module]"
                                                        @change="handleCheckboxChange($event, module)"
                                                        class="accent-primary h-3.5 w-3.5 cursor-pointer"
                                                    />
                                                    {{ moduleAllSelected[module] ? 'Deseleccionar' : 'Todos' }}
                                                </label>
                                            </div>

                                            <!-- Permisos del módulo — igual que antes -->
                                            <div class="flex flex-col gap-1.5">
                                                <label
                                                    v-for="permission in permissions"
                                                    :key="permission"
                                                    :for="permission"
                                                    class="flex cursor-pointer items-center gap-2 rounded px-1 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-muted"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :id="permission"
                                                        :value="permission"
                                                        v-model="form.permissions"
                                                        class="accent-primary h-3.5 w-3.5 shrink-0 cursor-pointer"
                                                    />
                                                    {{ permission }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="py-4 text-center text-xs text-muted-foreground">
                                No se encontraron permisos
                            </p>
                        </div>

                    </form>
                </div>

                <!-- Footer fijo -->
                <div class="shrink-0 border-t border-border px-6 py-4">
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" size="pill" class="min-w-[120px]" @click="closeDialog">
                            Cancelar
                        </Button>
                        <Button
                            size="pill"
                            class="min-w-[120px]"
                            @click="submit"
                        >
                            {{ role?.id ? 'Guardar Cambios' : 'Crear Rol' }}
                        </Button>
                    </div>
                </div>

            </TooltipProvider>
        </FormDialogContent>
    </Dialog>
</template>
