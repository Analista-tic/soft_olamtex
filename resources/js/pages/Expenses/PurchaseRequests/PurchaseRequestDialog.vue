<script setup lang="ts">
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import TextareaField from '@/components/TextareaField.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Contact } from '@/domain/purchases';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import PurchaseRequestItemsTable from './PurchaseRequestItemsTable.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import type { PurchaseRequestForm, PurchaseRequestItem } from '@/domain/purchases';
import { useAlertStack } from '@/composables/useAlert';
import HelpTooltip from '@/components/HelpTooltip.vue';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<PurchaseRequestForm>;
    readonly?: boolean;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh', message?: string): void;
    (e: 'error', message?: string): void;
}>();

const dialogOpen = ref(props.isOpen);
const localForm = ref<PurchaseRequestForm>(createDefaultForm());
const contacts = ref<Contact[]>([]);
const providerInput = ref('');
const isLoading = ref(false);
const contactPhoneInput = ref('');
const nextRequestId = ref<number | null>(null);
const isLoadingRequestId = ref(false);
const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();
const originalFormSnapshot = ref('');
const itemsTableRef = ref<{ addItem: () => void } | null>(null);
const { company, logoUrl } = useCompanyInfo();

const total = computed(() =>
    (localForm.value.items || []).reduce((acc, item) => acc + Math.max(Number(item.total) || 0, 0), 0),
);
const selectedContactData = computed(() => {
    if (!localForm.value.contact_id) return null;
    return contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id)) || null;
});

watch(
    () => localForm.value.contact_id,
    (val) => {
        const match = contacts.value.find((c) => Number(c.id) === Number(val));
        providerInput.value = match?.full_name || '';
        contactPhoneInput.value = match?.phone || '';
    },
);

watch(
    () => providerInput.value,
    (val) => {
        if (!val) {
            localForm.value.contact_id = null;
            return;
        }
        const match = contacts.value.find((c) => c.full_name?.toLowerCase() === val.toLowerCase());
        if (match) {
            localForm.value.contact_id = match.id;
        }
    },
);

const id = computed(() => {
    if (props.dialogType === 'edit') {
        return props.form?.id?.toString() || localForm.value.id?.toString() || '';
    }
    if (isLoadingRequestId.value) return 'Cargando...';
    if (nextRequestId.value == null) return '';
    return nextRequestId.value.toString();
});

function createDefaultForm(): PurchaseRequestForm {
    return {
        request_number: '',
        contact_id: null,
        requester_user_id: null,
        date: getTodayDate(),
        estimated_amount: null,
        description: '',
        items: [createEmptyItem()],
        status: undefined,
    };
}

function getTodayDate() {
    return new Date().toISOString().split('T')[0];
}

function createEmptyItem(): PurchaseRequestItem {
    return {
        item_id: null,
        price: '',
        quantity: '',
        description: '',
        total: 0,
        name: '',
    };
}

function resetForm() {
    let backendProviderName: string | null = null;
    let backendProviderPhone: string | null = null;

    if (props.form && Object.keys(props.form).length > 0) {
        const clone = JSON.parse(JSON.stringify(props.form));
        clone.estimated_amount = clone.estimated_amount ?? null;
        // compatibilidad: el backend guarda `document_number`, el formulario usa `request_number`
        clone.request_number = clone.request_number ?? clone.document_number ?? '';
        // normalizar fecha a formato YYYY-MM-DD para el InputDate
        if (clone.date) {
            const parsed = new Date(clone.date);
            if (!Number.isNaN(parsed.getTime())) {
                clone.date = parsed.toISOString().split('T')[0];
            }
        }
        if (!Array.isArray(clone.items) || !clone.items.length) {
            clone.items = [createEmptyItem()];
        } else {
            // normalize items to include `__name` for display (from related item or manual item_name)
            // ensure numeric fields are numbers so they render correctly even en modo readonly
            clone.items = clone.items.map((it: any) => {
                const name = it?.item?.name || it?.item_name || it?.__name || '';
                return {
                    item_id: it.item_id ?? null,
                    price: it.price != null && it.price !== '' ? Number(it.price) : 0,
                    quantity: it.quantity != null && it.quantity !== '' ? Number(it.quantity) : 0,
                    description: it.description ?? '',
                    total: it.total != null ? Number(it.total) : 0,
                    __name: name,
                } as PurchaseRequestItem;
            });
        }
        localForm.value = clone;
        backendProviderName = clone.provider_name ?? null;
        backendProviderPhone = clone.provider_phone ?? null;
    } else {
        localForm.value = createDefaultForm();
    }

    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
    originalFormSnapshot.value = stableStringify(localForm.value);

    // Preferir valores explícitos del backend (`provider_name`, `provider_phone`) cuando existan;
    // si no, usar los datos del contacto seleccionado (cuando esté cargada la lista).
    providerInput.value = backendProviderName ?? selectedContactData.value?.full_name ?? '';
    contactPhoneInput.value = backendProviderPhone ?? selectedContactData.value?.phone ?? '';
}

