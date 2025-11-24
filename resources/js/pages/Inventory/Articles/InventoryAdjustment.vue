<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Input from '@/components/ui/input/Input.vue';
import { Button } from '@/components/ui/button';
import { PlusCircle } from 'lucide-vue-next';

import InventoryAdjustmentForm from './InventoryAdjustmentForm.vue';

import {
  Pagination,
  PaginationEllipsis,
  PaginationFirst,
  PaginationLast,
  PaginationList,
  PaginationListItem,
  PaginationNext,
  PaginationPrev,
} from '@/components/ui/pagination';

type InventoryAdjustment = {
  id: number;
  document_number: string;
  date: string;
  type: 'entrada' | 'salida';
  observation?: string | null;
  total: number;
};

const breadcrumbs = [
  { title: 'Inventario', href: '/inventario' },
  { title: 'Ajuste de Inventario', href: '/inventario/ajuste-inventario' },
];

const adjustments = ref<InventoryAdjustment[]>([
  {
    id: 1,
    document_number: 'AJU-0001',
    date: '2025-11-21',
    type: 'entrada',
    observation: 'Ajuste inicial de inventario',
    total: 150000,
  },
  {
    id: 2,
    document_number: 'AJU-0002',
    date: '2025-11-22',
    type: 'salida',
    observation: 'Consumo interno',
    total: -25000,
  },
]);

const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(adjustments.value.length);

// estado del diálogo de formulario
const isAdjustmentFormOpen = ref(false);

function handlePageChange(newPage: number) {
  currentPage.value = newPage;
  // cuando conectes backend, aquí llamas a fetchAdjustments(newPage, searchQuery.value)
}

function openCreateAdjustment() {
  isAdjustmentFormOpen.value = true;
}

function closeAdjustmentForm() {
  isAdjustmentFormOpen.value = false;
}
</script>

<template>
  <Head title="Ajuste de Inventario" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
      <!-- Título y botón crear -->
      <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold">Ajuste de Inventario</h1>

        <Button size="sm" class="flex items-center gap-1" @click="openCreateAdjustment">
          <PlusCircle class="h-4 w-4" /> Crear ajuste
        </Button>
      </div>

      <!-- Buscador (por ahora solo visual) -->
      <div class="relative flex items-center gap-4">
        <div class="relative w-full max-w-sm">
          <Input
            id="search_adjustment"
            v-model="searchQuery"
            placeholder="Buscar por número de documento u observación"
            class="h-8 w-full"
          />
        </div>
      </div>

      <!-- Tabla -->
      <div
        class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min"
      >
        <Table class="text-center">
          <TableHeader>
            <TableRow>
              <TableHead># Documento</TableHead>
              <TableHead>Fecha</TableHead>
              <TableHead>Tipo</TableHead>
              <TableHead>Observación</TableHead>
              <TableHead>Total</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="adjustment in adjustments" :key="adjustment.id">
              <TableCell>{{ adjustment.document_number }}</TableCell>
              <TableCell>{{ adjustment.date }}</TableCell>
              <TableCell class="capitalize">
                {{ adjustment.type }}
              </TableCell>
              <TableCell>
                {{ adjustment.observation || '-' }}
              </TableCell>
              <TableCell>
                {{ adjustment.total.toFixed(2) }}
              </TableCell>
            </TableRow>

            <TableRow v-if="adjustments.length === 0">
              <TableCell :colspan="5" class="text-center text-gray-400">
                No hay ajustes de inventario registrados.
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>

        <Pagination
          class="mt-5 flex justify-center"
          :items-per-page="itemsPerPage"
          :total="totalItems"
          :sibling-count="1"
          show-edges
          v-model:current-page="currentPage"
          @page-changed="handlePageChange"
        >
          <PaginationList v-slot="{ items }" class="flex items-center gap-1">
            <PaginationFirst />
            <PaginationPrev />

            <template
              v-for="(item, index) in items"
              :key="item.type === 'page' ? `page-${item.value}` : `ellipsis-${index}`"
            >
              <!-- Caso páginas -->
              <PaginationListItem
                v-if="item.type === 'page'"
                :value="item.value"
                as-child
              >
                <Button
                  class="h-6 w-6 p-0"
                  :variant="item.value === currentPage ? 'default' : 'outline'"
                  @click="handlePageChange(item.value as number)"
                >
                  {{ item.value }}
                </Button>
              </PaginationListItem>
              <PaginationEllipsis v-else />
            </template>

            <PaginationNext />
            <PaginationLast />
          </PaginationList>

        </Pagination>

      </div>
    </div>

    <!-- Modal de nuevo ajuste -->
    <InventoryAdjustmentForm
      :is-open="isAdjustmentFormOpen"
      @close="closeAdjustmentForm"
    />
  </AppLayout>
</template>
