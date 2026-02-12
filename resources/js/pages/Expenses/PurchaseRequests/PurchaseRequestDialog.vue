<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputDate from '@/components/InputDate.vue';
import InputField from '@/components/InputField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import type { PurchaseRequestForm } from '@/domain/purchases';
import axios from 'axios';
import { FileText, Upload, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PurchaseRequestItemsTable from './PurchaseRequestItemsTable.vue';

// Tipo local que extiende PurchaseRequestForm para el formulario del di?logo
type PurchaseRequestDialogForm = Omit<PurchaseRequestForm, 'items' | 'estimated_amount'> & {
    items?: PurchaseRequestForm['items'];
    estimated_amount?: number | null;
    // destination ya est? en PurchaseRequestForm como string | null, no necesitamos sobrescribirlo
    document_path?: string | null;
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

const dialogOpen = ref(props.isOpen);
const localForm = ref<PurchaseRequestDialogForm>(createDefaultForm());
const isLoading = ref(false);
const nextRequestId = ref<number | null>(null);
const isLoadingRequestId = ref(false);
const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();
const originalFormSnapshot = ref('');
const itemsTableRef = ref<InstanceType<typeof PurchaseRequestItemsTable> | null>(null);

// Archivo y preview
const documentFile = ref<File | null>(null);
const documentPreview = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

// Base URL for purchase requests API endpoint
const PURCHASE_REQUESTS_BASE_URL = '/gastos/solicitudes-compra';

// Computed para determinar si se puede editar
const canEdit = computed(() => {
    if (props.readonly) return false;
    if (props.dialogType === 'create') return true;
    // Verificar si el formulario tiene m?todo canEdit o si el status permite edici?n
    const status = localForm.value.status;
    return status === 'draft' || status === 'pending' || !status;
});

const isReadOnly = computed(() => props.readonly || !canEdit.value);

const id = computed(() => {
    if (props.dialogType === 'edit') {
        return props.form?.id?.toString() || localForm.value.id?.toString() || '';
    }
    if (isLoadingRequestId.value) return 'Cargando...';
    if (nextRequestId.value == null) return '';
    return nextRequestId.value.toString();
});

const hasDocument = computed(() => {
    return Boolean(documentFile.value || localForm.value.document_path);
});

const documentUrl = computed(() => {
    if (documentPreview.value) return documentPreview.value;
    if (localForm.value.document_path) {
        return `/storage/${localForm.value.document_path}`;
    }
    return null;
});

const isImageFile = computed(() => {
    if (documentFile.value) {
        return documentFile.value.type.startsWith('image/');
    }
    if (localForm.value.document_path) {
        const ext = localForm.value.document_path.split('.').pop()?.toLowerCase();
        return ['jpg', 'jpeg', 'png', 'webp'].includes(ext || '');
    }
    return false;
});

function createDefaultForm(): PurchaseRequestDialogForm {
    return {
        request_number: '',
        provider_name: null,
        requester_user_id: null,
        date: getTodayDate(),
        destination: '',
        estimated_amount: null,
        description: '',
        status: 'draft', // Por defecto, las nuevas solicitudes se crean en estado 'draft'
        items: [
            {
                item_id: null,
                name: '',
                price: 0,
                quantity: 1,
                description: '',
                total: 0,
            },
        ],
        document_path: null,
    };
}

function getTodayDate() {
    return new Date().toISOString().split('T')[0];
}

function resetForm() {
    if (props.form && Object.keys(props.form).length > 0) {
        const clone = JSON.parse(JSON.stringify(props.form));
        clone.estimated_amount = clone.estimated_amount ?? null;
        clone.request_number = clone.request_number ?? '';

        // normalizar fecha a formato YYYY-MM-DD
        if (clone.date) {
            const parsed = new Date(clone.date);
            if (!Number.isNaN(parsed.getTime())) {
                clone.date = parsed.toISOString().split('T')[0];
            }
        }

        // Parsear items si viene como string JSON
        if (typeof clone.items === 'string') {
            try {
                clone.items = JSON.parse(clone.items);
            } catch {
                clone.items = [];
            }
        }

        // Asegurar que items sea un array con al menos un item
        if (!Array.isArray(clone.items) || clone.items.length === 0) {
            clone.items = [
                {
                    item_id: null,
                    name: '',
                    price: 0,
                    quantity: 1,
                    description: '',
                    total: 0,
                },
            ];
        }

        // Normalizar items para asegurar que tengan los campos correctos
        clone.items = clone.items.map((item: any) => ({
            item_id: item.item_id ?? null,
            name: item.name ?? item.item_name ?? '',
            price: Number(item.price) || 0,
            quantity: Number(item.quantity) || 1,
            description: item.description ?? '',
            total: Number(item.total) || 0,
        }));

        localForm.value = clone;
    } else {
        localForm.value = createDefaultForm();
    }

    // Reset archivo
    documentFile.value = null;
    documentPreview.value = null;

    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';
    originalFormSnapshot.value = stableStringify(localForm.value);
}

async function initializeDialog() {
    resetForm();
    if (props.dialogType === 'create') {
        nextRequestId.value = null;
        await fetchNextRequestId();
    }
}

async function fetchNextRequestId() {
    isLoadingRequestId.value = true;
    try {
        const { data } = await axios.get(`${PURCHASE_REQUESTS_BASE_URL}/data`, {
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
                .reduce(
                    (acc: any, key) => {
                        acc[key] = normalize(val[key]);
                        return acc;
                    },
                    {} as Record<string, unknown>,
                );
        }
        return val;
    };
    return JSON.stringify(normalize(value));
};

function handleFileSelect(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    // Validar tipo de archivo
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        setErrorMessage('Tipo de archivo no permitido. Solo se permiten im?genes (JPEG, PNG, WebP) y PDF.', 'warning');
        return;
    }

    // Validar tama?o (10MB)
    if (file.size > 10 * 1024 * 1024) {
        setErrorMessage('El archivo es demasiado grande. El tama?o m?ximo es 10MB.', 'warning');
        return;
    }

    documentFile.value = file;

    // Crear preview si es imagen
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            documentPreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        documentPreview.value = null;
    }
}

function removeDocument() {
    documentFile.value = null;
    documentPreview.value = null;
    if (localForm.value.document_path) {
        localForm.value.document_path = null;
    }
}

function triggerFileInput() {
    fileInputRef.value?.click();
}

async function handleSubmit(sendToApproval: boolean = false) {
    successMessage.value = '';
    errorMessage.value = '';
    errorType.value = 'error';

    if (!validateForm()) return;

    if (props.dialogType === 'edit' && stableStringify(localForm.value) === originalFormSnapshot.value && !documentFile.value) {
        if (sendToApproval) {
            // Si no hay cambios pero quiere enviar a aprobaci?n, solo enviar
            await handleSendToApproval();
        }
        return;
    }

    isLoading.value = true;

    try {
        const formData = new FormData();

        // A?adir datos del formulario
        const payload: any = {
            ...localForm.value,
        };

        // Eliminar estimated_amount ya que se calcula del total de items
        delete payload.estimated_amount;

        if (props.dialogType === 'create') {
            delete payload.id;
        }

        // A?adir items como JSON string
        if (localForm.value.items && Array.isArray(localForm.value.items) && localForm.value.items.length > 0) {
            formData.append('items', JSON.stringify(localForm.value.items));
        } else {
            // Asegurar que siempre haya al menos un item vac?o
            formData.append(
                'items',
                JSON.stringify([
                    {
                        item_id: null,
                        name: '',
                        price: 0,
                        quantity: 1,
                        description: '',
                        total: 0,
                    },
                ]),
            );
        }

        // A?adir archivo si existe
        if (documentFile.value) {
            formData.append('document', documentFile.value);
        } else if (props.dialogType === 'edit') {
            // Si estamos editando, verificar si se debe eliminar el documento
            // Solo eliminar si hab?a un documento anterior y ahora no hay
            try {
                const originalSnapshot = originalFormSnapshot.value ? JSON.parse(originalFormSnapshot.value) : null;
                const hadDocument = originalSnapshot?.document_path;
                const currentHasDocument = localForm.value.document_path;

                // Si hab?a documento y ahora no hay (ni nuevo archivo ni existente), eliminar
                if (hadDocument && !currentHasDocument && !documentFile.value) {
                    // Enviar como '1' para que Laravel lo interprete como true con $request->boolean()
                    formData.append('remove_document', '1');
                }
            } catch (e) {
                // Si hay error parseando el snapshot, no hacer nada
                console.warn('Error parsing original snapshot:', e);
            }
        }

        // A?adir resto de datos
        Object.keys(payload).forEach((key) => {
            // No incluir remove_document aqu?, ya se maneja arriba
            if (key !== 'items' && key !== 'remove_document' && payload[key] !== null && payload[key] !== undefined) {
                const value = payload[key];
                formData.append(key, typeof value === 'number' ? value.toString() : (value as string));
            }
        });

        // Para FormData, siempre usar POST, pero a?adir _method=PUT para actualizaciones
        if (props.dialogType === 'edit') {
            formData.append('_method', 'PUT');
        }

        const url = props.dialogType === 'create' ? PURCHASE_REQUESTS_BASE_URL : `${PURCHASE_REQUESTS_BASE_URL}/${localForm.value.id}`;

        const response = await axios({
            method: 'POST',
            url,
            data: formData,
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        // Si se guard? correctamente y se debe enviar a aprobaci?n, hacerlo
        if (sendToApproval && response.data?.data?.id) {
            try {
                await axios.post(`${PURCHASE_REQUESTS_BASE_URL}/${response.data.data.id}/send-to-approval`);
                const msg =
                    props.dialogType === 'create'
                        ? 'Solicitud creada y enviada a aprobaci?n correctamente.'
                        : 'Solicitud actualizada y enviada a aprobaci?n correctamente.';
                emit('refresh', msg);
                showSuccess(msg);
                dialogOpen.value = false;
                return;
            } catch (approvalError) {
                // Si falla el env?o a aprobaci?n, al menos se guard?
                const msg =
                    props.dialogType === 'create'
                        ? 'Solicitud creada correctamente, pero no se pudo enviar a aprobaci?n.'
                        : 'Solicitud actualizada correctamente, pero no se pudo enviar a aprobaci?n.';
                showError(msg);
                emit('refresh', msg);
                dialogOpen.value = false;
                return;
            }
        }

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

async function handleSendToApproval() {
    if (!localForm.value.id) {
        setErrorMessage('Debes guardar la solicitud antes de enviarla a aprobaci?n.', 'warning');
        return;
    }

    const status = localForm.value.status;
    if (status !== 'draft') {
        setErrorMessage('Solo las solicitudes en borrador pueden enviarse a aprobaci?n.', 'warning');
        return;
    }

    if (!confirm('?Est?s seguro de enviar esta solicitud a aprobaci?n?')) {
        return;
    }

    isLoading.value = true;
    try {
        await axios.post(`${PURCHASE_REQUESTS_BASE_URL}/${localForm.value.id}/send-to-approval`);
        const msg = 'Solicitud enviada a aprobaci?n correctamente.';
        emit('refresh', msg);
        showSuccess(msg);
        dialogOpen.value = false;
    } catch (e) {
        const serverMessage = (e as any)?.response?.data?.message || 'Error al enviar la solicitud a aprobaci?n. Por favor intenta nuevamente.';
        setErrorMessage(serverMessage);
        emit('error', serverMessage);
        console.error('Error enviando a aprobaci?n:', e);
    } finally {
        isLoading.value = false;
    }
}

const canSendToApproval = computed(() => {
    if (props.readonly) return false;
    const status = localForm.value.status;
    return status === 'draft' || status === undefined || status === null;
});

function validateForm() {
    if (!localForm.value.description || !String(localForm.value.description).trim()) {
        setErrorMessage('La descripci?n es obligatoria.', 'warning');
        return false;
    }

    const itemsCheck = (localForm.value.items || []).filter((it) => Number(it.quantity) > 0);
    if (!itemsCheck.length) {
        setErrorMessage('Debe agregar al menos un ?tem con cantidad mayor a cero a la solicitud.', 'warning');
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

watch(
    () => localForm.value.items,
    (items) => {
        if (items) {
            localForm.value.items = items;
        }
    },
    { deep: true },
);
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="flex h-[95vh] max-h-[95vh] w-full max-w-[95vw] flex-col p-0">
            <DialogHeader class="px-4 pb-3 pt-4">
                <DialogTitle>{{ dialogType === 'edit' ? 'Editar' : 'Nueva' }} Solicitud de Compra</DialogTitle>
                <DialogDescription> Completa los campos de la solicitud de compra. </DialogDescription>
            </DialogHeader>

            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                success-title="?xito"
                error-title="Error"
                :error-type="errorType"
                @close-success="clearSuccess"
                @close-error="clearError"
            />

            <div class="flex-1 overflow-y-auto scroll-smooth px-4 pr-2" style="scrollbar-width: thin">
                <TooltipProvider>
                    <form
                        @submit.prevent="handleSubmit()"
                        :class="{ 'pointer-events-none opacity-50': dialogType === 'create' && isLoadingRequestId }"
                        class="space-y-4"
                    >
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium"># Solicitud</Label>
                                        <HelpTooltip text="N?mero ?nico de la solicitud de compra." aria-label="Ayuda sobre n?mero de solicitud" />
                                    </div>
                                    <InputField
                                        label=""
                                        v-model="localForm.request_number"
                                        :placeholder="id ? `SOL-${id}` : 'SOL-001'"
                                        :readonly="true"
                                    />
                                    <span v-if="dialogType === 'create' && isLoadingRequestId" class="text-xs text-muted-foreground">
                                        Generando n?mero...
                                    </span>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Fecha</Label>
                                        <HelpTooltip text="Fecha de la solicitud." aria-label="Ayuda sobre fecha" />
                                    </div>
                                    <InputDate v-model="localForm.date" :readonly="isReadOnly" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Proveedor</Label>
                                        <HelpTooltip text="Escribe el nombre del proveedor para esta solicitud." aria-label="Ayuda sobre proveedor" />
                                    </div>
                                    <InputField
                                        label=""
                                        :model-value="localForm.provider_name ?? ''"
                                        @update:model-value="localForm.provider_name = $event || null"
                                        placeholder="Escribe el nombre del proveedor"
                                        :readonly="isReadOnly"
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
                                        placeholder="Ej: Almac?n, Producci?n, etc."
                                        :readonly="isReadOnly"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium"> Descripci?n <span class="text-red-500">*</span> </Label>
                                    <HelpTooltip
                                        text="Describe brevemente la solicitud de compra y su prop?sito."
                                        aria-label="Ayuda sobre descripci?n"
                                    />
                                </div>
                                <TextareaField
                                    label=""
                                    :model-value="localForm.description || ''"
                                    @update:model-value="localForm.description = $event"
                                    placeholder="Agrega la descripci?n y observaciones de la solicitud"
                                    :readonly="isReadOnly"
                                    :rows="3"
                                />
                            </div>
                        </div>

                        <div class="border-t border-border"></div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold">Items</h3>
                                    <p class="text-xs text-muted-foreground">Agrega los items necesarios para esta solicitud</p>
                                </div>
                                <Button v-if="!isReadOnly" type="button" variant="outline" size="sm" @click="itemsTableRef?.addItem()">
                                    <span class="mr-1">+</span>
                                    Agregar Item
                                </Button>
                            </div>
                            <PurchaseRequestItemsTable
                                ref="itemsTableRef"
                                :items="localForm.items || []"
                                :readonly="isReadOnly"
                                @update:items="localForm.items = $event"
                            />
                        </div>

                        <!-- Separador -->
                        <div class="border-t border-border"></div>

                        <!-- Documento adjunto -->
                        <div class="space-y-2">
                            <div>
                                <h3 class="text-sm font-semibold">Documento Adjunto</h3>
                                <p class="text-xs text-muted-foreground">Adjunta un documento o imagen relacionado con la solicitud (opcional)</p>
                            </div>

                            <input ref="fileInputRef" type="file" accept="image/*,application/pdf" class="hidden" @change="handleFileSelect" />

                            <div
                                v-if="!hasDocument"
                                class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-border bg-muted/30 p-4"
                            >
                                <Upload class="mb-2 h-8 w-8 text-muted-foreground" />
                                <p class="mb-2 text-center text-xs text-muted-foreground">Arrastra un archivo aqu? o haz clic para seleccionar</p>
                                <Button type="button" variant="outline" size="sm" @click="triggerFileInput" :disabled="isReadOnly">
                                    Seleccionar Archivo
                                </Button>
                                <p class="mt-1 text-center text-xs text-muted-foreground">Formatos: JPEG, PNG, WebP, PDF (m?x. 10MB)</p>
                            </div>

                            <div v-else class="space-y-2">
                                <div class="flex items-center gap-3 rounded-lg border border-border bg-card p-2">
                                    <div v-if="isImageFile && documentUrl" class="flex-shrink-0">
                                        <img :src="documentUrl" alt="Preview" class="h-16 w-16 rounded border border-border object-cover" />
                                    </div>
                                    <div v-else class="flex-shrink-0">
                                        <div class="flex h-16 w-16 items-center justify-center rounded border border-border bg-muted">
                                            <FileText class="h-5 w-5 text-muted-foreground" />
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">
                                            {{ documentFile?.name || 'Documento adjunto' }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ documentFile ? `${(documentFile.size / 1024).toFixed(2)} KB` : 'Archivo existente' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button v-if="!isReadOnly" type="button" variant="outline" size="sm" @click="triggerFileInput">
                                            Cambiar
                                        </Button>
                                        <Button v-if="!isReadOnly" type="button" variant="ghost" size="icon" @click="removeDocument">
                                            <X class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </TooltipProvider>
            </div>

            <DialogFooter class="flex justify-end gap-2 border-t px-4 py-3">
                <Button type="button" variant="outline" @click="dialogOpen = false"> Cancelar </Button>
                <Button v-if="!props.readonly && !isReadOnly" type="button" :disabled="isLoading" @click="handleSubmit(false)">
                    {{ isLoading ? 'Guardando...' : 'Guardar' }}
                </Button>
                <Button v-if="!props.readonly && !isReadOnly && canSendToApproval" type="button" :disabled="isLoading" @click="handleSubmit(true)">
                    {{ isLoading ? 'Guardando...' : 'Guardar y enviar a aprobaci?n' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
