<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';
import PillTabs from '@/components/common/PillTabs.vue';
import { Tabs, TabsContent } from '@/components/ui/tabs';
import type { AdditionalContact, Contact } from '@/domain/MasterData';
import type { Branch } from '@/domain/shared';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { useMasterDataStore } from '@/stores/masterData/masterData';
import { computed, nextTick, ref, watch } from 'vue';
import AdditionalContactsList from './AdditionalContactsList.vue';
import BranchesList from './BranchesList.vue';
import ContactForm from './ContactForm.vue';

interface ExtendedContact extends Contact {
    branches?: Branch[];
    additional_contacts?: AdditionalContact[];
}

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    contactData: Partial<ExtendedContact>;
    externalError?: string;
    externalErrorType?: 'success' | 'error' | 'info' | 'warning';
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', contactData: Partial<Contact>): void;
    (e: 'update:contactData', updatedContact: Partial<Contact>): void;
    (e: 'loading-state', value: boolean): void;
}>();

const internalIsOpen = ref(props.isOpen);
const isCatalogBootstrapLoading = ref(false);
const isContactHydrationLoading = ref(false);
const additionalContactsListRef = ref<InstanceType<typeof AdditionalContactsList> | null>(null);
const branchesListRef = ref<InstanceType<typeof BranchesList> | null>(null);
const masterDataStore = useMasterDataStore();
const blockingLoadErrorMessage = ref('');

const hasBlockingLoadError = computed(() => blockingLoadErrorMessage.value.trim() !== '');
const isBlockingLoading = computed(() => isCatalogBootstrapLoading.value || isContactHydrationLoading.value);
const canRenderForm = computed(() => !isBlockingLoading.value && !hasBlockingLoadError.value);
const dialogTitle = computed(() => (props.dialogType === 'create' ? 'Crear Contacto' : 'Editar Contacto'));
const shouldEmitLoadingState = computed(() => internalIsOpen.value && isBlockingLoading.value);

const form = ref<Partial<ExtendedContact>>({
    ...props.contactData,
    additional_contacts: props.contactData.additional_contacts ?? [],
    branches: props.contactData.branches ?? [],
});

const errors = ref<Record<string, string[]>>({});
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('error');

watch(
    () => props.isOpen,
    async (newValue) => {
        internalIsOpen.value = newValue;

        if (!newValue) {
            return;
        }

        await initializeDialog();
    },
    { immediate: true },
);

watch(internalIsOpen, (newValue) => {
    if (!newValue) {
        isCatalogBootstrapLoading.value = false;
        isContactHydrationLoading.value = false;
        emit('close');
    }
});

watch(
    shouldEmitLoadingState,
    (value) => {
        emit('loading-state', value);
    },
    { immediate: true },
);

watch(
    () => props.externalError,
    (msg) => {
        if (!msg) {
            return;
        }

        alertType.value = props.externalErrorType || 'warning';
        alertMessage.value = msg;

        const dialogEl = document.querySelector('[role="dialog"]');
        dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
    },
);

const fieldLabels: Record<string, string> = {
    email: 'Correo electrónico',
    id_number: 'Número de identificación',
    contact_type: 'Tipo de contacto',
    groups: 'Categoría proveedor/cliente',
    id_type: 'Tipo de identificacion',
    full_name: 'Nombre completo o razón social',
    person_type: 'Tipo de persona',
    department: 'Departamento',
    municipality: 'Municipio',
};

const requiredFieldKeys: (keyof Partial<Contact>)[] = [
    'contact_type',
    'groups',
    'id_type',
    'id_number',
    'full_name',
    'person_type',
    'department',
    'email',
];


const resetForm = () => {
    form.value = {
        ...props.contactData,
        additional_contacts: props.contactData.additional_contacts ?? [],
        branches: props.contactData.branches ?? [],
    };
};

async function bootstrapCatalogs() {
    isCatalogBootstrapLoading.value = true;

    try {
        await masterDataStore.fetchGroups(true);

        if (masterDataStore.groupsError) {
            blockingLoadErrorMessage.value = masterDataStore.groupsError;
            return;
        }

        if (!masterDataStore.groupsLoaded && masterDataStore.groups.length === 0) {
            blockingLoadErrorMessage.value = 'No se pudieron cargar los catalogos del formulario de contacto.';
        }
    } finally {
        isCatalogBootstrapLoading.value = false;
    }
}

