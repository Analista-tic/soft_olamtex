<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { AdditionalContact, Contact } from '@/domain/MasterData';
import type { Branch } from '@/domain/shared';
import { computed, ref, watch } from 'vue';
import AdditionalContactsList from './AdditionalContactsList.vue';
import ContactForm from './ContactForm.vue';
import AlertBox from '@/components/AlertBox.vue';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'; // Importar tabs de shadcn
import BranchesList from './BranchesList.vue'; // Componente para manejar sucursales
import { formatRequiredFieldsMessage } from '@/lib/alert-format';

// Extender el tipo Contact para incluir las propiedades adicionales
interface ExtendedContact extends Contact {
    branches?: Branch[];
    additional_contacts?: AdditionalContact[];
}

// Cambiar el tipo de `contactData` y `form` para usar ExtendedContact
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
    (e: 'update:contactData', updatedContact: Partial<Contact>): void; // Agregar este evento
}>();

// Estado interno del diálogo
const internalIsOpen = ref(props.isOpen);
const additionalContactsListRef = ref<InstanceType<typeof AdditionalContactsList> | null>(null);
const branchesListRef = ref<InstanceType<typeof BranchesList> | null>(null);

// Formulario reactivo con valores iniciales
const form = ref<Partial<ExtendedContact>>({
    ...props.contactData,
    additional_contacts: props.contactData.additional_contacts ?? [],
    branches: props.contactData.branches ?? [],
});

// Título del diálogo basado en el tipo
const dialogTitle = computed(() => (props.dialogType === 'create' ? 'Crear Contacto' : 'Editar Contacto'));

// Watchers para sincronización
watch(
    () => props.isOpen,
    (newValue) => {
        internalIsOpen.value = newValue;
        if (newValue) {
            resetForm();
            showEmptyAlert.value = false;
            alertMessage.value = '';
            errors.value = {};
        }
    },
);

watch(internalIsOpen, (newValue) => {
    if (!newValue) {
        emit('close');
    }
});

// Resetear el formulario con los datos actuales
const resetForm = () => {
    form.value = {
        ...props.contactData,
        additional_contacts: props.contactData.additional_contacts ?? [],
    };
};

const closeDialog = () => {
    internalIsOpen.value = false;
};

const errors = ref<Record<string, string[]>>({}); // Manejo de errores
const showEmptyAlert = ref(false);
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('error');

