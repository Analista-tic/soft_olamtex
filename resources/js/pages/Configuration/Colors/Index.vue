<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { hexToRgb } from '@/lib/colorUtils';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ColorDialog from './ColorDialog.vue';
import ColorSwatch from '@/components/common/ColorSwatch.vue';

type Color = {
    id: number;
    name: string;
    rgb: string | null;
    hexadecimal: string | null;
    description: string | null;
};

type ColorForm = {
    id?: number | null;
    name: string;
    hex: string;
    description: string;
};

function rgbToHex(rgb: string | null): string {
    if (!rgb) return '#9ca3af';
    const parts = rgb.split(',').map(Number);
    if (parts.length !== 3) return '#9ca3af';
    return '#' + parts
        .map((v) => Math.round(v).toString(16).padStart(2, '0'))
        .join('');
}

function createColorForm(color?: Partial<Color>): ColorForm {
    return {
        id: color?.id ?? null,
        name: color?.name ?? '',
        hex: rgbToHex(color?.rgb ?? null),
        description: color?.description ?? '',
    };
}

const breadcrumbs = [{ title: 'Colores', href: '/colores' }];

const allColors = ref<Color[]>([]);
const colorToDelete = ref<Color | null>(null);
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<ColorForm>(createColorForm());
const isSubmitting = ref(false);
const isLoading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const currentPage = ref(1);
const itemsPerPage = ref(10);
const searchQuery = ref('');
const sortBy = ref<'name' | 'description'>('name');
const sortDir = ref<'asc' | 'desc'>('asc');

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);

const filteredColors = computed(() => {
    const term = searchQuery.value.trim().toLowerCase();
    const base = !term ? allColors.value : allColors.value.filter((c) => c.name.toLowerCase().includes(term));

    const column = sortBy.value;
    const direction = sortDir.value === 'asc' ? 1 : -1;

    return [...base].sort((a, b) => {
        const left = String(a[column] ?? '').toLowerCase();
        const right = String(b[column] ?? '').toLowerCase();
        return left.localeCompare(right) * direction;
    });
});

const totalItems = computed(() => filteredColors.value.length);

const paginatedColors = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredColors.value.slice(start, start + itemsPerPage.value);
});

function toggleSort(column: 'name' | 'description') {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'asc';
    }

    currentPage.value = 1;
}

watch(searchQuery, () => {
    currentPage.value = 1;
});

watch([filteredColors, itemsPerPage], () => {
    const lastPage = Math.max(1, Math.ceil(filteredColors.value.length / itemsPerPage.value));
    if (currentPage.value > lastPage) {
        currentPage.value = lastPage;
    }
});

async function fetchColors() {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/colors');
        allColors.value = Array.isArray(data) ? data : [];
    } catch {
        allColors.value = [];
        errorType.value = 'error';
        errorMessage.value = 'Error al cargar los colores.';
    } finally {
        isLoading.value = false;
    }
}

function openDialog(type: 'create' | 'edit', color: Color | null = null) {
    dialogType.value = type;
    form.value = type === 'edit' && color ? createColorForm(color) : createColorForm();
    isDialogOpen.value = true;
}

function closeDialog() {
    isDialogOpen.value = false;
}

function confirmDeleteColor(color: Color) {
    colorToDelete.value = color;
    isDeleteDialogOpen.value = true;
}

async function handleDialogSubmit(formData: ColorForm) {
    const rgb = hexToRgb(formData.hex);
    const payload = {
        name: formData.name.trim(),
        rgb,
        hexadecimal: formData.hex.toUpperCase(),
        description: formData.description.trim() || null,
    };

    isSubmitting.value = true;
    try {
        if (dialogType.value === 'create') {
            await axios.post('/colors', payload);
            successMessage.value = 'Color creado correctamente.';
        } else {
            await axios.put(`/colors/${formData.id}`, payload);
            successMessage.value = 'Color actualizado correctamente.';
        }
        errorMessage.value = '';
        await fetchColors();
        closeDialog();
    } catch (error: any) {
        errorType.value = 'warning';
        errorMessage.value =
            error?.response?.data?.message ||
            'No se pudo guardar el color.';
    } finally {
        isSubmitting.value = false;
    }
}