async function initializeDialog() {
    isContactHydrationLoading.value = true;
    blockingLoadErrorMessage.value = '';

    try {
        await bootstrapCatalogs();
        resetForm();
        alertMessage.value = '';
        errors.value = {};

        if (blockingLoadErrorMessage.value) {
            alertType.value = 'error';
            alertMessage.value = blockingLoadErrorMessage.value;
        }

        await nextTick();
    } finally {
        isContactHydrationLoading.value = false;
    }
}

const closeDialog = () => {
    internalIsOpen.value = false;
};

const translateValidationMessage = (field: string, msg: string): string => {
    const lower = msg.toLowerCase();
    const label = fieldLabels[field] || 'Campo';

    if (lower.includes('email') && lower.includes('already been taken')) {
        return 'El correo electrónico ya está registrado.';
    }

    if ((lower.includes('id') || lower.includes('number')) && lower.includes('already been taken')) {
        return 'El número de identificación ya está registrado.';
    }

    if (field === 'email' && lower.includes('unico')) {
        return 'El correo electrónico ya está registrado.';
    }

    if (field === 'id_number' && lower.includes('unico')) {
        return 'El número de identificación ya está registrado.';
    }

    if (lower.includes('required')) {
        return `${label}: es obligatorio.`;
    }

    if (lower.includes('has already been taken')) {
        return 'Este valor ya está registrado.';
    }

    return msg;
};

const getMissingRequiredFields = () => {
    const isEmptyValue = (value: unknown) => {
        if (Array.isArray(value)) {
            return value.length === 0;
        }

        if (value === null || value === undefined) {
            return true;
        }

        if (typeof value === 'string') {
            return value.trim() === '';
        }

        return false;
    };

    return requiredFieldKeys.filter((field) => isEmptyValue((form.value as any)[field]));
};

const clearFieldError = (field: string) => {
    delete errors.value[field];
};

