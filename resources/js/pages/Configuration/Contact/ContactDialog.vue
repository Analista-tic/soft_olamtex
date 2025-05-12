<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { AdditionalContact, Contact } from '@/types';
import { computed, ref, watch } from 'vue';
import AdditionalContactsList from './AdditionalContactsList.vue';
import ContactForm from './ContactForm.vue';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    contactData: Partial<Contact>;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', contactData: Partial<Contact>): void;
    (e: 'update:contactData', updatedContact: Partial<Contact>): void; // Agregar este evento
}>();

// Estado interno del diálogo
const internalIsOpen = ref(props.isOpen);

// Formulario reactivo con valores iniciales
const form = ref<Partial<Contact>>({
    ...props.contactData,
    additional_contacts: props.contactData.additional_contacts ?? [],
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

        emit('submit', {
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

// Actualizar contactos adicionales
const updateAdditionalContacts = (contacts: AdditionalContact[]) => {
    form.value.additional_contacts = contacts;
    emit('update:contactData', { ...form.value });
};
</script>

<template>
    <Dialog v-model:open="internalIsOpen">
        <DialogContent class="max-w-3xl max-h-screen overflow-y-auto p-10">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ dialogType === 'create' ? 'crear' : 'editar' }} un contacto. Haz clic en guardar cuando termines.
                </DialogDescription>
            </DialogHeader>

            <!-- Formulario principal del contacto -->
            <ContactForm v-model:form="form" class="border-b pb-6" />

            <!-- Lista de contactos adicionales -->
            <AdditionalContactsList
                :additionalContacts="form.additional_contacts ?? []"
                @update:additionalContacts="updateAdditionalContacts"
            />


            <DialogFooter class="mt-4">
                <Button variant="outline" @click="closeDialog"> Cancelar </Button>
                <Button @click="submitForm">
                    {{ dialogType === 'create' ? 'Crear' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Estilos específicos si son necesarios */
</style>
