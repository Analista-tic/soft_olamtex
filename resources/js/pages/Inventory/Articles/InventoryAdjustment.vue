<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Input from '@/components/ui/input/Input.vue';
import { Button } from '@/components/ui/button';
import { PlusCircle, Search } from 'lucide-vue-next';
import axios from 'axios';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

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
  document_number: string | null;
  date: string;
  type: 'entrada' | 'salida';
  observation?: string | null;
  total: number | string;
};

const breadcrumbs = [
  { title: 'Inventario', href: '/inventario' },
  { title: 'Ajuste de Inventario', href: '/inventario/ajuste-inventario' },
];

const adjustments = ref<InventoryAdjustment[]>([]);
const totalItems = ref(0);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const isAdjustmentFormOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const adjustmentToEdit = ref<InventoryAdjustment | null>(null);
const adjustmentToDelete = ref<InventoryAdjustment | null>(null);
const isDeleteDialogOpen = ref(false);

async function fetchAdjustments(page = 1, search = '') {
  const { data } = await axios.get('/ajustes-inventario/data', {
    params: { page, per_page: itemsPerPage, search },
  });

  adjustments.value = data.data ?? [];
  totalItems.value = data.total ?? 0;
  currentPage.value = data.current_page ?? page;
}

function handlePageChange(newPage: number) {
  currentPage.value = newPage;
  fetchAdjustments(newPage, searchQuery.value);
}

function handleSearch() {
  currentPage.value = 1;
  fetchAdjustments(1, searchQuery.value);
}

function openCreateAdjustment() {
  dialogType.value = 'create';
  adjustmentToEdit.value = null;
  isAdjustmentFormOpen.value = true;
}

function closeAdjustmentForm() {
  isAdjustmentFormOpen.value = false;
}

function openEditAdjustment(adjustment: InventoryAdjustment) {
  dialogType.value = 'edit';
  adjustmentToEdit.value = adjustment;
  isAdjustmentFormOpen.value = true;
}

function handleAdjustmentSaved() {
  isAdjustmentFormOpen.value = false;
  fetchAdjustments(currentPage.value, searchQuery.value);
}

function confirmDelete(adjustment: InventoryAdjustment) {
  adjustmentToDelete.value = adjustment;
  isDeleteDialogOpen.value = true;
}

async function deleteAdjustment() {
  if (!adjustmentToDelete.value) return;
  await axios.delete(`/ajustes-inventario/${adjustmentToDelete.value.id}`);
  isDeleteDialogOpen.value = false;
  fetchAdjustments(currentPage.value, searchQuery.value);
}

onMounted(() => {
  fetchAdjustments();
});
</script>

<template>
  <Head title="Ajuste de Inventario" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
      <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold">Ajuste de Inventario</h1>

        <Button size="sm" class="flex items-center gap-1" @click="openCreateAdjustment">
          <PlusCircle class="h-4 w-4" /> Crear ajuste
        </Button>
      </div>

      <div class="relative flex items-center gap-2">
        <div class="relative w-full max-w-sm">
          <Input
            id="search_adjustment"
            v-model="searchQuery"
            placeholder="Buscar por numero de documento u observacion"
            class="h-8 w-full"
            @keyup.enter="handleSearch"
          />
        </div>
        <Button size="sm" variant="outline" class="h-8 px-3" @click="handleSearch">
          <Search class="h-4 w-4" />
          Buscar
        </Button>
      </div>

      <div
        class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min"
      >
        <Table class="text-center">
          <TableHeader>
            <TableRow>
              <TableHead># Documento</TableHead>
              <TableHead>Fecha</TableHead>
              <TableHead>Tipo</TableHead>
              <TableHead>Observacion</TableHead>
              <TableHead>Total</TableHead>
              <TableHead class="w-[140px]">Acciones</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="adjustment in adjustments" :key="adjustment.id">
              <TableCell>{{ adjustment.document_number ?? 'Sin numero' }}</TableCell>
              <TableCell>{{ adjustment.date }}</TableCell>
              <TableCell class="capitalize">{{ adjustment.type }}</TableCell>
              <TableCell>{{ adjustment.observation || '-' }}</TableCell>
              <TableCell>{{ Number(adjustment.total ?? 0).toFixed(2) }}</TableCell>
              <TableCell class="flex justify-center gap-1">
                <EditButton :onClick="() => openEditAdjustment(adjustment)" title="Editar ajuste" />
                <DeleteButton :onClick="() => confirmDelete(adjustment)" title="Eliminar ajuste" />
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

    <InventoryAdjustmentForm
      :is-open="isAdjustmentFormOpen"
      :dialog-type="dialogType"
      :adjustment="adjustmentToEdit"
      @close="closeAdjustmentForm"
      @saved="handleAdjustmentSaved"
    />

    <Dialog v-model:open="isDeleteDialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Confirmar eliminacion</DialogTitle>
          <DialogDescription>
            Estas seguro de eliminar este ajuste? Esta accion no se puede deshacer.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="isDeleteDialogOpen = false">Cancelar</Button>
          <Button variant="destructive" @click="deleteAdjustment">Eliminar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
