<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import axios from 'axios';
import { Pencil, Plus, Save, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export type SectionType = 'PREPARACION' | 'ENSAMBLE' | 'TERMINACION';

export type ManagedSection = {
    id: number;
    name: string;
    type: SectionType;
    is_active: boolean;
    usage_count: number;
};

export type ManagedCatalogOption = {
    id: number;
    name: string;
    is_active: boolean;
    usage_count: number;
};

export type ManagedMachine = {
    id: number;
    code: string;
    name: string;
    supplement_factor: number;
    is_active: boolean;
    usage_count: number;
};

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'catalogs-updated', payload: { sections: ManagedSection[]; actions: string[]; garmentParts: string[]; machines: ManagedMachine[] }): void;
}>();

// Las bases de tela/hilaza (antes 'fabric-bases'/'yarn-bases') se gestionan ahora
// desde Datos Maestros → Catálogo de materiales (una sola pantalla, en vez de este
// diálogo + la pantalla de nombres por separado). Ver enlace al final de este diálogo.
type TabKey = 'sections' | 'actions' | 'garment-parts' | 'machines';
type StatusValue = '1' | '0';

const localOpen = ref(props.open);
const activeTab = ref<TabKey>('sections');
const isLoading = ref(false);
const isSaving = ref(false);
const isDeleteDialogOpen = ref(false);
const deletingItemId = ref<number | null>(null);
const errorMessage = ref('');
const successMessage = ref('');

const sections = ref<ManagedSection[]>([]);
const actions = ref<ManagedCatalogOption[]>([]);
const garmentParts = ref<ManagedCatalogOption[]>([]);
const machines = ref<ManagedMachine[]>([]);

const form = ref({
    id: null as number | null,
    code: '',
    name: '',
    type: 'PREPARACION' as SectionType,
    supplement_factor: 1,
    is_active: '1' as StatusValue,
});

watch(
    () => props.open,
    (value) => {
        localOpen.value = value;
    },
    { immediate: true },
);

watch(
    localOpen,
    (value, previousValue) => {
        if (value && !previousValue) {
            void fetchCatalogs();
            return;
        }

        if (!value && previousValue !== undefined) {
            emit('close');
        }
    },
    { immediate: true },
);

watch(activeTab, () => resetForm());

const tabLabels: Record<TabKey, { title: string; singular: string; empty: string }> = {
    sections: {
        title: 'Secciones operacionales',
        singular: 'sección',
        empty: 'No hay secciones registradas.',
    },
    actions: {
        title: 'Acciones operacionales',
        singular: 'acción',
        empty: 'No hay acciones registradas.',
    },
    'garment-parts': {
        title: 'Partes de prenda',
        singular: 'parte de prenda',
        empty: 'No hay partes de prenda registradas.',
    },
    machines: {
        title: 'Máquinas',
        singular: 'máquina',
        empty: 'No hay máquinas registradas.',
    },
};

const typeOptions = [
    { id: 'PREPARACION', name: 'Preparación' },
    { id: 'ENSAMBLE', name: 'Ensamble' },
    { id: 'TERMINACION', name: 'Terminación' },
];

const statusOptions = [
    { id: '1', name: 'Activo' },
    { id: '0', name: 'Inactivo' },
];

const currentItems = computed(() => {
    if (activeTab.value === 'sections') return sections.value;
    if (activeTab.value === 'actions') return actions.value;
    if (activeTab.value === 'garment-parts') return garmentParts.value;
    return machines.value;
});

const isEditing = computed(() => form.value.id !== null);
const currentLabel = computed(() => tabLabels[activeTab.value]);
const deleteDescription = computed(() => {
    const item = currentItems.value.find((entry) => entry.id === deletingItemId.value);
    if (!item) return '';
    const label = 'nombre' in item ? item.nombre : item.name;
    return `¿Deseas eliminar ${currentLabel.value.singular} "${label}"?`;
});

function clearAlerts() {
    errorMessage.value = '';
    successMessage.value = '';
}

function resetForm() {
    form.value = {
        id: null,
        code: '',
        name: '',
        type: 'PREPARACION',
        supplement_factor: 1,
        is_active: '1',
    };
}

