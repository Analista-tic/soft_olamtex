<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import type { ManualOrderCodeSummary } from '@/domain/Textil';
import AppLayout from '@/layouts/AppLayout.vue';
import ManualRollProgramDialog from '@/pages/Textil/FabricRollOrders/ManualRollProgramDialog.vue';
import { useAuthStore } from '@/stores/auth';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowDownToLine, Plus, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const breadcrumbs = [{ title: 'Descargue de tela por orden', href: '/kardex-telas/descargue-orden' }];

const authStore = useAuthStore();
const canCreate = computed(() => authStore.can('crear descargue telas orden'));
const canDischarge = computed(() => authStore.can('descargar telas orden'));

const { successMessage, errorMessage, error, success, clear } = useGlobalAlert();

const searchQuery = ref('');
const codes = ref<ManualOrderCodeSummary[]>([]);
const isLoading = ref(false);
const isProgramDialogOpen = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const numFmt = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const fmtMeters = (v: number) => `${numFmt.format(v)} m`;

async function fetchCodes() {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/kardex-telas/descargue-orden/data', {
            params: { search: searchQuery.value.trim() || undefined, per_page: 100 },
        });
        codes.value = Array.isArray(data?.data) ? data.data : [];
    } catch {
        codes.value = [];
        error('No se pudo cargar el listado de rollos programados por orden.');
    } finally {
        isLoading.value = false;
    }
}

function scheduleFetch() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchCodes, 350);
}

watch(searchQuery, scheduleFetch);

function openCode(code: ManualOrderCodeSummary) {
    router.visit(route('kardex-telas.descargue-orden.show', { manualOrderCode: code.manual_order_code }));
}

function handleProgramSuccess(payload: { manualOrderCode: string }) {
    success('Programación registrada correctamente.');
    void fetchCodes();
    router.visit(route('kardex-telas.descargue-orden.show', { manualOrderCode: payload.manualOrderCode }));
}

onMounted(fetchCodes);
</script>

<template>
    <Head title="Descargue de tela por orden" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack :success-message="successMessage" :error-message="errorMessage" @close-success="clear" @close-error="clear" />

            <div class="flex items-center justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Descargue de tela por orden</h1>
                    <p class="text-sm text-muted-foreground">
                        Programa y descarga metros de rollo por número de orden de producción, mientras no exista una integración directa con el
                        Programador.
                    </p>
                </div>
                <Button v-if="canCreate" variant="default" class="shrink-0 gap-1.5 rounded-full" @click="isProgramDialogOpen = true">
                    <Plus class="h-4 w-4" />
                    Programar rollos
                </Button>
            </div>

            <FilterBar>
                <FilterField label="Buscar OP" :active="searchQuery.trim().length > 0" class="min-w-[220px] flex-1 p-0">
                    <div class="relative">
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar número de orden de producción"
                            class="h-10 w-full rounded-md border border-input bg-background pl-10 text-sm focus:border-primary focus:ring-0"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search class="text-muted-foreground" />
                        </span>
                    </div>
                </FilterField>
            </FilterBar>

            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 bg-card md:min-h-min">
                <div class="overflow-x-auto">
                    <Table class="min-w-full text-[0.9rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>OP</TableHead>
                                <TableHead class="text-right">Rollos</TableHead>
                                <TableHead class="text-right">Programado</TableHead>
                                <TableHead class="text-right">Consumido</TableHead>
                                <TableHead class="text-right">Sobreconsumo</TableHead>
                                <TableHead class="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow v-for="i in 5" :key="`fabric-roll-order-skeleton-${i}`">
                                    <TableCell v-for="col in 6" :key="`fabric-roll-order-skeleton-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <TableRow v-else-if="codes.length === 0">
                                <TableCell colspan="6" class="py-8 text-center text-sm text-muted-foreground">
                                    Todavía no se han programado rollos por orden de producción.
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="code in codes"
                                v-else
                                :key="code.manual_order_code"
                                class="cursor-pointer border-b hover:bg-muted/40"
                                @click="openCode(code)"
                            >
                                <TableCell class="font-medium">{{ code.manual_order_code }}</TableCell>
                                <TableCell class="text-right">{{ code.rolls_count }}</TableCell>
                                <TableCell class="text-right">{{ fmtMeters(code.programmed_meters) }}</TableCell>
                                <TableCell class="text-right">{{ fmtMeters(code.consumed_meters) }}</TableCell>
                                <TableCell
                                    class="text-right"
                                    :class="code.overconsumed_meters > 0 ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                                >
                                    {{ code.overconsumed_meters > 0 ? fmtMeters(code.overconsumed_meters) : '-' }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        :variant="canDischarge ? 'default' : 'outline'"
                                        class="gap-1.5 rounded-full px-4"
                                        :title="canDischarge ? 'Abrir la orden para descargar sus rollos' : 'Ver los rollos de la orden'"
                                        @click.stop="openCode(code)"
                                    >
                                        <ArrowDownToLine class="h-4 w-4" />
                                        {{ canDischarge ? 'Descargar' : 'Ver' }}
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <ManualRollProgramDialog v-model="isProgramDialogOpen" @success="handleProgramSuccess" />
    </AppLayout>
</template>
