<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAuthStore } from '@/stores/auth';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import type { BreadcrumbItem } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import MaterialBaseDialog, { type MaterialBaseForm, type MaterialBaseKind } from './MaterialBaseDialog.vue';
import MaterialNameDialog, { type MaterialNameForm, type MaterialNameKind } from './MaterialNameDialog.vue';

type MaterialBase = {
    id: number;
    codigo: string;
    nombre: string;
    factor?: number | null;
    is_active: boolean;
    usage_count: number;
};

type MaterialName = {
    id: number;
    nombre: string;
    fabric_base_id?: number;
    yarn_base_id?: number;
    base_nombre: string | null;
    factor?: number | null;
    is_active: boolean;
};

type BaseOption = { id: number; name: string };
type MaterialKind = 'fabric' | 'yarn';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Catálogo de materiales', href: route('nombres-materiales.index') },
];

const authStore = useAuthStore();
onMounted(() => void authStore.fetchUser());
const canSeeFactor = computed(() => authStore.can('ver factor material'));
const canCreate = computed(() => authStore.can('crear catalogo materiales'));

const { successMessage, errorMessage, errorType, success, error, clear } = useGlobalAlert();

const activeTab = ref<MaterialKind>('fabric');
const kindLabel = computed(() => (activeTab.value === 'fabric' ? 'tela' : 'hilaza'));

// ── Bases: tabla server-side (búsqueda + orden + paginación) ───────────────

const bases = ref<MaterialBase[]>([]);
const basesLoading = ref(false);
const basesSearch = ref('');
const basesCurrentPage = ref(1);
const basesPerPage = ref(10);
const basesTotal = ref(0);
const basesSortBy = ref<'codigo' | 'nombre' | 'factor'>('codigo');
const basesSortDir = ref<'asc' | 'desc'>('asc');

let basesFetchController: AbortController | null = null;

async function fetchBases(page = 1) {
    if (basesFetchController) basesFetchController.abort();
    const controller = new AbortController();
    basesFetchController = controller;
    basesLoading.value = true;

    try {
        const { data } = await axios.get(route('catalogo-materiales.index'), {
            params: {
                type: activeTab.value,
                search: basesSearch.value || undefined,
                page,
                per_page: basesPerPage.value,
                sort_by: basesSortBy.value,
                sort_direction: basesSortDir.value,
            },
            signal: controller.signal,
        });
        if (basesFetchController !== controller) return;
        bases.value = data.data ?? [];
        basesTotal.value = Number(data.total ?? 0);
        basesCurrentPage.value = Number(data.current_page ?? page);
    } catch (err) {
        if (axios.isCancel(err)) return;
        error('No se pudo cargar el catálogo de bases.');
    } finally {
        if (basesFetchController === controller) {
            basesFetchController = null;
            basesLoading.value = false;
        }
    }
}

function toggleBasesSort(column: 'codigo' | 'nombre' | 'factor') {
    if (basesSortBy.value === column) {
        basesSortDir.value = basesSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        basesSortBy.value = column;
        basesSortDir.value = 'asc';
    }
    void fetchBases(1);
}

// ── Nombres: tabla server-side, filtrable por la base seleccionada ─────────
// Hacer clic en una fila de "Bases" filtra esta tabla a solo sus nombres — es la
// forma en que se muestra la jerarquía base→nombre sin una tabla anidada.

const names = ref<MaterialName[]>([]);
const namesLoading = ref(false);
const namesSearch = ref('');
const namesCurrentPage = ref(1);
const namesPerPage = ref(10);
const namesTotal = ref(0);
const selectedBase = ref<MaterialBase | null>(null);

const nameEndpoints: Record<MaterialNameKind, { index: string; store: string; update: (id: number) => string; destroy: (id: number) => string }> = {
    fabric: {
        index: route('nombres-tela.index'),
        store: route('nombres-tela.store'),
        update: (id) => route('nombres-tela.update', { fabricName: id }),
        destroy: (id) => route('nombres-tela.destroy', { fabricName: id }),
    },
    yarn: {
        index: route('nombres-hilaza.index'),
        store: route('nombres-hilaza.store'),
        update: (id) => route('nombres-hilaza.update', { yarnName: id }),
        destroy: (id) => route('nombres-hilaza.destroy', { yarnName: id }),
    },
};

let namesFetchController: AbortController | null = null;