async function deleteColor() {
    if (!colorToDelete.value) return;
    try {
        await axios.delete(`/colors/${colorToDelete.value.id}`);
        successMessage.value = 'Color eliminado correctamente.';
        errorMessage.value = '';
        await fetchColors();
    } catch (error: any) {
        errorType.value = 'error';
        errorMessage.value =
            error?.response?.data?.message ||
            'No se pudo eliminar el color.';
    } finally {
        isDeleteDialogOpen.value = false;
        colorToDelete.value = null;
    }
}

function handlePageChange(page?: number) {
    currentPage.value = page ?? 1;
}

onMounted(() => {
    void fetchColors();
});
</script>

<template>
    <Head title="Gestión de colores" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            class="mb-2"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Gestión de colores</h1>
                    <p class="text-sm text-muted-foreground">
                        Administra los colores disponibles para asignar
                        a los ítems del catálogo.
                    </p>
                </div>
                <Button
                    variant="default"
                    size="pill"
                    class="flex h-10 items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                    @click="openDialog('create')"
                >
                    <PlusCircle class="h-4 w-4" />
                    Nuevo color
                </Button>
            </div>

            <FilterBar align="start" gap="gap-4">
                <FilterField label="Buscar color" :active="isSearchActive" class="min-w-[240px] flex-1 p-0">
                    <div class="relative w-full">
                        <Input
                            id="search_color"
                            v-model="searchQuery"
                            placeholder="Buscar por nombre"
                            class="h-10 w-full pl-10"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead>N° Color</TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('name')">
                                <span class="inline-flex items-center gap-1">
                                    Nombre
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'name'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                        <template v-else><span class="opacity-30">↕</span></template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>Color</TableHead>
                            <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('description')">
                                <span class="inline-flex items-center gap-1">
                                    Descripción
                                    <span class="text-muted-foreground">
                                        <template v-if="sortBy === 'description'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                        <template v-else><span class="opacity-30">↕</span></template>
                                    </span>
                                </span>
                            </TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="isLoading">
                            <TableRow v-for="i in 5" :key="`color-skeleton-${i}`">
                                <TableCell v-for="col in 5" :key="`color-skeleton-${i}-${col}`" class="px-4 py-3">
                                    <Skeleton class="h-4 w-full" />
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="!paginatedColors.length">
                            <TableRow>
                                <TableCell colspan="5">
                                    <div class="py-6 text-center text-sm text-muted-foreground">No hay colores registrados.</div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow v-for="color in paginatedColors" :key="color.id">
                                <TableCell class="font-medium">{{ color.id }}</TableCell>
                                <TableCell class="font-medium">{{ color.name }}</TableCell>
                                <TableCell>
                                    <ColorSwatch
                                        :rgb="color.rgb"
                                        :hex="color.hexadecimal"
                                        :name="color.name"
                                    />
                                </TableCell>
                                <TableCell>{{ color.description || '-' }}</TableCell>
                                <TableCell class="flex justify-center gap-1">
                                    <EditButton @click="openDialog('edit', color)" />
                                    <DeleteButton @click="confirmDeleteColor(color)" />
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>

                <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <ServerPagination
                        v-model:current-page="currentPage"
                        v-model:page-size="itemsPerPage"
                        :total-items="totalItems"
                        @fetch-data="handlePageChange"
                    />
                </div>
            </div>
        </div>

        <ColorDialog
            :open="isDialogOpen"
            :dialog-type="dialogType"
            :form="form"
            :loading="isSubmitting"
            @update:open="isDialogOpen = $event"
            @submit="handleDialogSubmit"
            @cancel="closeDialog"
        />

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Confirmar eliminación"
            description="¿Estás seguro de que deseas eliminar este color? Esta acción no se puede deshacer."
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="deleteColor"
        />
    </AppLayout>
</template>
