<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import EditButton from '@/components/common/EditButton.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PurchaseInvoice } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { MoreVertical, PlusCircle, Printer, Search, Mail, Package, Eye } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import PurchaseInvoiceDialog from './PurchaseInvoiceDialog.vue';
import PurchaseInvoicePrintDialog from './PurchaseInvoicePrintDialog.vue';
import SelectField from '@/components/SelectField.vue';
import InputDate from '@/components/InputDate.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const breadcrumbs = [
    { title: 'Gastos', href: '/gastos/facturas-compra' },
    { title: 'Facturas de Compra', href: '/gastos/facturas-compra' },
];

const invoices = ref<PurchaseInvoice[]>([]);
const isDialogOpen = ref(false);
const dialogType = ref<'create' | 'edit'>('create');
const isDialogReadOnly = ref(false);
const form = ref<Partial<PurchaseInvoice>>({});
const invoiceToDelete = ref<PurchaseInvoice | null>(null);
const isDeleteDialogOpen = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const isPrintDialogOpen = ref(false);
const invoiceToPrint = ref<PurchaseInvoice | null>(null);
const isSendingMail = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const statusFilter = ref('all');
const providerFilter = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const providers = ref<{ id: number; full_name: string }[]>([]);

async function fetchProviders() {
    try {
        const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
        providers.value = response.data.data;
    } catch (e) {
        console.log('Error al cargar proveedores:', e);
        providers.value = [];
    }
}

function openDialog(type: 'create' | 'edit', invoice: PurchaseInvoice | null = null, readOnly = false) {
    dialogType.value = type;
    isDialogReadOnly.value = readOnly;
    isDialogOpen.value = true;
    // Asegura el tipado correcto y evita referencias reactivas no deseadas
    form.value = invoice ? JSON.parse(JSON.stringify(invoice)) as Partial<PurchaseInvoice> : {};
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
}

function closeDialog() {
    isDialogReadOnly.value = false;
    isDialogOpen.value = false;
}

function confirmDeleteInvoice(invoice: PurchaseInvoice) {
    invoiceToDelete.value = invoice;
    isDeleteDialogOpen.value = true;
}

const warningMatcher = (msg: string) => /obligatori|complet[ao]s? los campos/i.test(msg);
const setErrorMessage = (msg: string) => {
    errorType.value = warningMatcher(msg) ? 'warning' : 'error';
    errorMessage.value = msg;
};

async function deleteInvoice(): Promise<void> {
    if (!invoiceToDelete.value) return;
    try {
        await axios.delete(`/gastos/facturas-compra/${invoiceToDelete.value.id}`);
        successMessage.value = 'Factura eliminada correctamente.';
        await fetchInvoices(currentPage.value, searchQuery.value);
    } catch (e) {
        console.log('Error al eliminar factura:', e);
        setErrorMessage('Error al eliminar la factura.');
    } finally {
        isDeleteDialogOpen.value = false;
    }
}


async function fetchInvoices(page = 1, search = ''): Promise<void> {
    const params: Record<string, any> = {
        page,
        per_page: itemsPerPage,
        search,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    };
    if (statusFilter.value !== 'all') params.status = statusFilter.value;
    if (providerFilter.value !== 'all' && providerFilter.value !== '' && providerFilter.value !== null) {
        params.provider_id = isNaN(Number(providerFilter.value)) ? providerFilter.value : Number(providerFilter.value);
    }
    try {
        const response = await axios.get('/gastos/facturas-compra/data', { params });
        invoices.value = Array.isArray(response.data.data)
            ? response.data.data
            : Array.isArray(response.data)
                ? response.data
                : [];
        totalItems.value = typeof response.data.total === 'number' ? response.data.total : 0;
    } catch (e) {
        console.log('Error al cargar facturas:', e);
        invoices.value = [];
        totalItems.value = 0;
    }
}

