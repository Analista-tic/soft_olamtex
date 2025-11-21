<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Branch, AdditionalContact, Contact } from '@/types';
import { computed, ref, watch } from 'vue';
import AdditionalContactsList from './AdditionalContactsList.vue';
import ContactForm from './ContactForm.vue';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'; // Importar tabs de shadcn
import BranchesList from './BranchesList.vue'; // Componente para manejar sucursales

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

const submitForm = async () => {
    try {
        // Validación básica antes de emitir
        if (!form.value.full_name?.trim()) {
            errors.value.full_name = ['El nombre completo es obligatorio.'];
            return;
        }

        // Validar contactos adicionales
        const invalidAdditionalContacts = form.value.additional_contacts?.some(
            (contact) => !contact.name?.trim() || !contact.email?.trim()
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
    if (errorResponse.response?.data?.errors) {
        errors.value = errorResponse.response.data.errors;
    } else {
        console.error('Error inesperado:', errorResponse);
    }
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
        <DialogContent class="max-w-3xl h-screen flex flex-col">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ dialogType === 'create' ? 'crear' : 'editar' }} un contacto. Haz clic en guardar cuando termines.
                </DialogDescription>
            </DialogHeader>

            <!-- Pestañas -->
            <Tabs defaultValue="basic-info" class="flex-1 overflow-y-auto px-2">
                <TabsList class="flex justify-center space-x-4">
                    <TabsTrigger value="basic-info" class="flex-1 text-center py-2">Información básica</TabsTrigger>
                    <TabsTrigger value="additional-info" class="flex-1 text-center py-2">Datos adicionales</TabsTrigger>
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
                    <p v-if="errors.additional_contacts" class="text-red-500 text-sm mt-2">
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

            <DialogFooter class="mt-4">
                <Button variant="outline" class="text-sm px-3 py-1" @click="closeDialog"> Cancelar </Button>
                <Button class="text-sm px-3 py-1" @click="submitForm">
                    {{ dialogType === 'create' ? 'Crear' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
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
