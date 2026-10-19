<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import CreatableSelectField from '@/components/CreatableSelectField.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogBody from '@/components/FormDialogBody.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import FormDialogHeader from '@/components/FormDialogHeader.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import StatusBanner from '@/components/common/StatusBanner.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import { useDialogLoadingState } from '@/composables/useDialogLoadingState';
import type { PurchaseRequestForm, PurchaseRequestItem } from '@/domain/Expenses';
import { canEditRequest as canEditRequestRule } from '@/domain/Expenses/rules';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import PurchaseRequestItemsTable from './PurchaseRequestItemsTable.vue';
import PurchaseTraceability from '../PurchaseTraceability.vue';

type PurchaseRequestDialogForm = Omit<PurchaseRequestForm, 'items' | 'estimated_amount'> & {
    items?: PurchaseRequestItem[];
    estimated_amount?: number | null;
    requester?: { name?: string };
    buyer?: { id?: number; name?: string } | null;
    solicited_to?: 'gerencia' | 'logistica' | null;
    buyer_user_id?: number | null;
    rejected_by?: { name?: string };
    rejected_at?: string | null;
    rejection_reason?: string | null;
};

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<PurchaseRequestDialogForm>;
    readonly?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'refresh', message?: string): void;
    (e: 'error', message?: string): void;
}>();

const PURCHASE_REQUESTS_BASE_URL = '/gastos/solicitudes-compra';

const authStore = useAuthStore();
const dialogOpen = ref(props.isOpen);
const localForm = ref<PurchaseRequestDialogForm>(createDefaultForm());
const users = ref<{ id: number; name: string; area_name: string }[]>([]);
const isLoadingUsers = ref(false);
const { successMessage, errorMessage, errorType, showError, clearSuccess, clearError } = useAlertStack();
const itemsTableRef = ref<InstanceType<typeof PurchaseRequestItemsTable> | null>(null);
const { isBootstrapping, isHydrating, isBlockingLoading, canRenderForm, startLoadingRun, isCurrentLoadingRun, cancelLoadingRun } =
    useDialogLoadingState(dialogOpen);