async function initializeDialog() {
    resetForm();
    await fetchContacts();
    if (props.dialogType === 'create') {
        nextRequestId.value = null;
        await fetchNextRequestId();
    }
}

async function fetchContacts() {
    try {
        const { data } = await axios.get('/contactos/data', { params: { per_page: 1000 } });
        contacts.value = data.data || [];
    } catch (e) {
        contacts.value = [];
        console.error('Error fetching contacts:', e);
    }
    // Después de cargar contactos, preferir el contacto relacionado si existe, sino mantener valores del backend
    if (localForm.value.contact_id) {
        const match = contacts.value.find((c) => Number(c.id) === Number(localForm.value.contact_id));
        if (match) {
            providerInput.value = match.full_name || providerInput.value || '';
            contactPhoneInput.value = match.phone || contactPhoneInput.value || '';
            return;
        }
    }

    // si no hay contacto asociado, conservar providerInput (viene del backend o del usuario)
    providerInput.value = providerInput.value || '';
    contactPhoneInput.value = contactPhoneInput.value || '';
}

async function fetchNextRequestId() {
    isLoadingRequestId.value = true;
    try {
        const { data } = await axios.get('/gastos/solicitudes-compra/data', {
            params: { per_page: 1, order_by: 'id', order_direction: 'desc' },
        });
        const lastId = Array.isArray(data.data) && data.data.length > 0 ? Number(data.data[0].id) : 0;
        nextRequestId.value = lastId + 1;
    } catch (e) {
        nextRequestId.value = 1;
        console.error('Error fetching next request ID:', e);
    } finally {
        isLoadingRequestId.value = false;
    }
}

function scrollToDialogTop() {
    const dialogEl = document.querySelector('[role="dialog"]');
    dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
}

function setErrorMessage(msg: string, type: 'success' | 'error' | 'info' | 'warning' = 'error') {
    showError(msg, type);
    scrollToDialogTop();
}

const stableStringify = (value: any): string => {
    const normalize = (val: any): any => {
        if (Array.isArray(val)) return val.map((v) => normalize(v));
        if (val && typeof val === 'object') {
            return Object.keys(val)
                .sort()
                .reduce((acc: any, key) => {
                    acc[key] = normalize(val[key]);
                    return acc;
                }, {} as Record<string, unknown>);
        }
        return val;
    };
    return JSON.stringify(normalize(value));
};

function handleAddLine() {
    if (props.readonly) return;
    itemsTableRef.value?.addItem();
}