const fieldLabels: Record<string, string> = {
    email: 'Correo electrónico',
    id_number: 'Número de identificación',
    contact_type: 'Tipo de contacto',
    groups: 'Categoría proveedor/cliente',
    id_type: 'Tipo de identificación',
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
const requiredFieldLabels = requiredFieldKeys.map((key) => fieldLabels[key as string] || String(key));
const emptyFieldsMessage = formatRequiredFieldsMessage(
    'Se debe completar todos los campos obligatorios',
    requiredFieldLabels,
);

const translateValidationMessage = (field: string, msg: string): string => {
    const lower = msg.toLowerCase();
    const label = fieldLabels[field] || 'Campo';
    if (lower.includes('email') && lower.includes('already been taken')) {
        return 'El correo electrónico ya está registrado.';
    }
    if ((lower.includes('id') || lower.includes('number')) && lower.includes('already been taken')) {
        return 'El número de identificación ya está registrado.';
    }
    if (field === 'email' && lower.includes('único')) {
        return 'El correo electrónico ya está registrado.';
    }
    if (field === 'id_number' && lower.includes('único')) {
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

watch(
    () => props.externalError,
    (msg) => {
        if (msg) {
            alertType.value = props.externalErrorType || 'warning';
            alertMessage.value = msg;
            const dialogEl = document.querySelector('[role="dialog"]');
            dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
        }
    },
);

const areRequiredFieldsEmpty = () => {
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

    return requiredFieldKeys.every((field) => isEmptyValue((form.value as any)[field]));
};

const submitForm = async () => {
    try {
        // Aviso temprano si todos los campos obligatorios están vacíos
        if (areRequiredFieldsEmpty()) {
            showEmptyAlert.value = true;
            const dialogEl = document.querySelector('[role="dialog"]');
            dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        // Validación básica antes de emitir
        if (!form.value.full_name?.trim()) {
            errors.value.full_name = ['El nombre completo es obligatorio.'];
            return;
        }

        // Validar contactos adicionales
        const invalidAdditionalContacts = form.value.additional_contacts?.some(
            (contact) => !contact.name?.trim() || !contact.email?.trim(),
        );
        if (invalidAdditionalContacts) {
            errors.value.additional_contacts = ['Todos los contactos adicionales deben tener nombre y correo válidos.'];
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
            additional_contacts: form.value.additional_contacts ?? [], // Asegurar que se envíen los contactos adicionales
        });
        errors.value = {}; // Limpiar errores si todo es exitoso
    } catch (error) {
        handleErrors(error); // Manejar errores de la respuesta
    }
};

const handleErrors = (errorResponse: any) => {
    const validationErrors = errorResponse?.response?.data?.errors;
    if (validationErrors) {
        errors.value = validationErrors;
        const translated = Object.entries(validationErrors)
            .flatMap(([field, msgs]) => (msgs as string[]).map((m) => translateValidationMessage(field, m)))
            .join('. ');
        alertType.value = 'warning';
        alertMessage.value = translated || 'Error de validación.';
        const dialogEl = document.querySelector('[role="dialog"]');
        dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    alertType.value = 'error';
    alertMessage.value = 'Ocurrió un error inesperado. Intenta nuevamente.';
    console.error('Error inesperado:', errorResponse);
};

const hasDuplicateAdditionalContacts = () => {
    const contacts = form.value.additional_contacts ?? [];

    const seen = new Set<string>();

    for (const c of contacts) {
        const name = (c.name ?? '').trim().toLowerCase();
        const email = (c.email ?? '').trim().toLowerCase();
        const phone = (c.phone ?? '').trim();

        // ignorar líneas totalmente vacías por si acaso
        if (!name && !email && !phone) continue;

        const key = `${name}|${email}|${phone}`;
        if (seen.has(key)) {
            return true; // ya había uno igual
        }
        seen.add(key);
    }

    return false;
};

const hasDuplicateBranches = () => {
    const branches = form.value.branches ?? [];

    const seen = new Set<string>();

    for (const b of branches) {
        const name = (b.name ?? '').trim().toLowerCase();
        const address = (b.address ?? '').trim().toLowerCase();
        const phone = (b.phone ?? '').trim();

        if (!name && !address && !phone) continue;

        const key = `${name}|${address}|${phone}`;
        if (seen.has(key)) {
            return true;
        }
        seen.add(key);
    }

    return false;
};

// Actualizar contactos adicionales
const updateAdditionalContacts = (contacts: AdditionalContact[]) => {
    form.value.additional_contacts = contacts;
    emit('update:contactData', { ...form.value });
};

// Estado interno para las sucursales
const updateBranches = (branches: any[]) => {
    form.value.branches = branches;
    emit('update:contactData', { ...form.value });
};
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[150vh] px-2 py-2">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription class="pb-4">
                    Completa los campos para {{ dialogType === 'create' ? 'crear' : 'editar' }} un contacto. Haz
                    clic en {{ dialogType === 'create' ? 'crear' : 'actualizar' }} cuando termines.
                </DialogDescription>
            </DialogHeader>

            <transition name="fade">
                <div
                    v-if="alertMessage"
                    class="pointer-events-none absolute left-1/2 top-4 z-20 w-full max-w-xl -translate-x-1/2"
                >
                    <AlertBox
                        :type="alertType"
                        :title="alertType === 'warning' ? 'Advertencia' : 'Error'"
                        :message="alertMessage"
                        class="pointer-events-auto"
                        @close="alertMessage = ''"
                    />
                </div>
            </transition>

            <div v-if="showEmptyAlert" class="absolute -top-26 left-1/2 z-20 w-full max-w-xl -translate-x-1/2">
                <AlertBox
                    type="warning"
                    title="Campos obligatorios"
                    :message="emptyFieldsMessage"
                    @close="showEmptyAlert = false"
                />
            </div>
            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <!-- Pestañas -->
            <Tabs defaultValue="basic-info" class="px-2 pt-1">
                <TabsList class="flex justify-center space-x-4">
                    <TabsTrigger value="basic-info" class="flex-1 text-center py-2">Información básica</TabsTrigger>
                    <TabsTrigger value="additional-info" class="flex-1 text-center py-2">Contactos adicionales</TabsTrigger>
                    <TabsTrigger value="branches" class="flex-1 text-center py-2">Sucursales</TabsTrigger>
                </TabsList>

                <TabsContent value="basic-info">
                    <ContactForm v-model:form="form" :errors="errors" class="border-b pb-6" />
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
                    <BranchesList
                        ref="branchesListRef"
                        :branches="form.branches ?? []"
                        @update:branches="updateBranches"
                    />
                </TabsContent>
            </Tabs>

            </div>
            <DialogFooter class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" size="pill" @click="closeDialog">
                    Cancelar
                </Button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                    @click="submitForm"
                >
                    {{ dialogType === 'create' ? 'Crear' : 'Actualizar' }}
                </button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>

<style scoped>
/* Estilos específicos si son necesarios */
.tabs-list {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.tabs-trigger {
    flex: 1;
    text-align: center;
    padding: 0.5rem 1rem;
    font-size: 1.1rem;
}
</style>