function createItemUid(): string {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID();
    }

    return `purchase-request-item-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function createDefaultForm(): PurchaseRequestDialogForm {
    return {
        request_number: '',
        provider_name: null,
        requester_user_id: null,
        solicited_to: null,
        buyer_user_id: null,
        date: getTodayDate(),
        destination: '',
        estimated_amount: null,
        description: '',
        status: 'draft',
        items: [],
    };
}

function getTodayDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function normalizeIsoDate(value: unknown, fallback: string | null = null): string | null {
    if (typeof value !== 'string') {
        return fallback;
    }

    const trimmed = value.trim();
    if (!trimmed) {
        return fallback;
    }

    const datePart = trimmed.includes('T') ? trimmed.split('T')[0] : trimmed.includes(' ') ? trimmed.split(' ')[0] : trimmed;
    if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
        return datePart;
    }

    const parsed = new Date(trimmed);
    if (Number.isNaN(parsed.getTime())) {
        return fallback;
    }

    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function normalizeItem(item: any): PurchaseRequestItem {
    return {
        uid: typeof item?.uid === 'string' && item.uid.trim() ? item.uid.trim() : createItemUid(),
        item_id: item?.item_id ?? null,
        name: item?.name ?? item?.item_name ?? '',
        price: Number(item?.price) || 0,
        quantity: Number(item?.quantity) || 1,
        description: item?.description ?? '',
        total: Number(item?.total) || 0,
        total_tax: item?.total_tax != null ? Number(item.total_tax) || 0 : undefined,
        image_url: typeof item?.image_url === 'string' && item.image_url.trim() ? item.image_url : null,
        image_file: null,
        _delete_image: false,
    };
}

const requestDateModel = computed<string | null>({
    get: () => normalizeIsoDate(localForm.value.date, getTodayDate()) || getTodayDate(),
    set: (value) => {
        localForm.value.date = normalizeIsoDate(value, getTodayDate()) || getTodayDate();
    },
});

const canEdit = computed(() => {
    if (props.readonly) {
        return false;
    }

    if (props.dialogType === 'create') {
        return true;
    }

    return canEditRequestRule(localForm.value.status || 'draft');
});

const isReadOnly = computed(() => props.readonly || !canEdit.value);

const isRejected = computed(() => localForm.value.status === 'rejected');
const rejectedByName = computed(() => localForm.value.rejected_by?.name ?? 'Usuario');
const rejectedAtFormatted = computed(() => {
    const at = localForm.value.rejected_at;
    if (!at) return '';
    return new Date(at).toLocaleString('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
});

const requestNumberPlaceholder = computed(() => {
    // El consecutivo lo asigna el servidor al guardar; en edición se muestra el real (no `SOL-{id}`).
    return props.form?.request_number || localForm.value.request_number || 'Se asigna al guardar';
});

const solicitadoAOptions = [
    { id: 'gerencia', name: 'Gerencia' },
    { id: 'logistica', name: 'Logística' },
];

const currentRequesterName = computed(() => {
    if (localForm.value.requester?.name) {
        return localForm.value.requester.name;
    }
    const user = authStore.user as any;
    return user?.name ?? '';
});

const userOptions = computed(() => {
    const options = users.value.map((u) => ({
        id: u.id,
        name: u.area_name ? `${u.name} — ${u.area_name}` : u.name,
    }));

    // El encargado vigente siempre debe tener etiqueta, aunque la búsqueda lo excluya.
    const buyer = localForm.value.buyer;
    if (buyer?.id && buyer.name && !options.some((o) => o.id === buyer.id)) {
        options.push({ id: buyer.id, name: buyer.name });
    }

    return options.sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));
});

async function fetchUsers(search = ''): Promise<void> {
    isLoadingUsers.value = true;
    try {
        const { data } = await axios.get('/usuarios/list', {
            params: search ? { search } : {},
        });
        users.value = Array.isArray(data.data) ? data.data : [];
    } catch (err) {
        console.error('Error cargando usuarios:', err);
        users.value = [];
    } finally {
        isLoadingUsers.value = false;
    }
}

const providerOptions = ref<{ id: string; name: string }[]>([]);
const isLoadingProviders = ref(false);

// Sugerencias de proveedor desde Contactos; el texto libre sigue permitido (provider_name es string).
let providersRequestId = 0;

async function fetchProviders(search = ''): Promise<void> {
    const requestId = ++providersRequestId;
    isLoadingProviders.value = true;
    try {
        const params = { per_page: 20, ...(search.trim() ? { search: search.trim() } : {}) };
        const [providers, both] = await Promise.all([
            axios.get('/contactos/data', { params: { ...params, contact_type: 'proveedor' } }),
            axios.get('/contactos/data', { params: { ...params, contact_type: 'cliente_proveedor' } }),
        ]);
        if (requestId !== providersRequestId) return;
        const names = [...(providers.data?.data ?? []), ...(both.data?.data ?? [])]
            .map((contact: { full_name?: string }) => String(contact.full_name ?? '').trim())
            .filter(Boolean);
        providerOptions.value = [...new Set(names)].sort((a, b) => a.localeCompare(b)).map((name) => ({ id: name, name }));
    } catch (err) {
        if (requestId !== providersRequestId) return;
        console.error('Error cargando proveedores:', err);
        providerOptions.value = [];
    } finally {
        if (requestId === providersRequestId) isLoadingProviders.value = false;
    }
}

function resetForm(source: Partial<PurchaseRequestDialogForm> = props.form) {
    if (source && Object.keys(source).length > 0) {
        const clone = JSON.parse(JSON.stringify(source));
        clone.estimated_amount = clone.estimated_amount ?? null;
        clone.request_number = clone.request_number ?? '';
        clone.date = normalizeIsoDate(clone.date, getTodayDate()) || getTodayDate();

        if (typeof clone.items === 'string') {
            try {
                clone.items = JSON.parse(clone.items);
            } catch {
                clone.items = [];
            }
        }

        clone.items = Array.isArray(clone.items) ? clone.items.map((item: any) => normalizeItem(item)) : [];
        localForm.value = clone;
    } else {
        localForm.value = createDefaultForm();
    }

    localForm.value.date = normalizeIsoDate(localForm.value.date, getTodayDate()) || getTodayDate();
    localForm.value.items = Array.isArray(localForm.value.items) ? localForm.value.items.map((item) => normalizeItem(item)) : [];

    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
}

async function initializeDialog() {
    const currentRun = startLoadingRun();
    isBootstrapping.value = true;
    isHydrating.value = props.dialogType === 'edit';

    try {
        await nextTick();
        await authStore.fetchUser();
        resetForm();

        // En modo solo lectura no hay buscadores de usuario/proveedor que llenar
        // (el nombre ya viene incluido en los datos de la solicitud), así que se omiten.
        const tasks: Promise<unknown>[] = props.readonly ? [] : [fetchUsers(), fetchProviders()];

        if (props.dialogType === 'edit' && props.form?.id) {
            tasks.push(
                axios.get(`${PURCHASE_REQUESTS_BASE_URL}/${props.form.id}`).then(({ data }) => {
                    if (isCurrentLoadingRun(currentRun)) {
                        resetForm(data?.data || props.form);
                    }
                }),
            );
        }

        await Promise.all(tasks);
    } catch (error) {
        const message = 'No se pudo cargar la solicitud de compra.';
        showError(message);
        emit('error', message);
        console.error('Error cargando solicitud de compra:', error);
    } finally {
        if (isCurrentLoadingRun(currentRun)) {
            isBootstrapping.value = false;
            isHydrating.value = false;
        }
    }
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
        if (isOpen) {
            await initializeDialog();
        } else {
            cancelLoadingRun();
        }
    },
);

watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
        cancelLoadingRun();
        emit('close');
    }
});
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <FormDialogContent class="flex max-h-[95vh] max-w-[95vw] flex-col px-2 py-2" :scrollable="false">
            <div class="flex min-h-0 flex-1 flex-col">
                <FormDialogHeader class="px-4 pb-3 pr-12 pt-4">
                    <DialogTitle>{{ props.readonly ? 'Ver' : dialogType === 'edit' ? 'Editar' : 'Nueva' }} Solicitud de Compra</DialogTitle>
                    <DialogDescription>Completa los campos de la solicitud de compra.</DialogDescription>
                </FormDialogHeader>

                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    success-title="Exito"
                    error-title="Error"
                    :error-type="errorType"
                    @close-success="clearSuccess"
                    @close-error="clearError"
                />

                <StatusBanner v-if="isRejected && canRenderForm" tone="danger" title="Solicitud rechazada" class="mx-4 mt-3">
                    <p>
                        Rechazada por <strong>{{ rejectedByName }}</strong>
                        <template v-if="rejectedAtFormatted"> el {{ rejectedAtFormatted }}</template>
                    </p>
                    <p class="mt-1 italic">{{ localForm.rejection_reason || 'Sin motivo registrado.' }}</p>
                </StatusBanner>

                <FormDialogBody class="relative px-4 pr-2" :class="{ 'min-h-[420px]': isBlockingLoading }" style="scrollbar-width: thin">
                    <FormLoadingOverlay
                        :active="isBlockingLoading"
                        :delay-ms="0"
                        message="Cargando solicitud de compra..."
                        description="Preparando consecutivo e items de la solicitud."
                    />

                    <TooltipProvider v-if="canRenderForm">
                        <div class="mt-2 space-y-4 pb-4">
                            <PurchaseTraceability
                                v-if="localForm.id && localForm.status !== 'draft'"
                                :url="`/gastos/solicitudes-compra/${localForm.id}/trazabilidad`"
                            />
                            <div class="space-y-3">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">N° Solicitud</Label>
                                            <HelpTooltip
                                                text="Número único de la solicitud de compra."
                                                aria-label="Ayuda sobre número de solicitud"
                                            />
                                        </div>
                                        <InputField
                                            label=""
                                            v-model="localForm.request_number"
                                            :placeholder="requestNumberPlaceholder"
                                            :readonly="true"
                                        />
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Fecha</Label>
                                            <HelpTooltip text="Fecha de la solicitud." aria-label="Ayuda sobre fecha" />
                                        </div>
                                        <DatePickerField
                                            v-model="requestDateModel"
                                            dusk="input-fecha"
                                            :disabled="isReadOnly"
                                            :clearable="true"
                                            :show-today="true"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Solicitante</Label>
                                            <HelpTooltip
                                                text="Es la persona que crea la solicitud (tú)."
                                                aria-label="Ayuda sobre solicitante"
                                            />
                                        </div>
                                        <InputField
                                            label=""
                                            :model-value="currentRequesterName"
                                            :readonly="true"
                                            placeholder="Nombre del solicitante"
                                        />
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Solicitado a</Label>
                                            <HelpTooltip
                                                text="Área a quien va dirigida esta solicitud para aprobación."
                                                aria-label="Ayuda sobre solicitado a"
                                            />
                                        </div>
                                        <SelectField
                                            label=""
                                            v-model="localForm.solicited_to"
                                            :options="solicitadoAOptions"
                                            :disabled="isReadOnly"
                                            option-label="name"
                                            option-value="id"
                                            placeholder="Selecciona el área"
                                        />
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Encargado de compra</Label>
                                            <HelpTooltip
                                                text="Persona encargada de gestionar esta compra."
                                                aria-label="Ayuda sobre encargado de compra"
                                            />
                                        </div>
                                        <SelectField
                                            label=""
                                            v-model="localForm.buyer_user_id"
                                            :options="userOptions"
                                            :searchable="true"
                                            :loading="isLoadingUsers"
                                            :disabled="isReadOnly"
                                            option-label="name"
                                            option-value="id"
                                            placeholder="Selecciona un encargado"
                                            @search="(t) => fetchUsers(t)"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Proveedor</Label>
                                            <HelpTooltip
                                                text="Escribe el nombre del proveedor para esta solicitud."
                                                aria-label="Ayuda sobre proveedor"
                                            />
                                        </div>
                                        <CreatableSelectField
                                            dusk="input-proveedor"
                                            label=""
                                            :model-value="localForm.provider_name ?? null"
                                            @update:model-value="localForm.provider_name = $event ? String($event) : null"
                                            :options="providerOptions"
                                            :loading="isLoadingProviders"
                                            :server-search="true"
                                            searchable
                                            clearable
                                            allow-custom
                                            custom-option-label='Usar "{value}"'
                                            search-placeholder="Buscar proveedor..."
                                            placeholder="Busca o escribe el nombre del proveedor"
                                            :button-class="'w-full'"
                                            :disabled="isReadOnly"
                                            @search="fetchProviders"
                                        />
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <Label class="text-sm font-medium">Destino</Label>
                                            <HelpTooltip
                                                text="Lugar o departamento de destino para los productos."
                                                aria-label="Ayuda sobre destino"
                                            />
                                        </div>
                                        <InputField
                                            label=""
                                            :model-value="localForm.destination ?? ''"
                                            @update:model-value="localForm.destination = $event || null"
                                            placeholder="Ej: Almacén, Producción, etc."
                                            :readonly="isReadOnly"
                                        />
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Descripción <span class="text-red-500">*</span></Label>
                                        <HelpTooltip
                                            text="Describe brevemente la solicitud de compra y su proposito."
                                            aria-label="Ayuda sobre descripción"
                                        />
                                    </div>
                                    <TextareaField
                                        dusk="input-descripcion"
                                        label=""
                                        :model-value="localForm.description || ''"
                                        @update:model-value="localForm.description = $event"
                                        placeholder="Agrega la descripción y observaciones de la solicitud"
                                        :readonly="isReadOnly"
                                        :rows="3"
                                    />
                                </div>
                            </div>

                            <InventoryDetailTableLayout
                                title="Ítems"
                                description="Agrega los ítems necesarios para esta solicitud"
                                :show-create-line-button="false"
                            >
                                <template #actions>
                                    <Button
                                        v-if="!isReadOnly"
                                        type="button"
                                        variant="outline"
                                        size="pill"
                                        dusk="btn-agregar-item"
                                        class="border-primary/40 bg-background text-primary hover:bg-primary/10 hover:text-primary"
                                        @click="itemsTableRef?.addItem()"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Agregar Item
                                    </Button>
                                </template>
                                <PurchaseRequestItemsTable
                                    ref="itemsTableRef"
                                    :items="localForm.items || []"
                                    :readonly="isReadOnly"
                                    @update:items="localForm.items = $event"
                                />
                            </InventoryDetailTableLayout>
                        </div>
                    </TooltipProvider>
                </FormDialogBody>

                <FormDialogFooter class="justify-end gap-4 bg-background">
                    <Button variant="outline" size="pill" @click="dialogOpen = false">Cancelar</Button>
                </FormDialogFooter>
            </div>
        </FormDialogContent>
    </Dialog>
</template>