async function fetchNames(page = 1) {
    if (namesFetchController) namesFetchController.abort();
    const controller = new AbortController();
    namesFetchController = controller;
    namesLoading.value = true;

    try {
        const { data } = await axios.get(nameEndpoints[activeTab.value].index, {
            params: {
                search: namesSearch.value || undefined,
                base_id: selectedBase.value?.id ?? undefined,
                page,
                per_page: namesPerPage.value,
            },
            signal: controller.signal,
        });
        if (namesFetchController !== controller) return;
        names.value = data.data ?? [];
        namesTotal.value = Number(data.total ?? 0);
        namesCurrentPage.value = Number(data.current_page ?? page);
    } catch (err) {
        if (axios.isCancel(err)) return;
        error('No se pudo cargar el catálogo de nombres.');
    } finally {
        if (namesFetchController === controller) {
            namesFetchController = null;
            namesLoading.value = false;
        }
    }
}

function selectBase(item: MaterialBase) {
    selectedBase.value = selectedBase.value?.id === item.id ? null : item;
    void fetchNames(1);
}

function clearBaseFilter() {
    selectedBase.value = null;
    void fetchNames(1);
}

// ── Opciones de base para el selector del diálogo de nombre ────────────────
// Independiente de la paginación de la tabla de bases: siempre trae TODAS las bases
// activas, vía el mismo endpoint que ya usaba el alta inline desde Sábana de productos.

const baseOptionsEndpoint: Record<MaterialKind, string> = {
    fabric: route('nombres-tela.bases-disponibles'),
    yarn: route('nombres-hilaza.bases-disponibles'),
};

const currentBaseOptions = ref<BaseOption[]>([]);

async function fetchBaseOptions() {
    try {
        const { data } = await axios.get(baseOptionsEndpoint[activeTab.value]);
        const rows = Array.isArray(data) ? data : (data?.data ?? []);
        currentBaseOptions.value = rows.map((b: { id: number; nombre?: string; name?: string }) => ({ id: b.id, name: b.nombre ?? b.name ?? '' }));
    } catch {
        currentBaseOptions.value = [];
    }
}

// ── Cambiar de pestaña reinicia búsqueda/filtro/página y recarga ───────────

function reloadTab() {
    basesSearch.value = '';
    basesCurrentPage.value = 1;
    namesSearch.value = '';
    namesCurrentPage.value = 1;
    selectedBase.value = null;
    void fetchBases(1);
    void fetchNames(1);
    void fetchBaseOptions();
}

watch(activeTab, reloadTab);
onMounted(reloadTab);

let basesSearchDebounce: ReturnType<typeof setTimeout> | undefined;
watch(basesSearch, () => {
    clearTimeout(basesSearchDebounce);
    basesSearchDebounce = setTimeout(() => void fetchBases(1), 300);
});

let namesSearchDebounce: ReturnType<typeof setTimeout> | undefined;
watch(namesSearch, () => {
    clearTimeout(namesSearchDebounce);
    namesSearchDebounce = setTimeout(() => void fetchNames(1), 300);
});

// ── Diálogo de base ──────────────────────────────────────────────────────

const isBaseDialogOpen = ref(false);
const isBaseSaving = ref(false);
const baseDialogErrorMessage = ref('');
const baseDialogKind = ref<MaterialBaseKind>('fabric');
const baseForm = ref<MaterialBaseForm>({ id: null, nombre: '', factor: 1, is_active: true });

const isNameDialogOpen = ref(false);
const isNameSaving = ref(false);
const nameDialogErrorMessage = ref('');
const nameDialogKind = ref<MaterialNameKind>('fabric');
const nameForm = ref<MaterialNameForm>({ id: null, nombre: '', base_id: null, is_active: true });

const isDeleteDialogOpen = ref(false);
const deleteTarget = ref<{ type: 'base' | 'name'; item: MaterialBase | MaterialName } | null>(null);

function openCreateBaseDialog() {
    baseDialogKind.value = activeTab.value;
    baseForm.value = { id: null, nombre: '', factor: 1, is_active: true };
    baseDialogErrorMessage.value = '';
    isBaseDialogOpen.value = true;
}

const editingBaseOriginalFactor = ref<number | null>(null);

function openEditBaseDialog(item: MaterialBase) {
    baseDialogKind.value = activeTab.value;
    baseForm.value = { id: item.id, nombre: item.nombre, factor: item.factor ?? 1, is_active: item.is_active };
    baseDialogErrorMessage.value = '';
    editingBaseOriginalFactor.value = item.factor ?? null;
    isBaseDialogOpen.value = true;
}

