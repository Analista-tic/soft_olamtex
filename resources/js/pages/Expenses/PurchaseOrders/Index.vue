<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PurchaseOrder } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { PlusCircle, Printer, Search, Mail, ReceiptText } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import PurchaseOrderDialog from './PurchaseOrderDialog.vue';
import PurchaseOrderPrintDialog from './PurchaseOrderPrintDialog.vue';
import SelectField from '@/components/SelectField.vue';
import InputDate from '@/components/InputDate.vue';

const breadcrumbs = [
    { title: 'Gastos', href: '/gastos/ordenes-compra' },
    { title: 'Órdenes de Compra', href: '/gastos/ordenes-compra' },
];

const orders = ref<PurchaseOrder[]>([]);
const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const form = ref<Partial<PurchaseOrder>>({});
const orderToDelete = ref<PurchaseOrder | null>(null);
const isDeleteDialogOpen = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const isPrintDialogOpen = ref(false);
const orderToPrint = ref<PurchaseOrder | null>(null);
const isSendingMail = ref(false);
const isConverting = ref<number | null>(null);

// NUEVOS FILTROS
const statusFilter = ref('all');
const providerFilter = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const providers = ref<{ id: number; full_name: string }[]>([]);

async function fetchProviders() {
    // Consumir correctamente los contactos como proveedores
    const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
    providers.value = response.data.data;
}

function openDialog(type: 'create' | 'edit', order: PurchaseOrder | null = null) {
    dialogType.value = type;
    isDialogOpen.value = true;
    form.value = order ? { ...order } : {};
}

function closeDialog() {
    isDialogOpen.value = false;
}

function confirmDeleteOrder(order: PurchaseOrder) {
    orderToDelete.value = order;
    isDeleteDialogOpen.value = true;
}

async function deleteOrder() {
    if (!orderToDelete.value) return;
    await axios.delete(`/gastos/ordenes-compra/${orderToDelete.value.id}`);
    fetchOrders();
    isDeleteDialogOpen.value = false;
}

async function fetchOrders(page = 1, search = '') {
    const params: any = {
        page,
        per_page: itemsPerPage,
        search,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    };
    if (statusFilter.value !== 'all') params.status = statusFilter.value;
    if (providerFilter.value !== 'all') params.provider_id = providerFilter.value;
    const response = await axios.get('/gastos/ordenes-compra/data', { params });
    orders.value = response.data.data;
    totalItems.value = response.data.total;
    currentPage.value = response.data.current_page;
}

// Actualiza los filtros y la tabla correctamente
watch([statusFilter, providerFilter, dateFrom, dateTo], () => {
    // Reinicia la página a 1 cada vez que cambian los filtros
    currentPage.value = 1;
    fetchOrders(1, searchQuery.value);
});

watch(searchQuery, (newQuery) => {
    currentPage.value = 1;
    fetchOrders(1, newQuery);
});

onMounted(() => {
    fetchOrders();
    fetchProviders();
});

function openPrintDialog(order: PurchaseOrder) {
    orderToPrint.value = order;
    isPrintDialogOpen.value = true;
    setTimeout(() => {
        window.print();
    }, 400); // Espera breve para que el diálogo se renderice antes de imprimir
}

async function sendOrderByEmail(order: PurchaseOrder) {
    if (isSendingMail.value) return;
    isSendingMail.value = true;
    try {
        await axios.post(`/gastos/ordenes-compra/${order.id}/enviar-correo`);
        alert('Orden enviada por correo correctamente.');
    } catch (e) {
        console.log('e', e);
        alert('Error al enviar el correo.');
    } finally {
        isSendingMail.value = false;
    }
}

async function convertToInvoice(order: PurchaseOrder) {
    if (isConverting.value !== null) return;
    isConverting.value = order.id ?? null; // order.id nunca debería ser undefined, pero por seguridad
    try {
        await axios.post(`/gastos/ordenes-compra/${order.id}/convert-to-invoice`);
        alert('Orden convertida en factura correctamente.');
        fetchOrders(currentPage.value, searchQuery.value);
    } catch (e: any) {
        // Validación específica del mensaje de error
        if (e?.response?.data?.error_code === 'ORDER_ALREADY_INVOICED') {
            alert('Esta orden de compra ya tiene una factura asociada.');
        } else {
            alert(e?.response?.data?.message || 'Error al convertir en factura.');
        }
    } finally {
        isConverting.value = null;
    }
}
</script>

