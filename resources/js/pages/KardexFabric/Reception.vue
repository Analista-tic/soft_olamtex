<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PurchaseInvoice } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import axios from 'axios';
import { Eye, Search, Check, X, ArrowLeft, Package2 } from 'lucide-vue-next';
import { onMounted, ref, watch, computed } from 'vue';
import SelectField from '@/components/SelectField.vue';
import InputDate from '@/components/InputDate.vue';
import FabricRollDialog from './FabricRollDialog.vue';

interface PageProps extends InertiaPageProps {
    invoiceId?: string | number;
    [key: string]: any;
}

const props = usePage<PageProps>().props;

const breadcrumbs = [
    { title: 'Kardex Telas', href: '/kardex-telas' },
    { title: 'Recepción', href: '/kardex-telas/recepcion' },
];

const invoices = ref<PurchaseInvoice[]>([]);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const totalItems = ref(0);
const selectedInvoice = ref<PurchaseInvoice | null>(null);
const isLoadingSpecificInvoice = ref(false);
const isFabricRollDialogOpen = ref(false);
const selectedItem = ref<any>(null);

const statusFilter = ref('pending');
const providerFilter = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const providers = ref<{ id: number; full_name: string }[]>([]);

// Computed para determinar si hay un ID específico en la URL
const specificInvoiceId = computed(() => {
    return props.invoiceId ? Number(props.invoiceId) : null;
});

// Computed para mostrar/ocultar la tabla según el modo
const showInvoicesList = computed(() => {
    return !specificInvoiceId.value && !selectedInvoice.value;
});

// Computed para mostrar la vista de factura específica
const showInvoiceDetail = computed(() => {
    return selectedInvoice.value !== null;
});

async function fetchProviders() {
    try {
        const response = await axios.get('/contactos/data', { params: { per_page: 1000 } });
        providers.value = response.data.data || [];
    } catch (e: any) {
        console.log('Error al cargar proveedores:', e);
        providers.value = [];
    }
}

async function fetchSpecificInvoice(invoiceId: number) {
    isLoadingSpecificInvoice.value = true;
    try {
        console.log('Cargando factura ID:', invoiceId);
        const response = await axios.get(`/gastos/facturas-compra/${invoiceId}`);
        console.log('Respuesta de la factura:', response.data);
        
        if (response.data && response.data.data) {
            selectedInvoice.value = response.data.data;
            console.log('Items de la factura:', selectedInvoice.value?.items);
        } else {
            console.error('Estructura de respuesta inesperada:', response.data);
            alert('Error: La respuesta del servidor no tiene el formato esperado');
        }
    } catch (e: any) {
        console.error('Error al cargar factura específica:', e);
        if (e?.response) {
            console.error('Respuesta del error:', e.response.data);
            alert(`Error al cargar la factura: ${e.response.data?.message || 'Error desconocido'}`);
        } else {
            alert('Error al cargar la factura. Verifique que el ID sea válido.');
        }
    } finally {
        isLoadingSpecificInvoice.value = false;
    }
}

function viewInvoice(invoice: PurchaseInvoice) {
    selectedInvoice.value = invoice;
}

function goBackToList() {
    selectedInvoice.value = null;
    // Si vino por URL específica, navegar de vuelta
    if (specificInvoiceId.value) {
        window.location.href = '/kardex-telas/recepcion';
    }
}

async function approveReception(invoice: PurchaseInvoice) {
    try {
        await axios.patch(`/kardex-telas/recepcion/${invoice.id}/aprobar`);
        if (showInvoicesList.value) {
            await fetchInvoices(currentPage.value, searchQuery.value);
        } else {
            // Si estamos viendo una factura específica, recargar esa factura
            if (invoice.id && typeof invoice.id === 'number') {
                await fetchSpecificInvoice(invoice.id);
            }
        }
        alert('Recepción aprobada correctamente');
    } catch (e: any) {
        console.log('Error al aprobar recepción:', e);
        alert('Error al aprobar la recepción');
    }
}