// Watcher único para filtros y búsqueda
watch(
    [statusFilter, providerFilter, dateFrom, dateTo, searchQuery],
    ([newStatus, newProvider, newFrom, newTo, newSearch], [oldStatus, oldProvider, oldFrom, oldTo, oldSearch]) => {
        if (
            newStatus !== oldStatus ||
            newProvider !== oldProvider ||
            newFrom !== oldFrom ||
            newTo !== oldTo ||
            newSearch !== oldSearch
        ) {
            // Solo cambia la página, el watcher de currentPage hará el fetch
            if (currentPage.value !== 1) {
                currentPage.value = 1;
            } else {
                fetchInvoices(1, newSearch);
            }
        }
    }
);

// Watcher para paginación
watch(currentPage, (newPage, oldPage) => {
    if (newPage !== oldPage) {
        fetchInvoices(newPage, searchQuery.value);
    }
});

onMounted(() => {
    fetchInvoices();
    fetchProviders();
});

function openPrintDialog(invoice: PurchaseInvoice) {
    invoiceToPrint.value = invoice;
    isPrintDialogOpen.value = true;
    setTimeout(() => {
        window.print();
    }, 400);
}

async function sendInvoiceByEmail(invoice: PurchaseInvoice) {
    if (isSendingMail.value) return;
    isSendingMail.value = true;
    try {
        await axios.post(`/gastos/facturas-compra/${invoice.id}/enviar-correo`);
        successMessage.value = 'Factura enviada por correo correctamente.';
    } catch {
        setErrorMessage('Error al enviar el correo.');
    } finally {
        isSendingMail.value = false;
    }
}

function goToReception(invoice: PurchaseInvoice) {
    // Navegar a la página de recepción con el ID de la factura
    router.visit(`/kardex-telas/recepcion/${invoice.id}`);
}

function viewInvoiceDetails(invoice: PurchaseInvoice) {
    // Abrir el dialog de edición en modo solo lectura
    openDialog('edit', invoice, true);
}

function handleDialogRefresh(message?: string) {
    if (message) {
        successMessage.value = message;
        errorMessage.value = '';
        errorType.value = 'error';
    }
    fetchInvoices(currentPage.value, searchQuery.value);
    isDialogOpen.value = false;
}

function handleDialogError(message?: string) {
    setErrorMessage(message || 'Error al guardar la factura.');
    successMessage.value = '';
}
</script>

