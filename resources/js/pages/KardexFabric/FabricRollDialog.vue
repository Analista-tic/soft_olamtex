<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import InputDate from '@/components/InputDate.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { PurchaseInvoiceItem } from '@/types';
import axios from 'axios';
import { ref, watch, computed, onMounted } from 'vue';
import AlertBox from '@/components/AlertBox.vue';

interface FabricRollForm {
    roll_number: string;
    purchase_document_item_id: number | null;
    quantity: number | string;
    roll_width: number | string;
    location: string;
    description: string;
    entry_date: string;
}

const props = defineProps<{
    isOpen: boolean;
    item: PurchaseInvoiceItem | null;
    apiUrl: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const dialogOpen = ref(props.isOpen);
const isLoading = ref(false);

// Alert state for the dialog
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('info');
const showAlert = ref(false);
const dialogInnerRef = ref<HTMLElement | null>(null);

function setAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
    alertMessage.value = message;
    alertType.value = type;
    showAlert.value = true;
    // scroll to top of dialog if possible
    if (dialogInnerRef.value && typeof dialogInnerRef.value.scrollTo === 'function') {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        const dialogEl = document.querySelector('[role="dialog"]');
        dialogEl?.scrollTo?.({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert() {
    alertMessage.value = '';
    showAlert.value = false;
}

const form = ref<FabricRollForm>({
    roll_number: '',
    purchase_document_item_id: null,
    quantity: '',
    roll_width: '',
    location: '',
    description: '',
    entry_date: new Date().toISOString().split('T')[0],
});

const itemInfo = computed(() => {
    if (!props.item) return null;
    return {
        articleName: props.item.item?.name || 'N/A',
        supplierCode: props.item.supplier_code || 'N/A',
        supplierColor: props.item.supplier_color || 'N/A',
        orderWidth: props.item.order_width || 'N/A',
        orderedQuantity: props.item.quantity || 0,
    };
});

// Calcular tolerancias y estados
const quantityTolerance = computed(() => {
    if (!props.item || !form.value.quantity) return null;
    const ordered = Number(props.item.quantity);
    const received = totalReceived.value + Number(form.value.quantity);
    const difference = Math.abs(ordered - received);
    const percentageDiff = (difference / ordered) * 100;

    return {
        ordered,
        received,
        difference,
        percentageDiff,
        isApproved: percentageDiff <= 30, // Tolerancia de 30%
    };
});

const widthTolerance = computed(() => {
    if (!props.item || !form.value.roll_width) return null;
    const ordered = Number(props.item.order_width) || 0;
    const received = Number(form.value.roll_width);
    const difference = Math.abs(ordered - received);
    
    return {
        ordered,
        received,
        difference,
        isApproved: difference <= 0.05, // Tolerancia de 0.05 cm
    };
});

const overallStatus = computed(() => {
    const qTol = quantityTolerance.value;
    const wTol = widthTolerance.value;
    
    if (!qTol && !wTol) return null;
    
    const quantityOk = !qTol || qTol.isApproved;
    const widthOk = !wTol || wTol.isApproved;
    
    return quantityOk && widthOk;
});

function resetForm() {
        form.value = {
            roll_number: '',
            purchase_document_item_id: props.item?.id || null,
        quantity: '',
        roll_width: props.item?.order_width?.toString() || '',
        location: '',
        description: '',
        entry_date: new Date().toISOString().split('T')[0],
    };
}

async function handleSubmit() {
    if (!validateForm()) return;
    
    isLoading.value = true;
    try {
        const payload = {
            ...form.value,
            quantity: Number(form.value.quantity),
            roll_width: Number(form.value.roll_width),
            purchase_document_item_id: props.item?.id,
        };

        await axios.post(props.apiUrl!, payload);
        emit('success');
        dialogOpen.value = false;
        resetForm();
    } catch (error: any) {
        console.error('Error al guardar rollo:', error);
        if (error.response?.data?.errors) {
            const errors = Object.values(error.response.data.errors).flat();
            setAlert(`Error: ${errors.join(', ')}`, 'error');
        } else {
            setAlert('Error al guardar el rollo. Por favor intente nuevamente.', 'error');
        }
    } finally {
        isLoading.value = false;
    }
}

function validateForm(): boolean {
    if (!form.value.roll_number.trim()) {
        setAlert('Por favor ingrese el número de rollo', 'warning');
        return false;
    }
    if (!form.value.quantity || Number(form.value.quantity) <= 0) {
        setAlert('Por favor ingrese una longitud válida', 'warning');
        return false;
    }
    if (!form.value.roll_width || Number(form.value.roll_width) <= 0) {
        setAlert('Por favor ingrese un ancho válido', 'warning');
        return false;
    }
    if (!form.value.entry_date) {
        setAlert('Por favor seleccione la fecha de entrada', 'warning');
        return false;
    }
    return true;
}

const activeTab = ref<'form' | 'list'>('form');
const rolls = ref<any[]>([]);
const rollsLoading = ref(false);

const totalReceived = computed(() => {
    return rolls.value.reduce((acc, roll) => acc + Number(roll.quantity || 0), 0);
});

async function fetchRolls() {
    if (!props.item?.id) {
        rolls.value = [];
        return;
    }
    rollsLoading.value = true;
    try {
        const res = await axios.get(`/kardex-telas/rollos/item/${props.item.id}`);
        rolls.value = Array.isArray(res.data) ? res.data : [];
    } catch (e: any) {
        console.log(e);
        rolls.value = [];
    } finally {
        rollsLoading.value = false;
    }
}

watch(
    () => props.isOpen,
    (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) {
            resetForm();
            fetchRolls();
        }
    }
);

watch(() => props.item?.id, (newId) => {
    if (props.isOpen && newId) fetchRolls();
});

watch(dialogOpen, (isOpen) => {
    if (!isOpen) emit('close');
});

onMounted(() => {
    if (props.isOpen && props.item?.id) fetchRolls();
});

// Formateo de fecha para la tabla
function formatDate(dateStr: string) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('es-ES');
}
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                <div ref="dialogInnerRef" class="relative">
                    <transition name="fade">
                        <div
                            v-if="showAlert && alertMessage"
                            class="pointer-events-none absolute left-1/2 top-4 z-30 w-full max-w-xl -translate-x-1/2"
                        >
                            <AlertBox
                                :type="alertType"
                                :title="alertType === 'warning' ? 'Advertencia' : alertType === 'success' ? 'Éxito' : alertType === 'info' ? 'Información' : 'Error'"
                                :message="alertMessage"
                                class="pointer-events-auto"
                                @close="clearAlert"
                            />
                        </div>
                    </transition>

                    <DialogHeader>
                        <DialogTitle>Registrar Rollo de Tela</DialogTitle>
                        <DialogDescription>
                            Relacione cada rollo al ítem de la orden.
                        </DialogDescription>
                    </DialogHeader>

            <!-- Tabs simplificados -->
            <div class="flex gap-2 mb-2">
                <button
                    :class="['px-3 py-1 rounded', activeTab === 'form' ? 'bg-blue-200 font-bold' : 'bg-gray-200']"
                    @click="activeTab = 'form'"
                >
                    Registrar
                </button>
                <button
                    :class="['px-3 py-1 rounded', activeTab === 'list' ? 'bg-blue-200 font-bold' : 'bg-gray-200']"
                    @click="activeTab = 'list'"
                >
                    Rollos ({{ rolls.length }})
                </button>
            </div>

            <div v-if="activeTab === 'form'">
                <!-- Info item pedido simplificada -->
                <div v-if="itemInfo" class="grid grid-cols-2 gap-2 p-2 bg-blue-50 rounded text-xs mb-3">
                    <div><b>Pedido:</b> {{ itemInfo.orderedQuantity }} mts</div>
                    <div><b>Ingresado:</b> {{ totalReceived }} mts</div>
                    <div><b>Ancho:</b> {{ itemInfo.orderWidth }} mts</div>
                    <div><b>Código prov:</b> {{ itemInfo.supplierCode }}</div>
                    <div><b>Color prov:</b> {{ itemInfo.supplierColor }}</div>
                </div>

                <form @submit.prevent="handleSubmit" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Cantidad y Ancho -->
                        <div>
                        <InputField
                            v-model="form.quantity"
                            label="Longitud (mts)"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                        />
                            <div v-if="quantityTolerance" class="text-xs mt-1" :class="quantityTolerance.isApproved ? 'text-green-600' : 'text-red-600'">
                                Dif: {{ quantityTolerance.difference.toFixed(2) }} mts ({{ quantityTolerance.percentageDiff.toFixed(2) }}%)
                            </div>
                        </div>
                        <div>
                            <InputField
                                v-model="form.roll_width"
                                label="Ancho (cm)"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                            />
                            <div v-if="widthTolerance" class="text-xs mt-1" :class="widthTolerance.isApproved ? 'text-green-600' : 'text-orange-600'">
                                Dif: {{ widthTolerance.difference.toFixed(2) }} cm
                            </div>
                        </div>
                        <!-- Número de rollo y fecha -->
                        <div>
                            <InputField
                                v-model="form.roll_number"
                                label="N° Rollo (RL)"
                                required
                            />
                        </div>
                        <div>
                            <InputDate
                                v-model="form.entry_date"
                                label="Fecha Entrada"
                                required
                            />
                        </div>
                        <!-- Ubicación y descripción -->
                        <div>
                            <InputField
                                v-model="form.location"
                                label="Ubicación"
                                placeholder="Ej: Bodega A"
                            />
                        </div>
                        <div>
                            <TextareaField
                                v-model="form.description"
                                label="Observaciones"
                                :rows="2"
                            />
                        </div>
                    </div>
                    <!-- Estado de tolerancia -->
                    <div v-if="overallStatus !== null" class="p-2 rounded text-sm mt-2"
                         :class="overallStatus ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                        {{ overallStatus ? 'TOLERANCIA APROBADA' : 'TOLERANCIA NO APROBADA' }}
                    </div>
                    <DialogFooter class="mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                        <div class="w-full md:w-auto">
                            <button
                                type="button"
                                class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                                @click="dialogOpen = false"
                            >
                                Cancelar
                            </button>
                        </div>
                        <div class="w-full md:w-auto">
                            <Button
                                type="submit"
                                class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                                :disabled="isLoading"
                                :class="overallStatus === false ? 'border border-red-600 bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500' : ''"
                            >
                                {{ isLoading ? 'Guardando...' : 'Guardar' }}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </div>

            <div v-else>
                <div v-if="rollsLoading" class="text-center py-4 text-gray-500">Cargando...</div>
                <div v-else>
                    <div v-if="rolls.length === 0" class="text-center py-4 text-gray-500">Sin rollos para este item.</div>
                    <div v-else>
                        <table class="min-w-full text-sm border rounded">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="px-2 py-1 border"># Rollo</th>
                                    <th class="px-2 py-1 border">Longitud</th>
                                    <th class="px-2 py-1 border">Ancho</th>
                                    <th class="px-2 py-1 border">Ubicación</th>
                                    <th class="px-2 py-1 border">Fecha</th>
                                    <th class="px-2 py-1 border">Obs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="roll in rolls" :key="roll.id">
                                    <td class="px-2 py-1 border">{{ roll.roll_number }}</td>
                                    <td class="px-2 py-1 border">{{ roll.quantity }}</td>
                                    <td class="px-2 py-1 border">{{ roll.roll_width }}</td>
                                    <td class="px-2 py-1 border">{{ roll.location }}</td>
                                    <td class="px-2 py-1 border">{{ formatDate(roll.entry_date) }}</td>
                                    <td class="px-2 py-1 border">{{ roll.description }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-1 text-right text-xs font-semibold">
                            Longitud total: {{ totalReceived }} mts
                        </div>
                    </div>
                </div>
                <DialogFooter class="mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                    <div class="w-full md:w-auto">
                        <button
                            type="button"
                            class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                            @click="dialogOpen = false"
                        >
                            Cerrar
                        </button>
                    </div>
                </DialogFooter>
            </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
