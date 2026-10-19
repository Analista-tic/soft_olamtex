<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import CreatableSelectField from '@/components/CreatableSelectField.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import Label from '@/components/ui/label/Label.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAlertStack } from '@/composables/useAlert';
import { useAuthStore } from '@/stores/auth';
import type { PurchaseRequestForm, PurchaseRequestItem } from '@/domain/Expenses';
import { canEditRequest as canEditRequestRule } from '@/domain/Expenses/rules';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import PurchaseRequestItemsTable from './PurchaseRequestItemsTable.vue';

// El tipo de dominio `PurchaseRequestForm` no incluye los campos de solo-lectura que
// llegan del backend (nombres a mostrar, área destino de aprobación, encargado de compra).
// Se extiende localmente igual que ya hace PurchaseRequestDialog.vue.
type PurchaseRequestFormExtended = Omit<PurchaseRequestForm, 'items' | 'estimated_amount'> & {
    items?: PurchaseRequestItem[];
    estimated_amount?: number | null;
    requester?: { name?: string };
    buyer?: { id?: number; name?: string } | null;
    solicited_to?: 'gerencia' | 'logistica' | null;
    buyer_user_id?: number | null;
};

const props = withDefaults(defineProps<{ mode?: 'create' | 'edit'; form?: Partial<PurchaseRequestForm> | null }>(), {
    mode: 'create',
    form: null,
});

const mode = computed(() => props.mode || 'create');

const PURCHASE_REQUESTS_BASE_URL = '/gastos/solicitudes-compra';

const authStore = useAuthStore();
const { successMessage, errorMessage, errorType, showError, clearSuccess, clearError } = useAlertStack();

const localForm = ref<PurchaseRequestFormExtended>(createDefaultForm());
const isLoading = ref(false);
const isSubmitting = ref(false);
const users = ref<{ id: number; name: string; area_name: string }[]>([]);
const isLoadingUsers = ref(false);
const providerOptions = ref<{ id: string; name: string }[]>([]);
const isLoadingProviders = ref(false);
const descriptionError = ref(false);
const itemsTableRef = ref<InstanceType<typeof PurchaseRequestItemsTable> | null>(null);

