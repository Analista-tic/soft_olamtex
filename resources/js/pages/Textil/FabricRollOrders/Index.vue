<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AlertStack from '@/components/common/AlertStack.vue';
import { ArrowDownToLine, Search } from 'lucide-vue-next';
import { Inertia } from '@inertiajs/inertia';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import type { Programmer } from '@/domain/product-sheet';

const breadcrumbs = [
  { title: 'Descargue de tela por orden', href: '/kardex-telas/descargue-orden' },
];

const searchQuery = ref('');

const programmers = ref<Programmer[]>([]);
const successMessage = ref('');
const errorMessage = ref('');
const isLoadingProgrammers = ref(false);

const normalizedValue = (value?: string | null) =>
  (value ?? '')
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();

const filteredProgrammers = computed(() => {
  const query = normalizedValue(searchQuery.value);
  return programmers.value.filter((programmer) => {
    const fieldsToSearch = [
      String(programmer.id),
      programmer.product_sheet?.reference,
      programmer.season,
      programmer.product_sheet?.section?.name,
      programmer.product_sheet?.group?.name,
      programmer.priority,
    ];
    const matchesSearch =
      !query || fieldsToSearch.some((field) => normalizedValue(field).includes(query));
    return matchesSearch;
  });
});

const getLotLabel = (programmer: Programmer) => `MF${programmer.id}`;

const getReferenceLabel = (programmer: Programmer) =>
  programmer.product_sheet?.reference ?? '-';

const getSeasonLabel = (programmer: Programmer) => programmer.season ?? '-';

const getSectionLabel = (programmer: Programmer) =>
  programmer.product_sheet?.section?.name ?? '-';

const getGroupLabel = (programmer: Programmer) =>
  programmer.product_sheet?.group?.name ?? '-';

const getPriorityLabel = (programmer: Programmer) => programmer.priority ?? '-';

const getScheduledUnitsLabel = (programmer: Programmer) =>
  programmer.units ?? '-';

function openDownloadWindow(programmer: Programmer) {
  if (!programmer?.id) return;
  const url = route('kardex-telas.descargue-orden.descarga', { programmer: programmer.id });
  Inertia.visit(url);
}

async function fetchProgrammers() {
  isLoadingProgrammers.value = true;
  errorMessage.value = '';
  try {
    const response = await axios.get('/comercial/programmers', {
      params: {
        per_page: 1000,
      },
    });
    const payload = response.data?.data ?? response.data;
    programmers.value = Array.isArray(payload) ? (payload as Programmer[]) : [];
  } catch {
    programmers.value = [];
    errorMessage.value = 'No se pudo cargar el programador.';
  } finally {
    isLoadingProgrammers.value = false;
  }
}

onMounted(fetchProgrammers);
</script>

<template>
  <Head title="Descargue de tela por orden" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
      <AlertStack
        :success-message="successMessage"
        :error-message="errorMessage"
        success-title="Exito"
        error-title="Error"
        @close-success="successMessage = ''"
        @close-error="errorMessage = ''"
      />

      <div class="flex items-center justify-between">
        <div class="flex flex-col gap-1">
          <h1 class="text-lg font-bold">Descargue de tela por orden</h1>
          <p class="text-sm text-muted-foreground">
            Registra el descargue de tela por orden de produccion, controlando el metraje utilizado y
            actualizando automaticamente el inventario con trazabilidad por rollo.
          </p>
        </div>
      </div>

      <FilterBar>
        <FilterField
          label="Buscar lote"
          :active="searchQuery.trim().length > 0"
          class="flex-1 min-w-[220px] p-0"
        >
          <div class="relative">
            <Input
              v-model="searchQuery"
              placeholder="Buscar lote"
              class="h-10 w-full rounded-md border border-gray-200 bg-white pl-10 text-sm focus:border-primary focus:ring-0"
            />
            <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
              <Search class="text-muted-foreground" />
            </span>
          </div>
        </FilterField>
      </FilterBar>

      <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 bg-white md:min-h-min">
        <div class="overflow-x-auto">
          <Table class="min-w-full text-[0.9rem]">
            <TableHeader>
              <TableRow>
                <TableHead>Lote</TableHead>
                <TableHead>Referencia</TableHead>
                <TableHead>Temporada</TableHead>
                <TableHead>Seccion</TableHead>
                <TableHead>Grupo</TableHead>
                <TableHead>Prioridad</TableHead>
                <TableHead>Unidades programadas</TableHead>
                <TableHead>Acciones</TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              <TableRow v-for="programmer in filteredProgrammers" :key="programmer.id" class="border-b">
                <TableCell class="text-center text-sm">{{ getLotLabel(programmer) }}</TableCell>

                <TableCell class="text-center text-sm">
                  {{ getReferenceLabel(programmer) }}
                </TableCell>

                <TableCell class="text-center text-sm">{{ getSeasonLabel(programmer) }}</TableCell>
                <TableCell class="text-center text-sm">{{ getSectionLabel(programmer) }}</TableCell>
                <TableCell class="text-center text-sm">{{ getGroupLabel(programmer) }}</TableCell>
                <TableCell class="text-center text-sm">{{ getPriorityLabel(programmer) }}</TableCell>
                <TableCell class="text-center text-sm">{{ getScheduledUnitsLabel(programmer) }}</TableCell>

                <TableCell class="flex justify-center gap-1">
                  <button
                    class="inline-flex items-center justify-center text-gray-500 transition-colors hover:text-blue-600"
                    title="Descargar orden"
                    type="button"
                    @click="openDownloadWindow(programmer)"
                  >
                    <ArrowDownToLine class="h-4 w-4" />
                  </button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>

          <div v-if="isLoadingProgrammers" class="p-4 text-center text-sm text-muted-foreground">
            Cargando programadores...
          </div>

          <div
            v-else-if="!isLoadingProgrammers && filteredProgrammers.length === 0"
            class="p-4 text-center text-sm text-muted-foreground"
          >
            No hay programadores disponibles.
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>










