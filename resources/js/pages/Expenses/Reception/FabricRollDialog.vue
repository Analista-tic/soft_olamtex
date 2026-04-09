<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { PurchaseInvoiceItem } from '@/domain/Expenses';
import axios from 'axios';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const TOLERANCE_QUANTITY_PERCENT = 30;
const TOLERANCE_WIDTH_CM = 0.05;

type FabricRollForm = {
    roll_number: string;
    purchase_document_item_id: number | null;
    quantity: number | string;
    roll_width: number | string;
    location: string;
    description: string;
    entry_date: string;
};

const props = defineProps<{
    isOpen: boolean;
    item: PurchaseInvoiceItem | null;
    apiUrl: string;
    /** Si la factura está abierta (no recepcionada), se pueden eliminar y editar rollos. Por defecto true. */
    canDeleteEditRolls?: boolean;
}>();

const canDeleteEditRolls = computed(() => props.canDeleteEditRolls !== false);

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const dialogOpen = ref(props.isOpen);
const isLoading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const errorType = ref<'error' | 'info' | 'warning'>('error');
const dialogInnerRef = ref<HTMLElement | null>(null);

function setAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
    if (type === 'success') {
        successMessage.value = message;
        errorMessage.value = '';
    } else {
        errorMessage.value = message;
        successMessage.value = '';
        errorType.value = type;
    }
    if (dialogInnerRef.value?.scrollTo) {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        document.querySelector('[role="dialog"]')?.scrollTo?.({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert() {
    successMessage.value = '';
    errorMessage.value = '';
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
        orderedQuantity: Number(props.item.quantity) || 0,
    };
});

const totalReceived = computed(() => rolls.value.reduce((acc, roll) => acc + Number(roll.quantity || 0), 0));

/** Tolerancia de cantidad: 30% por encima o por debajo del pedido. */
const quantityTolerance = computed(() => {
    if (!props.item || itemInfo.value == null) return null;
    const ordered = itemInfo.value.orderedQuantity;
    if (ordered <= 0) return null;
    const currentReceived = totalReceived.value;
    const thisQuantity = Number(form.value.quantity) || 0;
    const receivedAfterSave = currentReceived + thisQuantity;
    const difference = Math.abs(ordered - receivedAfterSave);
    const percentageDiff = (difference / ordered) * 100;
    const isWithinTolerance = percentageDiff <= TOLERANCE_QUANTITY_PERCENT;
    return {
        ordered,
        receivedAfterSave,
        difference,
        percentageDiff,
        isApproved: isWithinTolerance,
    };
});

/** Tolerancia de ancho: ≤ 0.05 cm. Si es mayor, el rollo sigue siendo aprobado (solo informativo). */
const widthTolerance = computed(() => {
    if (!props.item || form.value.roll_width === '' || form.value.roll_width == null) return null;
    const orderedWidth = Number(props.item.order_width) || 0;
    const receivedWidth = Number(form.value.roll_width);
    const difference = Math.abs(orderedWidth - receivedWidth);
    const withinTolerance = difference <= TOLERANCE_WIDTH_CM;
    return {
        ordered: orderedWidth,
        received: receivedWidth,
        difference,
        withinTolerance,
    };
});

/** Estado de aprobación: solo por cantidad. Si no cumple tolerancia 30% → no aprobada / a la espera. Ancho > 0.05 no bloquea. */
const approvalStatus = computed(() => {
    const q = quantityTolerance.value;
    if (!q) return null;
    if (q.isApproved) return 'approved';
    return 'pending_approval';
});

function resetForm() {
    editingRoll.value = null;
    form.value = {
        roll_number: '',
        purchase_document_item_id: props.item?.id ?? null,
        quantity: '',
        roll_width: props.item?.order_width?.toString() ?? '',
        location: '',
        description: '',
        entry_date: new Date().toISOString().split('T')[0],
    };
}

function fillFormFromRoll(roll: any) {
    const entryDate = roll.entry_date
        ? typeof roll.entry_date === 'string'
            ? roll.entry_date.split('T')[0]
            : roll.entry_date
        : new Date().toISOString().split('T')[0];
    form.value = {
        roll_number: roll.roll_number ?? roll.roll ?? '',
        purchase_document_item_id: roll.purchase_document_item_id ?? props.item?.id ?? null,
        quantity: roll.quantity ?? '',
        roll_width: roll.roll_width ?? props.item?.order_width?.toString() ?? '',
        location: roll.location ?? '',
        description: roll.description ?? '',
        entry_date: entryDate,
    };
}

function startEditRoll(roll: any) {
    editingRoll.value = roll;
    fillFormFromRoll(roll);
    activeTab.value = 'form';
    clearAlert();
}

/** Genera identificador único RL para el rollo. */
function generateRollNumber(): string {
    return `RL-${String(Date.now()).slice(-6)}`;
}

async function handleSubmit() {
    if (!validateForm()) return;
    isLoading.value = true;
    try {
        let rollNumber = String(form.value.roll_number || '').trim();
        if (!rollNumber) rollNumber = generateRollNumber();

        const payload = {
            roll_number: rollNumber,
            purchase_document_item_id: props.item!.id,
            quantity: Number(form.value.quantity),
            roll_width: Number(form.value.roll_width) || 0,
            location: String(form.value.location || '').trim() || null,
            description: form.value.description || null,
            entry_date: form.value.entry_date,
            partial_output: editingRoll.value?.partial_output ?? 0,
            actual_output: editingRoll.value?.actual_output ?? 0,
        };

        if (editingRoll.value) {
            await axios.put(`/rollos-tela/${editingRoll.value.id}`, payload);
            setAlert('Rollo actualizado correctamente', 'success');
            editingRoll.value = null;
            resetForm();
            await fetchRolls();
            activeTab.value = 'list';
        } else {
            await axios.post(props.apiUrl, { ...payload, approval_status: approvalStatus.value || 'pending_approval' });
            emit('success');
            dialogOpen.value = false;
            resetForm();
            await fetchRolls();
        }
    } catch (error: any) {
        const data = error.response?.data;
        const msg = data?.message || (data?.errors && Object.values(data.errors).flat().join(', ')) || 'Error al guardar el rollo. Intente de nuevo.';
        setAlert(msg, 'error');
    } finally {
        isLoading.value = false;
    }
}

async function deleteRoll(roll: any) {
    if (!props.canDeleteEditRolls) return;
    if (!confirm(`¿Eliminar el rollo ${roll.roll_number || roll.roll}? Esta acción no se puede deshacer.`)) return;
    isLoading.value = true;
    try {
        await axios.delete(`/rollos-tela/${roll.id}`);
        setAlert('Rollo eliminado', 'success');
        await fetchRolls();
        emit('success');
    } catch (error: any) {
        const data = error.response?.data;
        const msg = data?.message || (data?.errors && Object.values(data.errors).flat().join(', ')) || 'Error al eliminar el rollo.';
        setAlert(msg, 'error');
    } finally {
        isLoading.value = false;
    }
}

function validateForm(): boolean {
    if (!form.value.quantity || Number(form.value.quantity) <= 0) {
        setAlert('Ingrese una longitud válida (mts)', 'warning');
        return false;
    }
    if (form.value.roll_width === '' || form.value.roll_width == null || Number(form.value.roll_width) < 0) {
        setAlert('Ingrese un ancho válido (cm)', 'warning');
        return false;
    }
    if (!form.value.entry_date) {
        setAlert('Seleccione la fecha de entrada', 'warning');
        return false;
    }
    const loc = String(form.value.location || '').trim();
    if (!loc) {
        setAlert('Ingrese la ubicación del rollo', 'warning');
        return false;
    }
    return true;
}

const activeTab = ref<'form' | 'list'>('form');
const rolls = ref<any[]>([]);
const rollsLoading = ref(false);
/** Rollo en edición (si no es null, el formulario hace PUT en lugar de POST). */
const editingRoll = ref<any | null>(null);

async function fetchRolls() {
    if (!props.item?.id) {
        rolls.value = [];
        return;
    }
    rollsLoading.value = true;
    try {
        const res = await axios.get(`/kardex-telas/rollos/item/${props.item.id}`);
        rolls.value = Array.isArray(res.data) ? res.data : [];
    } catch {
        rolls.value = [];
    } finally {
        rollsLoading.value = false;
    }
}

function formatDate(dateStr: string) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return Number.isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('es-CO');
}