function createItemUid(): string {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID();
    }

    return `purchase-request-item-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function getTodayDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function createDefaultForm(): PurchaseRequestFormExtended {
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

// La ruta /editar solo debería alcanzarse con una solicitud en borrador (Index.vue ya
// filtra el botón Editar con esta misma regla), pero se deja la guarda por si se llega
// por URL directa a una solicitud que cambió de estado mientras tanto.
const isReadOnly = computed(() => mode.value === 'edit' && !canEditRequestRule(localForm.value?.status ?? props.form?.status ?? 'draft'));

const canSendToApproval = computed(() => canEditRequestRule(localForm.value.status || 'draft'));

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

const requestNumberPlaceholder = computed(() => {
    if (mode.value === 'edit') {
        return props.form?.request_number || localForm.value.request_number || '';
    }

    // El consecutivo lo asigna el servidor al guardar: mostrar uno "sugerido" se repetía entre usuarios simultáneos.
    return 'Se asigna al guardar';
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

function appendFormDataValue(formData: FormData, key: string, value: unknown) {
    if (value === null || value === undefined || value === '') {
        return;
    }

    formData.append(key, typeof value === 'number' ? value.toString() : String(value));
}

function appendItemsToFormData(formData: FormData, items: PurchaseRequestItem[]) {
    items.forEach((item, index) => {
        appendFormDataValue(formData, `items[${index}][uid]`, item.uid);
        appendFormDataValue(formData, `items[${index}][item_id]`, item.item_id);
        appendFormDataValue(formData, `items[${index}][name]`, item.name ?? '');
        appendFormDataValue(formData, `items[${index}][price]`, Number(item.price) || 0);
        appendFormDataValue(formData, `items[${index}][quantity]`, Number(item.quantity) || 0);
        appendFormDataValue(formData, `items[${index}][description]`, item.description ?? '');
        appendFormDataValue(formData, `items[${index}][total]`, Number(item.total) || 0);

        if (item.total_tax != null) {
            appendFormDataValue(formData, `items[${index}][total_tax]`, Number(item.total_tax) || 0);
        }

        if (item._delete_image) {
            formData.append(`items[${index}][_delete_image]`, '1');
        }

        if (item.image_file) {
            formData.append(`items[${index}][image]`, item.image_file);
        }
    });
}

function validateForm(): boolean {
    descriptionError.value = false;
    if (!localForm.value.description || !String(localForm.value.description).trim()) {
        descriptionError.value = true;
        showError('La descripción es obligatoria.', 'warning');
        return false;
    }

    const items = Array.isArray(localForm.value.items) ? localForm.value.items : [];
    if (items.length < 1) {
        showError('Debe agregar al menos un ítem a la solicitud.', 'warning');
        return false;
    }

    return true;
}

function initializeFromForm() {
    const f = props.form as PurchaseRequestFormExtended | null;
    if (!f) return;
    const clone: PurchaseRequestFormExtended = JSON.parse(JSON.stringify(f));
    clone.date = normalizeIsoDate(clone.date, getTodayDate()) || getTodayDate();
    clone.items = Array.isArray(clone.items) ? clone.items.map((item) => normalizeItem(item)) : [];
    localForm.value = clone;
}

function handleAddLine() {
    itemsTableRef.value?.addItem?.();
}

function cancel() {
    router.visit(PURCHASE_REQUESTS_BASE_URL);
}

async function handleSubmit(sendToApproval = false) {
    clearError();
    if (!validateForm()) return;

    isSubmitting.value = true;

    try {
        const formData = new FormData();
        const payload: Record<string, unknown> = {
            ...localForm.value,
            date: normalizeIsoDate(localForm.value.date, getTodayDate()) || getTodayDate(),
        };

        // El backend no acepta `status` en PUT: en edición solo se guarda el borrador y
        // luego se envía a aprobación con un segundo request (mismo patrón que el diálogo).
        if (mode.value === 'create') {
            payload.status = sendToApproval ? 'pending' : 'draft';
        } else {
            delete payload.status;
        }

        delete payload.estimated_amount;
        delete payload.items;
        if (mode.value === 'create') {
            delete payload.id;
        }

        appendItemsToFormData(formData, Array.isArray(localForm.value.items) ? localForm.value.items : []);

        Object.keys(payload).forEach((key) => {
            appendFormDataValue(formData, key, payload[key]);
        });

        if (mode.value === 'edit') {
            formData.append('_method', 'PUT');
        }

        const requestId = props.form?.id ?? localForm.value.id;
        const url = mode.value === 'create' ? PURCHASE_REQUESTS_BASE_URL : `${PURCHASE_REQUESTS_BASE_URL}/${requestId}`;

        await axios({
            method: 'POST',
            url,
            data: formData,
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        if (sendToApproval && mode.value === 'edit') {
            await axios.post(`${PURCHASE_REQUESTS_BASE_URL}/${requestId}/send-to-approval`);
        }

        router.visit(PURCHASE_REQUESTS_BASE_URL);
    } catch (error: any) {
        const serverMessage =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors || {})?.flat()?.[0] ||
            'Error al guardar la solicitud. Por favor intenta nuevamente.';
        showError(serverMessage);
        console.error('Error guardando solicitud:', error);
    } finally {
        isSubmitting.value = false;
    }
}

onMounted(async () => {
    isLoading.value = true;
    try {
        await Promise.all([fetchUsers(), fetchProviders()]);
        if (mode.value !== 'create' && props.form) {
            initializeFromForm();
        }
    } finally {
        isLoading.value = false;
    }
});
</script>

<template>
    <Head :title="mode === 'create' ? 'Nueva Solicitud de Compra' : 'Editar Solicitud de Compra'" />
    <AppLayout
        :breadcrumbs="
            mode === 'create'
                ? [{ title: 'Solicitudes de Compra', href: '/gastos/solicitudes-compra' }, { title: 'Nueva Solicitud', href: '' }]
                : [
                      { title: 'Solicitudes de Compra', href: '/gastos/solicitudes-compra' },
                      { title: localForm.request_number || props.form?.request_number || 'Editar', href: '' },
                  ]
        "
    >
        <div class="flex flex-col gap-4 p-3">
            <div class="flex flex-col gap-1">
                <h1 class="text-lg font-bold">{{ mode === 'create' ? 'Nueva Solicitud de Compra' : 'Editar Solicitud de Compra' }}</h1>
                <p class="text-sm text-muted-foreground">Completa los campos y añade los ítems de la solicitud.</p>
            </div>

            <AlertStack :successMessage="successMessage" :errorMessage="errorMessage" :errorType="errorType" @close-success="clearSuccess" @close-error="clearError" />

            <p v-if="isReadOnly" class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800" dusk="solicitud-solo-lectura">
                Esta solicitud ya no se puede modificar (no está en borrador).
            </p>

            <fieldset :disabled="isReadOnly" class="m-0 min-w-0 space-y-6 border-0 p-0">
                <div class="space-y-3 rounded-xl border border-sidebar-border/70 p-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">N° Solicitud</Label>
                                <HelpTooltip text="Número único de la solicitud de compra." aria-label="Ayuda sobre número de solicitud" />
                            </div>
                            <InputField label="" :model-value="requestNumberPlaceholder" readonly />
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">Fecha</Label>
                                <HelpTooltip text="Fecha de la solicitud." aria-label="Ayuda sobre fecha" />
                            </div>
                            <DatePickerField v-model="requestDateModel" dusk="input-fecha" :disabled="isReadOnly" :clearable="true" :show-today="true" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">Solicitante</Label>
                                <HelpTooltip text="Es la persona que crea la solicitud (tú)." aria-label="Ayuda sobre solicitante" />
                            </div>
                            <InputField label="" :model-value="currentRequesterName" :readonly="true" placeholder="Nombre del solicitante" />
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">Solicitado a</Label>
                                <HelpTooltip text="Área a quien va dirigida esta solicitud para aprobación." aria-label="Ayuda sobre solicitado a" />
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
                                <HelpTooltip text="Persona encargada de gestionar esta compra." aria-label="Ayuda sobre encargado de compra" />
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
                                <HelpTooltip text="Escribe el nombre del proveedor para esta solicitud." aria-label="Ayuda sobre proveedor" />
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
                                <HelpTooltip text="Lugar o departamento de destino para los productos." aria-label="Ayuda sobre destino" />
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
                            <HelpTooltip text="Describe brevemente la solicitud de compra y su proposito." aria-label="Ayuda sobre descripción" />
                        </div>
                        <TextareaField
                            dusk="input-descripcion"
                            label=""
                            :model-value="localForm.description || ''"
                            @update:model-value="
                                localForm.description = $event;
                                descriptionError = false;
                            "
                            :error="descriptionError ? 'Este campo es obligatorio.' : false"
                            placeholder="Agrega la descripción y observaciones de la solicitud"
                            :readonly="isReadOnly"
                            :rows="3"
                        />
                    </div>
                </div>

                <InventoryDetailTableLayout title="Ítems" description="Agrega los ítems necesarios para esta solicitud" :show-create-line-button="false">
                    <template #actions>
                        <Button
                            v-if="!isReadOnly"
                            type="button"
                            variant="outline"
                            size="pill"
                            dusk="btn-agregar-item"
                            class="border-primary/40 bg-background text-primary hover:bg-primary/10 hover:text-primary"
                            @click="handleAddLine"
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
            </fieldset>

            <div class="flex justify-end gap-2 pt-2">
                <Button variant="outline" size="pill" @click="cancel">{{ isReadOnly ? 'Volver' : 'Cancelar' }}</Button>
                <Button v-if="!isReadOnly" variant="default" size="pill" dusk="btn-guardar-borrador" :disabled="isSubmitting" @click.prevent="handleSubmit(false)">
                    {{ isSubmitting ? 'Guardando...' : 'Guardar borrador' }}
                </Button>
                <Button
                    v-if="!isReadOnly && canSendToApproval"
                    variant="default"
                    size="pill"
                    dusk="btn-guardar-y-enviar"
                    :disabled="isSubmitting"
                    @click.prevent="handleSubmit(true)"
                >
                    {{ isSubmitting ? 'Guardando...' : 'Guardar y enviar a aprobación' }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
