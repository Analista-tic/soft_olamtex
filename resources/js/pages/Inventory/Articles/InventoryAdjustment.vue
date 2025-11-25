<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import Input from '@/components/ui/input/Input.vue';
import { Button } from '@/components/ui/button';
import { PlusCircle, Search, MoreVertical, Printer, Mail, ReceiptText } from 'lucide-vue-next';
import axios from 'axios';
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AlertStack from '@/components/common/AlertStack.vue';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import InventoryAdjustmentForm from './InventoryAdjustmentForm.vue';
import InventoryAdjustmentPrintDialog from './InventoryAdjustmentPrintDialog.vue';
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
  type: string;
  movement_reason?: string | null;
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
const successMessage = ref<string>('');
const errorMessage = ref<string>('');
const isSendingMail = ref(false);
const isConverting = ref<number | null>(null);
const isPrintDialogOpen = ref(false);
const adjustmentToPrint = ref<InventoryAdjustment | null>(null);

const adjustmentTypeOptions = [
  { id: 'entrada_sin_factura', name: 'Entrada de inventario sin factura', direction: 'entrada' },
  { id: 'en_bodega_para_despacho', name: 'En bodega para despacho', direction: 'entrada' },
  { id: 'salida_insumos_orden_produccion', name: 'Salida de insumos para orden de producción', direction: 'salida' },
  { id: 'salida_insumos_otras_areas', name: 'Salida insumos otras áreas', direction: 'salida' },
  { id: 'salida_papeleria', name: 'Salida papelería', direction: 'salida' },
  { id: 'salida_aseo', name: 'Salida aseo', direction: 'salida' },
  { id: 'salida_ajustar_inventario', name: 'Salida para ajustar inventario', direction: 'salida' },
  { id: 'devolucion_proveedor', name: 'Devolución proveedor', direction: 'salida' },
  // Compatibilidad con registros anteriores
  { id: 'entrada', name: 'Entrada', direction: 'entrada' },
  { id: 'salida', name: 'Salida', direction: 'salida' },
];

const adjustmentTypeMap = adjustmentTypeOptions.reduce<Record<string, string>>((acc, opt) => {
  acc[opt.id] = opt.name;
  return acc;
}, {});

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
  successMessage.value = dialogType.value === 'create'
    ? 'El ajuste se creó correctamente.'
    : 'El ajuste se actualizó correctamente.';
  errorMessage.value = '';
}

function confirmDelete(adjustment: InventoryAdjustment) {
  adjustmentToDelete.value = adjustment;
  isDeleteDialogOpen.value = true;
}

async function deleteAdjustment() {
  if (!adjustmentToDelete.value) return;
  try {
    await axios.delete(`/ajustes-inventario/${adjustmentToDelete.value.id}`);
    isDeleteDialogOpen.value = false;
    fetchAdjustments(currentPage.value, searchQuery.value);
    successMessage.value = 'El ajuste se eliminó correctamente.';
    errorMessage.value = '';
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'No se pudo eliminar el ajuste.';
    successMessage.value = '';
  }
}

async function openPrintDialog(adjustment: InventoryAdjustment) {
  try {
    const { data } = await axios.get(`/ajustes-inventario/${adjustment.id}`);
    adjustmentToPrint.value = data;
    isPrintDialogOpen.value = true;
    setTimeout(() => {
      window.print();
    }, 400);
  } catch (e: any) {
    errorMessage.value = e?.response?.data?.message || 'No se pudo cargar el ajuste para imprimir.';
    successMessage.value = '';
  }
}

async function sendByEmail(adjustment: InventoryAdjustment) {
  if (isSendingMail.value) return;
  isSendingMail.value = true;
  try {
    await axios.post(`/ajustes-inventario/${adjustment.id}/enviar-correo`);
    successMessage.value = 'Ajuste enviado por correo correctamente.';
    errorMessage.value = '';
  } catch (e: any) {
    errorMessage.value = e?.response?.data?.message || 'Error al enviar el correo.';
    successMessage.value = '';
  } finally {
    isSendingMail.value = false;
  }
}

async function convertToInvoice(adjustment: InventoryAdjustment) {
  if (isConverting.value !== null) return;
  isConverting.value = adjustment.id;
  try {
    await axios.post(`/ajustes-inventario/${adjustment.id}/convert-to-invoice`);
    successMessage.value = 'Ajuste convertido en factura correctamente.';
    errorMessage.value = '';
    fetchAdjustments(currentPage.value, searchQuery.value);
  } catch (e: any) {
    errorMessage.value = e?.response?.data?.message || 'Error al convertir en factura.';
    successMessage.value = '';
  } finally {
    isConverting.value = null;
  }
}

onMounted(() => {
  fetchAdjustments();
});
</script>

<template>
  <Head title="Ajuste de Inventario" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
      <AlertStack
        :success-message="successMessage"
        :error-message="errorMessage"
        success-title="Éxito"
        error-title="Error"
        @close-success="successMessage = ''"
        @close-error="errorMessage = ''"
      />

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
              <TableCell class="capitalize">
                {{ adjustmentTypeMap[adjustment.movement_reason as string] || adjustment.type }}
              </TableCell>
              <TableCell>{{ adjustment.observation || '-' }}</TableCell>
              <TableCell>{{ Number(adjustment.total ?? 0).toFixed(2) }}</TableCell>
              <TableCell class="flex justify-center gap-1">
                <EditButton :onClick="() => openEditAdjustment(adjustment)" title="Editar ajuste" />
                <DeleteButton :onClick="() => confirmDelete(adjustment)" title="Eliminar ajuste" />
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="h-8 w-8 p-0">
                      <MoreVertical class="h-4 w-4" />
                      <span class="sr-only">Acciones</span>
                    </Button>
                  </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                    <DropdownMenuItem class="cursor-pointer gap-2" @click="openPrintDialog(adjustment)">
                      <Printer class="h-4 w-4" /> Imprimir
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      class="cursor-pointer gap-2"
                      :class="isSendingMail ? 'opacity-60 cursor-not-allowed' : ''"
                      :disabled="isSendingMail"
                      @click="sendByEmail(adjustment)"
                    >
                      <Mail class="h-4 w-4" /> Enviar al correo
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      class="cursor-pointer gap-2"
                      :class="isConverting === adjustment.id ? 'opacity-60 cursor-not-allowed' : ''"
                      :disabled="isConverting === adjustment.id"
                      @click="convertToInvoice(adjustment)"
                    >
                      <ReceiptText class="h-4 w-4" /> Convertir en factura
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
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
    <InventoryAdjustmentPrintDialog
      :is-open="isPrintDialogOpen"
      :adjustment="adjustmentToPrint"
      @close="isPrintDialogOpen = false"
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