function closeBaseDialog() {
    isBaseDialogOpen.value = false;
    baseDialogErrorMessage.value = '';
}

// El factor afecta el SAM de todo lo que use esta base: antes de guardar un cambio
// de factor se pide confirmación explícita, mostrando cuántas fichas y operaciones
// de orden se recalcularían (las operaciones con avance ya reportado no se tocan).
const isFactorImpactDialogOpen = ref(false);
const factorImpactDescription = ref('');
let factorImpactResolve: ((confirmed: boolean) => void) | null = null;

async function confirmFactorImpactIfNeeded(baseId: number, newFactor: number, originalFactor: number | null): Promise<boolean> {
    if (originalFactor === null || Math.abs(newFactor - originalFactor) < 0.00001) {
        return true;
    }

    try {
        const { data } = await axios.get(route('catalogo-materiales.factor-impact', { fabricBase: baseId }));
        const impact = data?.data ?? { sheets: 0, order_operations: 0 };
        if (!impact.sheets && !impact.order_operations) {
            return true;
        }

        factorImpactDescription.value = `Esto recalculará ${impact.sheets} ficha(s) y ${impact.order_operations} operación(es) de orden que usan esta base. Las operaciones con avance ya reportado no se tocan.`;
        isFactorImpactDialogOpen.value = true;

        return await new Promise((resolve) => {
            factorImpactResolve = resolve;
        });
    } catch {
        // Si el preview falla, no bloquear el guardado — solo se pierde el aviso previo.
        return true;
    }
}

function confirmFactorImpact() {
    isFactorImpactDialogOpen.value = false;
    factorImpactResolve?.(true);
    factorImpactResolve = null;
}

// ConfirmDialog solo emite update:open(false) al cancelar (clic en "Cancelar", ESC o
// clic fuera) — no tiene un evento @cancel propio, así que se detecta aquí.
watch(isFactorImpactDialogOpen, (open) => {
    if (!open && factorImpactResolve) {
        factorImpactResolve(false);
        factorImpactResolve = null;
    }
});

async function handleBaseSubmit(submittedForm: MaterialBaseForm) {
    const kind = baseDialogKind.value;
    const catalog = kind === 'fabric' ? 'fabric-bases' : 'yarn-bases';

    if (!submittedForm.nombre.trim()) {
        baseDialogErrorMessage.value = 'El nombre es obligatorio.';
        return;
    }

    const payload: Record<string, unknown> = {
        nombre: submittedForm.nombre.trim(),
        is_active: submittedForm.is_active,
    };
    if (kind === 'fabric') {
        payload.factor = Number(submittedForm.factor ?? 1);
    }

    if (kind === 'fabric' && submittedForm.id) {
        const proceed = await confirmFactorImpactIfNeeded(submittedForm.id, Number(payload.factor), editingBaseOriginalFactor.value);
        if (!proceed) return;
    }

    isBaseSaving.value = true;
    baseDialogErrorMessage.value = '';
    try {
        if (submittedForm.id) {
            const { data } = await axios.put(route('catalogo-materiales.update', { catalog, id: submittedForm.id }), payload);
            success(data?.message ?? `Base de ${kindLabel.value} actualizada correctamente.`);
        } else {
            await axios.post(route('catalogo-materiales.store', { catalog }), payload);
            success(`Base de ${kindLabel.value} creada correctamente.`);
        }
        closeBaseDialog();
        await fetchBases(basesCurrentPage.value);
        await fetchBaseOptions();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const validationErrors = axiosErr?.response?.data?.errors;
        baseDialogErrorMessage.value = validationErrors
            ? Object.values(validationErrors).flat().join('\n')
            : (axiosErr?.response?.data?.message ?? 'No se pudo guardar el registro.');
    } finally {
        isBaseSaving.value = false;
    }
}

// ── Diálogo de nombre ────────────────────────────────────────────────────

function openCreateNameDialog() {
    nameDialogKind.value = activeTab.value;
    nameForm.value = { id: null, nombre: '', base_id: selectedBase.value?.id ?? null, is_active: true };
    nameDialogErrorMessage.value = '';
    isNameDialogOpen.value = true;
}

function openEditNameDialog(item: MaterialName) {
    nameDialogKind.value = activeTab.value;
    nameForm.value = {
        id: item.id,
        nombre: item.nombre,
        base_id: activeTab.value === 'fabric' ? (item.fabric_base_id ?? null) : (item.yarn_base_id ?? null),
        is_active: item.is_active,
    };
    nameDialogErrorMessage.value = '';
    isNameDialogOpen.value = true;
}