async function rejectReception(invoice: PurchaseInvoice) {
    if (!confirm('¿Está seguro de rechazar esta recepción?')) return;
    try {
        await axios.patch(`/kardex-telas/recepcion/${invoice.id}/rechazar`);
        if (showInvoicesList.value) {
            await fetchInvoices(currentPage.value, searchQuery.value);
        } else {
            // Si estamos viendo una factura específica, recargar esa factura
            if (invoice.id && typeof invoice.id === 'number') {
                await fetchSpecificInvoice(invoice.id);
            }
        }
        alert('Recepción rechazada');
    } catch (e: any) {
        console.log('Error al rechazar recepción:', e);
        alert('Error al rechazar la recepción');
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
    } catch (e: any) {
        console.log('Error al cargar facturas:', e);
        invoices.value = [];
        totalItems.value = 0;
    }
}

function calculateInvoiceTotal(invoice: PurchaseInvoice): number {
    const subtotal = (invoice.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0);
    const iva = (invoice.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0);
    const retenciones = (invoice.withholdings || []).reduce(
        (acc, w) => acc + ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100),
        0
    );
    return subtotal + iva - retenciones;
}

function getStatusBadge(status: string) {
    switch (status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'approved':
            return 'bg-green-100 text-green-800';
        case 'rejected':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getStatusText(status: string) {
    switch (status) {
        case 'pending':
            return 'Pendiente';
        case 'approved':
            return 'Aprobado';
        case 'rejected':
            return 'Rechazado';
        default:
            return 'Desconocido';
    }
}

// Watchers y eventos solo para modo lista
watch(
    [statusFilter, providerFilter, dateFrom, dateTo, searchQuery],
    ([newStatus, newProvider, newFrom, newTo, newSearch], [oldStatus, oldProvider, oldFrom, oldTo, oldSearch]) => {
        if (!showInvoicesList.value) return; // No ejecutar watchers en modo específico
        
        if (
            newStatus !== oldStatus ||
            newProvider !== oldProvider ||
            newFrom !== oldFrom ||
            newTo !== oldTo ||
            newSearch !== oldSearch
        ) {
            if (currentPage.value !== 1) {
                currentPage.value = 1;
            } else {
                fetchInvoices(1, newSearch);
            }
        }
    }
);

watch(currentPage, (newPage, oldPage) => {
    if (!showInvoicesList.value) return; // No ejecutar watchers en modo específico
    
    if (newPage !== oldPage) {
        fetchInvoices(newPage, searchQuery.value);
    }
});

onMounted(async () => {
    await fetchProviders();
    
    if (specificInvoiceId.value) {
        // Modo específico: cargar solo la factura solicitada
        await fetchSpecificInvoice(specificInvoiceId.value);
    } else {
        // Modo lista: cargar todas las facturas
        await fetchInvoices();
    }
});

function openFabricRollDialog(item: any) {
    selectedItem.value = item;
    isFabricRollDialogOpen.value = true;
}

function closeFabricRollDialog() {
    isFabricRollDialogOpen.value = false;
    selectedItem.value = null;
}

function onFabricRollSuccess() {
    alert('Rollo registrado correctamente');
    closeFabricRollDialog();
    // Recargar la factura específica para mostrar los rollos actualizados
    if (selectedInvoice.value && selectedInvoice.value.id && typeof selectedInvoice.value.id === 'number') {
        fetchSpecificInvoice(selectedInvoice.value.id);
    }
}
</script>

<template>
    <Head :title="specificInvoiceId ? `Recepción - Factura #${specificInvoiceId}` : 'Recepción de Telas'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-col gap-4 rounded-xl p-3">
            <!-- Header principal -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <!-- Botón de volver solo cuando se está viendo una factura -->
                    <Button 
                        v-if="showInvoiceDetail" 
                        variant="ghost" 
                        size="sm" 
                        @click="goBackToList"
                        class="flex items-center gap-2"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Volver a la lista
                    </Button>
                    <h1 class="text-base font-bold">
                        {{ showInvoiceDetail ? `Recepción - Factura #${selectedInvoice?.id}` : 'Recepción de Telas' }}
                    </h1>
                </div>
                <div class="text-xs text-gray-600">
                    {{ showInvoiceDetail ? 'Revisión y aprobación de factura' : 'Gestión de recepciones de facturas de telas' }}
                </div>
            </div>

            <!-- Vista de lista de facturas -->
            <template v-if="showInvoicesList">
                <!-- Mostrar loading si se está cargando factura específica -->
                <div v-if="specificInvoiceId && isLoadingSpecificInvoice" class="text-center py-8">
                    <p class="text-gray-600">Cargando factura...</p>
                </div>

                <!-- Filtros -->
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
                
                <!-- Tabla de facturas -->
                <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min overflow-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>#Factura</TableHead>
                                <TableHead>Proveedor</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Total</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Artículos</TableHead>
                                <TableHead>Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="invoice in invoices" :key="invoice.id" class="text-center">
                                <TableCell class="font-medium">{{ invoice.id }}</TableCell>
                                <TableCell>{{ invoice.contact?.full_name }}</TableCell>
                                <TableCell>{{ invoice.date }}</TableCell>
                                <TableCell class="font-medium">
                                    {{
                                        calculateInvoiceTotal(invoice).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}
                                </TableCell>
                                <TableCell>
                                    <span 
                                        :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusBadge(invoice.status || 'pending')]"
                                    >
                                        {{ getStatusText(invoice.status || 'pending') }}
                                    </span>
                                </TableCell>
                                <TableCell>{{ (invoice.items || []).length }} artículo(s)</TableCell>
                                <TableCell class="flex justify-center gap-1">
                                    <button
                                        class="flex items-center text-gray-500 transition-colors duration-200 hover:text-blue-600"
                                        @click="viewInvoice(invoice)"
                                        title="Ver detalle"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </button>
                                    <template v-if="invoice.status === 'pending' || !invoice.status">
                                        <button
                                            class="flex items-center text-gray-500 transition-colors duration-200 hover:text-green-600"
                                            @click="approveReception(invoice)"
                                            title="Aprobar recepción"
                                        >
                                            <Check class="h-4 w-4" />
                                        </button>
                                        <button
                                            class="flex items-center text-gray-500 transition-colors duration-200 hover:text-red-600"
                                            @click="rejectReception(invoice)"
                                            title="Rechazar recepción"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </template>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </template>

            <!-- Vista detallada de factura (sin dialog) -->
            <template v-if="showInvoiceDetail && selectedInvoice">
                <div class="space-y-6">
                    <!-- Botones de acción en la parte superior -->
                    <div class="flex justify-end gap-3 p-4 bg-gray-50 rounded-lg">
                        <template v-if="selectedInvoice.status === 'pending' || !selectedInvoice.status">
                            <Button variant="destructive" @click="rejectReception(selectedInvoice)">
                                Rechazar Recepción
                            </Button>
                            <Button variant="default" @click="approveReception(selectedInvoice)">
                                Aprobar Recepción
                            </Button>
                        </template>
                        <div v-else class="text-sm text-gray-600">
                            Estado: <span :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusBadge(selectedInvoice.status)]">
                                {{ getStatusText(selectedInvoice.status) }}
                            </span>
                        </div>
                    </div>

                    <!-- Información general -->
                    <div class="grid grid-cols-2 gap-4 p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Fecha de factura</p>
                            <p class="text-sm">{{ selectedInvoice.date }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Fecha de entrega</p>
                            <p class="text-sm">{{ selectedInvoice.delivery_date }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700"># Factura proveedor</p>
                            <p class="text-sm">{{ selectedInvoice.supplier_invoice_number || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Estado</p>
                            <span 
                                :class="['px-2 py-1 rounded-full text-sm font-medium', getStatusBadge(selectedInvoice.status || 'pending')]"
                            >
                                {{ getStatusText(selectedInvoice.status || 'pending') }}
                            </span>
                        </div>
                    </div>

                    <!-- Header de empresa -->
                    <div class="grid grid-cols-3 items-start gap-4 p-3 bg-white border rounded-lg">
                        <div>
                            <div class="text-sm font-semibold">Factura #{{ selectedInvoice.id }}</div>
                            <p class="text-sm text-gray-600">{{ selectedInvoice.supplier_invoice_number || 'Sin número de proveedor' }}</p>
                        </div>
                        <div class="relative flex h-full items-center justify-center rounded p-2">
                            <img src="/images/olamtex.png" alt="Logo Olamtex" class="max-h-8 object-contain" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold">Datos empresa</div>
                            <p class="text-sm">Razón Social: <span class="font-normal">OLAMTEX S.A.S.</span></p>
                            <p class="text-sm">NIT: <span class="font-normal">900123456-7</span></p>
                            <p class="text-sm">Correo: <span class="font-normal">info@olamtex.com</span></p>
                        </div>
                    </div>

                    <!-- Información del proveedor -->
                    <div class="grid grid-cols-3 gap-4 p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Proveedor</p>
                            <p class="text-sm">{{ selectedInvoice.contact?.full_name || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Número de identificación</p>
                            <p class="text-sm">{{ selectedInvoice.contact?.id_number || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Número de contacto</p>
                            <p class="text-sm">{{ selectedInvoice.contact?.phone || 'N/A' }}</p>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div v-if="selectedInvoice.description" class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-700 mb-1">Observaciones</p>
                        <p class="text-sm">{{ selectedInvoice.description }}</p>
                    </div>

                    <!-- Artículos -->
                    <div>
                        <h3 class="text-sm font-medium mb-2">Artículos de la Factura</h3>
                        <div class="border rounded-lg overflow-hidden">
                            <Table>
                                <TableHeader>
                                    <TableRow class="text-sm">
                                        <TableHead class="text-sm py-2">Artículo</TableHead>
                                        <TableHead class="text-sm py-2">Código</TableHead>
                                        <TableHead class="text-sm py-2">Descripción</TableHead>
                                        <TableHead class="text-sm py-2">Código Proveedor</TableHead>
                                        <TableHead class="text-sm py-2">Color Proveedor</TableHead>
                                        <TableHead class="text-sm py-2">Ancho</TableHead>
                                        <TableHead class="text-sm py-2">Cantidad</TableHead>
                                        <TableHead class="text-sm py-2">Precio</TableHead>
                                        <TableHead class="text-sm py-2">IVA</TableHead>
                                        <TableHead class="text-sm py-2">Total</TableHead>
                                        <TableHead class="text-sm py-2">Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="item in selectedInvoice.items" :key="item.id" class="text-sm">
                                        <TableCell class="py-2">{{ item.article?.name || 'N/A' }}</TableCell>
                                        <TableCell class="py-2">{{ item.article?.code || 'N/A' }}</TableCell>
                                        <TableCell class="py-2">{{ item.description || '-' }}</TableCell>
                                        <TableCell class="py-2">{{ item.supplier_code || '-' }}</TableCell>
                                        <TableCell class="py-2">{{ item.supplier_color || '-' }}</TableCell>
                                        <TableCell class="py-2">{{ item.order_width ? `${item.order_width} cm` : '-' }}</TableCell>
                                        <TableCell class="py-2">{{ item.quantity }}</TableCell>
                                        <TableCell class="py-2">
                                            {{
                                                Number(item.price || 0).toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                })
                                            }}
                                        </TableCell>
                                        <TableCell class="py-2">
                                            {{
                                                Number(item.total_tax || 0).toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                })
                                            }}
                                        </TableCell>
                                        <TableCell class="py-2">
                                            {{
                                                Number(item.total || 0).toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0,
                                                })
                                            }}
                                        </TableCell>
                                        <TableCell class="py-2">
                                            <button
                                                class="flex items-center text-gray-500 transition-colors duration-200 hover:text-blue-600"
                                                @click="openFabricRollDialog(item)"
                                                title="Registrar rollo"
                                            >
                                                <Package2 class="h-4 w-4" />
                                            </button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <!-- Retenciones y Totales detallados -->
                    <div class="grid grid-cols-2 gap-6 items-start pt-4">
                        <!-- Retenciones a la izquierda -->
                        <div v-if="selectedInvoice.withholdings && selectedInvoice.withholdings.length > 0">
                            <h3 class="text-sm font-medium mb-2">Retenciones</h3>
                            <div class="border rounded-lg overflow-hidden">
                                <Table>
                                    <TableHeader>
                                        <TableRow class="text-sm">
                                            <TableHead class="py-2 px-3 text-sm">Retención</TableHead>
                                            <TableHead class="py-2 px-3 text-sm">Base</TableHead>
                                            <TableHead class="py-2 px-3 text-sm">%</TableHead>
                                            <TableHead class="py-2 px-3 text-sm">Valor</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(withholding, index) in selectedInvoice.withholdings" :key="index" class="text-sm">
                                            <TableCell class="py-2 px-3">{{ withholding.name || 'N/A' }}</TableCell>
                                            <TableCell class="py-2 px-3">
                                                {{
                                                    Number(withholding.base_amount || 0).toLocaleString('es-CO', {
                                                        style: 'currency',
                                                        currency: 'COP',
                                                        minimumFractionDigits: 0,
                                                        maximumFractionDigits: 0,
                                                    })
                                                }}
                                            </TableCell>
                                            <TableCell class="py-2 px-3">{{ withholding.percentage || 0 }}%</TableCell>
                                            <TableCell class="py-2 px-3">
                                                {{
                                                    ((Number(withholding.base_amount) || 0) * (Number(withholding.percentage) || 0) / 100).toLocaleString('es-CO', {
                                                        style: 'currency',
                                                        currency: 'COP',
                                                        minimumFractionDigits: 0,
                                                        maximumFractionDigits: 0,
                                                    })
                                                }}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                        <div v-else>
                            <!-- Espacio vacío cuando no hay retenciones -->
                        </div>

                        <!-- Totales detallados a la derecha -->
                        <div class="flex justify-end">
                            <div class="w-full max-w-xs space-y-1 p-4 bg-gray-50 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <span class="font-medium text-gray-700 text-sm">Subtotal</span>
                                    <span class="text-sm">{{
                                        ((selectedInvoice.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0)).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="font-medium text-gray-700 text-sm">IVA 19%</span>
                                    <span class="text-sm">{{
                                        ((selectedInvoice.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0)).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                                <div class="flex justify-between items-center pt-1 border-t">
                                    <span class="font-semibold text-gray-800 text-sm">Total factura</span>
                                    <span class="font-semibold text-gray-800 text-sm">{{
                                        (((selectedInvoice.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0)) + 
                                         ((selectedInvoice.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0))).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                                <!-- Mostrar cada retención individual -->
                                <template v-for="(w, idx) in selectedInvoice.withholdings || []" :key="idx">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">- {{ w.name }}</span>
                                        <span class="text-red-700">-{{
                                            ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100).toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                        }}</span>
                                    </div>
                                </template>
                                <div v-if="(selectedInvoice.withholdings || []).length" class="flex justify-between items-center pt-1 border-t text-sm">
                                    <span class="font-medium text-gray-700">Total retenciones</span>
                                    <span class="text-red-700">-{{
                                        ((selectedInvoice.withholdings || []).reduce(
                                            (acc, w) => acc + ((Number(w.base_amount) || 0) * (Number(w.percentage) || 0) / 100),
                                            0
                                        )).toLocaleString('es-CO', {
                                            style: 'currency',
                                            currency: 'COP',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        })
                                    }}</span>
                                </div>
                                <div class="flex justify-between items-center pt-2 mt-1 border-t-2 border-gray-300">
                                    <span class="font-bold text-base text-green-700">Total a pagar</span>
                                    <span class="font-bold text-base text-green-700">
                                        {{
                                            calculateInvoiceTotal(selectedInvoice).toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción en la parte inferior -->
                    <div class="flex justify-end gap-3 p-4 bg-gray-50 rounded-lg">
                        <template v-if="selectedInvoice.status === 'pending' || !selectedInvoice.status">
                            <Button variant="destructive" @click="rejectReception(selectedInvoice)">
                                Rechazar Recepción
                            </Button>
                            <Button variant="default" @click="approveReception(selectedInvoice)">
                                Aprobar Recepción
                            </Button>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Diálogo para registrar rollos de tela -->
            <FabricRollDialog 
                :isOpen="isFabricRollDialogOpen" 
                :item="selectedItem" 
                :apiUrl="'/kardex-telas/rollos'"
                @close="closeFabricRollDialog"
                @success="onFabricRollSuccess"
            />
        </div>
    </AppLayout>
</template>