watch(
    () => props.isOpen,
    (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) {
            resetForm();
            form.value.roll_number = generateRollNumber();
            fetchRolls();
        }
    },
);

watch(
    () => props.item?.id,
    (newId) => {
        if (props.isOpen && newId) fetchRolls();
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) emit('close');
});

onMounted(() => {
    if (props.isOpen && props.item?.id) fetchRolls();
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="flex max-h-[90vh] max-w-3xl flex-col gap-0 p-0">
            <div class="flex-shrink-0 px-6 pb-2 pt-5">
                <DialogHeader>
                    <DialogTitle class="text-lg">{{ editingRoll ? 'Editar rollo' : 'Registrar rollo de tela' }}</DialogTitle>
                    <DialogDescription class="text-sm text-muted-foreground">
                        {{
                            editingRoll
                                ? 'Modifique los datos del rollo y guarde.'
                                : 'Cada recepción guarda un nuevo rollo con identificador único (RL). Relacione el rollo al ítem de la factura.'
                        }}
                    </DialogDescription>
                </DialogHeader>
            </div>

            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="successMessage = ''"
                @close-error="errorMessage = ''"
            />

            <div class="flex-1 overflow-y-auto px-6 py-4" style="scrollbar-width: thin">
                <div ref="dialogInnerRef" class="space-y-5">
                    <!-- Tabs -->
                    <div class="flex gap-2 border-b pb-2">
                        <button
                            type="button"
                            :class="[
                                'rounded-t px-4 py-2 text-sm font-medium transition',
                                activeTab === 'form'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted/50 text-muted-foreground hover:bg-muted',
                            ]"
                            @click="
                                resetForm();
                                form.roll_number = generateRollNumber();
                                form.roll_width = props.item?.order_width?.toString() ?? '';
                                activeTab = 'form';
                            "
                        >
                            {{ editingRoll ? 'Editar rollo' : 'Registrar rollo' }}
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-t px-4 py-2 text-sm font-medium transition',
                                activeTab === 'list'
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted/50 text-muted-foreground hover:bg-muted',
                            ]"
                            @click="activeTab = 'list'"
                        >
                            Rollos recibidos ({{ rolls.length }})
                        </button>
                    </div>

                    <template v-if="activeTab === 'form'">
                        <!-- Resumen del ítem -->
                        <div v-if="itemInfo" class="rounded-lg border bg-muted/30 p-4">
                            <h3 class="mb-3 text-sm font-semibold">Resumen del ítem</h3>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                <div><span class="text-muted-foreground">Tela:</span> {{ itemInfo.articleName }}</div>
                                <div><span class="text-muted-foreground">Pedido:</span> {{ itemInfo.orderedQuantity }} mts</div>
                                <div><span class="text-muted-foreground">Ya ingresado:</span> {{ totalReceived }} mts</div>
                                <div><span class="text-muted-foreground">Ancho ref.:</span> {{ itemInfo.orderWidth }} cm</div>
                                <div><span class="text-muted-foreground">Cód. proveedor:</span> {{ itemInfo.supplierCode }}</div>
                                <div><span class="text-muted-foreground">Color:</span> {{ itemInfo.supplierColor }}</div>
                            </div>
                        </div>

                        <form @submit.prevent="handleSubmit" class="space-y-5">
                            <!-- Longitud y ancho (destacados) -->
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <InputField
                                        v-model="form.quantity"
                                        label="Longitud (mts)"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="Ej: 100"
                                        required
                                        class="text-base"
                                    />
                                    <p
                                        v-if="quantityTolerance"
                                        class="mt-1 text-xs"
                                        :class="quantityTolerance.isApproved ? 'text-green-600' : 'text-amber-600'"
                                    >
                                        Tras guardar: {{ quantityTolerance.receivedAfterSave.toFixed(2) }} mts ({{
                                            quantityTolerance.percentageDiff.toFixed(1)
                                        }}% {{ quantityTolerance.isApproved ? '— dentro de tolerancia' : '— fuera de tolerancia ±30%' }})
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <InputField
                                        v-model="form.roll_width"
                                        label="Ancho del rollo (cm)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="Ej: 1.50"
                                        required
                                        class="text-base"
                                    />
                                    <p
                                        v-if="widthTolerance"
                                        class="mt-1 text-xs"
                                        :class="widthTolerance.withinTolerance ? 'text-green-600' : 'text-muted-foreground'"
                                    >
                                        Dif. {{ widthTolerance.difference.toFixed(2) }} cm
                                        {{ widthTolerance.withinTolerance ? '(dentro de ±0.05 cm)' : '(ancho no bloquea aprobación)' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Número de rollo y fecha -->
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <InputField v-model="form.roll_number" label="Número de rollo (RL)" placeholder="Generado automáticamente" readonly />
                                <InputDate v-model="form.entry_date" label="Fecha de entrada" required />
                            </div>

                            <!-- Ubicación -->
                            <div>
                                <InputField v-model="form.location" label="Ubicación" placeholder="Ej: Bodega A, estante 2" required />
                            </div>
                            <!-- Observaciones -->
                            <div>
                                <TextareaField v-model="form.description" label="Observaciones" :rows="2" placeholder="Opcional" />
                            </div>

                            <DialogFooter class="mt-4 border-t pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="rounded-full px-5 py-2 text-sm font-semibold"
                                    @click="dialogOpen = false"
                                >
                                    Cancelar
                                </Button>
                                <Button type="submit" class="rounded-full px-5 py-2 text-sm font-semibold shadow-sm" :disabled="isLoading">
                                    {{ isLoading ? 'Guardando...' : editingRoll ? 'Actualizar rollo' : 'Guardar rollo' }}
                                </Button>
                                <Button
                                    v-if="editingRoll"
                                    type="button"
                                    variant="outline"
                                    class="rounded-full px-5 py-2 text-sm font-semibold"
                                    @click="
                                        resetForm();
                                        form.roll_number = generateRollNumber();
                                        form.roll_width = props.item?.order_width?.toString() ?? '';
                                    "
                                >
                                    Cancelar edición
                                </Button>
                            </DialogFooter>
                        </form>
                    </template>

                    <template v-else>
                        <div v-if="rollsLoading" class="py-8 text-center text-muted-foreground">Cargando rollos...</div>
                        <div v-else-if="rolls.length === 0" class="py-8 text-center text-muted-foreground">
                            Aún no hay rollos registrados para este ítem.
                        </div>
                        <div v-else class="space-y-3">
                            <div class="overflow-hidden rounded-lg border">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-muted/50">
                                            <th class="px-4 py-3 text-left font-medium"># Rollo</th>
                                            <th class="px-4 py-3 text-right font-medium">Longitud</th>
                                            <th class="px-4 py-3 text-right font-medium">Ancho</th>
                                            <th class="px-4 py-3 text-left font-medium">Ubicación</th>
                                            <th class="px-4 py-3 text-left font-medium">Fecha</th>
                                            <th v-if="canDeleteEditRolls" class="w-24 px-4 py-3 text-center font-medium">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="roll in rolls" :key="roll.id" class="border-t">
                                            <td class="px-4 py-3 font-medium">{{ roll.roll_number || roll.roll }}</td>
                                            <td class="px-4 py-3 text-right">{{ roll.quantity }} mts</td>
                                            <td class="px-4 py-3 text-right">{{ roll.roll_width }} cm</td>
                                            <td class="px-4 py-3">{{ roll.location || '—' }}</td>
                                            <td class="px-4 py-3">{{ formatDate(roll.entry_date) }}</td>
                                            <td v-if="canDeleteEditRolls" class="px-4 py-3">
                                                <div class="flex items-center justify-center gap-1">
                                                    <button
                                                        type="button"
                                                        class="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-primary"
                                                        title="Editar rollo"
                                                        @click="startEditRoll(roll)"
                                                    >
                                                        <Pencil class="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-destructive"
                                                        title="Eliminar rollo"
                                                        @click="deleteRoll(roll)"
                                                    >
                                                        <Trash2 class="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-sm font-medium text-muted-foreground">Longitud total recibida: {{ totalReceived.toFixed(2) }} mts</p>
                            <DialogFooter class="mt-4 border-t pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="rounded-full px-5 py-2 text-sm font-semibold"
                                    @click="dialogOpen = false"
                                >
                                    Cerrar
                                </Button>
                                <Button
                                    type="button"
                                    class="rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                                    @click="
                                        resetForm();
                                        form.roll_number = generateRollNumber();
                                        form.roll_width = props.item?.order_width?.toString() ?? '';
                                        activeTab = 'form';
                                    "
                                >
                                    Registrar otro rollo
                                </Button>
                            </DialogFooter>
                        </div>
                    </template>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