function emitCatalogOptions() {
    emit('catalogs-updated', {
        sections: sections.value.filter((item) => item.is_active),
        actions: actions.value.filter((item) => item.is_active).map((item) => item.name),
        garmentParts: garmentParts.value.filter((item) => item.is_active).map((item) => item.name),
        machines: machines.value.filter((item) => item.is_active),
    });
}

async function fetchCatalogs(preserveAlerts = false) {
    isLoading.value = true;
    if (!preserveAlerts) {
        clearAlerts();
    }

    try {
        const { data } = await axios.get('/produccion/operaciones/catalog-management');
        sections.value = data?.data?.sections ?? [];
        actions.value = data?.data?.actions ?? [];
        garmentParts.value = data?.data?.garment_parts ?? [];
        machines.value = data?.data?.machines ?? [];
        emitCatalogOptions();
        resetForm();
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message ?? 'No fue posible cargar los catálogos.';
    } finally {
        isLoading.value = false;
    }
}

function startCreate() {
    resetForm();
}

function startEdit(item: ManagedSection | ManagedCatalogOption | ManagedMachine) {
    form.value = {
        id: item.id,
        code: 'code' in item ? String((item as ManagedMachine).code) : '',
        name: (item as ManagedSection | ManagedCatalogOption).name ?? '',
        type: 'type' in item ? (item as ManagedSection).type : 'PREPARACION',
        supplement_factor: 'supplement_factor' in item ? Number((item as ManagedMachine).supplement_factor) : 1,
        is_active: item.is_active ? '1' : '0',
    };
}

function askDelete(itemId: number) {
    deletingItemId.value = itemId;
    isDeleteDialogOpen.value = true;
}

async function saveItem() {
    isSaving.value = true;
    clearAlerts();

    const payload: Record<string, unknown> = {
        is_active: form.value.is_active === '1',
    };

    if (activeTab.value === 'machines') {
        if (!form.value.code.trim()) {
            errorMessage.value = 'El código de la máquina es obligatorio.';
            isSaving.value = false;
            return;
        }
        if (!form.value.name.trim()) {
            errorMessage.value = 'El nombre de la máquina es obligatorio.';
            isSaving.value = false;
            return;
        }
        payload.code = form.value.code.trim().toUpperCase();
        payload.name = form.value.name.trim().toUpperCase();
        payload.supplement_factor = Number(form.value.supplement_factor ?? 0);
    } else {
        const name = form.value.name.trim().toUpperCase();
        if (!name) {
            errorMessage.value = `El nombre de ${currentLabel.value.singular} es obligatorio.`;
            isSaving.value = false;
            return;
        }
        payload.name = name;
        if (activeTab.value === 'sections') {
            payload.type = form.value.type;
        }
    }

    try {
        if (form.value.id) {
            await axios.put(`/produccion/operaciones/catalog-management/${activeTab.value}/${form.value.id}`, payload);
            successMessage.value = `${currentLabel.value.singular.charAt(0).toUpperCase()}${currentLabel.value.singular.slice(1)} actualizada correctamente.`;
        } else {
            await axios.post(`/produccion/operaciones/catalog-management/${activeTab.value}`, payload);
            successMessage.value = `${currentLabel.value.singular.charAt(0).toUpperCase()}${currentLabel.value.singular.slice(1)} creada correctamente.`;
        }

        await fetchCatalogs(true);
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message
            ?? Object.values(error?.response?.data?.errors ?? {})?.flat()?.[0]
            ?? 'No fue posible guardar el registro.';
    } finally {
        isSaving.value = false;
    }
}

async function confirmDelete() {
    if (!deletingItemId.value) return;

    clearAlerts();

    try {
        await axios.delete(`/produccion/operaciones/catalog-management/${activeTab.value}/${deletingItemId.value}`);
        successMessage.value = `${currentLabel.value.singular.charAt(0).toUpperCase()}${currentLabel.value.singular.slice(1)} eliminada correctamente.`;
        deletingItemId.value = null;
        await fetchCatalogs(true);
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message
            ?? Object.values(error?.response?.data?.errors ?? {})?.flat()?.[0]
            ?? 'No fue posible eliminar el registro.';
    }
}
</script>