function closeNameDialog() {
    isNameDialogOpen.value = false;
    nameDialogErrorMessage.value = '';
}

async function handleNameSubmit(submittedForm: MaterialNameForm) {
    const kind = nameDialogKind.value;

    if (!submittedForm.nombre.trim()) {
        nameDialogErrorMessage.value = 'El nombre es obligatorio.';
        return;
    }
    if (!submittedForm.base_id) {
        nameDialogErrorMessage.value = `Debes seleccionar una base de ${kind === 'fabric' ? 'tela' : 'hilaza'}.`;
        return;
    }

    const payload: Record<string, unknown> = {
        nombre: submittedForm.nombre.trim(),
        is_active: submittedForm.is_active,
    };
    payload[kind === 'fabric' ? 'fabric_base_id' : 'yarn_base_id'] = Number(submittedForm.base_id);

    isNameSaving.value = true;
    nameDialogErrorMessage.value = '';
    try {
        if (submittedForm.id) {
            await axios.put(nameEndpoints[kind].update(submittedForm.id), payload);
            success(`Nombre de ${kindLabel.value} actualizado correctamente.`);
        } else {
            await axios.post(nameEndpoints[kind].store, payload);
            success(`Nombre de ${kindLabel.value} creado correctamente.`);
        }
        closeNameDialog();
        await fetchNames(namesCurrentPage.value);
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const validationErrors = axiosErr?.response?.data?.errors;
        nameDialogErrorMessage.value = validationErrors
            ? Object.values(validationErrors).flat().join('\n')
            : (axiosErr?.response?.data?.message ?? 'No se pudo guardar el registro.');
    } finally {
        isNameSaving.value = false;
    }
}

// ── Eliminar ─────────────────────────────────────────────────────────────

function confirmDeleteBase(item: MaterialBase) {
    deleteTarget.value = { type: 'base', item };
    isDeleteDialogOpen.value = true;
}

function confirmDeleteName(item: MaterialName) {
    deleteTarget.value = { type: 'name', item };
    isDeleteDialogOpen.value = true;
}

const deleteDescription = computed(() => {
    if (!deleteTarget.value) return '';
    const nombre = deleteTarget.value.item.nombre;
    return `¿Seguro que deseas eliminar "${nombre}"? Esta acción no se puede deshacer.`;
});

async function performDelete() {
    if (!deleteTarget.value) return;
    const { type, item } = deleteTarget.value;
    const kind = activeTab.value;

    try {
        if (type === 'base') {
            const catalog = kind === 'fabric' ? 'fabric-bases' : 'yarn-bases';
            await axios.delete(route('catalogo-materiales.destroy', { catalog, id: item.id }));
            success(`Base de ${kindLabel.value} eliminada correctamente.`);
            if (selectedBase.value?.id === item.id) selectedBase.value = null;
            await fetchBases(basesCurrentPage.value);
            await fetchBaseOptions();
            await fetchNames(1);
        } else {
            await axios.delete(nameEndpoints[kind].destroy(item.id));
            success(`Nombre de ${kindLabel.value} eliminado correctamente.`);
            await fetchNames(namesCurrentPage.value);
        }
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        error(axiosErr?.response?.data?.message ?? 'No se pudo eliminar el registro.');
    } finally {
        isDeleteDialogOpen.value = false;
        deleteTarget.value = null;
    }
}
</script>