async function handleSubmit() {
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';

    if (!validateForm()) return;
    if (props.dialogType === 'edit' && stableStringify(localForm.value) === originalFormSnapshot.value) {
        return;
    }

    isLoading.value = true;
    const payload: any = {
        ...localForm.value,
        estimated_amount:
            localForm.value.estimated_amount == null
                ? null
                : Number(localForm.value.estimated_amount),
    };

    // mapar campo visible `request_number` al nombre que usa el backend
    if (payload.request_number !== undefined) {
        payload.document_number = payload.request_number || null;
        delete payload.request_number;
    }

    // Build items list: include lines with either `item_id` (existing item) or `__name` (manual name), and quantity > 0.
    if (Array.isArray(localForm.value.items)) {
        const filtered = localForm.value.items
            .map((item: PurchaseRequestItem) => {
                const { name, ...rest } = item;
                return {
                    ...rest,
                    item_id: item.item_id === null || item.item_id === undefined ? null : Number(item.item_id),
                    item_name: name || null,
                    price: Number(item.price) || 0,
                    quantity: Number(item.quantity) || 0,
                };
            })
            .filter((it) => (it.item_id || (it.item_name && String(it.item_name).trim())) && Number(it.quantity) > 0);

        if (filtered.length) {
            payload.items = filtered;
        } else {
            delete payload.items;
        }
    }

    // Determine provider fields: if providerInput matches a contact, use its id; otherwise send provider_name/phone
    const matched = contacts.value.find((c) => c.full_name?.toLowerCase() === (providerInput.value || '').toLowerCase());
    if (matched) {
        payload.contact_id = Number(matched.id);
        delete payload.provider_name;
        delete payload.provider_phone;
    } else {
        payload.contact_id = null;
        payload.provider_name = providerInput.value && providerInput.value.trim() ? providerInput.value.trim() : null;
        payload.provider_phone = contactPhoneInput.value && contactPhoneInput.value.trim() ? contactPhoneInput.value.trim() : null;
    }

    if (props.dialogType === 'create') {
        delete payload.id;
    }

    const method = props.dialogType === 'create' ? 'POST' : 'PUT';
    const url =
        props.dialogType === 'create'
            ? '/gastos/solicitudes-compra'
            : `/gastos/solicitudes-compra/${localForm.value.id}`;

    try {
        await axios({ method, url, data: payload });
        const msg = props.dialogType === 'create' ? 'Solicitud creada correctamente.' : 'Solicitud actualizada correctamente.';
        emit('refresh', msg);
        showSuccess(msg);
        dialogOpen.value = false;
    } catch (e) {
        const serverMessage =
            (e as any)?.response?.data?.message ||
            Object.values((e as any)?.response?.data?.errors || {})?.flat()?.[0] ||
            'Error al guardar la solicitud. Por favor intenta nuevamente.';
        setErrorMessage(serverMessage);
        emit('error', serverMessage);
        console.error('Error guardando solicitud:', e);
    } finally {
        isLoading.value = false;
    }
}

function validateForm() {
    if (!localForm.value.description || !String(localForm.value.description).trim()) {
        setErrorMessage('La descripción es obligatoria.', 'warning');
        return false;
    }

    // Allow items without a linked `item_id` (they will be ignored server-side).
    // But require at least one item with quantity > 0 so the request isn't empty.
    const itemsCheck = (localForm.value.items || []).filter((it) => Number(it.quantity) > 0);
    if (!itemsCheck.length) {
        setErrorMessage('La solicitud debe contener al menos un ítem con cantidad mayor a cero.', 'warning');
        return false;
    }

    return true;
}

watch(
    () => props.form,
    () => {
        if (props.dialogType === 'edit' && dialogOpen.value) {
            resetForm();
        }
    },
    { deep: true },
);