<template>
    <Head title="Órdenes de Compra" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Órdenes de Compra</h1>
                <Button @click="openDialog('create')" variant="default" size="sm"><PlusCircle class="h-4 w-4" /> Nueva Orden</Button>
            </div>
            <!-- FILTROS: UNA SOLA FILA -->
            <div class="flex items-end gap-4 mb-2 flex-wrap">
                <div class="relative max-w-xs flex-1 min-w-[220px]">
                    <Input v-model="searchQuery" placeholder="Buscar orden" class="h-8 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
                <!-- Estado: usando SelectField -->
                <div class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label="Estado"
                        :options="[
                            { id: 'all', name: 'Todos los estados' },
                            { id: 'pending', name: 'Pendiente' },
                            { id: 'invoiced', name: 'Facturada' },
                        ]"
                    />
                </div>
                <!-- Proveedor: usando SelectField -->
                <div class="min-w-[180px]">
                    <SelectField
                        v-model="providerFilter"
                        label="Proveedor"
                        :options="[{ id: 'all', name: 'Todos los proveedores' }, ...providers.map((prov: { id: number; full_name: string }) => ({ id: String(prov.id), name: prov.full_name }))]"
                    />
                </div>
                <div class="min-w-[140px]">
                    <InputDate v-model="dateFrom" label="Desde" placeholder="Desde" />
                </div>
                <div class="min-w-[140px]">
                    <InputDate v-model="dateTo" label="Hasta" placeholder="Hasta" />
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>#Orden</TableHead>
                            <TableHead>Proveedor</TableHead>
                            <TableHead>Fecha</TableHead>
                            <TableHead>Número Orden</TableHead>
                            <TableHead>Entrega</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="order in orders" :key="order.id" class="text-center">
                            <TableCell>{{ order.id }}</TableCell>
                            <TableCell>{{ order.contact?.full_name }}</TableCell>
                            <TableCell>{{ order.date }}</TableCell>
                            <TableCell>{{ order.supplier_order_number }}</TableCell>
                            <TableCell>{{ order.delivery_date }}</TableCell>
                            <TableCell>{{ order.description }}</TableCell>
                            <TableCell class="flex justify-center gap-1">
                                <EditButton :onClick="() => openDialog('edit', order)" title="Editar orden" />
                                <DeleteButton :onClick="() => confirmDeleteOrder(order)" title="Eliminar orden" />
                                <button
                                    :class="['flex items-center text-gray-500 transition-colors duration-200 hover:text-green-500']"
                                    @click="openPrintDialog(order)"
                                    title="Imprimir orden"
                                >
                                    <Printer class="h-4 w-4" />
                                </button>
                                <button
                                    :disabled="isSendingMail"
                                    :class="['flex items-center text-gray-500 transition-colors duration-200 hover:text-blue-600', isSendingMail && 'opacity-50 cursor-not-allowed']"
                                    @click="sendOrderByEmail(order)"
                                    title="Enviar orden por correo"
                                >
                                    <Mail class="h-4 w-4" />
                                </button>
                                <!-- Botón Convertir en Factura: solo si no está facturada -->
                                <button
                                    v-if="order.status !== 'converted'"
                                    :disabled="isConverting === order.id"
                                    :class="[
                                        'flex items-center text-gray-500 transition-colors duration-200 hover:text-green-600',
                                        isConverting === order.id && 'opacity-50 cursor-not-allowed'
                                    ]"
                                    @click="convertToInvoice(order)"
                                    title="Convertir esta orden en factura"
                                >
                                    <ReceiptText class="h-4 w-4"/>
                                </button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
        <PurchaseOrderDialog :isOpen="isDialogOpen" :dialogType="dialogType" :form="form" @close="closeDialog" @refresh="fetchOrders" />
        <PurchaseOrderPrintDialog :isOpen="isPrintDialogOpen" :order="orderToPrint" @close="isPrintDialogOpen = false" />
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirmar eliminación</DialogTitle>
                    <DialogDescription> ¿Estás seguro de que deseas eliminar esta orden? Esta acción no se puede deshacer. </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancelar</Button>
                    <Button variant="destructive" @click="deleteOrder">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