<template>
    <Head title="Catálogo de materiales" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div>
                <h1 class="text-xl font-bold">Catálogo de materiales</h1>
                <p class="text-sm text-muted-foreground">
                    Cada nombre específico pertenece a una base — la base es la que trae el factor usado en el SAM.
                    Un ítem de inventario selecciona el nombre; la base se hereda automáticamente. Haz clic en una
                    base para ver solo sus nombres.
                </p>
            </div>

            <AlertStack :success-message="successMessage" :error-message="errorMessage" :error-type="errorType" @close-success="clear" @close-error="clear" />

            <Tabs v-model="activeTab">
                <TabsList class="grid w-full max-w-md grid-cols-2">
                    <TabsTrigger value="fabric">Tela</TabsTrigger>
                    <TabsTrigger value="yarn">Hilaza</TabsTrigger>
                </TabsList>

                <TabsContent v-for="kind in (['fabric', 'yarn'] as MaterialKind[])" :key="kind" :value="kind" class="mt-4 space-y-6">
                    <section>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold">Bases de {{ kindLabel }}</h2>
                            <Button v-if="canCreate" size="sm" @click="openCreateBaseDialog">
                                <PlusCircle class="mr-2 h-4 w-4" />
                                Nueva base
                            </Button>
                        </div>
                        <div class="relative mb-2 max-w-xs">
                            <Input v-model="basesSearch" placeholder="Buscar base por código o nombre" class="h-9 pl-9" />
                            <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center justify-center px-2.5">
                                <Search class="h-4 w-4 text-muted-foreground" />
                            </span>
                        </div>
                        <div class="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleBasesSort('codigo')">
                                            <span class="inline-flex items-center gap-1">
                                                Código
                                                <span class="text-muted-foreground">
                                                    <template v-if="basesSortBy === 'codigo'">{{ basesSortDir === 'asc' ? '↑' : '↓' }}</template>
                                                    <template v-else><span class="opacity-30">↕</span></template>
                                                </span>
                                            </span>
                                        </TableHead>
                                        <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleBasesSort('nombre')">
                                            <span class="inline-flex items-center gap-1">
                                                Nombre
                                                <span class="text-muted-foreground">
                                                    <template v-if="basesSortBy === 'nombre'">{{ basesSortDir === 'asc' ? '↑' : '↓' }}</template>
                                                    <template v-else><span class="opacity-30">↕</span></template>
                                                </span>
                                            </span>
                                        </TableHead>
                                        <TableHead
                                            v-if="kind === 'fabric' && canSeeFactor"
                                            class="cursor-pointer select-none whitespace-nowrap text-center"
                                            @click="toggleBasesSort('factor')"
                                        >
                                            <span class="inline-flex items-center justify-center gap-1">
                                                Factor
                                                <span class="text-muted-foreground">
                                                    <template v-if="basesSortBy === 'factor'">{{ basesSortDir === 'asc' ? '↑' : '↓' }}</template>
                                                    <template v-else><span class="opacity-30">↕</span></template>
                                                </span>
                                            </span>
                                        </TableHead>
                                        <TableHead class="text-center">Uso</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead class="text-right">Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <template v-if="basesLoading">
                                        <TableRow v-for="i in 3" :key="`base-skeleton-${i}`">
                                            <TableCell v-for="col in 6" :key="`base-skeleton-${i}-${col}`" class="px-4 py-3">
                                                <Skeleton class="h-4 w-full" />
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                    <TableRow v-else-if="bases.length === 0">
                                        <TableCell colspan="6" class="py-8 text-center text-muted-foreground">
                                            {{ basesSearch ? `Ninguna base coincide con "${basesSearch}".` : `No hay bases de ${kindLabel} registradas.` }}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow
                                        v-for="item in bases"
                                        v-else
                                        :key="item.id"
                                        class="cursor-pointer"
                                        :class="selectedBase?.id === item.id ? 'bg-primary/5' : 'hover:bg-muted/40'"
                                        @click="selectBase(item)"
                                    >
                                        <TableCell class="font-mono text-xs">{{ item.codigo }}</TableCell>
                                        <TableCell class="font-medium">
                                            <span class="inline-flex items-center gap-2">
                                                {{ item.nombre }}
                                                <Badge v-if="selectedBase?.id === item.id" variant="outline" class="text-[10px]">Filtrando nombres</Badge>
                                            </span>
                                        </TableCell>
                                        <TableCell v-if="kind === 'fabric' && canSeeFactor" class="text-center font-mono text-xs">
                                            {{ item.factor != null ? Number(item.factor).toFixed(2) : '—' }}
                                        </TableCell>
                                        <TableCell class="text-center font-mono text-xs">{{ item.usage_count }}</TableCell>
                                        <TableCell>
                                            <Badge :variant="item.is_active ? 'default' : 'secondary'">{{ item.is_active ? 'Activo' : 'Inactivo' }}</Badge>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <div v-if="canCreate" class="flex items-center justify-end gap-2" @click.stop>
                                                <EditButton @click="openEditBaseDialog(item)" />
                                                <DeleteButton @click="confirmDeleteBase(item)" />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                            <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                                <ServerPagination
                                    v-model:current-page="basesCurrentPage"
                                    v-model:page-size="basesPerPage"
                                    :total-items="basesTotal"
                                    @fetch-data="fetchBases"
                                />
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-semibold">
                                    {{ selectedBase ? `Nombres de la base "${selectedBase.nombre}"` : `Nombres de ${kindLabel}` }}
                                </h2>
                                <Button v-if="selectedBase" variant="ghost" size="sm" class="h-6 gap-1 px-2 text-xs text-muted-foreground" @click="clearBaseFilter">
                                    <X class="h-3 w-3" />
                                    Ver todos
                                </Button>
                            </div>
                            <Button v-if="canCreate" size="sm" @click="openCreateNameDialog">
                                <PlusCircle class="mr-2 h-4 w-4" />
                                Nuevo nombre
                            </Button>
                        </div>
                        <div class="relative mb-2 max-w-xs">
                            <Input v-model="namesSearch" placeholder="Buscar nombre" class="h-9 pl-9" />
                            <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center justify-center px-2.5">
                                <Search class="h-4 w-4 text-muted-foreground" />
                            </span>
                        </div>
                        <div class="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nombre</TableHead>
                                        <TableHead>Base</TableHead>
                                        <TableHead v-if="kind === 'fabric' && canSeeFactor" class="text-center">Factor</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead class="text-right">Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <template v-if="namesLoading">
                                        <TableRow v-for="i in 3" :key="`name-skeleton-${i}`">
                                            <TableCell v-for="col in 5" :key="`name-skeleton-${i}-${col}`" class="px-4 py-3">
                                                <Skeleton class="h-4 w-full" />
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                    <TableRow v-else-if="names.length === 0">
                                        <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                                            <template v-if="namesSearch">Ningún nombre coincide con "{{ namesSearch }}".</template>
                                            <template v-else-if="selectedBase">Esta base todavía no tiene nombres de {{ kindLabel }} registrados.</template>
                                            <template v-else>No hay nombres de {{ kindLabel }} registrados.</template>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-for="item in names" v-else :key="item.id">
                                        <TableCell class="font-medium">{{ item.nombre }}</TableCell>
                                        <TableCell>{{ item.base_nombre ?? '—' }}</TableCell>
                                        <TableCell v-if="kind === 'fabric' && canSeeFactor" class="text-center font-mono text-xs">
                                            {{ item.factor != null ? Number(item.factor).toFixed(2) : '—' }}
                                        </TableCell>
                                        <TableCell>
                                            <Badge :variant="item.is_active ? 'default' : 'secondary'">{{ item.is_active ? 'Activo' : 'Inactivo' }}</Badge>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <div v-if="canCreate" class="flex items-center justify-end gap-2">
                                                <EditButton @click="openEditNameDialog(item)" />
                                                <DeleteButton @click="confirmDeleteName(item)" />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                            <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                                <ServerPagination
                                    v-model:current-page="namesCurrentPage"
                                    v-model:page-size="namesPerPage"
                                    :total-items="namesTotal"
                                    @fetch-data="fetchNames"
                                />
                            </div>
                        </div>
                    </section>
                </TabsContent>
            </Tabs>
        </div>

        <MaterialBaseDialog
            :open="isBaseDialogOpen"
            :kind="baseDialogKind"
            :form="baseForm"
            :can-see-factor="canSeeFactor"
            :loading="isBaseSaving"
            :error-message="baseDialogErrorMessage"
            @update:open="isBaseDialogOpen = $event"
            @submit="handleBaseSubmit"
            @cancel="closeBaseDialog"
        />

        <MaterialNameDialog
            :open="isNameDialogOpen"
            :kind="nameDialogKind"
            :form="nameForm"
            :base-options="currentBaseOptions"
            :loading="isNameSaving"
            :error-message="nameDialogErrorMessage"
            @update:open="isNameDialogOpen = $event"
            @submit="handleNameSubmit"
            @cancel="closeNameDialog"
        />

        <ConfirmDialog
            v-model:open="isDeleteDialogOpen"
            title="Eliminar registro"
            :description="deleteDescription"
            confirm-text="Eliminar"
            cancel-text="Cancelar"
            :destructive="true"
            @confirm="performDelete"
        />

        <ConfirmDialog
            v-model:open="isFactorImpactDialogOpen"
            title="Cambiar el factor de la base"
            :description="factorImpactDescription"
            confirm-text="Continuar y recalcular"
            cancel-text="Cancelar"
            :destructive="false"
            @confirm="confirmFactorImpact"
        />
    </AppLayout>
</template>