<template>
    <Dialog v-model:open="localOpen">
        <FormDialogContent size="lg" class="flex max-h-[95vh] min-h-0 w-[calc(100vw-2rem)] max-w-[1180px] flex-col p-0">
            <div class="border-b border-sidebar-border/70 bg-muted/20 px-5 py-3 dark:border-sidebar-border">
                <FormHeader
                    title="Parametrización de catálogos operativos"
                    description="Administra secciones, acciones y partes de prenda desde una sola interfaz."
                />
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4" style="scrollbar-width: thin">
                <AlertStack :success-message="successMessage" :error-message="errorMessage" @close-success="successMessage = ''" @close-error="errorMessage = ''" />

                <p class="rounded-md border border-dashed bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                    Las bases y nombres de tela/hilaza se gestionan ahora en
                    <a :href="route('nombres-materiales.index')" class="font-medium text-primary underline underline-offset-2">
                        Datos Maestros → Catálogo de materiales
                    </a>.
                </p>

                <Tabs v-model="activeTab" class="space-y-4">
                    <TabsList class="grid w-full grid-cols-4">
                        <TabsTrigger value="sections">Secciones</TabsTrigger>
                        <TabsTrigger value="actions">Acciones</TabsTrigger>
                        <TabsTrigger value="garment-parts">Partes de prenda</TabsTrigger>
                        <TabsTrigger value="machines">Máquinas</TabsTrigger>
                    </TabsList>

                    <TabsContent value="sections" class="mt-0">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
                            <section class="space-y-4 rounded-lg border bg-card p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold">{{ isEditing ? 'Editar sección' : 'Nueva sección' }}</p>
                                        <p class="text-xs text-muted-foreground">Controla nombre, tipo y estado.</p>
                                    </div>
                                    <Button variant="outline" size="sm" @click="startCreate">
                                        <Plus class="mr-1 h-4 w-4" /> Nueva
                                    </Button>
                                </div>

                                <div class="space-y-3">
                                    <InputField v-model="form.name" label="Nombre" placeholder="Ej: ENSAMBLE" required />
                                    <SelectField v-model="form.type" label="Tipo" :options="typeOptions" required />
                                    <SelectField v-model="form.is_active" label="Estado" :options="statusOptions" required />
                                </div>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    <Button :disabled="isSaving" @click="saveItem">
                                        <Save class="mr-1 h-4 w-4" />
                                        {{ isEditing ? 'Actualizar' : 'Guardar' }}
                                    </Button>
                                    <Button variant="outline" :disabled="isSaving" @click="resetForm">
                                        <X class="mr-1 h-4 w-4" /> Cancelar
                                    </Button>
                                </div>
                            </section>

                            <section class="rounded-lg border bg-card p-4">
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold">{{ currentLabel.title }}</p>
                                        <p class="text-xs text-muted-foreground">Edita o inactiva registros sin salir de operaciones.</p>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Nombre</TableHead>
                                                <TableHead>Tipo</TableHead>
                                                <TableHead class="text-center">Uso</TableHead>
                                                <TableHead class="text-center">Estado</TableHead>
                                                <TableHead class="text-center">Acciones</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-if="isLoading">
                                                <TableCell colspan="5" class="py-10 text-center text-sm text-muted-foreground">Cargando secciones…</TableCell>
                                            </TableRow>
                                            <TableRow v-else-if="sections.length === 0">
                                                <TableCell colspan="5" class="py-10 text-center text-sm text-muted-foreground">{{ currentLabel.empty }}</TableCell>
                                            </TableRow>
                                            <TableRow v-for="item in sections" v-else :key="item.id">
                                                <TableCell class="font-medium">{{ item.name }}</TableCell>
                                                <TableCell class="capitalize">{{ item.type }}</TableCell>
                                                <TableCell class="text-center font-mono text-xs">{{ item.usage_count }}</TableCell>
                                                <TableCell class="text-center">
                                                    <TableStatusBadge :label="item.is_active ? 'Activo' : 'Inactivo'" :tone="item.is_active ? 'green' : 'red'" />
                                                </TableCell>
                                                <TableCell>
                                                    <div class="flex items-center justify-center gap-1">
                                                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="startEdit(item)">
                                                            <Pencil class="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="askDelete(item.id)">
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </section>
                        </div>
                    </TabsContent>

                    <TabsContent value="actions" class="mt-0">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
                            <section class="space-y-4 rounded-lg border bg-card p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold">{{ isEditing ? 'Editar acción' : 'Nueva acción' }}</p>
                                        <p class="text-xs text-muted-foreground">El cambio se refleja en el catálogo y en las operaciones existentes.</p>
                                    </div>
                                    <Button variant="outline" size="sm" @click="startCreate">
                                        <Plus class="mr-1 h-4 w-4" /> Nueva
                                    </Button>
                                </div>

                                <div class="space-y-3">
                                    <InputField v-model="form.name" label="Nombre" placeholder="Ej: UNIR" required />
                                    <SelectField v-model="form.is_active" label="Estado" :options="statusOptions" required />
                                </div>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    <Button :disabled="isSaving" @click="saveItem">
                                        <Save class="mr-1 h-4 w-4" />
                                        {{ isEditing ? 'Actualizar' : 'Guardar' }}
                                    </Button>
                                    <Button variant="outline" :disabled="isSaving" @click="resetForm">
                                        <X class="mr-1 h-4 w-4" /> Cancelar
                                    </Button>
                                </div>
                            </section>

                            <section class="rounded-lg border bg-card p-4">
                                <div class="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Nombre</TableHead>
                                                <TableHead class="text-center">Uso</TableHead>
                                                <TableHead class="text-center">Estado</TableHead>
                                                <TableHead class="text-center">Acciones</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-if="isLoading">
                                                <TableCell colspan="4" class="py-10 text-center text-sm text-muted-foreground">Cargando acciones…</TableCell>
                                            </TableRow>
                                            <TableRow v-else-if="actions.length === 0">
                                                <TableCell colspan="4" class="py-10 text-center text-sm text-muted-foreground">{{ currentLabel.empty }}</TableCell>
                                            </TableRow>
                                            <TableRow v-for="item in actions" v-else :key="item.id">
                                                <TableCell class="font-medium">{{ item.name }}</TableCell>
                                                <TableCell class="text-center font-mono text-xs">{{ item.usage_count }}</TableCell>
                                                <TableCell class="text-center">
                                                    <TableStatusBadge :label="item.is_active ? 'Activo' : 'Inactivo'" :tone="item.is_active ? 'green' : 'red'" />
                                                </TableCell>
                                                <TableCell>
                                                    <div class="flex items-center justify-center gap-1">
                                                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="startEdit(item)">
                                                            <Pencil class="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="askDelete(item.id)">
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </section>
                        </div>
                    </TabsContent>

                    <TabsContent value="garment-parts" class="mt-0">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
                            <section class="space-y-4 rounded-lg border bg-card p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold">{{ isEditing ? 'Editar parte de prenda' : 'Nueva parte de prenda' }}</p>
                                        <p class="text-xs text-muted-foreground">El cambio se refleja en el catálogo y en las operaciones existentes.</p>
                                    </div>
                                    <Button variant="outline" size="sm" @click="startCreate">
                                        <Plus class="mr-1 h-4 w-4" /> Nueva
                                    </Button>
                                </div>

                                <div class="space-y-3">
                                    <InputField v-model="form.name" label="Nombre" placeholder="Ej: PRETINA" required />
                                    <SelectField v-model="form.is_active" label="Estado" :options="statusOptions" required />
                                </div>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    <Button :disabled="isSaving" @click="saveItem">
                                        <Save class="mr-1 h-4 w-4" />
                                        {{ isEditing ? 'Actualizar' : 'Guardar' }}
                                    </Button>
                                    <Button variant="outline" :disabled="isSaving" @click="resetForm">
                                        <X class="mr-1 h-4 w-4" /> Cancelar
                                    </Button>
                                </div>
                            </section>

                            <section class="rounded-lg border bg-card p-4">
                                <div class="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Nombre</TableHead>
                                                <TableHead class="text-center">Uso</TableHead>
                                                <TableHead class="text-center">Estado</TableHead>
                                                <TableHead class="text-center">Acciones</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-if="isLoading">
                                                <TableCell colspan="4" class="py-10 text-center text-sm text-muted-foreground">Cargando partes…</TableCell>
                                            </TableRow>
                                            <TableRow v-else-if="garmentParts.length === 0">
                                                <TableCell colspan="4" class="py-10 text-center text-sm text-muted-foreground">{{ currentLabel.empty }}</TableCell>
                                            </TableRow>
                                            <TableRow v-for="item in garmentParts" v-else :key="item.id">
                                                <TableCell class="font-medium">{{ item.name }}</TableCell>
                                                <TableCell class="text-center font-mono text-xs">{{ item.usage_count }}</TableCell>
                                                <TableCell class="text-center">
                                                    <TableStatusBadge :label="item.is_active ? 'Activo' : 'Inactivo'" :tone="item.is_active ? 'green' : 'red'" />
                                                </TableCell>
                                                <TableCell>
                                                    <div class="flex items-center justify-center gap-1">
                                                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="startEdit(item)">
                                                            <Pencil class="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="askDelete(item.id)">
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </section>
                        </div>
                    </TabsContent>

                    <TabsContent value="machines" class="mt-0">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
                            <section class="space-y-4 rounded-lg border bg-card p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold">{{ isEditing ? 'Editar máquina' : 'Nueva máquina' }}</p>
                                        <p class="text-xs text-muted-foreground">Administra código, nombre, suplemento y estado.</p>
                                    </div>
                                    <Button variant="outline" size="sm" @click="startCreate">
                                        <Plus class="mr-1 h-4 w-4" /> Nueva
                                    </Button>
                                </div>

                                <div class="space-y-3">
                                    <InputField v-model="form.code" label="Código" placeholder="Ej: MAQ-001" required />
                                    <InputField v-model="form.name" label="Nombre" placeholder="Ej: RECTA INDUSTRIAL" required />
                                    <InputField v-model.number="form.supplement_factor" label="Suplemento" type="number" step="0.0001" min="0" required />
                                    <SelectField v-model="form.is_active" label="Estado" :options="statusOptions" required />
                                </div>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    <Button :disabled="isSaving" @click="saveItem">
                                        <Save class="mr-1 h-4 w-4" />
                                        {{ isEditing ? 'Actualizar' : 'Guardar' }}
                                    </Button>
                                    <Button variant="outline" :disabled="isSaving" @click="resetForm">
                                        <X class="mr-1 h-4 w-4" /> Cancelar
                                    </Button>
                                </div>
                            </section>

                            <section class="rounded-lg border bg-card p-4">
                                <div class="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Código</TableHead>
                                                <TableHead>Nombre</TableHead>
                                                <TableHead class="text-center">Supl.</TableHead>
                                                <TableHead class="text-center">Uso</TableHead>
                                                <TableHead class="text-center">Estado</TableHead>
                                                <TableHead class="text-center">Acciones</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-if="isLoading">
                                                <TableCell colspan="6" class="py-10 text-center text-sm text-muted-foreground">Cargando máquinas…</TableCell>
                                            </TableRow>
                                            <TableRow v-else-if="machines.length === 0">
                                                <TableCell colspan="6" class="py-10 text-center text-sm text-muted-foreground">{{ currentLabel.empty }}</TableCell>
                                            </TableRow>
                                            <TableRow v-for="item in machines" v-else :key="item.id">
                                                <TableCell class="font-mono text-xs">{{ item.code }}</TableCell>
                                                <TableCell class="font-medium">{{ item.name }}</TableCell>
                                                <TableCell class="text-center font-mono text-xs">{{ item.supplement_factor }}</TableCell>
                                                <TableCell class="text-center font-mono text-xs">{{ item.usage_count }}</TableCell>
                                                <TableCell class="text-center">
                                                    <TableStatusBadge :label="item.is_active ? 'Activo' : 'Inactivo'" :tone="item.is_active ? 'green' : 'red'" />
                                                </TableCell>
                                                <TableCell>
                                                    <div class="flex items-center justify-center gap-1">
                                                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="startEdit(item)">
                                                            <Pencil class="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="askDelete(item.id)">
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </section>
                        </div>
                    </TabsContent>

                </Tabs>
            </div>

            <DialogFooter class="border-t px-5 py-3">
                <Button variant="outline" @click="localOpen = false">Cerrar</Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <ConfirmDialog
        v-model:open="isDeleteDialogOpen"
        title="Eliminar registro"
        :description="deleteDescription"
        confirm-text="Eliminar"
        cancel-text="Cancelar"
        :destructive="true"
        @confirm="confirmDelete"
    />
</template>