const handleErrors = (errorResponse: any) => {
    const validationErrors = errorResponse?.response?.data?.errors;

    if (validationErrors) {
        errors.value = validationErrors;

        const translated = Object.entries(validationErrors)
            .flatMap(([field, msgs]) => (msgs as string[]).map((message) => translateValidationMessage(field, message)))
            .join('. ');

        alertType.value = 'warning';
        alertMessage.value = translated || 'Error de validacion.';

        const dialogEl = document.querySelector('[role="dialog"]');
        dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    alertType.value = 'error';
    alertMessage.value = 'Ocurrio un error inesperado. Intenta nuevamente.';
    console.error('Error inesperado:', errorResponse);
};

const hasDuplicateAdditionalContacts = () => {
    const contacts = form.value.additional_contacts ?? [];
    const seen = new Set<string>();

    for (const contact of contacts) {
        const name = (contact.name ?? '').trim().toLowerCase();
        const email = (contact.email ?? '').trim().toLowerCase();
        const phone = (contact.phone ?? '').trim();

        if (!name && !email && !phone) continue;

        const key = `${name}|${email}|${phone}`;
        if (seen.has(key)) {
            return true;
        }

        seen.add(key);
    }

    return false;
};

const hasDuplicateBranches = () => {
    const branches = form.value.branches ?? [];
    const seen = new Set<string>();

    for (const branch of branches) {
        const name = (branch.name ?? '').trim().toLowerCase();
        const address = (branch.address ?? '').trim().toLowerCase();
        const phone = (branch.phone ?? '').trim();

        if (!name && !address && !phone) continue;

        const key = `${name}|${address}|${phone}`;
        if (seen.has(key)) {
            return true;
        }

        seen.add(key);
    }

    return false;
};

const submitForm = async () => {
    if (isBlockingLoading.value || hasBlockingLoadError.value) {
        return;
    }

    try {
        const missingFields = getMissingRequiredFields();

        if (missingFields.length > 0) {
            errors.value = Object.fromEntries(missingFields.map((field) => [field, ['Este campo es obligatorio.']]));
            alertMessage.value = formatRequiredFieldsMessage(
                'Se debe completar todos los campos obligatorios',
                missingFields.map((key) => fieldLabels[key as string] || String(key)),
            );
            alertType.value = 'warning';

            const dialogEl = document.querySelector('[role="dialog"]');
            dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        if (!form.value.full_name?.trim()) {
            errors.value.full_name = ['El nombre completo es obligatorio.'];
            return;
        }

        const invalidAdditionalContacts = form.value.additional_contacts?.some(
            (contact) => !contact.name?.trim() || !contact.email?.trim(),
        );

        if (invalidAdditionalContacts) {
            errors.value.additional_contacts = ['Todos los contactos adicionales deben tener nombre y correo validos.'];
            return;
        }

        if (hasDuplicateAdditionalContacts()) {
            additionalContactsListRef.value?.showDuplicateAlert();
            return;
        }

        if (hasDuplicateBranches()) {
            branchesListRef.value?.showDuplicateAlert();
            return;
        }

        await emit('submit', {
            ...form.value,
            additional_contacts: form.value.additional_contacts ?? [],
        });

        errors.value = {};
    } catch (error) {
        handleErrors(error);
    }
};

const updateAdditionalContacts = (contacts: AdditionalContact[]) => {
    form.value.additional_contacts = contacts;
    emit('update:contactData', { ...form.value });
};

const updateBranches = (branches: Branch[]) => {
    form.value.branches = branches;
    emit('update:contactData', { ...form.value });
};
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <FormDialogContent
            size="lg"
            class="flex max-h-[95vh] max-w-[75vw] flex-col px-2 py-2"
            :scrollable="false"
        >
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ dialogType === 'create' ? 'crear' : 'editar' }} un contacto. Haz clic en
                    {{ dialogType === 'create' ? 'crear' : 'actualizar' }} cuando termines.
                </DialogDescription>
            </DialogHeader>

            <form class="flex min-h-0 flex-1 flex-col" novalidate @submit.prevent="submitForm">
                <div class="relative flex-1 overflow-y-auto scroll-smooth px-5 py-4" :class="{ 'min-h-[420px]': isBlockingLoading }" style="scrollbar-width: thin">
                    <FormLoadingOverlay
                        :active="isBlockingLoading"
                        :delay-ms="0"
                        message="Cargando formulario de contacto..."
                        description="Preparando catalogos y datos iniciales necesarios."
                    />

                    <AlertStack :error-message="alertMessage" :error-type="alertType" @close-error="alertMessage = ''" />

                    <fieldset v-if="canRenderForm" :disabled="isBlockingLoading" class="space-y-3">
                        <Tabs default-value="basic-info" class="pt-1">
                            <PillTabs
                                size="sm"
                                :tabs="[
                                    { value: 'basic-info', label: 'Información básica' },
                                    { value: 'additional-info', label: 'Contactos adicionales' },
                                    { value: 'branches', label: 'Sucursales' },
                                ]"
                            />

                            <TabsContent value="basic-info">
                                <ContactForm v-model:form="form" :errors="errors" class="pb-6" @clear-error="clearFieldError" />
                            </TabsContent>

                            <TabsContent value="additional-info">
                                <AdditionalContactsList
                                    ref="additionalContactsListRef"
                                    :additionalContacts="form.additional_contacts ?? []"
                                    @update:additionalContacts="updateAdditionalContacts"
                                />
                                <p v-if="errors.additional_contacts" class="mt-2 text-sm text-destructive">
                                    {{ errors.additional_contacts[0] }}
                                </p>
                            </TabsContent>

                            <TabsContent value="branches">
                                <BranchesList ref="branchesListRef" :branches="form.branches ?? []" @update:branches="updateBranches" />
                            </TabsContent>
                        </Tabs>
                    </fieldset>

                    <div v-else-if="hasBlockingLoadError" class="flex min-h-[240px] items-center justify-center text-center text-sm text-muted-foreground">
                        {{ blockingLoadErrorMessage }}
                    </div>
                </div>
            </form>

            <DialogFooter class="justify-end gap-2 border-t border-border px-5 py-4">
                <Button variant="outline" size="pill" @click="closeDialog">Cancelar</Button>
                <Button type="button" variant="default" size="pill" class="shadow-sm" :disabled="isBlockingLoading || hasBlockingLoadError" @click="submitForm">
                    {{ dialogType === 'create' ? 'Crear' : 'Actualizar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