watch(
    () => props.isOpen,
    async (isOpen) => {
        dialogOpen.value = isOpen;
        if (isOpen) await initializeDialog();
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) emit('close');
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-[200vh] max-h-[90vh] flex flex-col p-0">
            <DialogHeader class="relative flex-shrink-0 px-4 pt-4">
                <div class="flex flex-col gap-1">
                    <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Solicitud de Compra</DialogTitle>
                    <DialogDescription class="pb-2">
                        Completa los campos y añade los artículos requeridos.
                    </DialogDescription>
                </div>
            </DialogHeader>
            <AlertStack
                :success-message="successMessage"
                    :error-message="errorMessage"
                    success-title="Éxito"
                    error-title="Error"
                    :error-type="errorType"
                    @close-success="clearSuccess"
                    @close-error="clearError"
                />
                <div class="overflow-y-auto flex-1 px-4 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                    <TooltipProvider>
                        <form
                            @submit.prevent="handleSubmit()"
                            :class="{'pointer-events-none opacity-50': dialogType === 'create' && isLoadingRequestId}"
                            class="space-y-6"
                        >
                    <div class="mt-8 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border space-y-4">
                        <div class="grid grid-cols-3 items-start gap-4">
                            <div>
                                <InputField
                                    label="# Solicitud de Compra"
                                    v-model="localForm.request_number"
                                    :placeholder="id ? `SOL-${id}` : 'SOL-001'"
                                    :readonly="props.readonly"
                                    :disabled="props.readonly"
                                />
                                <span v-if="dialogType === 'create' && isLoadingRequestId" class="text-gray-400">Cargando...</span>
                            </div>
                            <div class="relative flex h-full items-center justify-center rounded p-4">
                                <img
                                    :src="logoUrl"
                                    alt="Logotipo de la empresa"
                                    class="max-h-24 max-w-[160px] w-auto object-contain"
                                />
                            </div>
                            <div>
                                <div class="text-sm font-semibold">Datos empresa</div>
                                <p class="text-xs">
                                    Razón Social:
                                    <span class="font-normal">{{ company?.name || 'Sin nombre' }}</span>
                                </p>
                                <p class="text-xs">
                                    NIT:
                                    <span class="font-normal">{{ company?.nit || 'Sin NIT' }}</span>
                                </p>
                                <p class="text-xs">
                                    Correo:
                                    <span class="font-normal">{{ company?.email || 'Sin correo' }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Proveedor
                                    </Label>
                                    <HelpTooltip
                                        text="Escribe o selecciona el proveedor al que se le solicitarán los productos."
                                        aria-label="Ayuda sobre proveedor"
                                    />
                                </div>
                                <InputField
                                    v-model="providerInput"
                                    label=""
                                    placeholder="Escribe un proveedor"
                                    list="providers-list"
                                    :readonly="props.readonly"
                                    :disabled="props.readonly"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Número de contacto
                                    </Label>
                                    <HelpTooltip
                                        text="Número telefónico del proveedor para coordinar la solicitud."
                                        aria-label="Ayuda sobre número de contacto"
                                    />
                                </div>
                                <InputField
                                    v-model="contactPhoneInput"
                                    label=""
                                    placeholder="Escribe un número"
                                    :readonly="props.readonly"
                                    :disabled="props.readonly"
                                />
                            </div>
                        </div>
                        <datalist id="providers-list">
                            <option
                                v-for="contact in contacts"
                                :key="contact.id"
                                :value="contact.full_name || ''"
                            />
                        </datalist>

                        <div class="grid grid-cols-1 gap-4">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Fecha de solicitud
                                    </Label>
                                    <HelpTooltip
                                        text="Fecha en la que se registra la solicitud de compra."
                                        aria-label="Ayuda sobre fecha de solicitud"
                                    />
                                </div>
                                <InputDate v-model="localForm.date" label="" :disabled="props.readonly" />
                            </div>
                        </div>

                                        <InventoryDetailTableLayout :showCreateLineButton="!props.readonly" :createLineButtonDisabled="props.readonly" @create-line="handleAddLine">
                                            <PurchaseRequestItemsTable
                                                ref="itemsTableRef"
                                                :items="localForm.items || []"
                                                :readonly="props.readonly"
                                                @update:items="(items) => (localForm.items = items)"
                                            />
                                        </InventoryDetailTableLayout>

                        <div class="space-y-4 pt-6">
                            <div class="flex flex-col items-end space-y-0 text-sm w-full">
                                <div class="w-full max-w-xs rounded p-2 space-y-1 bg-card text-card-foreground shadow-sm">
                                    <div class="flex justify-between items-center pt-1">
                                        <span class="font-bold text-card-foreground">Total solicitud</span>
                                        <span class="font-bold text-card-foreground">{{
                                            Number(total || 0).toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">
                                    Descripción<span class="text-red-500">*</span>
                                </Label>
                                <HelpTooltip
                                    text="Describe brevemente la solicitud de compra y su propósito."
                                    aria-label="Ayuda sobre descripción"
                                />
                            </div>
                            <TextareaField
                                v-model="localForm.description"
                                placeholder="Agrega observaciones adicionales y el motivo para esta solicitud"
                                :readonly="props.readonly"
                            />
                        </div>
                    </div>

                    <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex justify-end gap-2 px-4 pb-4">`
                        <template v-if="props.readonly">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                                @click="dialogOpen = false"
                            >
                                Cerrar
                            </button>
                        </template>
                        <template v-else>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                                @click="dialogOpen = false"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                :disabled="isLoading"
                                class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                @click.prevent="handleSubmit"
                            >
                                {{ isLoading ? 'Guardando...' : dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
                            </button>
                        </template>
                    </DialogFooter>
                        </form>
                    </TooltipProvider>
                </div>
        </DialogContent>
    </Dialog>
</template>