<template>
    <Head title="Facturas de Compra" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            @close-success="successMessage = ''"
            @close-error="errorMessage = ''"
        />
        <div class="flex h-full flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Facturas de Compra</h1>
                <div class="flex gap-2">
                    <Button 
                        @click="router.visit('/kardex-telas/recepcion')" 
                        variant="outline" 
                        size="sm"
                    >
                        <Package class="h-4 w-4" /> Ver Todas las Recepciones
                    </Button>
                    <Button @click="openDialog('create')" variant="default" size="sm">
                        <PlusCircle class="h-4 w-4" /> Nueva Factura
                    </Button>
                </div>
            </div>
            <div class="flex items-end gap-4 mb-2 flex-wrap">
                <div class="relative max-w-xs flex-1 min-w-[220px]">
                    <Input v-model="searchQuery" placeholder="Buscar factura" class="h-8 w-full pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search :size="20" class="text-muted-foreground" />
                    </span>
                </div>
                <div class="min-w-[180px]">
                    <SelectField
                        v-model="statusFilter"
                        label="Estado"
                        :options="[
                            { id: 'all', name: 'Todos los estados' },
                            { id: 'pending', name: 'Pendiente' },
                            { id: 'approved', name: 'Aprobado' },
                            { id: 'rejected', name: 'Rechazado' }
                        ]"
                    />
                </div>
                <div class="min-w-[180px]">
                    <SelectField
                        v-model="providerFilter"
                        label="Proveedor"
                        :options="[{ id: 'all', name: 'Todos los proveedores' }, ...providers.map(prov => ({ id: String(prov.id), name: prov.full_name }))]"
                    />
                </div>
                <div class="min-w-[140px]">
                    <InputDate v-model="dateFrom" label="Desde" />
                </div>
                <div class="min-w-[140px]">
                    <InputDate v-model="dateTo" label="Hasta" />
                </div>
            </div>
            <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min overflow-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>#Factura</TableHead>
                            <TableHead>#Orden</TableHead>
                            <TableHead>Proveedor</TableHead>
                            <TableHead>Fecha</TableHead>
                            <TableHead>Número Factura</TableHead>
                            <TableHead>Entrega</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="invoice in invoices" :key="invoice.id" class="text-center">
                            <TableCell class="font-medium">{{ invoice.id }}</TableCell>
                            <TableCell>{{ invoice.purchase_order_id || '-' }}</TableCell>
                            <TableCell>{{ invoice.contact?.full_name }}</TableCell>
                            <TableCell>{{ invoice.date }}</TableCell>
                            <TableCell>{{ invoice.supplier_invoice_number || '-' }}</TableCell>
                            <TableCell>{{ invoice.delivery_date }}</TableCell>
                            <TableCell>
                                <span 
                                    v-if="invoice.status"
                                    :class="[
                                        'px-2 py-1 rounded-full text-xs font-medium',
                                        invoice.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                        invoice.status === 'approved' ? 'bg-green-100 text-green-800' :
                                        invoice.status === 'rejected' ? 'bg-red-100 text-red-800' :
                                        'bg-gray-100 text-gray-800'
                                    ]"
                                >
                                    {{ 
                                        invoice.status === 'pending' ? 'Pendiente' :
                                        invoice.status === 'approved' ? 'Aprobado' :
                                        invoice.status === 'rejected' ? 'Rechazado' :
                                        'Sin estado'
                                    }}
                                </span>
                                <span v-else class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    Pendiente
                                </span>
                            </TableCell>
                            <TableCell class="max-w-32 truncate">{{ invoice.description || '-' }}</TableCell>
                            <TableCell>
                                <div class="flex justify-center gap-1 flex-wrap">
                                    <button
                                        class="flex items-center text-gray-500 transition-colors duration-200 hover:text-blue-600"
                                        @click="viewInvoiceDetails(invoice)"
                                        title="Ver detalles"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </button>
                                    <EditButton :onClick="() => openDialog('edit', invoice)" />
                                    <DeleteButton :onClick="() => confirmDeleteInvoice(invoice)" />
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 p-0">
                                                <MoreVertical class="h-4 w-4" />
                                                <span class="sr-only">Acciones</span>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-48">
                                            <DropdownMenuItem class="cursor-pointer gap-2" @click="openPrintDialog(invoice)">
                                                <Printer class="h-4 w-4" /> Imprimir
                                            </DropdownMenuItem>
                                            <DropdownMenuItem class="cursor-pointer gap-2" @click="goToReception(invoice)">
                                                <Package class="h-4 w-4" /> Ir a recepción
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="cursor-pointer gap-2"
                                                :class="isSendingMail ? 'opacity-60 cursor-not-allowed' : ''"
                                                :disabled="isSendingMail"
                                                @click="sendInvoiceByEmail(invoice)"
                                            >
                                                <Mail class="h-4 w-4" /> Enviar al correo
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
        <PurchaseInvoiceDialog
            v-if="isDialogOpen"
            :isOpen="isDialogOpen"
            :dialogType="dialogType"
            :form="form"
            :readOnly="isDialogReadOnly"
            @close="closeDialog"
            @refresh="handleDialogRefresh"
            @error="handleDialogError"
        />
        <PurchaseInvoicePrintDialog
            v-if="isPrintDialogOpen"
            :isOpen="isPrintDialogOpen"
            :invoice="invoiceToPrint"
            @close="isPrintDialogOpen = false"
        />
        <Dialog v-if="isDeleteDialogOpen" v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirmar eliminación</DialogTitle>
                    <DialogDescription>
                        ¿Estás seguro de que deseas eliminar esta factura? Esta acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancelar</Button>
                    <Button variant="destructive" @click="deleteInvoice">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
